<?php

namespace App\Services\Bootstrap\Steps;

use App\Models\Announcement;
use App\Models\Bootstrap\BootstrapEntry;
use App\Services\Bootstrap\BootstrapStep;

/**
 * Bootstrap Phase 5 (announcement drafts). Three DRAFT announcements the owner can edit and send from Admin > Announcements. They are never sent by bootstrap:
 * status stays `draft` with zero recipients, and sending is a deliberate admin action. The copy states no price, discount or promise (those live in PricingEngine and the
 * coupons), so a draft cannot go stale or contradict the catalogue. Created once per item (the manifest remembers it): a draft the admin deletes is not recreated.
 */
class AnnouncementsStep implements BootstrapStep
{
    public function name(): string
    {
        return 'announcements';
    }

    /** @return list<array{item: string, title: string, body: string, icon: string, cta_label: string, cta_url: string, bullets: list<string>}> */
    public function drafts(): array
    {
        return [
            [
                'item' => 'draft-esim-trips', 'icon' => 'plane', 'cta_label' => 'See plans', 'cta_url' => '/catalogue',
                'title' => 'Landing somewhere new? Get your eSIM before you fly',
                'body' => 'Pick your destination, install the eSIM, and you are online the moment you land. No SIM swaps, no roaming surprises.',
                'bullets' => ['190+ countries', 'Installs in minutes', 'See the full price before you pay'],
            ],
            [
                'item' => 'draft-keep-your-number', 'icon' => 'phone', 'cta_label' => 'Explore numbers', 'cta_url' => '/numbers',
                'title' => 'A number for verification, or one that stays yours',
                'body' => 'Get a number to receive a one-time code, or keep a permanent line with calls, texts and forwarding. If a code never arrives, you are refunded automatically.',
                'bullets' => ['One-time codes and rentals', 'Permanent lines with forwarding', 'Automatic refund when no code arrives'],
            ],
            [
                'item' => 'draft-one-wallet', 'icon' => 'wallet', 'cta_label' => 'Open my wallet', 'cta_url' => '/wallet',
                'title' => 'Top up once, use it for everything',
                'body' => 'One wallet pays for eSIM data, numbers and gift cards. Top up by card, bank transfer or mobile money where it is available.',
                'bullets' => ['One balance for every product', 'Every charge shown before you confirm'],
            ],
        ];
    }

    public function plan(string $profile): array
    {
        $rows = [];
        foreach ($this->drafts() as $d) {
            if (! $this->exists($d['item'])) {
                $rows[] = ['kind' => 'announcement', 'item' => $d['item'], 'note' => 'DRAFT, never sent automatically: '.$d['title']];
            }
        }

        return $rows;
    }

    public function apply(string $profile): array
    {
        $created = $preserved = 0;
        foreach ($this->drafts() as $d) {
            if ($this->exists($d['item'])) {
                $preserved++;

                continue;
            }
            Announcement::create([
                'title' => $d['title'], 'body' => $d['body'], 'icon' => $d['icon'], 'style' => 'banner_hero', 'bullets' => $d['bullets'],
                'cta_label' => $d['cta_label'], 'cta_url' => $d['cta_url'], 'audience' => 'all', 'recipients' => 0, 'status' => 'draft',
            ]);
            BootstrapEntry::updateOrCreate(['step' => $this->name(), 'kind' => 'announcement', 'item' => $d['item']], ['checksum' => BootstrapEntry::checksum([$d['title'], $d['body']])]);
            $created++;
        }

        return ['created' => $created, 'preserved' => $preserved, 'skipped' => 0];
    }

    /** Drafts are optional: a missing one is never a failure. Only a draft that somehow went out unreviewed would be (bootstrap never sends). */
    public function verify(string $profile): array
    {
        return [];
    }

    private function exists(string $item): bool
    {
        return BootstrapEntry::where(['step' => $this->name(), 'kind' => 'announcement', 'item' => $item])->exists();
    }
}
