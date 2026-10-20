<?php

namespace App\Services\N2N\Brands;

use App\Models\BrandPartner;
use App\Models\N2N\BrandProfileVisit;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Brand profile visits (MASTER-ONLY): one row per visitor per day, so a brand sees how many different people looked at its profile today and in the
 * last 24 hours, and which members they were. Never throws into a page load; the owner's own views, crawlers and link previews are not counted.
 * A signed-out visitor is keyed by a one-way hash (never the raw IP). A member can opt out of being named (users.n2n_private_visits): they are still counted.
 */
class BrandVisitService
{
    public const RETENTION_DAYS = 90;

    public function record(BrandPartner $brand, ?User $viewer, ?Request $request = null): bool
    {
        try {
            $request ??= request();
            if ($viewer && (int) $viewer->id === (int) $brand->owner_user_id) {
                return false;
            }
            $ua = (string) $request->userAgent();
            if ($ua === '' || preg_match('/bot|crawl|spider|slurp|preview|facebookexternalhit|whatsapp|telegram|curl|wget|headless/i', $ua) || $request->headers->get('Purpose') === 'prefetch') {
                return false;
            }
            $key = $viewer ? 'u'.$viewer->id : 'g'.sha1($request->ip().'|'.$ua.'|'.config('app.key'));
            $day = now()->toDateString();
            $row = BrandProfileVisit::query()->firstOrCreate(
                ['brand_partner_id' => $brand->id, 'visitor_key' => $key, 'visited_on' => $day],
                ['visitor_user_id' => $viewer?->id, 'hits' => 0, 'first_at' => now(), 'last_at' => now()],
            );
            $row->increment('hits');
            $row->forceFill(['last_at' => now()])->save();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /** @return array{last24h:int, today:int, week:int, members24h:int, guests24h:int, views24h:int} distinct visitors, not page loads (views24h is the page loads) */
    public function stats(BrandPartner $brand): array
    {
        $q = fn () => BrandProfileVisit::query()->where('brand_partner_id', $brand->id);
        $since = now()->subDay();
        $last = $q()->where('last_at', '>=', $since);

        return [
            'last24h' => (clone $last)->count(),
            'today' => $q()->where('visited_on', now()->toDateString())->count(),
            'week' => $q()->where('visited_on', '>=', now()->subDays(6)->toDateString())->count(),
            'members24h' => (clone $last)->whereNotNull('visitor_user_id')->count(),
            'guests24h' => (clone $last)->whereNull('visitor_user_id')->count(),
            'views24h' => (int) (clone $last)->sum('hits'),
        ];
    }

    /** @return list<array{name:string, handle:?string, naara_id:?string, at:\Illuminate\Support\Carbon, hits:int, private:bool}> members who visited in the last 24 hours, newest first */
    public function recentMembers(BrandPartner $brand, int $limit = 20): array
    {
        return BrandProfileVisit::query()->with('visitor:id,name,username,naara_id,n2n_private_visits')
            ->where('brand_partner_id', $brand->id)->whereNotNull('visitor_user_id')->where('last_at', '>=', now()->subDay())
            ->orderByDesc('last_at')->limit($limit)->get()
            ->map(function (BrandProfileVisit $v) {
                $u = $v->visitor;
                $private = ! $u || (bool) $u->n2n_private_visits;

                return ['name' => $private ? 'A private visitor' : $u->name, 'handle' => $private ? null : ($u->username ?: $u->naara_id), 'naara_id' => $private ? null : $u->naara_id, 'at' => $v->last_at, 'hits' => (int) $v->hits, 'private' => $private];
            })->all();
    }

    public function prune(): int
    {
        return BrandProfileVisit::query()->where('visited_on', '<', now()->subDays(self::RETENTION_DAYS)->toDateString())->delete();
    }
}
