<?php

namespace App\Services\Business\Softphone;

use App\Http\Controllers\VoiceTokenController;
use App\Models\BusinessSoftphoneDevice;
use App\Models\User;
use App\Models\WalletGroup;
use App\Models\WalletGroupMember;
use App\Services\SMS\Numbers\TwilioService;
use App\Support\Business\BusinessSettings;
use App\Support\ProviderStatus;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Who may use the native softphone and what they are given (MASTER-ONLY). The phone rings through the SAME Twilio client identity the browser dialer and ring groups
 * already use (naara_user_{id}), so no call-routing change is needed: a ring-group seat with "ring in the browser" on rings every device registered under that identity.
 * Only an active member of a business workspace is eligible (the consumer browser dialer stays as it is). The pairing token is a Sanctum token that can do exactly one
 * thing: ask for softphone access tokens. It carries no wallet or account authority, expires in 30 days and is revoked when the phone is unpaired.
 */
class SoftphoneAccess
{
    public const ABILITY = 'softphone';

    public const TOKEN_DAYS = 30;

    public const PLATFORMS = ['ios', 'android'];

    /** @return array{ok:bool,code:?string,message:?string} */
    public function eligibility(User $user): array
    {
        if (! BusinessSettings::softphoneLive()) {
            return ['ok' => false, 'code' => 'off', 'message' => 'The native softphone is not switched on.'];
        }
        if (! ProviderStatus::isActive('twilio')) {
            return ['ok' => false, 'code' => 'voice_off', 'message' => 'Calling is not available right now.'];
        }
        $seat = WalletGroupMember::query()->where('user_id', $user->id)->where('status', WalletGroupMember::STATUS_ACTIVE)
            ->whereIn('wallet_group_id', WalletGroup::query()->where('kind', WalletGroup::KIND_BUSINESS)->select('id'))->exists();
        if (! $seat) {
            return ['ok' => false, 'code' => 'no_workspace', 'message' => 'Only members of a business workspace can use the softphone.'];
        }

        return ['ok' => true, 'code' => null, 'message' => null];
    }

    public static function pushCredentialFor(string $platform): ?string
    {
        $sid = (string) config('services.twilio.push_credential_'.$platform, '');

        return preg_match('/^CR[0-9a-f]{32}$/i', $sid) === 1 ? $sid : null;
    }

    /**
     * Pair a phone: remember it, and issue the one-purpose token. Re-pairing the same phone replaces its token.
     *
     * @return array{token:string,device:BusinessSoftphoneDevice}
     */
    public function pair(User $user, string $deviceId, string $platform, ?string $appVersion = null, ?string $osVersion = null): array
    {
        $existing = BusinessSoftphoneDevice::query()->where('user_id', $user->id)->where('device_id', $deviceId)->first();
        if ($existing?->token_id) {
            PersonalAccessToken::query()->whereKey($existing->token_id)->where('tokenable_id', $user->id)->delete();
        }
        $new = $user->createToken('softphone:'.Str::limit($deviceId, 40, ''), [self::ABILITY], now()->addDays(self::TOKEN_DAYS));
        $device = BusinessSoftphoneDevice::query()->updateOrCreate(['user_id' => $user->id, 'device_id' => $deviceId], [
            'platform' => $platform, 'app_version' => $appVersion, 'os_version' => $osVersion, 'push_ready' => self::pushCredentialFor($platform) !== null,
            'token_id' => $new->accessToken->id, 'paired_at' => now(), 'last_seen_at' => now(),
        ]);

        return ['token' => $new->plainTextToken, 'device' => $device];
    }

    /**
     * A short-lived Twilio Voice access token for one paired phone. `push` says whether Twilio can wake the phone (the platform's push credential is set): without it the
     * phone rings only while the app is open, and the app is told so it can say that plainly.
     *
     * @return array{token:string,identity:string,ttl:int,push:bool}
     */
    public function accessToken(User $user, BusinessSoftphoneDevice $device): array
    {
        $identity = VoiceTokenController::identityFor((int) $user->id);
        $push = self::pushCredentialFor($device->platform);
        $ttl = 3600;
        $jwt = app(TwilioService::class)->accessToken($identity, $ttl, $push);
        $device->forceFill(['last_seen_at' => now(), 'push_ready' => $push !== null])->save();

        return ['token' => $jwt, 'identity' => $identity, 'ttl' => $ttl, 'push' => $push !== null];
    }

    public function unpair(User $user, string $deviceId): void
    {
        $device = BusinessSoftphoneDevice::query()->where('user_id', $user->id)->where('device_id', $deviceId)->first();
        if ($device?->token_id) {
            PersonalAccessToken::query()->whereKey($device->token_id)->where('tokenable_id', $user->id)->delete();
        }
        $device?->delete();
    }
}
