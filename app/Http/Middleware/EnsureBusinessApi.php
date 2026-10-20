<?php

namespace App\Http\Middleware;

use App\Models\WalletGroup;
use App\Support\Business\BusinessSettings;
use App\Support\Business\WorkspaceAdminAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the Business Suite API (MASTER-ONLY, Prompt 18): the integrations module must be on, the key must be bound to a business workspace, and the person who
 * owns the key must still be that workspace's owner or an admin. Without all three the answer is a plain 404, so the surface does not exist for anyone else.
 * The resolved workspace is handed to the controller as the `business_workspace` request attribute.
 */
class EnsureBusinessApi
{
    public function handle(Request $request, Closure $next): Response
    {
        $client = $request->user();
        $group = $client?->wallet_group_id ? WalletGroup::find($client->wallet_group_id) : null;

        abort_unless(BusinessSettings::module('integrations') && $group && $group->isBusiness() && $client->owner && WorkspaceAdminAccess::can($group, $client->owner) && app(\App\Services\Business\BusinessAccess::class)->allowsWorkspace($group, 'integrations'), 404);

        $request->attributes->set('business_workspace', $group);

        return $next($request);
    }
}
