{{-- A voice message that plays like a chat app's: round play/pause, the note's waveform, tap or drag to seek, speed, a dot on unheard notes (master ships the full player; an install without it keeps the browser's own control). --}}
@props(['src', 'id', 'mine' => false, 'seconds' => 0])
@if (file_exists(resource_path('js/n2n/nvp.js')))
    @vite('resources/js/n2n/nvp.js')
    <div class="nvp nvp--support {{ $mine ? 'nvp--mine' : '' }}" wire:ignore data-nvp data-id="{{ $id }}" data-src="{{ $src }}" data-dur="{{ (int) $seconds }}" data-mine="{{ $mine ? 1 : 0 }}" data-voice-note>
        <button type="button" class="nvp__btn" aria-label="Play voice message"><svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M8 5.5v13a1 1 0 0 0 1.5.86l10.5-6.5a1 1 0 0 0 0-1.72L9.5 4.64A1 1 0 0 0 8 5.5Z"/></svg></button>
        <div class="nvp__body" role="slider" tabindex="0" aria-label="Voice message position" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
            <canvas class="nvp__wave" data-no-swipe aria-hidden="true"></canvas>
            <div class="nvp__row"><span class="nvp__time">0:00</span><i class="nvp__dot" hidden aria-label="Not played yet"></i><button type="button" class="nvp__speed" hidden aria-label="Playback speed">1x</button></div>
        </div>
    </div>
@else
    <audio controls preload="none" src="{{ $src }}" class="mt-2 w-full"></audio>
@endif
