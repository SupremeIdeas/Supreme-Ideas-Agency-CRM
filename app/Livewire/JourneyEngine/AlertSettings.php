<?php

namespace App\Livewire\JourneyEngine;

use App\Services\JourneyEngine\ChannelConsent;
use App\Support\Auditor;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * "Naara alerts" (Journey Engine J2): the user's own switches for being told off-app when something needs them (data low, eSIM expiring, a renewal the wallet cannot
 * cover). Each channel is OFF until the user turns it on, turning it off takes effect on the next run, and the page says exactly what is and is not sent. Suggestions never
 * leave the app, so there is no switch for them.
 */
#[Layout('components.layouts.customer')]
class AlertSettings extends Component
{
    public bool $push = false;

    public bool $email = false;

    public ?string $notice = null;

    public function mount(ChannelConsent $consent): void
    {
        $user = auth()->user();
        $this->push = $consent->granted($user, 'push');
        $this->email = $consent->granted($user, 'email');
    }

    public function toggle(string $channel, ChannelConsent $consent): void
    {
        if (! in_array($channel, ChannelConsent::CHANNELS, true)) {
            return;
        }
        $user = auth()->user();
        $on = ! $consent->granted($user, $channel);
        $on ? $consent->grant($user, $channel) : $consent->revoke($user, $channel);
        $this->{$channel} = $on;
        Auditor::log($on ? 'journey.alert_consent_granted' : 'journey.alert_consent_revoked', null, null, ['channel' => $channel]);
        $this->notice = __($on ? 'journey_engine.delivery.on' : 'journey_engine.delivery.off', ['channel' => __("journey_engine.delivery.channel_{$channel}")]);
    }

    public function render()
    {
        return view('livewire.journey-engine.alert-settings');
    }
}
