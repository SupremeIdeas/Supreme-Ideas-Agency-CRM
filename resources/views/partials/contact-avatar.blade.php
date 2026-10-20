@php($__c = $contact->avatarColor())
{{-- Inline gradient: the colour is chosen at runtime per contact (data, not theme). The initials sit on a darkened scrim so white stays
     legible whatever hue the contact got. --}}
<span class="ns-ct__avatar flex shrink-0 items-center justify-center rounded-full font-bold {{ $size ?? 'h-10 w-10 text-sm' }}"
      style="background-image: linear-gradient(135deg, {{ $__c['from'] }}, {{ $__c['to'] }})"
      aria-hidden="true"><span>{{ $contact->initials() }}</span></span>
