<?php

use App\Http\Controllers\Api\V1\CatalogueController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\QuoteController;
use App\Http\Controllers\Api\V1\WhiteLabel\WhiteLabelLicenseController;
use App\Http\Controllers\Api\V1\WhiteLabel\WhiteLabelThemeController;
use App\Http\Controllers\Api\V1\WhiteLabel\WhiteLabelUpdateController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Sanctum-authenticated API (blueprint Section 3.1). Rate limited 300/min
// authenticated, 60/min public (Section 19.2). Every external call runs as a
// queued job, never synchronously in the request cycle.
Route::middleware(['throttle:api', 'auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});

// Native softphone (MASTER-ONLY). The native module calls these with the one-purpose token the web view paired for it. Guarded so a stray copy can never half-register it.
if (class_exists(\App\Http\Controllers\Business\Softphone\NativeSoftphoneController::class)) {
    Route::prefix('softphone')->middleware(['throttle:api', 'auth:sanctum'])->group(function () {
        Route::post('token', [\App\Http\Controllers\Business\Softphone\NativeSoftphoneController::class, 'token']);
        Route::post('status', [\App\Http\Controllers\Business\Softphone\NativeSoftphoneController::class, 'status']);
        Route::post('unpair', [\App\Http\Controllers\Business\Softphone\NativeSoftphoneController::class, 'unpair']);
    });
}

/*
 | Developer API — reselling surface (ROADMAP §Layer 2). OFF until the admin
 | enables `developer_api.enabled`. Authenticates an ApiClient (the Sanctum
 | tokenable), gates on the client being usable, and enforces per-route scopes
 | (the key's Sanctum abilities). Every price is the DEVELOPER lane price
 | (wholesale + admin markup, MarginGuard-floored) — cost is never exposed.
 */
Route::prefix('v1')
    ->middleware(['throttle:api', 'api.enabled', 'auth:sanctum', 'api.client'])
    ->group(function () {
        Route::get('catalogue', [CatalogueController::class, 'index'])->middleware('api.scope:catalogue');
        Route::post('quote', [QuoteController::class, 'store'])->middleware('api.scope:quote');
        Route::post('orders', [OrderController::class, 'store'])->middleware('api.scope:order');
        Route::get('orders/{reference}', [OrderController::class, 'show'])->middleware('api.scope:status');

        // Business Suite integrations (MASTER-ONLY, read-only + webhook subscriptions). Guarded with class_exists so a stray copy can never half-register it.
        if (class_exists(\App\Http\Controllers\Api\V1\Business\ContactsController::class)) {
            Route::middleware('business.api')->prefix('business')->group(function () {
                Route::get('contacts', [\App\Http\Controllers\Api\V1\Business\ContactsController::class, 'index'])->middleware('api.scope:contacts:read');
                Route::get('calls', [\App\Http\Controllers\Api\V1\Business\CallsController::class, 'index'])->middleware('api.scope:calls:read');
                Route::get('messages', [\App\Http\Controllers\Api\V1\Business\MessagesController::class, 'index'])->middleware('api.scope:messages:read');
                Route::get('webhooks', [\App\Http\Controllers\Api\V1\Business\WebhooksController::class, 'index'])->middleware('api.scope:webhook:subscribe');
                Route::post('webhooks', [\App\Http\Controllers\Api\V1\Business\WebhooksController::class, 'store'])->middleware('api.scope:webhook:subscribe');
                Route::delete('webhooks/{id}', [\App\Http\Controllers\Api\V1\Business\WebhooksController::class, 'destroy'])->whereNumber('id')->middleware('api.scope:webhook:subscribe');
            });
        }
    });

/*
 | White-label distribution API (Updater Batch 4). The publisher side, on the
 | master platform: registered white-label instances check for and download
 | signed .naaraupdate packages (code AND theme). OFF until the admin enables
 | `white_label_api.enabled`. Authenticates a WhiteLabelInstance (the Sanctum
 | tokenable), gates on the instance being active, enforces per-route scopes
 | (the token's Sanctum abilities), and logs every authenticated call centrally.
 | Downloads re-validate tier/version eligibility server-side; the receiving
 | instance still verifies the signature locally before applying.
 */
Route::prefix('v1/white-label')
    ->middleware(['throttle:api', 'whitelabel.enabled', 'auth:sanctum', 'whitelabel.usable'])
    ->name('white-label.')
    ->group(function () {
        Route::get('updates/check', [WhiteLabelUpdateController::class, 'check'])
            ->middleware('api.scope:updates.check')->name('updates.check');
        Route::get('updates/{package}/download', [WhiteLabelUpdateController::class, 'download'])
            ->middleware('api.scope:updates.download')->name('updates.download');
        // Batch 5 §4 — closes the oversight loop: reports what happened when a
        // downloaded package was actually applied. Reuses updates.check (no new
        // scope needed) and serves both code and theme outcome reports.
        Route::post('updates/report', [WhiteLabelUpdateController::class, 'report'])
            ->middleware('api.scope:updates.check')->name('updates.report');
        // Batch 8 — the fork polls its feature-entitlement (level + lock list)
        // on check-in. Reuses updates.check (reading what you're entitled to is
        // a natural part of the checking relationship).
        Route::get('entitlement', [WhiteLabelUpdateController::class, 'entitlement'])
            ->middleware('api.scope:updates.check')->name('entitlement');
        Route::get('themes/check', [WhiteLabelThemeController::class, 'check'])
            ->middleware('api.scope:themes.check')->name('themes.check');
        Route::get('themes/{package}/download', [WhiteLabelThemeController::class, 'download'])
            ->middleware('api.scope:themes.download')->name('themes.download');
    });

/*
 | White-label License Authority — enrolment surface (Updater Batch 6). Same
 | feature flag as the distribution API, but deliberately OUTSIDE auth:sanctum:
 | a fresh fork has no token yet. `register` files a pending request an admin
 | reviews; `activate` exchanges an admin-issued license key for the Sanctum API
 | token the fork then uses (what NAARA_UPDATE_API_TOKEN becomes). Rate-limited —
 | activate is the one place a valid key mints access, so it must not be a
 | brute-force oracle (every unusable key returns the same generic 403).
 */
Route::prefix('v1/white-label')
    ->middleware(['throttle:api', 'whitelabel.enabled'])
    ->name('white-label.')
    ->group(function () {
        Route::post('register', [WhiteLabelLicenseController::class, 'register'])->name('register');
        Route::post('activate', [WhiteLabelLicenseController::class, 'activate'])->name('activate');
    });

/*
 | Naara Widgets (merchant embed + WordPress loader) — MASTER-ONLY (docs/build-specs/NAARA-MERCHANT-WIDGETS-BLUEPRINT.md §6).
 | Platform kill switch -> per-key throttle -> live key + Origin-allowlist check -> per-route product scope. Never cached, never `*`.
 */
// Registered only where the module's code exists (it is master-only), so a stray copy of this file elsewhere can never half-register it.
if (class_exists(\App\Http\Controllers\Api\V1\MerchantWidget\CatalogueController::class)) {
Route::prefix('v1/merchant-widget')
    ->middleware(['merchant.widget.enabled', 'throttle:merchant-widget', 'merchant.widget.key'])
    ->name('merchant-widget.')
    ->group(function () {
        Route::get('catalogue', [\App\Http\Controllers\Api\V1\MerchantWidget\CatalogueController::class, 'index'])
            ->middleware('merchant.widget.scope:esim,number,giftcard')->name('catalogue');
        Route::get('number-quote', [\App\Http\Controllers\Api\V1\MerchantWidget\NumberQuoteController::class, 'show'])
            ->middleware('merchant.widget.scope:number')->name('number-quote');
        Route::post('giftcards/{product}/quote', [\App\Http\Controllers\Api\V1\MerchantWidget\GiftCardQuoteController::class, 'store'])
            ->middleware('merchant.widget.scope:giftcard')->name('giftcard-quote');
        Route::post('checkout/sessions', [\App\Http\Controllers\Api\V1\MerchantWidget\CheckoutSessionController::class, 'store'])
            ->middleware('throttle:merchant-widget-checkout')->name('checkout-session');
        Route::get('orders/{reference}/status', [\App\Http\Controllers\Api\V1\MerchantWidget\OrderStatusController::class, 'show'])
            ->where('reference', '.*')->name('order-status');
        Route::prefix('account')->middleware('throttle:merchant-widget-account')->name('account.')->group(function () {
            $c = \App\Http\Controllers\Api\V1\MerchantWidget\ShopperAccountController::class;
            Route::post('code', [$c, 'requestCode'])->name('code');
            Route::post('verify', [$c, 'verify'])->name('verify');
            Route::get('orders', [$c, 'orders'])->name('orders');
            Route::get('export', [$c, 'export'])->name('export');
            Route::post('delete-request', [$c, 'requestDeletion'])->name('delete');
            Route::post('logout', [$c, 'logout'])->name('logout');
        });
        Route::post('events', [\App\Http\Controllers\Api\V1\MerchantWidget\EventController::class, 'store'])->name('events');
        Route::match(['OPTIONS'], '{any}', fn () => response('', 204))->where('any', '.*');
    });
}
