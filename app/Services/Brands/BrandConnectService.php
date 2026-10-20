<?php

namespace App\Services\Brands;

use App\Jobs\BrandConnectIntroJob;
use App\Models\BrandConnectRequest;
use App\Models\BrandPartner;
use App\Models\BrandPartnerFollower;
use App\Models\Setting;
use App\Models\User;
use App\Services\N2N\Credit\InsufficientUsageCredit;
use App\Services\N2N\Credit\N2NPricing;
use App\Services\N2N\Identity\NaaraId;
use App\Services\N2N\Messaging\LinkFilter;
use App\Services\N2N\Messaging\MessageService;
use App\Services\N2N\Messaging\MessagingException;
use App\Support\Auditor;
use App\Support\Notifications\Notify;
use Illuminate\Support\Facades\DB;

/**
 * Brand Connect (Brand-Growth Phase 6, MASTER-ONLY): what happens after someone taps Connect on a brand.
 *   open mode    - connecting is instant; the welcome message (if the owner turned it on) goes out at once.
 *   approve mode - the connection waits for the owner to accept or decline. Accepting sends the welcome message; declining removes the connection quietly.
 * The welcome message is an ordinary Naara chat message FROM the brand owner, so it is paid from the OWNER'S NaaraCredit at the normal message price, arrives as a message
 * request the person can accept or decline, and is sent at most once per person per brand. No credit means no message (the owner is told); it never borrows from the wallet.
 * The whole feature is behind an admin switch that is OFF by default.
 */
class BrandConnectService
{
    public const MODES = ['open' => 'Instant: people connect right away', 'approve' => 'Approval: I accept or decline each one'];

    public static function enabled(): bool
    {
        return (bool) Setting::getValue('brand.connect.enabled', false);
    }

    /** A person tapped Connect (a follower row was created). Records it and, in open mode, queues the welcome message. */
    public function onConnect(BrandPartnerFollower $follower): ?BrandConnectRequest
    {
        if (! self::enabled()) {
            return null;
        }
        $brand = BrandPartner::query()->find($follower->brand_partner_id);
        if (! $brand || ! $brand->owner_user_id || (int) $brand->owner_user_id === (int) $follower->user_id) {
            return null;
        }
        $approve = $brand->connect_mode === 'approve';
        $req = BrandConnectRequest::query()->firstOrCreate(['brand_partner_id' => $brand->id, 'user_id' => $follower->user_id], ['status' => $approve ? BrandConnectRequest::PENDING : BrandConnectRequest::ACCEPTED]);
        if (! $req->wasRecentlyCreated) {
            return $req;   // reconnecting later never repeats a decision or a welcome message
        }
        if ($approve) {
            $who = User::query()->find($follower->user_id);
            Notify::event($brand->owner, 'brand.connect_request', ['name' => $who?->name ?: 'Someone', 'brand' => $brand->brand_name], dedupe: 'bcr-'.$req->id);
        } else {
            $this->queueIntro($brand, $req);
        }

        return $req;
    }

    public function accept(BrandPartner $brand, int $requestId, User $by): BrandConnectRequest
    {
        $req = $this->owned($brand, $requestId);
        if ($req->status === BrandConnectRequest::PENDING) {
            $req->forceFill(['status' => BrandConnectRequest::ACCEPTED, 'decided_at' => now(), 'decided_by' => $by->id])->save();
            Auditor::log('brand.connect_accepted', BrandConnectRequest::class, $req->id, ['brand' => $brand->id]);
            if ($req->user) {
                Notify::event($req->user, 'brand.connect_accepted', ['brand' => $brand->brand_name], dedupe: 'bca-'.$req->id);
            }
            $this->queueIntro($brand, $req);
        }

        return $req->refresh();
    }

    /** Decline removes the connection (and its count) quietly: the person is not told, and can never be messaged by the welcome flow for this brand. */
    public function decline(BrandPartner $brand, int $requestId, User $by): BrandConnectRequest
    {
        $req = $this->owned($brand, $requestId);
        if ($req->status === BrandConnectRequest::PENDING) {
            DB::transaction(function () use ($req, $brand, $by) {
                $req->forceFill(['status' => BrandConnectRequest::DECLINED, 'decided_at' => now(), 'decided_by' => $by->id])->save();
                $locked = BrandPartner::query()->whereKey($brand->id)->lockForUpdate()->first();
                if (BrandPartnerFollower::query()->where('brand_partner_id', $brand->id)->where('user_id', $req->user_id)->delete() > 0 && $locked) {
                    $locked->decrement('naara_followers_count');
                }
            });
            Auditor::log('brand.connect_declined', BrandConnectRequest::class, $req->id, ['brand' => $brand->id]);
        }

        return $req->refresh();
    }

    /** Validate and save the owner's settings. @throws \InvalidArgumentException */
    public function saveSettings(BrandPartner $brand, string $mode, bool $introEnabled, ?string $text): void
    {
        if (! array_key_exists($mode, self::MODES)) {
            throw new \InvalidArgumentException('Pick instant or approval.');
        }
        $text = $text === null ? null : trim(preg_replace('/\s+/u', ' ', $text) ?? '');
        if ($introEnabled && ($text === null || $text === '')) {
            throw new \InvalidArgumentException('Write the welcome message first, or turn it off.');
        }
        if ($text !== null && $text !== '') {
            if (mb_strlen($text) > 240) {
                throw new \InvalidArgumentException('Keep the welcome message to 240 characters.');
            }
            if (($why = LinkFilter::problem($text)) !== null) {
                throw new \InvalidArgumentException($why);   // chat does not allow links, and the welcome message is a chat message
            }
        }
        $brand->forceFill(['connect_mode' => $mode, 'intro_enabled' => $introEnabled, 'intro_text' => $text ?: null])->save();
    }

    /** Send the welcome message for one request (called by the queued job). Idempotent: only a `queued` request is acted on, and the chat message has a fixed client id. */
    public function deliverIntro(int $requestId): void
    {
        $req = BrandConnectRequest::query()->with(['brandPartner.owner', 'user'])->find($requestId);
        if (! $req || $req->intro_status !== 'queued' || ! $req->brandPartner || ! $req->user) {
            return;
        }
        $brand = $req->brandPartner;
        $owner = $brand->owner;
        if (! $owner || ! $brand->intro_enabled || blank($brand->intro_text) || $req->status !== BrandConnectRequest::ACCEPTED) {
            $req->forceFill(['intro_status' => 'none'])->save();

            return;
        }
        try {
            $r = app(MessageService::class)->send($owner, ['to' => NaaraId::for($req->user)], 'brandintro-'.$req->id.'-'.$brand->id, (string) $brand->intro_text);
            $req->forceFill(['intro_status' => 'sent', 'conversation_id' => $r['conversation']->id ?? null])->save();
        } catch (InsufficientUsageCredit) {
            $req->forceFill(['intro_status' => 'no_credit'])->save();
            Notify::event($owner, 'brand.intro_skipped', ['brand' => $brand->brand_name, 'cost' => rtrim(rtrim(number_format(N2NPricing::messageCostCents() / 100, 2), '0'), '.')], dedupe: 'bis-'.$brand->id.'-'.now()->format('Ymd'));
        } catch (MessagingException $e) {
            \Illuminate\Support\Facades\Log::info('brand intro not delivered: '.$e->getMessage());
            $req->forceFill(['intro_status' => 'blocked'])->save();   // they blocked the owner, a request cap, or similar: nothing to retry
        } catch (\Throwable $e) {
            report($e);
            $req->forceFill(['intro_status' => 'failed'])->save();
        }
    }

    private function queueIntro(BrandPartner $brand, BrandConnectRequest $req): void
    {
        if (! $brand->intro_enabled || blank($brand->intro_text) || ($req->intro_status ?? 'none') !== 'none') {
            return;
        }
        $req->forceFill(['intro_status' => 'queued'])->save();
        BrandConnectIntroJob::dispatch($req->id)->afterCommit();
    }

    private function owned(BrandPartner $brand, int $requestId): BrandConnectRequest
    {
        return BrandConnectRequest::query()->with('user')->where('brand_partner_id', $brand->id)->findOrFail($requestId);
    }
}
