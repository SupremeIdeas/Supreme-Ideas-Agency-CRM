<?php

namespace App\Services\MerchantWidgets;

use App\Models\Merchant;
use App\Models\MerchantWidgetKey;
use App\Support\Auditor;
use App\Support\MerchantWidgetSettings;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Create / rotate / revoke Naara Widgets keys (MASTER-ONLY, blueprint §5.1, §11). The one-time secret is returned ONCE here and only
 * its sha256 is stored. Every state change is audited.
 */
class MerchantWidgetKeyService
{
    /**
     * @param  list<string>  $domains  exact hosts (no scheme, no wildcard)
     * @param  list<string>  $scopes
     * @return array{key: MerchantWidgetKey, secret: string}
     */
    public function create(Merchant $merchant, string $label, array $domains, array $scopes, ?array $theme = null, ?int $actorId = null): array
    {
        $domains = $this->normaliseDomains($domains);
        $scopes = array_values(array_intersect($scopes, MerchantWidgetKey::SCOPES));
        if ($domains === []) {
            throw new InvalidArgumentException('Add at least one domain this key may be used on.');
        }
        if ($scopes === []) {
            throw new InvalidArgumentException('Choose at least one product type.');
        }
        $live = MerchantWidgetKey::query()->where('merchant_id', $merchant->id)->where('status', MerchantWidgetKey::ACTIVE)->count();
        if ($live >= MerchantWidgetSettings::maxKeys()) {
            throw new InvalidArgumentException('You have reached the maximum number of active widget keys.');
        }

        $secret = 'wks_'.Str::random(40);
        $key = MerchantWidgetKey::create([
            'merchant_id' => $merchant->id,
            'label' => Str::limit(trim($label) ?: 'My site', 80, ''),
            'public_key' => $this->uniquePublicKey(),
            'secret_hash' => hash('sha256', $secret),
            'allowed_domains' => $domains,
            'product_scopes' => $scopes,
            'theme' => $this->cleanTheme($theme),
            'status' => MerchantWidgetKey::ACTIVE,
        ]);
        Auditor::log('merchant_widget.key_created', MerchantWidgetKey::class, $key->id, ['merchant_id' => $merchant->id, 'domains' => $domains, 'scopes' => $scopes, 'by' => $actorId]);

        return ['key' => $key, 'secret' => $secret];
    }

    /** Revoke now. The very next request 403s (the middleware reads status live, never from cache). */
    public function revoke(MerchantWidgetKey $key, string $reason = 'revoked', ?int $actorId = null): MerchantWidgetKey
    {
        $key->forceFill(['status' => MerchantWidgetKey::REVOKED, 'revoked_at' => now(), 'revoked_reason' => Str::limit($reason, 120, '')])->save();
        Auditor::log('merchant_widget.key_revoked', MerchantWidgetKey::class, $key->id, ['reason' => $reason, 'by' => $actorId]);

        return $key;
    }

    /** Issue a replacement (new public key + secret, same settings) and revoke the old one in one step. */
    public function rotate(MerchantWidgetKey $key, ?int $actorId = null): array
    {
        $new = $this->create($key->merchant, $key->label, $key->allowedDomains(), (array) $key->product_scopes, $key->theme, $actorId);
        $this->revoke($key, 'rotated', $actorId);

        return $new;
    }

    public function verifySecret(MerchantWidgetKey $key, string $secret): bool
    {
        return hash_equals((string) $key->secret_hash, hash('sha256', $secret));
    }

    /** @return list<string> */
    public function normaliseDomains(array $domains): array
    {
        $out = [];
        foreach ($domains as $d) {
            $d = strtolower(trim((string) $d));
            $d = preg_replace('#^https?://#', '', $d);
            $d = rtrim((string) explode('/', (string) $d)[0], '.');
            // exact host only (optionally with a port); wildcards are refused in v1 (blueprint §13.4)
            if ($d !== '' && preg_match('/^(?=.{1,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}(:\d{2,5})?$|^localhost(:\d{2,5})?$/', $d) === 1) {
                $out[$d] = true;
            }
        }

        return array_keys($out);
    }

    private function cleanTheme(?array $theme): ?array
    {
        if ($theme === null) {
            return null;
        }
        $color = (string) ($theme['primary_color'] ?? '');
        $mode = (string) ($theme['mode'] ?? 'light');

        return [
            'primary_color' => preg_match('/^#[0-9a-fA-F]{6}$/', $color) === 1 ? strtolower($color) : null,
            'mode' => in_array($mode, ['light', 'dark'], true) ? $mode : 'light',
        ];
    }

    private function uniquePublicKey(): string
    {
        do {
            $k = 'wk_live_'.Str::lower(Str::random(24));
        } while (MerchantWidgetKey::query()->where('public_key', $k)->exists());

        return $k;
    }
}
