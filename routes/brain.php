<?php

use App\Livewire\Brain\Chat;
use Illuminate\Support\Facades\Route;

/** NaaraBrain routes (MASTER-ONLY). Loaded by App\Providers\BrainServiceProvider. Off = the friendly "Coming soon" sheet (feature.live), never a bare 404 to a person. */
Route::middleware(['auth', 'active', 'feature.live:brain', 'throttle:120,1'])->group(function () {
    Route::get('/brain', Chat::class)->name('brain.chat');
});
