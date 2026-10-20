<?php

namespace App\Services\ProviderRadar;

use App\Models\ProviderApiWatch;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * API version-watch (Updater, master-only). For every provider with a versioned base URL in config/services.php it records the version this install is pinned to,
 * then asks the provider two read-only, unauthenticated questions: do your response headers announce a version, a Deprecation or a Sunset date, and does the next
 * minor/major version of your API answer differently from a nonsense version (a catch-all host that answers every path the same way never raises a false alarm)?
 * It never sends credentials, never calls a money endpoint and never changes code: it only raises a finding the admin acknowledges.
 */
class ApiVersionWatch
{
    /** services.{key} => label. Only providers whose base URL exists in config are checked. */
    public const PROVIDERS = [
        'esimgo' => 'eSIM Go', 'airalo' => 'Airalo', 'quibity' => 'Quibity', 'zendit' => 'Zendit', 'oneglobal' => '1GLOBAL', 'gigs' => 'Gigs', 'getatext' => 'Getatext',
        'fivesim' => '5sim', 'telnyx' => 'Telnyx', 'twilio' => 'Twilio', 'flutterwave' => 'Flutterwave', 'paystack' => 'Paystack', 'paypal' => 'PayPal', 'nowpayments' => 'NOWPayments',
        'stripe' => 'Stripe', 'elevenlabs' => 'ElevenLabs', 'anthropic' => 'Anthropic', 'plivo' => 'Plivo', 'bitrefill' => 'Bitrefill', 'esimaccess' => 'eSIM Access', 'ubigi' => 'Ubigi',
    ];

    /** Response headers that carry a version a provider announces itself. */
    private const VERSION_HEADERS = ['api-version', 'x-api-version', 'stripe-version', 'x-twilio-api-version', 'anthropic-version'];

    /** @return array<string, string> provider key => label that this install can check right now */
    public function providers(): array
    {
        return array_filter(self::PROVIDERS, fn ($label, $key) => filled(config("services.$key.base_url")), ARRAY_FILTER_USE_BOTH);
    }

    /** The version in a base URL's last path piece: v2.5, v3, 2010-04-01. Null when the URL carries none. */
    public static function pinned(string $baseUrl): ?string
    {
        $path = trim((string) parse_url($baseUrl, PHP_URL_PATH), '/');
        $last = $path === '' ? '' : (string) \Illuminate\Support\Arr::last(explode('/', $path));

        return preg_match('/^(v\d+(\.\d+)?|\d{4}-\d{2}-\d{2})$/', $last) ? $last : null;
    }

    /** Next minor and next major of a vN or vN.M version, as URLs. Dated versions have no predictable successor. @return array<string, string> version => url */
    public static function candidates(string $baseUrl, ?string $pinned): array
    {
        if ($pinned === null || ! preg_match('/^v(\d+)(?:\.(\d+))?$/', $pinned, $m)) {
            return [];
        }
        $major = (int) $m[1];
        $out = [];
        if (isset($m[2]) && $m[2] !== '') {
            $out['v'.$major.'.'.((int) $m[2] + 1)] = null;
        }
        $out['v'.($major + 1)] = null;
        foreach ($out as $v => $_) {
            $out[$v] = preg_replace('#/'.preg_quote($pinned, '#').'/?$#', '/'.$v, rtrim($baseUrl, '/'));
        }

        return $out;
    }

    /** One provider: probe, store, return the row. */
    public function check(string $key): ProviderApiWatch
    {
        $baseUrl = rtrim((string) config("services.$key.base_url"), '/');
        $pinned = self::pinned($baseUrl);
        $row = ProviderApiWatch::firstOrNew(['provider' => $key]);

        $status = null;
        $headers = [];
        try {
            $res = $this->http()->get($baseUrl);
            $status = $res->status();
            $headers = array_change_key_case($res->headers(), CASE_LOWER);
        } catch (Throwable) {
            $status = 0;
        }

        $row->fill(['base_url' => $baseUrl, 'pinned_version' => $pinned, 'http_status' => $status, 'last_checked_at' => now()]);

        // A host that did not answer (or answered 5xx) tells us nothing about versions: keep the earlier findings, mark it unreachable.
        if ($status === 0 || $status >= 500) {
            $row->status = 'unreachable';
            $row->save();

            return $row;
        }

        $observed = null;
        foreach (self::VERSION_HEADERS as $h) {
            if (! empty($headers[$h][0])) {
                $observed = mb_substr((string) $headers[$h][0], 0, 80);
                break;
            }
        }
        $deprecation = null;
        foreach (['sunset', 'deprecation', 'warning'] as $h) {
            if (! empty($headers[$h][0])) {
                $deprecation = mb_substr($h.': '.$headers[$h][0], 0, 255);
                break;
            }
        }

        $newer = [];
        $cands = self::candidates($baseUrl, $pinned);
        if ($cands !== []) {
            $control = $this->statusOf(preg_replace('#/'.preg_quote((string) $pinned, '#').'/?$#', '/v999', $baseUrl));
            foreach ($cands as $version => $url) {
                $s = $this->statusOf($url);
                if ($s !== null && $s !== $control && ! in_array($s, [0, 404, 410], true) && $s < 500) {
                    $newer[] = $version;
                }
            }
        }

        $versionChanged = $observed !== null && $row->observed_version !== null && $row->observed_version !== $observed;
        $signature = substr(sha1(json_encode([$pinned, $observed, $deprecation, $newer])), 0, 40);
        $newStatus = $deprecation !== null ? 'deprecated' : ($newer !== [] ? 'newer_available' : ($versionChanged ? 'changed' : 'ok'));
        if ($row->exists && $row->signature !== $signature) {
            $row->last_changed_at = now();
        }
        $row->fill(['observed_version' => $observed, 'newer_versions' => $newer, 'deprecation' => $deprecation, 'status' => $newStatus, 'signature' => $signature]);
        $row->save();

        return $row;
    }

    /** @return array<string, string> provider key => outcome */
    public function run(): array
    {
        $out = [];
        foreach (array_keys($this->providers()) as $key) {
            try {
                $out[$key] = $this->check($key)->status;
            } catch (Throwable $e) {
                $out[$key] = 'error';
            }
        }

        return $out;
    }

    public function acknowledge(string $key): void
    {
        $row = ProviderApiWatch::where('provider', $key)->first();
        $row?->forceFill(['acknowledged_signature' => $row->signature, 'acknowledged_at' => now()])->save();
    }

    private function http(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::timeout(8)->connectTimeout(5)->withoutRedirecting()->withHeaders(['User-Agent' => 'NaaraSim-api-version-watch', 'Accept' => 'application/json']);
    }

    private function statusOf(string $url): ?int
    {
        try {
            return $this->http()->get($url)->status();
        } catch (Throwable) {
            return null;
        }
    }
}
