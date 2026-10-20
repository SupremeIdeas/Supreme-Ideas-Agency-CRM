<?php

namespace App\Http\Controllers\Omni;

use App\Http\Controllers\Controller;
use App\Jobs\Omni\ProcessOmniRawEvent;
use App\Models\ChannelConnection;
use App\Models\Omni\OmniConversation;
use App\Models\Omni\OmniIdentity;
use App\Models\Omni\OmniMessage;
use App\Models\Omni\OmniRawEvent;
use App\Services\Omni\AdapterRegistry;
use App\Services\Omni\Pipeline;
use App\Services\Omni\WebChatToken;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public web chat API (MASTER-ONLY). Three calls, all on the site's public key and a signed visitor token: open a session, send a message, poll for new ones.
 * CORS is granted only to origins the business listed (an empty list means same-origin only). Visitor input is length-limited, a hidden honeypot field drops bots,
 * and the visitor can only ever read the messages of their own conversation. Processing the visitor's message is plain database work, so it runs inline and the
 * agent sees it at once; nothing external is called from this request.
 */
class WebChatController extends Controller
{
    private function site(string $publicId): ChannelConnection
    {
        $c = ChannelConnection::query()->where('public_id', $publicId)->where('channel', ChannelConnection::WEBCHAT)->first();
        abort_unless($c && $c->isActive(), 404);

        return $c;
    }

    /** @return array<string, string> the CORS headers for this request, or [] when the origin is not allowed */
    private function cors(Request $request, ChannelConnection $c): array
    {
        $origin = (string) $request->headers->get('Origin', '');
        $allowed = array_map('strtolower', (array) ($c->credentials['allowed_origins'] ?? []));
        if ($origin === '' || ! in_array(strtolower(rtrim($origin, '/')), $allowed, true)) {
            return [];
        }

        return ['Access-Control-Allow-Origin' => $origin, 'Access-Control-Allow-Headers' => 'Authorization, Content-Type', 'Access-Control-Allow-Methods' => 'GET, POST, OPTIONS', 'Access-Control-Max-Age' => '600', 'Vary' => 'Origin'];
    }

    private function reply(Request $request, ChannelConnection $c, array $data, int $status = 200): JsonResponse
    {
        return response()->json($data, $status, $this->cors($request, $c));
    }

    /** A cross-origin call from a site that is not on the list is refused outright (a same-origin call carries no Origin header). */
    private function guardOrigin(Request $request, ChannelConnection $c): void
    {
        $origin = (string) $request->headers->get('Origin', '');
        abort_if($origin !== '' && $this->cors($request, $c) === [] && strtolower(rtrim($origin, '/')) !== strtolower($request->getSchemeAndHttpHost()), 403);
    }

    public function preflight(Request $request, string $publicId)
    {
        $c = $this->site($publicId);

        return response('', 204, $this->cors($request, $c));
    }

    public function session(Request $request, string $publicId): JsonResponse
    {
        $c = $this->site($publicId);
        $this->guardOrigin($request, $c);
        $existing = $request->bearerToken() ? WebChatToken::visitor($c, $request->bearerToken()) : null;
        $cfg = (array) $c->credentials;

        return $this->reply($request, $c, [
            'token' => WebChatToken::issue($c, $existing),
            'title' => (string) ($cfg['title'] ?? $c->label),
            'greeting' => (string) ($cfg['greeting'] ?? 'Hi! How can we help?'),
            'color' => preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($cfg['color'] ?? '')) ? $cfg['color'] : '#0A6E6E',
            'ask_email' => (bool) ($cfg['ask_email'] ?? true),
        ]);
    }

    public function send(Request $request, AdapterRegistry $registry, string $publicId): JsonResponse
    {
        $c = $this->site($publicId);
        $this->guardOrigin($request, $c);
        $visitor = WebChatToken::visitor($c, $request->bearerToken());
        abort_unless($visitor !== null, 401);
        $data = $request->validate(['text' => 'required|string|max:2000', 'mid' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{8,64}$/'], 'name' => 'nullable|string|max:120', 'email' => 'nullable|email|max:190', 'website' => 'nullable|string|max:0']);
        $payload = ['visitor' => $visitor, 'mid' => $data['mid'], 'text' => $data['text'], 'name' => $data['name'] ?? null, 'email' => $data['email'] ?? null];
        try {
            $raw = OmniRawEvent::query()->create(['connection_id' => $c->id, 'provider_event_id' => $registry->for(ChannelConnection::WEBCHAT)->eventId($payload), 'payload' => $payload, 'status' => 'pending', 'received_at' => now()]);
        } catch (UniqueConstraintViolationException) {
            return $this->reply($request, $c, ['ok' => true, 'duplicate' => true]);   // the browser retried: already have it
        }
        $c->forceFill(['last_event_at' => now()])->save();
        if (! app(Pipeline::class)->process($raw)) {
            ProcessOmniRawEvent::dispatch($raw->id);
        }

        return $this->reply($request, $c, ['ok' => true], 202);
    }

    public function poll(Request $request, string $publicId): JsonResponse
    {
        $c = $this->site($publicId);
        $this->guardOrigin($request, $c);
        $visitor = WebChatToken::visitor($c, $request->bearerToken());
        abort_unless($visitor !== null, 401);
        $identity = OmniIdentity::query()->where('workspace_id', $c->wallet_group_id)->where('channel', ChannelConnection::WEBCHAT)->where('external_id', $visitor)->first();
        $conv = $identity ? OmniConversation::query()->forWorkspace((int) $c->wallet_group_id)->where('contact_id', $identity->contact_id)->where('connection_id', $c->id)->first() : null;
        $after = max(0, (int) $request->query('after', 0));
        $rows = $conv ? OmniMessage::query()->forWorkspace((int) $c->wallet_group_id)->where('conversation_id', $conv->id)->where('id', '>', $after)->whereNotIn('status', ['failed'])->orderBy('id')->limit(100)->get() : collect();

        return $this->reply($request, $c, ['messages' => $rows->map(fn ($m) => ['id' => $m->id, 'from' => $m->direction === 'in' ? 'visitor' : 'team', 'text' => (string) $m->body, 'at' => $m->created_at?->toIso8601String()])->all()]);
    }
}
