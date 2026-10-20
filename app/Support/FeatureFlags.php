<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Feature toggles (owner request). An EXTRA control layer on top of the
 * key-driven "Coming Soon" strategy: the admin can switch a whole feature on or
 * off from the panel, independently of whether its API keys are set.
 *
 * A feature is EFFECTIVELY enabled only when BOTH are true:
 *   - the admin has it switched on (Setting `features.<key>.enabled`), and
 *   - its requirements are met (all required config keys present) — or it needs
 *     no keys at all.
 *
 * So an admin can pre-disable a feature they're not ready for, and a feature
 * whose keys aren't in yet stays "Coming Soon" even when switched on. Feature
 * code asks FeatureFlags::enabled('naara_push') and nothing half-runs.
 */
class FeatureFlags
{
    private const CACHE_PREFIX = 'features.enabled.';

    /**
     * The toggleable features. `requires` lists config keys that must all be
     * non-empty for the feature to actually run; `guide` names an in-panel
     * setup guide; `default` is the shipped on/off.
     */
    public const FEATURES = [
        // White Label purchase funnel (MASTER only; Funnel blueprint Phase 0.5). All four ship OFF: money-adjacent phases
        // go live one at a time, after address/QR integrity and quote-math tests are green.
        'wl_funnel' => [
            'name' => 'White Label purchase funnel',
            'group' => 'White Label',
            'icon' => 'globe',
            'description' => 'Landing page to registration to guided purchase for White Label licenses. Creates a funnel ID for visitors who arrive from the White Label landing page.',
            'requires' => [],
            'guide' => null,
            'default' => false,
        ],
        'wl_manual_payments' => [
            'name' => 'White Label manual payments',
            'group' => 'White Label',
            'icon' => 'credit-card',
            'description' => 'Pay for a White Label license by bank transfer (USD, GBP, EUR) or crypto (USDT, USDC) with admin receipt review. Keep OFF until every receiving address has had a test transaction.',
            'requires' => [],
            'guide' => null,
            'default' => false,
        ],
        'wl_custom_orders' => [
            'name' => 'White Label custom orders',
            'group' => 'White Label',
            'icon' => 'file-text',
            'description' => 'Let a buyer request a custom White Label build and receive a quote instead of choosing a fixed plan.',
            'requires' => [],
            'guide' => null,
            'default' => false,
        ],
        'wl_preview_unlock' => [
            'name' => 'White Label preview unlock',
            'group' => 'White Label',
            'icon' => 'clock',
            'description' => 'Give a funnel visitor time-boxed Merchant V2 preview access so they can see the White Label purchase flow. Scoped, audited, and revoked when the preview ends.',
            'requires' => [],
            'guide' => null,
            'default' => false,
        ],
        'naara_push' => [
            'name' => 'Naara Push',
            'group' => 'Notifications',
            'icon' => 'bell',
            'description' => 'Browser push notifications that reach users even when the tab or app is closed. Self-hosted (VAPID) — no third-party push service.',
            'requires' => ['webpush.vapid.public_key', 'webpush.vapid.private_key'],
            'guide' => 'webpush',
            'default' => true,
        ],
        'in_app_notifications' => [
            'name' => 'In-app notifications',
            'group' => 'Notifications',
            'icon' => 'bell',
            'description' => 'The notification bell + centre. Orders, wallet, refunds, support replies and offers appear in-app.',
            'requires' => [],
            'guide' => null,
            'default' => true,
        ],
        'ai_support' => [
            'name' => 'NaaraCare AI agent',
            'group' => 'Support',
            'icon' => 'id-card',
            'description' => 'The AI support agent that diagnoses, analyses evidence, and resolves safe tickets on autopilot.',
            'requires' => ['services.anthropic.api_key'],
            'guide' => 'anthropic',
            'default' => true,
        ],
        'voice_replies' => [
            'name' => 'Spoken support replies',
            'group' => 'Support',
            'icon' => 'mic',
            'description' => 'Read support replies aloud to paying customers (ElevenLabs).',
            'requires' => ['services.elevenlabs.api_key'],
            'guide' => 'elevenlabs',
            'default' => true,
        ],
        'naara_connect' => [
            'name' => 'Naara Connect (Data + Calls eSIM)',
            'group' => 'Products',
            'icon' => 'signal',
            'description' => 'The Data + Calls tab in the eSIM store. Off until a Full-eSIM provider (1GLOBAL, Gigs or Monty Mobile) has its keys saved or a synced plan includes calls: it then switches itself on. Switch it off to hide the tab, or on to force it as soon as the line can deliver.',
            'requires' => [],
            'guide' => null,
            'default' => 'auto',   // not stored yet: follows availability; once an admin flips the switch their choice wins
        ],
        'naara_gift' => [
            'name' => 'Naara Gift (gift cards)',
            'group' => 'Products',
            'icon' => 'gift',
            'description' => 'The gift-card storefront. On by default; it opens as soon as any gift provider (Reloadly, Zendit or Bitrefill) has its keys saved and the catalogue is synced, and shows "Coming Soon" until then.',
            'requires' => ['services.reloadly.client_id', 'services.reloadly.client_secret'],
            // Any ONE registered gift provider is enough to open the store (Zendit or Bitrefill alone must not leave it "Coming Soon").
            'requires_any' => [['services.reloadly.client_id', 'services.reloadly.client_secret'], ['services.zendit.api_key'], ['services.bitrefill.api_key']],
            'guide' => null,
            'default' => true,
        ],
    ];

    /** Admin's on/off switch for a feature (ignores whether keys are present). */
    public static function adminEnabled(string $key): bool
    {
        if (! isset(self::FEATURES[$key])) {
            return false;
        }

        $cached = Cache::rememberForever(self::CACHE_PREFIX.$key, function () use ($key) {
            try {
                $stored = Setting::getValue("features.$key.enabled");
            } catch (\Throwable) {
                $stored = null;
            }

            if ($stored === null && self::FEATURES[$key]['default'] === 'auto') {
                return null;   // decided live below (availability changes when keys are saved)
            }

            return $stored === null ? (bool) self::FEATURES[$key]['default'] : (bool) $stored;
        });
        return $cached ?? self::configured($key);
    }

    /** Whether the feature's required API keys are all present. */
    public static function configured(string $key): bool
    {
        if ($key === 'naara_connect') {
            return NaaraConnect::available();
        }
        if (! empty(self::FEATURES[$key]['requires_any'])) {
            foreach (self::FEATURES[$key]['requires_any'] as $group) {
                if (array_filter($group, fn ($c) => empty(config($c))) === []) {
                    return true;
                }
            }

            return false;
        }
        foreach (self::FEATURES[$key]['requires'] ?? [] as $configKey) {
            if (empty(config($configKey))) {
                return false;
            }
        }

        return true;
    }

    /** Effectively live: switched on AND its keys are present. */
    public static function enabled(string $key): bool
    {
        return self::adminEnabled($key) && self::configured($key);
    }

    public static function setEnabled(string $key, bool $on): void
    {
        if (! isset(self::FEATURES[$key])) {
            return;
        }
        Setting::setValue("features.$key.enabled", $on, 'features');
        self::flush($key);
    }

    /**
     * All features with their live state for the admin panel.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function all(): array
    {
        return collect(self::FEATURES)->map(fn ($meta, $key) => $meta + [
            'key' => $key,
            'admin_enabled' => self::adminEnabled($key),
            'configured' => self::configured($key),
            'enabled' => self::enabled($key),
            'needs_keys' => ! empty($meta['requires']) || $key === 'naara_connect',
        ])->values()->all();
    }

    public static function flush(?string $key = null): void
    {
        if ($key !== null) {
            Cache::forget(self::CACHE_PREFIX.$key);

            return;
        }
        foreach (array_keys(self::FEATURES) as $k) {
            Cache::forget(self::CACHE_PREFIX.$k);
        }
    }

    public static function isFeatureKey(string $key): bool
    {
        return str_starts_with($key, 'features.');
    }
}
