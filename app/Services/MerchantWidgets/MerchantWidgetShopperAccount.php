<?php

namespace App\Services\MerchantWidgets;

use App\Http\Controllers\Api\V1\MerchantWidget\OrderStatusController;
use App\Jobs\AlertAdminJob;
use App\Mail\MerchantWidgetShopperCodeMail;
use App\Models\EsimOrder;
use App\Models\Merchant;
use App\Models\MerchantWidgetDataRequest;
use App\Models\MerchantWidgetOrder;
use App\Models\MerchantWidgetShopperSession;
use App\Support\Auditor;
use App\Support\MerchantWidgetSettings;
use App\Support\Niche\LpaActivation;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * The returning-shopper "my orders" area of Naara Widgets (MASTER-ONLY). A shopper proves their email with a short emailed code and then holds a long random token;
 * with it they see ONLY their own orders placed through THIS merchant, can download everything held about them, and can ask for deletion (an admin approves).
 * Codes and tokens are stored as hashes, the code is single-use and attempt-limited, and asking for a code never reveals whether an email has orders.
 */
class MerchantWidgetShopperAccount
{
    private const CODE_ATTEMPTS = 5;

    public function normalise(string $email): string
    {
        return Str::lower(trim($email));
    }

    /** Email a code. Always looks the same from outside; only sends when this merchant really has orders for the address. */
    public function requestCode(Merchant $merchant, string $email, ?string $ip): void
    {
        $email = $this->normalise($email);
        $limiter = 'mwa:code:'.sha1($merchant->id.'|'.$email);
        if (RateLimiter::tooManyAttempts($limiter, 3)) {
            return;   // silently: three codes per 15 minutes per address
        }
        RateLimiter::hit($limiter, 900);

        if (! MerchantWidgetOrder::query()->where('merchant_id', $merchant->id)->where('buyer_email', $email)->exists()) {
            return;
        }
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $minutes = MerchantWidgetSettings::shopperCodeMinutes();
        MerchantWidgetShopperSession::query()->where('merchant_id', $merchant->id)->where('email', $email)->whereNull('token_hash')->delete();
        MerchantWidgetShopperSession::create([
            'merchant_id' => $merchant->id, 'email' => $email, 'code_hash' => $this->hashCode($code), 'code_expires_at' => now()->addMinutes($minutes), 'ip' => $ip,
        ]);
        Mail::to($email)->send(new MerchantWidgetShopperCodeMail($merchant->business_name, $code, $minutes));
    }

    /** @return array{token: string, expires_at: string}|null null when the code is wrong, expired or used up */
    public function verify(Merchant $merchant, string $email, string $code): ?array
    {
        $email = $this->normalise($email);
        $row = MerchantWidgetShopperSession::query()->where('merchant_id', $merchant->id)->where('email', $email)->whereNull('token_hash')->latest('id')->first();
        if ($row === null || $row->code_expires_at === null || $row->code_expires_at->isPast() || $row->attempts >= self::CODE_ATTEMPTS) {
            return null;
        }
        $row->increment('attempts');
        if (! hash_equals((string) $row->code_hash, $this->hashCode(trim($code)))) {
            return null;
        }
        $token = Str::random(56);
        $expires = now()->addDays(MerchantWidgetSettings::shopperSessionDays());
        $row->forceFill(['code_hash' => null, 'code_expires_at' => null, 'token_hash' => hash('sha256', $token), 'token_expires_at' => $expires, 'last_used_at' => now()])->save();
        // keep a shopper to a handful of live sessions
        $stale = MerchantWidgetShopperSession::query()->where('merchant_id', $merchant->id)->where('email', $email)->whereNotNull('token_hash')->orderByDesc('id')->pluck('id')->slice(5);
        if ($stale->isNotEmpty()) {
            MerchantWidgetShopperSession::whereIn('id', $stale->all())->delete();
        }
        Auditor::log('merchant_widget.shopper_signed_in', MerchantWidgetShopperSession::class, $row->id, ['merchant_id' => $merchant->id]);

        return ['token' => $token, 'expires_at' => $expires->toIso8601String()];
    }

    /** The live session for a token on this merchant, or null. */
    public function resolve(Merchant $merchant, ?string $token): ?MerchantWidgetShopperSession
    {
        if ($token === null || strlen($token) < 40) {
            return null;
        }
        $s = MerchantWidgetShopperSession::query()->where('merchant_id', $merchant->id)->where('token_hash', hash('sha256', $token))->first();
        if ($s === null || $s->token_expires_at === null || $s->token_expires_at->isPast()) {
            return null;
        }
        if ($s->last_used_at === null || $s->last_used_at->lt(now()->subMinutes(10))) {
            $s->forceFill(['last_used_at' => now()])->saveQuietly();
        }

        return $s;
    }

    public function logout(MerchantWidgetShopperSession $s): void
    {
        $s->delete();
    }

    /** @return list<array<string, mixed>> */
    public function orders(MerchantWidgetShopperSession $s): array
    {
        $out = [];
        $rows = MerchantWidgetOrder::query()->where('merchant_id', $s->merchant_id)->where('buyer_email', $s->email)
            ->whereNotIn('status', [MerchantWidgetOrder::PENDING])->orderByDesc('id')->limit(100)->get();
        foreach ($rows as $o) {
            $out[] = $this->present($o);
        }

        return $out;
    }

    /** One order as the shopper sees it: masked (never cost, supplier or anyone else's data). */
    public function present(MerchantWidgetOrder $o): array
    {
        $o = app(MerchantWidgetFulfilmentService::class)->syncNumberOutcome($o);
        $result = $o->status === MerchantWidgetOrder::COMPLETED ? OrderStatusController::result($o) : null;
        $row = [
            'reference' => $o->reference,
            'kind' => $o->kind,
            'status' => $o->status,
            'title' => $this->title($o, $result),
            'price_usd' => round((float) $o->merchant_price_usd, 2),
            'created_at' => $o->created_at?->toIso8601String(),
            'result' => $result,
        ];
        if ($o->kind === 'esim' && $o->esim_order_id && ($e = EsimOrder::find($o->esim_order_id))) {
            $lpa = (string) ($e->lpa_string ?: ($result['lpa'] ?? ''));
            $row['esim'] = [
                'status' => $e->status,
                'activated_at' => $e->activated_at?->toIso8601String(),
                'expires_at' => $e->expires_at?->toIso8601String(),
                'data_remaining_mb' => $e->data_remaining_mb,
                'qr_code_url' => $this->https($e->qr_code_url),
                'lpa' => $lpa !== '' ? $lpa : null,
                'install_link' => $lpa !== '' ? LpaActivation::universalLink($lpa) : null,
            ];
        }
        if (in_array($o->status, [MerchantWidgetOrder::FAILED, MerchantWidgetOrder::REFUNDED], true)) {
            $row['note'] = 'This order did not complete. Anything you paid has been returned to your Naara wallet balance.';
        }

        return $row;
    }

    /** Everything held about this shopper for this merchant, as a download. @return array<string, mixed> */
    public function export(MerchantWidgetShopperSession $s): array
    {
        MerchantWidgetDataRequest::create(['merchant_id' => $s->merchant_id, 'email' => $s->email, 'kind' => MerchantWidgetDataRequest::EXPORT, 'status' => 'done', 'handled_at' => now()]);
        Auditor::log('merchant_widget.shopper_export', MerchantWidgetShopperSession::class, $s->id, ['merchant_id' => $s->merchant_id]);

        return [
            'exported_at' => now()->toIso8601String(),
            'email' => $s->email,
            'merchant' => Merchant::find($s->merchant_id)?->business_name,
            'orders' => $this->orders($s),
            'data_requests' => MerchantWidgetDataRequest::query()->where('merchant_id', $s->merchant_id)->where('email', $s->email)->orderBy('id')
                ->get(['kind', 'status', 'created_at', 'handled_at'])->toArray(),
            'note' => 'Payment card details are never held by us: payments are processed by the payment provider you chose.',
        ];
    }

    /** One open deletion request per address; an admin completes it (account deletion is never self-serve). */
    public function requestDeletion(MerchantWidgetShopperSession $s): MerchantWidgetDataRequest
    {
        $existing = MerchantWidgetDataRequest::query()->where('merchant_id', $s->merchant_id)->where('email', $s->email)->where('kind', MerchantWidgetDataRequest::DELETE)->where('status', 'open')->first();
        if ($existing !== null) {
            return $existing;
        }
        $req = MerchantWidgetDataRequest::create(['merchant_id' => $s->merchant_id, 'email' => $s->email, 'kind' => MerchantWidgetDataRequest::DELETE, 'status' => 'open']);
        Auditor::log('merchant_widget.shopper_delete_requested', MerchantWidgetDataRequest::class, $req->id, ['merchant_id' => $s->merchant_id]);
        AlertAdminJob::dispatch('merchant_widget_delete_request', 'A widget shopper asked for their data to be deleted (Admin > Widgets > Data requests).', ['request_id' => $req->id]);

        return $req;
    }

    private function title(MerchantWidgetOrder $o, ?array $result): string
    {
        return match ($o->kind) {
            'esim' => (string) ($result['plan'] ?? 'Travel eSIM'),
            'giftcard' => trim(((string) ($result['brand'] ?? 'Gift card')).' gift card'),
            'number' => trim(((string) ($result['service'] ?? 'Verification')).' number'.(isset($result['country']) ? ' ('.$result['country'].')' : '')),
            default => 'Order',
        };
    }

    private function https(?string $url): ?string
    {
        return is_string($url) && str_starts_with($url, 'https://') ? $url : null;
    }

    private function hashCode(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }
}
