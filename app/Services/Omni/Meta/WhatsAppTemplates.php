<?php

namespace App\Services\Omni\Meta;

use App\Models\ChannelConnection;
use App\Services\Inbox\ChannelSendException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * WhatsApp message templates for ONE connection's WhatsApp Business Account (MASTER-ONLY), through Meta's Graph API (GET/POST/DELETE /{waba-id}/message_templates).
 * A template is the only message WhatsApp lets a business send outside the 24-hour window, and Meta must approve it first (status PENDING, APPROVED, REJECTED, PAUSED,
 * DISABLED). Created here as utility or marketing text templates with an optional footer and quick-reply buttons; Meta's rules are checked before the call so a person
 * sees a plain reason instead of a Graph error: lowercase name with underscores, body up to 1,024 characters, variables numbered {{1}}, {{2}} in order with an example for
 * each, and a body that neither starts nor ends with a variable. The access token is the connection's own and never leaves the server.
 */
class WhatsAppTemplates
{
    public const CATEGORIES = ['UTILITY' => 'Utility (order updates, bookings, receipts)', 'MARKETING' => 'Marketing (offers, news; needs the person to have opted in)'];

    public static function wabaId(ChannelConnection $c): ?string
    {
        $id = $c->secret('waba_id');

        return $id !== null && preg_match('/^\d{5,32}$/', $id) ? $id : null;
    }

    /** Remember the WhatsApp Business Account id for a hand-connected number, after Meta confirms it with the connection's own token. @throws ChannelSendException */
    public function saveWabaId(ChannelConnection $c, string $wabaId): void
    {
        $wabaId = trim($wabaId);
        if (! preg_match('/^\d{5,32}$/', $wabaId)) {
            throw new ChannelSendException('The WhatsApp Business Account ID is a number of 15 or 16 digits (API Setup page in your Meta app).');
        }
        $r = $this->call($c, 'get', $this->graph().'/'.$wabaId, ['fields' => 'name']);
        if (! $r->successful()) {
            throw new ChannelSendException('Meta did not accept that account ID with this connection\'s access token. Check both.');
        }
        $c->forceFill(['credentials' => array_merge((array) $c->credentials, ['waba_id' => $wabaId])])->save();
    }

    /** @return list<array{id:string,name:string,language:string,category:string,status:string,quality:?string,body:string,footer:?string,buttons:list<string>,vars:int,reason:?string}> @throws ChannelSendException */
    public function all(ChannelConnection $c, bool $fresh = false): array
    {
        $waba = self::wabaId($c) ?? throw new ChannelSendException('Add the WhatsApp Business Account ID for this number first.');
        $key = 'omni.wa_templates.'.$c->id;
        if ($fresh) {
            Cache::forget($key);
        }

        return Cache::remember($key, 60, function () use ($c, $waba) {
            $r = $this->call($c, 'get', $this->graph().'/'.$waba.'/message_templates', ['limit' => 100, 'fields' => 'id,name,language,category,status,components,quality_score,rejected_reason']);
            if (! $r->successful()) {
                throw new ChannelSendException($this->why($r, 'Meta would not list the templates.'));
            }
            $out = [];
            foreach ((array) $r->json('data', []) as $t) {
                $body = '';
                $footer = null;
                $buttons = [];
                foreach ((array) ($t['components'] ?? []) as $comp) {
                    match (strtoupper((string) ($comp['type'] ?? ''))) {
                        'BODY' => $body = (string) ($comp['text'] ?? ''),
                        'FOOTER' => $footer = (string) ($comp['text'] ?? ''),
                        'BUTTONS' => $buttons = array_values(array_map(fn ($b) => (string) ($b['text'] ?? ''), (array) ($comp['buttons'] ?? []))),
                        default => null,
                    };
                }
                $out[] = [
                    'id' => (string) ($t['id'] ?? ''), 'name' => (string) ($t['name'] ?? ''), 'language' => (string) ($t['language'] ?? ''), 'category' => strtoupper((string) ($t['category'] ?? '')),
                    'status' => strtoupper((string) ($t['status'] ?? '')), 'quality' => ($q = strtoupper((string) ($t['quality_score']['score'] ?? ''))) !== '' && $q !== 'UNKNOWN' ? $q : null,
                    'body' => $body, 'footer' => $footer, 'buttons' => $buttons, 'vars' => self::variableCount($body),
                    'reason' => ($rr = (string) ($t['rejected_reason'] ?? '')) !== '' && strtoupper($rr) !== 'NONE' ? $rr : null,
                ];
            }

            return $out;
        });
    }

    /** @return list<array<string,mixed>> only the templates that can be sent right now */
    public function approved(ChannelConnection $c): array
    {
        return array_values(array_filter($this->all($c), fn ($t) => $t['status'] === 'APPROVED'));
    }

    /**
     * @param  list<string>  $examples one example value per variable, in order
     * @param  list<string>  $buttons  up to three quick-reply labels
     *
     * @throws ChannelSendException
     */
    public function create(ChannelConnection $c, string $name, string $category, string $language, string $body, array $examples = [], ?string $footer = null, array $buttons = []): void
    {
        $waba = self::wabaId($c) ?? throw new ChannelSendException('Add the WhatsApp Business Account ID for this number first.');
        $name = trim($name);
        $body = trim($body);
        $footer = $footer !== null && trim($footer) !== '' ? trim($footer) : null;
        $buttons = array_values(array_filter(array_map('trim', $buttons), fn ($b) => $b !== ''));
        $this->assertValid($name, $category, $language, $body, $examples, $footer, $buttons);

        $components = [['type' => 'BODY', 'text' => $body]];
        $vars = self::variableCount($body);
        if ($vars > 0) {
            $components[0]['example'] = ['body_text' => [array_map(fn ($e) => trim((string) $e), array_slice(array_values($examples), 0, $vars))]];
        }
        if ($footer !== null) {
            $components[] = ['type' => 'FOOTER', 'text' => $footer];
        }
        if ($buttons !== []) {
            $components[] = ['type' => 'BUTTONS', 'buttons' => array_map(fn ($b) => ['type' => 'QUICK_REPLY', 'text' => $b], $buttons)];
        }
        $r = $this->call($c, 'post', $this->graph().'/'.$waba.'/message_templates', ['name' => $name, 'language' => $language, 'category' => $category, 'components' => $components]);
        if (! $r->successful()) {
            throw new ChannelSendException($this->why($r, 'Meta would not take that template.'));
        }
        Cache::forget('omni.wa_templates.'.$c->id);
    }

    /** @throws ChannelSendException */
    public function delete(ChannelConnection $c, string $name): void
    {
        $waba = self::wabaId($c) ?? throw new ChannelSendException('Add the WhatsApp Business Account ID for this number first.');
        $r = $this->call($c, 'delete', $this->graph().'/'.$waba.'/message_templates', ['name' => $name]);
        if (! $r->successful()) {
            throw new ChannelSendException($this->why($r, 'Meta would not delete that template.'));
        }
        Cache::forget('omni.wa_templates.'.$c->id);
    }

    /** The highest {{n}} used, which is how many values the template needs. */
    public static function variableCount(string $body): int
    {
        preg_match_all('/\{\{(\d+)\}\}/', $body, $m);

        return $m[1] === [] ? 0 : max(array_map('intval', $m[1]));
    }

    /** The text a customer will read, with the values filled in. */
    public static function render(string $body, array $params): string
    {
        return (string) preg_replace_callback('/\{\{(\d+)\}\}/', fn ($m) => (string) ($params[(int) $m[1] - 1] ?? ''), $body);
    }

    /** @throws ChannelSendException */
    private function assertValid(string $name, string $category, string $language, string $body, array $examples, ?string $footer, array $buttons): void
    {
        if (! preg_match('/^[a-z0-9_]{1,512}$/', $name)) {
            throw new ChannelSendException('The template name may only have lowercase letters, numbers and underscores, for example order_update.');
        }
        if (! array_key_exists($category, self::CATEGORIES)) {
            throw new ChannelSendException('Pick Utility or Marketing.');
        }
        if (! preg_match('/^[a-z]{2}(_[A-Z]{2})?$/', $language)) {
            throw new ChannelSendException('The language is a code such as en, en_US or fr.');
        }
        if ($body === '' || mb_strlen($body) > 1024) {
            throw new ChannelSendException($body === '' ? 'Write the message text.' : 'The message text can be at most 1,024 characters.');
        }
        preg_match_all('/\{\{(\d+)\}\}/', $body, $m);
        $nums = array_map('intval', $m[1]);
        $vars = $nums === [] ? 0 : max($nums);
        $uniq = array_values(array_unique($nums));
        sort($uniq);
        if ($vars > 0 && $uniq !== range(1, $vars)) {
            throw new ChannelSendException('Number the variables in order: {{1}}, {{2}}, {{3}} with none missing.');
        }
        if ($vars > 0 && (preg_match('/^\s*\{\{\d+\}\}/', $body) || preg_match('/\{\{\d+\}\}\s*$/', $body))) {
            throw new ChannelSendException('Meta does not allow a variable at the very start or very end of the text. Add words around it.');
        }
        if ($vars > 0) {
            $given = array_values(array_filter(array_map(fn ($e) => trim((string) $e), $examples), fn ($e) => $e !== ''));
            if (count($given) < $vars) {
                throw new ChannelSendException('Give an example value for each variable ('.$vars.' needed). Meta uses them to review the template.');
            }
        }
        if ($footer !== null && mb_strlen($footer) > 60) {
            throw new ChannelSendException('The footer can be at most 60 characters.');
        }
        if (count($buttons) > 3) {
            throw new ChannelSendException('Use at most three buttons.');
        }
        foreach ($buttons as $b) {
            if (mb_strlen($b) > 25) {
                throw new ChannelSendException('A button label can be at most 25 characters.');
            }
        }
    }

    private function graph(): string
    {
        return 'https://graph.facebook.com/'.(string) config('services.whatsapp.graph_version', 'v25.0');
    }

    private function call(ChannelConnection $c, string $verb, string $url, array $data)
    {
        $token = (string) $c->secret('access_token');
        if ($token === '') {
            throw new ChannelSendException('This WhatsApp connection has no access token. Reconnect it from Channels.');
        }
        try {
            $req = Http::withToken($token)->timeout(20);

            return match ($verb) {
                'get' => $req->get($url, $data),
                'delete' => $req->send('DELETE', $url, ['query' => $data]),
                default => $req->post($url, $data),
            };
        } catch (\Throwable) {
            throw new ChannelSendException('We could not reach Meta. Try again in a minute.');
        }
    }

    private function why($r, string $fallback): string
    {
        $code = (int) $r->json('error.code');
        $detail = (string) ($r->json('error.error_user_msg') ?: $r->json('error.message'));

        return match (true) {
            $code === 190 || $r->status() === 401 => 'Meta rejected the access token. Reconnect this number from Channels.',
            $code === 100 && $detail !== '' => 'Meta says: '.mb_substr($detail, 0, 200),
            $code === 368 || $code === 4 || $r->status() === 429 => 'Meta asked us to slow down. Try again in a few minutes.',
            $detail !== '' => $fallback.' '.mb_substr($detail, 0, 200),
            default => $fallback,
        };
    }
}
