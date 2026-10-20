<?php

use App\Http\Controllers\N2N\MediaController;
use App\Http\Controllers\N2N\MessagingController;
use App\Http\Controllers\N2N\RealtimeController;
use App\Livewire\N2N\Chat;
use App\Livewire\N2N\IdentitySettings;
use App\Livewire\N2N\UsageCredits;
use Illuminate\Support\Facades\Route;

/** N2N routes (MASTER-ONLY). Loaded by App\Providers\N2NServiceProvider; every one is behind the admin switch. */
Route::middleware(['n2n.enabled', 'auth', 'active'])->group(function () {
    Route::get('/account/naara-id', IdentitySettings::class)->name('n2n.identity');
    Route::get('/credits/n2n', UsageCredits::class)->middleware('throttle:60,1')->name('n2n.credits');

    Route::get('/n2n/chat', Chat::class)->name('n2n.chat');
    Route::get('/n2n/planner', \App\Livewire\N2N\Planner::class)->middleware('throttle:60,1')->name('n2n.planner');
    Route::get('/n2n/events/{eventId}.ics', [\App\Http\Controllers\N2N\EventController::class, 'ics'])->whereNumber('eventId')->middleware('throttle:30,1')->name('n2n.event.ics');
    Route::prefix('n2n/api')->name('n2n.api.')->middleware('throttle:n2n-send')->group(function () {
        Route::post('messages', [MessagingController::class, 'send'])->name('start');
        Route::post('media/quote', [MessagingController::class, 'quote'])->name('media.quote');
        Route::post('media/presign', [MessagingController::class, 'presign'])->name('media.presign');
        Route::post('media/finalize', [MessagingController::class, 'finalize'])->name('media.finalize');
        // A plain numeric id, not model binding: a missing and a not-yours conversation must answer identically (no IDOR oracle).
        Route::post('conversations/{conversationId}/messages', [MessagingController::class, 'send'])->whereNumber('conversationId')->name('send');
        Route::post('live/{messageId}/update', [MessagingController::class, 'liveUpdate'])->whereNumber('messageId')->middleware('throttle:30,1')->name('live.update');
        Route::post('live/{messageId}/stop', [MessagingController::class, 'liveStop'])->whereNumber('messageId')->name('live.stop');
        Route::get('live/{messageId}', [MessagingController::class, 'liveRead'])->whereNumber('messageId')->middleware('throttle:30,1')->name('live.read');
    });

    Route::get('/n2n/status/{post}/media', [\App\Http\Controllers\N2N\StatusMediaController::class, 'show'])->whereNumber('post')->middleware('throttle:240,1')->name('n2n.status.media');
    Route::get('/n2n/media/{attachment}', [MediaController::class, 'show'])->whereNumber('attachment')->middleware('throttle:240,1')->name('n2n.media');

    // Batch 12: per-member conversation tools (export, own wallpaper photo). Plain numeric ids: missing and not-yours answer the same.
    Route::get('/n2n/api/conversations/{conversationId}/peek', [\App\Http\Controllers\N2N\PeekController::class, 'show'])->whereNumber('conversationId')->middleware('throttle:60,1')->name('n2n.api.peek');
    Route::get('/n2n/chat/{conversationId}/export', [\App\Http\Controllers\N2N\ConversationToolsController::class, 'export'])->whereNumber('conversationId')->middleware('throttle:6,1')->name('n2n.chat.export');
    Route::get('/n2n/wallpaper/{attachment}', [\App\Http\Controllers\N2N\ConversationToolsController::class, 'wallpaper'])->whereNumber('attachment')->middleware('throttle:240,1')->name('n2n.wallpaper');
    Route::post('/n2n/api/conversations/{conversationId}/wallpaper', [\App\Http\Controllers\N2N\ConversationToolsController::class, 'upload'])->whereNumber('conversationId')->middleware('throttle:10,1')->name('n2n.api.wallpaper');
    Route::delete('/n2n/api/conversations/{conversationId}/wallpaper', [\App\Http\Controllers\N2N\ConversationToolsController::class, 'remove'])->whereNumber('conversationId')->middleware('throttle:10,1')->name('n2n.api.wallpaper.remove');

    Route::prefix('n2n/realtime')->name('n2n.realtime.')->middleware('throttle:n2n-realtime')->group(function () {
        Route::get('config', [RealtimeController::class, 'config'])->name('config');
        Route::get('sync', [RealtimeController::class, 'sync'])->name('sync');
        Route::post('ping', [RealtimeController::class, 'ping'])->name('ping');
        Route::post('typing', [RealtimeController::class, 'typing'])->name('typing');
        Route::get('seen/{naaraId}', [RealtimeController::class, 'seen'])->name('seen');
    });
});

// Batch 8 (brand profile extension + local profiles). Public: the album smart-link page and cached thumbnails (no personal data).
Route::middleware(['n2n.enabled'])->group(function () {
    Route::get('/n2n/brand/{brand}/a/{slug}', [\App\Http\Controllers\N2N\AlbumController::class, 'show'])->whereNumber('brand')->middleware('throttle:120,1')->name('n2n.album');
    Route::get('/n2n/thumb/{provider}/{id}', [\App\Http\Controllers\N2N\ProfileMediaController::class, 'thumb'])->where('provider', 'youtube|vimeo')->where('id', '[A-Za-z0-9_-]{5,20}')->middleware('throttle:240,1')->name('n2n.thumb');
});
// Batch 11 (group calls, VPS only). The link page is public (a visitor is sent through sign-up with the host's referral code); everything else needs a signed-in member.
Route::middleware(['n2n.enabled'])->get('/n2n/call/{token}', [\App\Http\Controllers\N2N\GroupCallController::class, 'landing'])->where('token', '[A-Za-z0-9]{1,64}')->middleware('throttle:60,1')->name('n2n.group.join');
Route::middleware(['n2n.enabled', 'auth', 'active'])->group(function () {
    Route::get('/n2n/group/{callId}', [\App\Http\Controllers\N2N\GroupCallController::class, 'room'])->whereNumber('callId')->name('n2n.group.room');
    Route::prefix('n2n/api/group')->name('n2n.api.group.')->middleware('throttle:n2n-call-signal')->group(function () {
        $c = \App\Http\Controllers\N2N\GroupCallController::class;
        Route::get('config', [$c, 'config'])->name('config');
        Route::post('/', [$c, 'create'])->name('create');
        Route::post('join', [$c, 'join'])->name('join');
        Route::get('{callId}/status', [$c, 'status'])->whereNumber('callId')->name('status');
        Route::get('{callId}/state', [$c, 'state'])->whereNumber('callId')->name('state');
        Route::post('{callId}/heartbeat', [$c, 'heartbeat'])->whereNumber('callId')->name('heartbeat');
        Route::post('{callId}/leave', [$c, 'leave'])->whereNumber('callId')->name('leave');
        Route::post('{callId}/close', [$c, 'close'])->whereNumber('callId')->name('close');
        Route::post('{callId}/link', [$c, 'newLink'])->whereNumber('callId')->name('link');
        Route::delete('{callId}/link', [$c, 'revokeLinks'])->whereNumber('callId')->name('link.revoke');
        Route::post('{callId}/people/{userId}/appoint', [$c, 'appoint'])->whereNumber(['callId', 'userId'])->name('appoint');
        Route::post('{callId}/people/{userId}/demote', [$c, 'demote'])->whereNumber(['callId', 'userId'])->name('demote');
        Route::post('{callId}/people/{userId}/mute', [$c, 'mute'])->whereNumber(['callId', 'userId'])->name('mute');
        Route::post('{callId}/people/{userId}/remove', [$c, 'remove'])->whereNumber(['callId', 'userId'])->name('remove');
        Route::post('{callId}/people/{userId}/approve', [$c, 'approve'])->whereNumber(['callId', 'userId'])->name('approve');
    });
});
Route::middleware(['n2n.enabled', 'auth', 'active'])->group(function () {
    // Voice calls (Batch 10): numeric ids only, so a missing call and a call that is not yours answer the same.
    Route::get('/n2n/calls', \App\Livewire\N2N\Calls::class)->name('n2n.calls');
    Route::prefix('n2n/api/calls')->name('n2n.api.calls.')->middleware('throttle:n2n-call-signal')->group(function () {
        Route::get('config', [\App\Http\Controllers\N2N\CallController::class, 'config'])->name('config');
        Route::post('/', [\App\Http\Controllers\N2N\CallController::class, 'start'])->name('start');
        Route::post('{callId}/accept', [\App\Http\Controllers\N2N\CallController::class, 'accept'])->whereNumber('callId')->name('accept');
        Route::post('{callId}/decline', [\App\Http\Controllers\N2N\CallController::class, 'decline'])->whereNumber('callId')->name('decline');
        Route::post('{callId}/hangup', [\App\Http\Controllers\N2N\CallController::class, 'hangup'])->whereNumber('callId')->name('hangup');
        Route::post('{callId}/signal', [\App\Http\Controllers\N2N\CallController::class, 'signal'])->whereNumber('callId')->name('signal');
        Route::post('{callId}/heartbeat', [\App\Http\Controllers\N2N\CallController::class, 'heartbeat'])->whereNumber('callId')->name('heartbeat');
        Route::get('{callId}/ice', [\App\Http\Controllers\N2N\CallController::class, 'ice'])->whereNumber('callId')->name('ice');
        // After-call rating sheet and its Trustpilot invitation.
        Route::get('{callId}/review', [\App\Http\Controllers\N2N\CallController::class, 'reviewPrompt'])->whereNumber('callId')->name('review');
        Route::post('{callId}/review', [\App\Http\Controllers\N2N\CallController::class, 'reviewRate'])->whereNumber('callId')->name('review.rate');
        Route::post('{callId}/review/dismiss', [\App\Http\Controllers\N2N\CallController::class, 'reviewDismiss'])->whereNumber('callId')->name('review.dismiss');
        Route::post('{callId}/review/opened', [\App\Http\Controllers\N2N\CallController::class, 'reviewOpened'])->whereNumber('callId')->name('review.opened');
    });
    Route::get('/n2n/people', \App\Livewire\N2N\People::class)->name('n2n.people');
    Route::get('/n2n/updates', \App\Livewire\N2N\Updates::class)->name('n2n.updates');
    Route::get('/u/{handle}', \App\Livewire\N2N\MemberProfile::class)->where('handle', '[^/]{2,40}')->middleware('throttle:120,1')->name('n2n.profile');
    Route::get('/account/naara-profile', \App\Livewire\N2N\ProfileSettings::class)->name('n2n.profile.settings');
    Route::get('/brand/manage/connections', \App\Livewire\BrandConnections::class)->name('brand.connections');
    Route::get('/brand/manage/profile', \App\Livewire\N2N\BrandProfileManage::class)->name('n2n.brand.manage');
    Route::get('/n2n/profile-picture/{userId}/{slot}', [\App\Http\Controllers\N2N\ProfileMediaController::class, 'picture'])->whereNumber(['userId', 'slot'])->middleware('throttle:240,1')->name('n2n.profile.picture');
});

// Brand-Growth Phase 5: the public Business Card (page, QR download, contact card). Public, throttled; only a listed brand has one.
Route::get('/b/{code}', [\App\Http\Controllers\BusinessCardController::class, 'show'])->where('code', '[0-9A-Za-z*~$=-]{3,30}')->middleware('throttle:120,1')->name('business-card.show');
Route::get('/b/{code}/qr.{format}', [\App\Http\Controllers\BusinessCardController::class, 'qr'])->where('code', '[0-9A-Za-z*~$=-]{3,30}')->where('format', 'svg|png')->middleware('throttle:60,1')->name('business-card.qr');
Route::get('/b/{code}/card.vcf', [\App\Http\Controllers\BusinessCardController::class, 'vcard'])->where('code', '[0-9A-Za-z*~$=-]{3,30}')->middleware('throttle:60,1')->name('business-card.vcard');
Route::get('/b/{code}/card.pdf', [\App\Http\Controllers\BusinessCardController::class, 'pdf'])->where('code', '[0-9A-Za-z*~$=-]{3,30}')->middleware('throttle:20,1')->name('business-card.pdf');

// Gestures blueprint: anonymous gesture counters (batched beacon from the engine) and the member's gesture settings screen.
Route::middleware(['auth', 'active', 'n2n.enabled'])->group(function () {
    Route::post('/n2n/api/gesture-stat', [\App\Http\Controllers\N2N\GestureStatController::class, 'store'])->middleware('throttle:30,1')->name('n2n.api.gesture-stat');
    Route::get('/account/chat-gestures', \App\Livewire\N2N\GestureSettings::class)->name('n2n.gestures');
});
