{{-- nx:converted (skin tokens; legacy palette classes removed 2026-10-05) --}}
{{-- Premium brand card on the skin system: shared by the Featured band and the category sections (BUILD-9 §8.1). The cover is a photo (or the
     brand's own colour) under a fixed dark scrim, so its title keeps light ink in every skin. --}}
<div class="ns-bh__card ns-ring" wire:key="brand-{{ $brand->id }}">
    @php($hero = $brand->hero_image_path ?: $brand->fallback_image)
    <a href="{{ route('rewards.hunt.profile', $brand) }}" wire:navigate class="ns-bh__cover" aria-label="{{ $brand->brand_name }}">
        @if ($hero)
            <img src="{{ $hero }}" alt="">
        @else
            <div style="width:100%;height:100%;background: linear-gradient(135deg, {{ $brand->background_color }}, {{ $brand->background_color }}99)"></div>
        @endif
        <span class="ns-bh__covertext">
            <h3><span>{{ $brand->brand_name }}</span>@if ($brand->isNaaraPro())<x-naara-pro-badge size="md" />@endif</h3>
            @if ($brand->is_featured)<span class="ns-bh__feat"><x-nx.icon name="check" style="font-size:12px" /> Featured</span>@endif
        </span>
    </a>
    <div class="ns-bh__body">
        <div class="ns-bh__meta">
            @if ($brand->category)<span class="ns-bh__tag"><x-nx.icon name="hash" style="font-size:12px" /> {{ $brand->category }}</span>@else<span></span>@endif
            {{-- Connect: a plain in-platform follow, distinct from the handle-follow-for-credits grid below. No reward, just a relationship + follower count. --}}
            @php($following = isset($followingBrandIds[$brand->id]))
            <button type="button" wire:click="toggleConnect({{ $brand->id }})" wire:loading.attr="disabled" wire:target="toggleConnect({{ $brand->id }})" class="ns-bh__connect {{ $following ? 'is-on' : '' }}" aria-pressed="{{ $following ? 'true' : 'false' }}">
                <x-nx.icon :name="$following ? 'check' : 'plus'" style="font-size:13px" /> {{ $following ? 'Connected' : 'Connect' }}
            </button>
        </div>
        @if ($brand->short_description)<p class="ns-bh__desc">{{ $brand->short_description }}</p>@endif

        @if (! empty($brand->official_hidden))
            <p class="ns-bh__desc" style="margin-top:12px" data-official-note>Follow {{ $brand->brand_name }}'s official accounts in the Follow {{ \App\Support\BrandSettings::name() }} band at the top. Each follow is rewarded once.</p>
        @endif
        @if ($brand->handles->isNotEmpty())
            <div class="ns-bh__grid" style="margin-top:16px">
                @foreach ($brand->handles as $h)
                    @include('livewire.partials.hunt-handle-card', ['handle' => $h, 'claimed' => isset($claimedBrandHandles[$h->id]), 'action' => 'followBrandHandle'])
                @endforeach
            </div>
        @endif

        {{-- Video previews with watch-to-earn (server-confirmed watch-time). --}}
        @foreach ($brand->videos as $v)
            @php($embed = $v->embedUrl())
            @if ($embed)
                <div class="ns-bh__video" wire:key="vid-{{ $v->id }}"
                     x-data="{
                        playing: false, token: null, elapsed: 0, confirmed: 0, done: false, timer: null, startedAt: 0,
                        async start() {
                            const r = await $wire.videoStart({{ $v->id }});
                            if (!r) return;
                            this.token = r.token; this.playing = true; this.startedAt = Date.now();
                            this.timer = setInterval(async () => {
                                this.elapsed = Math.floor((Date.now() - this.startedAt) / 1000);
                                const s = await $wire.videoHeartbeat(this.token, this.elapsed);
                                this.confirmed = s.confirmed;
                                if (s.claimed || s.already) { this.done = true; clearInterval(this.timer); }
                            }, 10000);
                        }
                     }" x-init="() => { window.addEventListener('beforeunload', () => timer && clearInterval(timer)); }">
                    <template x-if="!playing">
                        <button @click="start()" type="button" class="ns-cta ns-cta--ghost" style="height:48px;font-size:16px"><x-nx.icon name="play" /> Watch to earn</button>
                    </template>
                    <template x-if="playing">
                        <div>
                            <div class="ns-bh__frame"><iframe :src="token ? '{{ $embed }}' : ''" allow="autoplay; encrypted-media" allowfullscreen referrerpolicy="strict-origin-when-cross-origin" title="Video preview"></iframe></div>
                            <p class="ns-small" style="margin:8px 0 0;text-align:center">
                                <span x-show="!done">Keep watching: <span x-text="confirmed"></span>s / 60s confirmed</span>
                                <span x-show="done" style="font-weight:700;color:color-mix(in srgb, rgb(var(--nx-ok)) 72%, rgb(var(--nx-text)))">Reward unlocked!</span>
                            </p>
                        </div>
                    </template>
                </div>
            @endif
        @endforeach
    </div>
</div>
