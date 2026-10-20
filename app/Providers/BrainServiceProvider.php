<?php

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/** NaaraBrain (MASTER-ONLY): loads its routes. Registered by AppServiceProvider only where this class exists; every route sits behind the `brain` feature switch. */
class BrainServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (! $this->app->routesAreCached()) {
            Route::middleware('web')->group(base_path('routes/brain.php'));
        }
    }
}
