<?php

use App\Http\Controllers\Handbooks\HandbookFileController;
use App\Http\Controllers\Handbooks\HandbookReaderController;
use App\Livewire\Handbooks\Library;
use App\Livewire\Handbooks\Store;
use Illuminate\Support\Facades\Route;

/** Naara Travel Handbook Store routes (MASTER-ONLY). Loaded by App\Providers\HandbookServiceProvider. Public pages are indexable; buying, the library and every file need a signed-in member. */
Route::get('/handbooks', [\App\Http\Controllers\Handbooks\HandbookStoreController::class, 'landing'])->name('handbooks.store');
Route::get('/handbooks/all', Store::class)->name('handbooks.directory');
Route::get('/handbooks/series/{seriesId}', [\App\Http\Controllers\Handbooks\HandbookPageController::class, 'series'])->whereNumber('seriesId')->name('handbooks.series');
Route::get('/handbooks/{slug}/cover', [HandbookFileController::class, 'cover'])->where('slug', '[a-z0-9-]{1,120}')->middleware('throttle:240,1')->name('handbooks.cover');
Route::get('/handbooks/{slug}/back-cover', [HandbookFileController::class, 'backCover'])->where('slug', '[a-z0-9-]{1,120}')->middleware('throttle:240,1')->name('handbooks.backcover');
Route::get('/handbooks/{slug}', [\App\Http\Controllers\Handbooks\HandbookPageController::class, 'show'])->where('slug', '[a-z0-9-]{1,120}')->name('handbooks.show');

Route::get('/library/sw.js', [HandbookReaderController::class, 'worker'])->name('handbooks.worker');

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/library', Library::class)->name('handbooks.library');
    Route::middleware('throttle:120,1')->group(function () {
        Route::get('/library/{slug}/read', [HandbookReaderController::class, 'show'])->where('slug', '[a-z0-9-]{1,120}')->name('handbooks.read');
        Route::get('/library/{slug}/epub', [HandbookReaderController::class, 'read'])->where('slug', '[a-z0-9-]{1,120}')->name('handbooks.file.read');
        Route::get('/library/{slug}/key', [HandbookReaderController::class, 'key'])->where('slug', '[a-z0-9-]{1,120}')->name('handbooks.key');
        Route::match(['get', 'post'], '/library/{slug}/progress', [HandbookReaderController::class, 'progress'])->where('slug', '[a-z0-9-]{1,120}')->name('handbooks.progress');
        Route::match(['get', 'post', 'delete'], '/library/{slug}/bookmarks', [HandbookReaderController::class, 'bookmarks'])->where('slug', '[a-z0-9-]{1,120}')->name('handbooks.bookmarks');
        Route::get('/library/{slug}/listen', [\App\Http\Controllers\Handbooks\HandbookAudioController::class, 'show'])->where('slug', '[a-z0-9-]{1,120}')->name('handbooks.listen');
        Route::get('/library/{slug}/audio/{trackId}', [\App\Http\Controllers\Handbooks\HandbookAudioController::class, 'stream'])->where('slug', '[a-z0-9-]{1,120}')->whereNumber('trackId')->name('handbooks.audio');
    });
    Route::get('/library/{slug}/download/{format}', [HandbookReaderController::class, 'download'])->where('slug', '[a-z0-9-]{1,120}')->where('format', 'epub|pdf')->name('handbooks.download');
});
