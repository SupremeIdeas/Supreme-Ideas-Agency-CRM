<?php

namespace App\Services\Handbooks;

use App\Models\HandbookDownload;
use App\Models\User;
use Illuminate\Support\Collection;

/** Download watch (MASTER-ONLY): flags a buyer whose downloads in the last 24 hours look like a copy being shared (many networks, or an unusual number of downloads). A flag is a prompt for a person to look, never an automatic block. */
class HandbookAnomalies
{
    /** @return Collection<int, array{user_id:int, email:string, downloads:int, networks:int, books:int, last_at:string}> */
    public function scan(): Collection
    {
        $maxIps = (int) config('handbooks.anomaly_ips_per_day', 3);
        $maxDl = (int) config('handbooks.anomaly_downloads_per_day', 12);
        $rows = HandbookDownload::query()->where('created_at', '>=', now()->subDay())
            ->selectRaw('user_id, count(*) as downloads, count(distinct ip_hash) as networks, count(distinct handbook_id) as books, max(created_at) as last_at')
            ->groupBy('user_id')->get()
            ->filter(fn ($r) => (int) $r->networks >= $maxIps || (int) $r->downloads >= $maxDl);
        $emails = User::query()->whereIn('id', $rows->pluck('user_id'))->pluck('email', 'id');

        return $rows->map(fn ($r) => [
            'user_id' => (int) $r->user_id, 'email' => (string) ($emails[$r->user_id] ?? 'unknown'), 'downloads' => (int) $r->downloads,
            'networks' => (int) $r->networks, 'books' => (int) $r->books, 'last_at' => (string) $r->last_at,
        ])->sortByDesc('networks')->values();
    }
}
