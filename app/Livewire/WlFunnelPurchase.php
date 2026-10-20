<?php

namespace App\Livewire;

use App\Models\WlFunnelInstance;
use App\Services\WhiteLabel\Funnel\WlFunnel;
use App\Services\WhiteLabel\Funnel\WlFunnelStates;
use App\Support\ThemePreset;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * "Naara guide": the conversational White Label purchase wizard (Funnel Phase 3, MASTER-ONLY). One question at a time, progress, Back/Edit, autosave on every step,
 * save-as-draft with the funnel reference. It needs no merchant status and grants no access. Paying is a later phase: the last screen says so plainly instead of pretending.
 */
#[Layout('components.layouts.customer')]
class WlFunnelPurchase extends Component
{
    public int $step = 1;

    public string $brand_name = '';

    public string $palette_key = '';

    public ?string $primary = '#0A6E6E';

    public ?string $accent = '#D4A017';

    public string $hosting_choice = 'supreme_ideas_server';

    public bool $disclaimer = false;

    public string $contact_preference = 'notification';

    public string $whatsapp = '';

    public bool $whatsapp_consent = false;

    public string $billing_name = '';

    public string $billing_address = '';

    public string $billing_country = '';

    public string $billing_tax_id = '';

    public string $billing_email = '';

    public string $plan_key = '';

    public ?string $notice = null;

    public function mount(WlFunnel $funnel)
    {
        abort_unless(WlFunnel::enabled(), 404);   // switched off: a plain 404 (the landing buttons already point elsewhere)
        $user = Auth::user();
        $inst = $funnel->forUser($user);
        if (! $inst) {
            // A signed-in person who did not come through the landing page has no instance: send them to the page that starts one.
            return $this->redirectRoute('white-label.landing');
        }
        $d = (array) $inst->draft;
        $this->fill([
            'brand_name' => (string) ($d['brand_name'] ?? ''), 'palette_key' => (string) ($d['palette_key'] ?? ''), 'primary' => $d['primary'] ?? '#0A6E6E', 'accent' => $d['accent'] ?? '#D4A017',
            'hosting_choice' => (string) ($d['hosting_choice'] ?? 'supreme_ideas_server'), 'disclaimer' => ! empty($d['hosting_disclaimer_ack']),
            'contact_preference' => (string) ($d['contact_preference'] ?? 'notification'), 'whatsapp' => (string) ($d['whatsapp'] ?? ''), 'whatsapp_consent' => ! empty($d['whatsapp_consent']),
            'billing_name' => (string) ($d['billing_name'] ?? $user->name), 'billing_address' => (string) ($d['billing_address'] ?? ''), 'billing_country' => (string) ($d['billing_country'] ?? strtoupper((string) $user->country_code)),
            'billing_tax_id' => (string) ($d['billing_tax_id'] ?? ''), 'billing_email' => (string) ($d['billing_email'] ?? $user->email),
            'plan_key' => (string) ($d['plan_key'] ?? $inst->selected_plan_key ?? $inst->plan_key_at_landing),
        ]);
        $this->step = max(1, min(count(WlFunnel::STEPS), (int) $inst->draft_step));
    }

    private function instance(): WlFunnelInstance
    {
        return app(WlFunnel::class)->forUser(Auth::user()) ?? abort(404);
    }

    private function payload(string $stepKey): array
    {
        return match ($stepKey) {
            'brand' => ['brand_name' => $this->brand_name],
            'palette' => ['palette_key' => $this->palette_key, 'primary' => $this->palette_key === 'custom' ? $this->primary : null, 'accent' => $this->palette_key === 'custom' ? $this->accent : null],
            'hosting' => ['hosting_choice' => $this->hosting_choice, 'disclaimer' => $this->disclaimer],
            'contact' => ['contact_preference' => $this->contact_preference, 'whatsapp' => $this->whatsapp, 'whatsapp_consent' => $this->whatsapp_consent],
            'billing' => ['billing_name' => $this->billing_name, 'billing_address' => $this->billing_address, 'billing_country' => $this->billing_country, 'billing_tax_id' => $this->billing_tax_id, 'billing_email' => $this->billing_email],
            'plan' => ['plan_key' => $this->plan_key],
        };
    }

    /** Save this step (autosave) and move on. A validation error keeps the person here with a specific message. */
    public function next(WlFunnel $funnel): void
    {
        $this->notice = null;
        $key = WlFunnel::STEPS[$this->step - 1];
        if ($key !== 'review') {
            try {
                $funnel->saveStep($this->instance(), $key, $this->payload($key), Auth::user());
            } catch (ValidationException $e) {
                $this->setErrorBag($e->validator->errors());

                return;
            }
        }
        $this->resetErrorBag();
        $this->step = min(count(WlFunnel::STEPS), $this->step + 1);
    }

    public function back(): void
    {
        $this->resetErrorBag();
        $this->step = max(1, $this->step - 1);
    }

    public function goTo(int $step): void
    {
        $inst = $this->instance();
        if ($step >= 1 && $step <= max(1, (int) $inst->draft_step)) {
            $this->resetErrorBag();
            $this->step = $step;
        }
    }

    public function saveDraft(WlFunnel $funnel): void
    {
        $inst = $this->instance();
        $funnel->saveDraft($inst, Auth::user());
        $this->notice = 'Saved. Come back any time and quote '.$inst->public_code.' if you need our help.';
    }

    public function render(WlFunnel $funnel)
    {
        $inst = $this->instance();
        $palettes = ThemePreset::all()->take(24)->map(function ($p) {
            $c = $p['tokens']['colors'] ?? [];
            $hex = fn ($t) => is_string($t) && preg_match('/^(\d{1,3}) (\d{1,3}) (\d{1,3})$/', $t, $m) ? sprintf('#%02x%02x%02x', $m[1], $m[2], $m[3]) : null;

            return ['slug' => $p['slug'], 'name' => $p['name'], 'primary' => $hex($c['primary'] ?? null), 'accent' => $hex($c['accent'] ?? null)];
        })->filter(fn ($p) => $p['primary'])->values();

        return view('livewire.wl-funnel-purchase', [
            'inst' => $inst, 'keys' => WlFunnel::STEPS, 'plans' => $funnel->plans(), 'palettes' => $palettes,
            'review' => $this->step === count(WlFunnel::STEPS) ? $funnel->review($inst) : null,
            'contrast' => $this->contrast((string) $this->primary),
            'hostingTerms' => $funnel->hostingTerms(),
        ]);
    }

    /** WCAG contrast of white text on the chosen main colour, so a low-contrast palette is flagged (never blocked). */
    private function contrast(string $hex): ?float
    {
        if (! preg_match('/^#([0-9a-fA-F]{6})$/', $hex, $m)) {
            return null;
        }
        $lum = function (string $h) {
            $v = array_map(fn ($x) => hexdec($x) / 255, str_split($h, 2));
            $v = array_map(fn ($c) => $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4, $v);

            return 0.2126 * $v[0] + 0.7152 * $v[1] + 0.0722 * $v[2];
        };

        return round((1.05) / ($lum($m[1]) + 0.05), 2);
    }
}
