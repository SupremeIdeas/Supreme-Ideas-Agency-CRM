<?php

namespace App\Livewire;

use App\Jobs\SyncVoiceWebhookJob;
use App\Models\CallForwardingRule;
use App\Models\VirtualNumber;
use App\Support\Auditor;
use App\Services\Voice\VoiceProviders;
use App\Support\ProviderStatus;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Call forwarding for permanent NaaraSim numbers (Live Voice — Part A). A user
 * points inbound calls on their number at their real phone. Feature-gated on the
 * voice providers' status (voice rides the same credentials): the screen is hidden
 * until Twilio or Telnyx is Active. A number is forwarded by the provider that owns it.
 */
#[Layout('components.layouts.customer')]
class CallForwarding extends Component
{
    public ?int $numberId = null;

    public string $forwardTo = '';

    public string $fallback = '';

    public ?string $error = null;

    public function mount(): void
    {
        abort_unless($this->voiceActive(), 404);
    }

    private function voiceActive(): bool
    {
        return ProviderStatus::isActive('twilio') || VoiceProviders::for('telnyx')->available();
    }

    /** The user's provisioned, voice-capable permanent numbers. */
    private function myNumbers()
    {
        return VirtualNumber::query()
            ->where('user_id', Auth::id())
            ->where('status', 'active')
            ->latest('id')->get();
    }

    public function edit(int $numberId): void
    {
        $this->numberId = $numberId;
        // Owner-scoped: numberId arrives from the client, and the rule exposes
        // the user's private forward-to / fallback numbers — an unscoped lookup
        // would disclose another user's forwarding destinations.
        $rule = CallForwardingRule::where('user_id', Auth::id())
            ->where('virtual_number_id', $numberId)->first();
        $this->forwardTo = $rule?->forward_to_number ?? '';
        $this->fallback = $rule?->fallback_number ?? '';
        $this->error = null;
    }

    public function save(): void
    {
        abort_unless($this->voiceActive(), 404);
        $this->error = null;
        $this->validate([
            'numberId' => 'required|integer',
            'forwardTo' => ['required', 'regex:/^\+[1-9]\d{6,14}$/'],
            'fallback' => ['nullable', 'regex:/^\+[1-9]\d{6,14}$/'],
        ], [
            'forwardTo.regex' => 'Enter a valid number in international format, e.g. +2348012345678.',
            'fallback.regex' => 'Enter a valid international number, or leave blank.',
        ]);

        $number = VirtualNumber::where('user_id', Auth::id())->find($this->numberId);
        if ($number === null) {
            $this->error = 'Number not found.';

            return;
        }

        // Lane rule: forwarding runs on the provider that owns the number, and only providers with a voice driver can do it.
        if (! VoiceProviders::supports($number->provider) || ! VoiceProviders::for($number->provider)->available()) {
            $this->error = 'Call forwarding isn’t available on this number yet.';

            return;
        }

        $rule = CallForwardingRule::updateOrCreate(
            ['twilio_number' => $number->phone_number],
            [
                'user_id' => Auth::id(),
                'virtual_number_id' => $number->id,
                'forward_to_number' => $this->forwardTo,
                'fallback_number' => $this->fallback ?: null,
                'status' => CallForwardingRule::ACTIVE,
            ],
        );

        // Point the number's inbound Voice URL at our TwiML webhook (queued).
        if ($number->sid) {
            SyncVoiceWebhookJob::dispatch($number->sid, route('webhooks.'.$number->provider.'.voice'), attach: true, provider: $number->provider);
        }
        Auditor::log('voice.forwarding_set', 'CallForwardingRule', $rule->id, ['number_id' => $number->id]);

        $this->reset('numberId', 'forwardTo', 'fallback');
        $this->dispatch('nx-toast', type: 'success', message: 'Call forwarding is on for '.$number->phone_number.'.');
    }

    public function disable(int $ruleId): void
    {
        $rule = CallForwardingRule::where('user_id', Auth::id())->findOrFail($ruleId);
        $rule->update(['status' => CallForwardingRule::INACTIVE]);

        $owner = $rule->virtualNumber;
        if ($owner?->sid && VoiceProviders::supports($owner->provider)) {
            SyncVoiceWebhookJob::dispatch($owner->sid, route('webhooks.'.$owner->provider.'.voice'), attach: false, provider: $owner->provider);
        }
        Auditor::log('voice.forwarding_disabled', 'CallForwardingRule', $rule->id);
        $this->dispatch('nx-toast', type: 'success', message: 'Call forwarding turned off.');
    }

    public function render()
    {
        $numbers = $this->myNumbers();
        $rules = CallForwardingRule::where('user_id', Auth::id())
            ->get()->keyBy('virtual_number_id');

        return view('livewire.call-forwarding', compact('numbers', 'rules'));
    }
}
