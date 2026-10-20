<?php

namespace App\Providers;

use App\Support\SchedulerHealth;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;

/** Registers the Business Suite's scheduled work (MASTER-ONLY). Only ever added when this class exists, so a fork can never half-register it. */
class BusinessSuiteServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(\App\Services\Business\AiVoice\AgentLlm::class, \App\Services\Business\AiVoice\AnthropicAgentLlm::class);
    }

    public function boot(): void
    {
        SchedulerHealth::register('business:purge-recordings', 'Business call recording retention (expired recordings are deleted)', 86400);

        SchedulerHealth::register('business:send-scheduled', 'Business inbox scheduled messages (due messages are delivered)', 60);

        $this->app->booted(function () {
            $this->app->make(Schedule::class)->command('business:purge-recordings')->dailyAt('02:40')->withoutOverlapping();
            $this->app->make(Schedule::class)->command('business:send-scheduled')->everyMinute()->withoutOverlapping();
        });
    }
}
