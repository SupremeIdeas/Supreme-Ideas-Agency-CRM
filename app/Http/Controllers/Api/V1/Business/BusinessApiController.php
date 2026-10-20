<?php

namespace App\Http\Controllers\Api\V1\Business;

use App\Http\Controllers\Controller;
use App\Models\VirtualNumber;
use App\Models\WalletGroup;
use Illuminate\Http\Request;

/** Shared helpers for the read-only Business Suite API (MASTER-ONLY, Prompt 18). Everything is scoped to the key's own workspace; nothing here writes. */
abstract class BusinessApiController extends Controller
{
    protected function workspace(Request $request): WalletGroup
    {
        return $request->attributes->get('business_workspace');
    }

    /** @return list<int> the workspace's own phone lines, so SMS conversations on them count as the workspace's */
    protected function lineIds(WalletGroup $g): array
    {
        return VirtualNumber::query()->where('wallet_group_id', $g->id)->pluck('id')->map(fn ($i) => (int) $i)->all();
    }

    protected function limit(Request $request, int $default = 50): int
    {
        return max(1, min(100, (int) $request->query('limit', $default)));
    }
}
