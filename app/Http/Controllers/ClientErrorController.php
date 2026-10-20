<?php

namespace App\Http\Controllers;

use App\Models\ClientError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Receives the browser's "something broke" beacon so a reported "hang" has a stack trace behind it (engineering blueprint 0.3).
 * Open to guests (the login page can break too) but throttled, size-capped and scrubbed: no IP, no query string, no tokens or emails in the text.
 */
class ClientErrorController extends Controller
{
    private const KINDS = ['error', 'rejection', 'livewire'];

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'kind' => ['required', 'in:'.implode(',', self::KINDS)],
            'message' => ['required', 'string', 'max:2000'],
            'stack' => ['nullable', 'string', 'max:8000'],
            'route' => ['nullable', 'string', 'max:300'],
            'component' => ['nullable', 'string', 'max:120'],
            'vw' => ['nullable', 'integer', 'min:0', 'max:20000'],
        ]);

        $message = self::scrub($data['message'], 500);
        $stack = isset($data['stack']) ? self::scrub($data['stack'], 4000) : null;
        $first = $stack ? (string) strtok($stack, "\n") : '';
        $fingerprint = sha1($data['kind'].'|'.$message.'|'.$first);

        // The same failure repeating in a loop must not flood the table: at most 5 identical reports per hour.
        $recent = ClientError::query()->where('fingerprint', $fingerprint)->where('created_at', '>=', now()->subHour())->count();
        if ($recent >= 5) {
            return response()->json(['ok' => true, 'stored' => false]);
        }

        ClientError::create([
            'user_id' => $request->user()?->id,
            'kind' => $data['kind'],
            'fingerprint' => $fingerprint,
            'route' => isset($data['route']) ? mb_substr(strtok($data['route'], '?#') ?: '', 0, 160) : null,
            'component' => $data['component'] ?? null,
            'message' => $message,
            'stack' => $stack,
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
            'viewport_w' => $data['vw'] ?? null,
        ]);

        return response()->json(['ok' => true, 'stored' => true]);
    }

    /** Removes what must never be stored: emails, long tokens/hex, bearer values, and query strings inside URLs. */
    public static function scrub(string $text, int $max): string
    {
        $text = preg_replace('/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/', '[email]', $text) ?? $text;
        $text = preg_replace('/\b(Bearer|Basic)\s+[A-Za-z0-9._\-=]+/i', '$1 [redacted]', $text) ?? $text;
        $text = preg_replace('/([?&](?:token|key|secret|code|password|signature|sig|api_key|access_token)=)[^&\s)"\']+/i', '$1[redacted]', $text) ?? $text;
        $text = preg_replace('/\b[A-Fa-f0-9]{32,}\b|\b[A-Za-z0-9_\-]{40,}\b/', '[token]', $text) ?? $text;

        return mb_substr($text, 0, $max);
    }
}
