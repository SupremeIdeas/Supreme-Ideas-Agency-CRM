<?php

namespace App\Services\Api;

use App\Models\ApiClient;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Issues and manages Developer API clients + their Sanctum tokens
 * (ROADMAP §Layer 2). The plaintext token is returned exactly ONCE at
 * creation/rotation and never stored (only its last four, for display). Scopes
 * are validated against ApiClient::SCOPES and become the token's abilities, so a
 * client can only reach the endpoints it was granted.
 */
class ApiClientService
{
    /**
     * Create a client for a developer and mint its first token.
     *
     * @param  list<string>  $scopes
     * @return array{client: ApiClient, token: string}
     */
    public function create(User $owner, string $name, array $scopes, ?int $walletGroupId = null): array
    {
        $walletGroupId = $this->workspaceFor($owner, $walletGroupId);
        $scopes = $this->sanitizeScopes($scopes, $walletGroupId !== null);

        $client = ApiClient::create([
            'owner_user_id' => $owner->id,
            'wallet_group_id' => $walletGroupId,
            'name' => $this->cleanName($name),
            'scopes' => $scopes,
            'is_active' => true,
        ]);

        return ['client' => $client, 'token' => $this->issueToken($client, $scopes)];
    }

    /**
     * Rotate a client's token: revoke every existing token and mint a fresh one.
     * Any leaked/old key stops working immediately.
     */
    public function rotate(ApiClient $client): string
    {
        $client->tokens()->delete();

        return $this->issueToken($client, $this->sanitizeScopes($client->scopes ?? [], $client->wallet_group_id !== null));
    }

    /** Update the scopes on a client and re-issue its token to match. */
    public function setScopes(ApiClient $client, array $scopes): string
    {
        $scopes = $this->sanitizeScopes($scopes, $client->wallet_group_id !== null);
        $client->update(['scopes' => $scopes]);
        $client->tokens()->delete();

        return $this->issueToken($client, $scopes);
    }

    /** Disable a client (keeps history) and kill its tokens. */
    public function revoke(ApiClient $client): void
    {
        $client->tokens()->delete();
        $client->update(['is_active' => false]);
    }

    /** Re-enable a previously revoked client and mint a fresh token. */
    public function reactivate(ApiClient $client): string
    {
        $client->update(['is_active' => true]);

        return $this->rotate($client);
    }

    /** Mint a Sanctum token with the given abilities and record its last four. */
    private function issueToken(ApiClient $client, array $scopes): string
    {
        $new = $client->createToken($client->name, $scopes);
        $plain = $new->plainTextToken; // format: {id}|{secret}

        $client->update(['token_last_four' => substr($plain, -4)]);

        return $plain;
    }

    /** Keep only known scopes; empty falls back to read-only catalogue access. */
    private function sanitizeScopes(array $scopes, bool $workspaceBound = false): array
    {
        $allowed = $workspaceBound ? ApiClient::availableScopes() : ApiClient::SCOPES;
        $valid = array_values(array_intersect($allowed, $scopes));

        return $valid !== [] ? $valid : ['catalogue'];
    }

    /** A key is bound to a workspace only when the Suite's integrations module is on and the owner owns or administers that workspace; otherwise it stays an ordinary key. */
    private function workspaceFor(User $owner, ?int $walletGroupId): ?int
    {
        if (! $walletGroupId || ! class_exists(\App\Support\Business\WorkspaceAdminAccess::class) || ! \App\Support\Business\BusinessSettings::module('integrations')) {
            return null;
        }
        $group = \App\Models\WalletGroup::find($walletGroupId);

        return $group && $group->isBusiness() && \App\Support\Business\WorkspaceAdminAccess::can($group, $owner) && app(\App\Services\Business\BusinessAccess::class)->allowsWorkspace($group, 'integrations') ? $group->id : null;
    }

    private function cleanName(string $name): string
    {
        return Str::limit(trim($name) ?: 'API client', 60, '');
    }
}
