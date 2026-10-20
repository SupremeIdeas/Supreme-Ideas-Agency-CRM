<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * UX/localization/platform-reach blueprint Phase G — the microphone
 * Permissions-Policy bug: an unconditional `microphone=()` locked the
 * feature out for every origin, including same-origin, so a browser
 * microphone grant for support-chat voice notes could never actually work.
 * Locks in the fix (`microphone=(self)`). Geolocation is `(self)` too: the chat
 * composer's Location button (and Naara live location) call
 * navigator.geolocation, which `geolocation=()` silently blocked. Camera stays
 * locked at `()`: attach-camera uses a native file picker, not getUserMedia.
 */
class SecurityHeadersTest extends TestCase
{
    public function test_permissions_policy_allows_same_origin_microphone_and_geolocation_but_locks_camera(): void
    {
        $response = $this->get('/');

        $response->assertHeader('Permissions-Policy', 'geolocation=(self), microphone=(self), camera=(self)');
    }
}
