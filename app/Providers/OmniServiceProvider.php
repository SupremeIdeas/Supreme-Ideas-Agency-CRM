<?php

namespace App\Providers;

use App\Services\Omni\AdapterRegistry;
use App\Services\Omni\Adapters\FakeChannelAdapter;
use Illuminate\Support\ServiceProvider;

/** Naara Respond omni core (MASTER-ONLY): the adapter registry and its adapters. Registered by AppServiceProvider only where this class exists. */
class OmniServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(\App\Services\Omni\Agent\LlmClient::class, \App\Services\Omni\Agent\ClaudeGatewayLlm::class);
        $this->app->singleton(AdapterRegistry::class, function () {
            $registry = new AdapterRegistry;
            $registry->register(new \App\Services\Omni\Adapters\TelegramAdapter(new \App\Services\Inbox\TelegramBotClient));
            $registry->register(new \App\Services\Omni\Adapters\WebChatAdapter);
            $registry->register(new \App\Services\Omni\Adapters\EmailAdapter);
            $registry->register(new \App\Services\Omni\Adapters\WhatsAppAdapter);
            $registry->register(new \App\Services\Omni\Adapters\InstagramAdapter);
            $registry->register(new \App\Services\Omni\Adapters\MessengerAdapter);
            $registry->register(new \App\Services\Omni\Adapters\SmsAdapter);
            if ($this->app->runningUnitTests() || config('omni.fake', false)) {
                $registry->register(new FakeChannelAdapter);   // the proof channel exists only in tests or when switched on
            }

            return $registry;
        });
    }
}
