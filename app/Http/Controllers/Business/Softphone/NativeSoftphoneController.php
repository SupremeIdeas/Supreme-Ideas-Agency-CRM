<?php

namespace App\Http\Controllers\Business\Softphone;

use App\Http\Controllers\Controller;
use App\Models\BusinessSoftphoneDevice;
use App\Services\Business\Softphone\SoftphoneAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * The native softphone's server side (MASTER-ONLY). Two doors:
 *   POST /softphone/native/pair  : from the signed-in web view (session + CSRF). Hands the native module a one-purpose token for THIS phone.
 *   /api/softphone/*             : from the native module with that token (Bearer). It can only ask for Twilio access tokens and manage its own device row.
 * Everything answers a plain 404 when the module is off, so nothing hints at it. No secret and no wallet authority is ever returned; the token can only ask for softphone access.
 */
class NativeSoftphoneController extends Controller
{
    public function __construct(private readonly SoftphoneAccess $access)
    {
    }

    private function allowed(Request $request): void
    {
        $e = $this->access->eligibility($request->user());
        abort_if(! $e['ok'] && $e['code'] === 'off', 404);
        abort_unless($e['ok'], 403, (string) $e['message']);
    }

    /** @return array{0:string,1:string} */
    private function device(Request $request): array
    {
        $v = $request->validate(['device_id' => ['required', 'string', 'min:8', 'max:64', 'regex:/^[A-Za-z0-9._-]+$/'], 'platform' => ['required', 'in:ios,android']]);

        return [$v['device_id'], $v['platform']];
    }

    private function throttle(Request $request, string $key, int $max): void
    {
        $k = 'softphone:'.$key.':'.$request->user()->id;
        abort_if(RateLimiter::tooManyAttempts($k, $max), 429);
        RateLimiter::hit($k, 60);
    }

    public function pair(Request $request): JsonResponse
    {
        $this->allowed($request);
        $this->throttle($request, 'pair', 10);
        [$deviceId, $platform] = $this->device($request);
        $v = $request->validate(['app_version' => ['nullable', 'string', 'max:24'], 'os_version' => ['nullable', 'string', 'max:24']]);
        $paired = $this->access->pair($request->user(), $deviceId, $platform, $v['app_version'] ?? null, $v['os_version'] ?? null);

        return response()->json(['token' => $paired['token'], 'expires_in_days' => SoftphoneAccess::TOKEN_DAYS, 'push_ready' => $paired['device']->push_ready]);
    }

    private function asPhone(Request $request): void
    {
        abort_unless($request->user()?->tokenCan(SoftphoneAccess::ABILITY), 403, 'This token cannot be used for the softphone.');
        $this->allowed($request);
    }

    public function token(Request $request): JsonResponse
    {
        $this->asPhone($request);
        $this->throttle($request, 'token', 30);
        [$deviceId, $platform] = $this->device($request);
        $device = BusinessSoftphoneDevice::query()->where('user_id', $request->user()->id)->where('device_id', $deviceId)->first();
        abort_unless($device !== null && $device->platform === $platform, 404);
        // a token minted for one phone's pairing cannot be used as another phone
        $current = $request->user()->currentAccessToken();
        abort_unless($current && (int) $current->id === (int) $device->token_id, 403, 'Pair this phone again.');

        return response()->json($this->access->accessToken($request->user(), $device));
    }

    public function status(Request $request): JsonResponse
    {
        $this->asPhone($request);
        [$deviceId] = $this->device($request);
        $device = BusinessSoftphoneDevice::query()->where('user_id', $request->user()->id)->where('device_id', $deviceId)->first();

        return response()->json(['paired' => $device !== null, 'push_ready' => (bool) $device?->push_ready, 'identity' => \App\Http\Controllers\VoiceTokenController::identityFor((int) $request->user()->id)]);
    }

    public function unpair(Request $request): JsonResponse
    {
        $this->asPhone($request);
        [$deviceId] = $this->device($request);
        $this->access->unpair($request->user(), $deviceId);

        return response()->json(['ok' => true]);
    }
}
