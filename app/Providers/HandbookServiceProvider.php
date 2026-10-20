<?php

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/** Naara Travel Handbook Store (MASTER-ONLY). Registered by AppServiceProvider only where this class exists, so a fork can never half-register the store. */
class HandbookServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (! $this->app->routesAreCached()) {
            Route::middleware('web')->group(base_path('routes/handbooks.php'));
        }
        // A Naara eSIM earns a travel handbook. Queued and after-commit: it can never fail or slow the order.
        \App\Models\EsimOrder::created(function (\App\Models\EsimOrder $o) {
            if ($o->merchant_client_id === null && $o->user_id) {
                \App\Jobs\HandbookEsimUnlockJob::dispatch($o->id)->afterCommit();
            }
        });
    }
}
