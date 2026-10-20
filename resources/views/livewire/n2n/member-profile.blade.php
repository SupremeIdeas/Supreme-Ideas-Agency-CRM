{{-- nx:converted --}}
{{-- Local member profile (N2N Batch 8, MASTER-ONLY). Renders only fields ProfileVisibility allowed. Skin tokens only. --}}
<div>
<x-nx.page class="ns-pg ns-pg--mid">
    <div class="ns-pg__card ns-ring" style="text-align:center">
        <div style="display:flex;justify-content:center;gap:10px;flex-wrap:wrap">
            @forelse (($data['pictures'] ?? []) as $slot)
                <img src="{{ route('n2n.profile.picture', ['userId' => $subjectId, 'slot' => $slot]) }}" alt="{{ $data['name'] }}" loading="lazy" width="112" height="112" style="width:112px;height:112px;border-radius:9999px;object-fit:cover">
            @empty
                <x-nx.avatar :name="$data['name']" :size="112" />
            @endforelse
        </div>
        <h1 class="ns-h1" style="margin-top:12px">{{ $data['name'] }}</h1>
        @if ($data['username'])<p class="ns-sub" style="margin:2px 0 0">{{ '@'.$data['username'] }}</p>@endif
        @isset($data['naara_id'])<p class="ns-sub" style="margin:2px 0 0;letter-spacing:.04em">{{ $data['naara_id'] }}</p>@endisset
        @isset($data['bio'])<p style="margin:12px 0 0;color:rgb(var(--nx-text))">{{ $data['bio'] }}</p>@endisset
        <dl style="margin:12px 0 0;display:grid;gap:6px">
            @isset($data['birthday'])<div><dt class="ns-pg__lbl">Birthday</dt><dd style="margin:0">{{ $data['birthday'] }}</dd></div>@endisset
            @isset($data['email'])<div><dt class="ns-pg__lbl">Email</dt><dd style="margin:0">{{ $data['email'] }}</dd></div>@endisset
            @isset($data['phone'])<div><dt class="ns-pg__lbl">Phone</dt><dd style="margin:0">{{ $data['phone'] }}</dd></div>@endisset
        </dl>
        @isset($data['business'])
            @php($biz = $data['business'])
            <div class="ns-pg__card" data-business-card style="margin-top:14px;text-align:start;display:flex;gap:12px;align-items:center">
                @if ($biz['image'])<img src="{{ $biz['image'] }}" alt="" width="56" height="56" loading="lazy" style="width:56px;height:56px;border-radius:14px;object-fit:cover">@endif
                <div style="min-width:0">
                    <p class="ns-pg__lbl" style="margin:0">Business</p>
                    <p style="margin:2px 0 0;font-weight:700;color:rgb(var(--nx-text))">{{ $biz['name'] }} <span class="ns-sub">· Naara Pro</span></p>
                    @if ($biz['category'])<p class="ns-sub" style="margin:0">{{ $biz['category'] }}</p>@endif
                    @if ($biz['about'])<p style="margin:4px 0 0;color:rgb(var(--nx-text-2))">{{ $biz['about'] }}</p>@endif
                </div>
            </div>
        @endisset
        @if ($notice)<p class="ns-sub" role="status" style="margin:10px 0 0">{{ $notice }}</p>@endif
        @if ($counts)<p class="ns-sub" style="margin:8px 0 0">{{ $counts['followers'] }} followers · {{ $counts['following'] }} following</p>@endif
        <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap;margin-top:16px">
            @if ($isSelf)
                <a href="{{ route('n2n.profile.settings') }}" wire:navigate class="ns-cta ns-cta--pill">Edit profile and privacy</a>
            @else
                <a href="{{ route('n2n.chat', ['to' => $data['naara_id'] ?? $data['username']]) }}" wire:navigate class="ns-cta ns-cta--pill"><x-nx.icon name="chat" /> Message</a>
                @if (! empty($data['naara_id']) && \App\Services\N2N\Calls\CallsAvailability::enabled())
                    <button type="button" class="ns-cta ns-cta--pill" data-n2n-call="{{ $data['naara_id'] }}"><x-nx.icon name="phone" /> Call</button>
                    @if (\App\Services\N2N\Calls\CallsAvailability::videoEnabled())<button type="button" class="ns-cta ns-cta--pill" data-n2n-video-call="{{ $data['naara_id'] }}"><x-nx.icon name="video" /> Video</button>@endif
                @endif
                @if ($iFollow)
                    <button type="button" wire:click="unfollow" wire:loading.attr="disabled" wire:target="unfollow" class="ns-cta ns-cta--pill">{{ $connected ? 'Connected' : 'Following' }}</button>
                @else
                    <button type="button" wire:click="follow" wire:loading.attr="disabled" wire:target="follow" class="ns-cta ns-cta--pill">{{ $followsMe ? 'Follow back' : 'Follow' }}</button>
                @endif
            @endif
        </div>
    </div>
</x-nx.page>
</div>
