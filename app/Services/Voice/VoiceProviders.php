<?php

namespace App\Services\Voice;

use App\Models\VirtualNumber;
use App\Services\SMS\VoiceProviderInterface;

/**
 * Which voice stack serves a number. Lane rule (CLAUDE.md): a number's voice is handled by the provider that owns it, never
 * crossed to another. Twilio stays the default and the only provider the in-browser dialer supports.
 */
final class VoiceProviders
{
    /** Providers that implement VoiceProviderInterface, with the container id of each implementation. */
    public const BINDINGS = ['twilio' => 'number.twilio', 'telnyx' => 'voice.telnyx'];

    public static function supports(?string $provider): bool
    {
        return $provider !== null && isset(self::BINDINGS[strtolower($provider)]);
    }

    public static function for(string $provider): VoiceProviderInterface
    {
        $id = self::BINDINGS[strtolower($provider)] ?? throw new \InvalidArgumentException("No voice driver for [{$provider}].");

        return app($id);
    }

    public static function forNumber(VirtualNumber $number): ?VoiceProviderInterface
    {
        return self::supports($number->provider) ? self::for($number->provider) : null;
    }
}
