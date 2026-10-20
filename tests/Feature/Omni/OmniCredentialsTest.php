<?php

namespace Tests\Feature\Omni;

use App\Services\Omni\OmniCredentials;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Credential status reports DEFAULT / SET / MISSING and never leaks a value. */
class OmniCredentialsTest extends TestCase
{
    use RefreshDatabase;

    public function test_defaults_are_marked_and_a_missing_secret_is_flagged_without_leaking_values(): void
    {
        config(['omni.meta.app_secret' => '', 'omni.telegram.platform_bot_token' => 'SUPER-SECRET-TOKEN-123', 'omni.meta.app_id' => '2195469598059043']);
        $rows = collect(OmniCredentials::status())->keyBy('env');
        $this->assertSame('DEFAULT', $rows['OMNI_META_APP_ID']['status']);
        $this->assertSame('MISSING', $rows['OMNI_META_APP_SECRET']['status']);
        $this->assertSame('SET', $rows['OMNI_TELEGRAM_BOT_TOKEN']['status']);
        $this->assertStringNotContainsString('SUPER-SECRET-TOKEN-123', json_encode($rows));
        $this->artisan('naara:respond:credentials')->assertSuccessful()->doesntExpectOutputToContain('SUPER-SECRET-TOKEN-123');
    }

    public function test_the_verify_check_warns_for_missing_and_passes_when_everything_is_present(): void
    {
        config(['omni.meta.app_secret' => '', 'omni.meta.verify_token' => '', 'omni.telegram.platform_bot_token' => '', 'services.anthropic.api_key' => '']);
        $this->assertStringContainsString('missing', (new \App\Support\Verify\Checks\OmniCredentialsStatus)->run()->detail);
        config(['omni.meta.app_secret' => 'x', 'omni.meta.verify_token' => 'x', 'omni.telegram.platform_bot_token' => 'x', 'services.anthropic.api_key' => 'x', 'mail.mailers.smtp.host' => 'smtp.test', 'omni.meta.config_id' => '1', 'services.whatsapp.access_token' => 'x', 'services.whatsapp.waba_id' => '1', 'services.whatsapp.phone_number_id' => '1']);
        $this->assertStringContainsString('All credentials present', (new \App\Support\Verify\Checks\OmniCredentialsStatus)->run()->detail);
    }

    public function test_the_owners_meta_values_can_be_saved_in_admin_api_keys_and_take_effect(): void
    {
        $map = \App\Support\ProviderKeys::fieldMap();
        foreach (['meta_app_id' => 'omni.meta.app_id', 'meta_app_secret' => 'omni.meta.app_secret', 'meta_config_id' => 'omni.meta.config_id', 'meta_verify_token' => 'omni.meta.verify_token', 'meta_system_user_token' => 'services.whatsapp.access_token', 'meta_test_waba_id' => 'services.whatsapp.waba_id', 'meta_test_phone_number_id' => 'services.whatsapp.phone_number_id'] as $field => $path) {
            $this->assertSame($path, $map[$field] ?? null, $field);
        }
        config(['services.whatsapp.app_secret' => '', 'services.whatsapp.verify_token' => '']);
        \App\Support\ProviderKeys::save(['meta_app_secret' => 'a1b2c3d4e5f60718293a4b5c6d7e8f90', 'meta_verify_token' => 'naara-verify-7Hk29Qm4xTb81LzPw05vNc3R', 'meta_config_id' => '1234567890123456']);
        \App\Support\ProviderKeys::applyToConfig();
        $this->assertSame('1234567890123456', config('omni.meta.config_id'));
        $this->assertSame('a1b2c3d4e5f60718293a4b5c6d7e8f90', config('services.whatsapp.app_secret'), 'the older WhatsApp webhook uses the same Meta app secret');
        $rows = collect(OmniCredentials::status())->keyBy('env');
        $this->assertSame('SET', $rows['OMNI_META_CONFIG_ID']['status']);
        $this->assertSame('SET', $rows['OMNI_META_APP_SECRET']['status']);
    }
}
