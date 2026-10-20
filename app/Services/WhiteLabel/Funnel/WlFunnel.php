<?php

namespace App\Services\WhiteLabel\Funnel;

use App\Models\Setting;
use App\Models\User;
use App\Models\WhiteLabelLicensePlan;
use App\Models\WhiteLabelProjectIntake;
use App\Models\WlFunnelEvent;
use App\Models\WlFunnelInstance;
use App\Support\FeatureFlags;
use App\Support\Notifications\Notify;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use libphonenumber\PhoneNumberUtil;

/**
 * The White Label purchase funnel, phases 1 and 3 (MASTER-ONLY): the instance with its ID, the explicit state machine, and the conversational wizard's saved answers.
 * Only a visitor who arrives from the White Label landing page gets an instance; registering anywhere else never creates one. Nothing here grants any access:
 * the wizard needs no merchant status, so no stranger is ever "unlocked" as a merchant. Payments, licence issuance and invoicing are later phases and stay behind their flags.
 */
class WlFunnel
{
    public const COOKIE = 'wl_funnel_visitor';

    public const COOKIE_DAYS = 30;

    public const STEPS = ['brand', 'palette', 'hosting', 'contact', 'billing', 'plan', 'review'];

    public static function enabled(): bool
    {
        return FeatureFlags::enabled('wl_funnel');
    }

    public static function draftTtlDays(): int
    {
        return max(1, (int) Setting::getValue('wl.funnel.draft_ttl_days', 30));
    }

    /** @return \Illuminate\Support\Collection<int, WhiteLabelLicensePlan> the four plans, cheapest first (prices always come from the rows, never from code) */
    public function plans()
    {
        return WhiteLabelLicensePlan::where('is_active', true)->orderBy('price_usd')->get();
    }

    public function newVisitorId(): string
    {
        return bin2hex(random_bytes(16));
    }

    // ---- instances ---------------------------------------------------------------------------------------------------------------------------

    /**
     * Start (or resume) from a landing-page plan button. Reuses the person's open instance; only an instance that never progressed past the landing click changes its
     * remembered plan, so a bought-into draft is never overwritten by browsing another button.
     */
    public function start(string $planKey, string $visitorId, ?User $user = null, array $utm = []): WlFunnelInstance
    {
        $plan = WhiteLabelLicensePlan::where('key', $planKey)->where('is_active', true)->first();
        if (! $plan) {
            throw new \InvalidArgumentException('That plan is not available.');
        }

        return DB::transaction(function () use ($plan, $visitorId, $user, $utm) {
            $open = WlFunnelInstance::query()->whereNotIn('status', [WlFunnelStates::EXPIRED, WlFunnelStates::REJECTED, WlFunnelStates::REFUNDED, WlFunnelStates::LIVE])
                ->where(fn ($q) => $q->when($user, fn ($q2) => $q2->where('user_id', $user->id))->orWhere('visitor_id', $visitorId))
                ->orderByDesc('id')->lockForUpdate()->first();
            if ($open) {
                if (in_array($open->status, [WlFunnelStates::LANDING, WlFunnelStates::REGISTERING], true) && $open->plan_key_at_landing !== $plan->key) {
                    $open->forceFill(['plan_key_at_landing' => $plan->key, 'selected_plan_key' => null])->save();
                }
                if ($user && ! $open->user_id) {
                    $this->claim($open, $user);
                }
                if ($open->status === WlFunnelStates::ABANDONED) {
                    $this->transition($open, $user ? WlFunnelStates::REGISTERED : WlFunnelStates::REGISTERING, 'user', ['revived' => true]);
                }

                return $open->refresh();
            }
            $inst = WlFunnelInstance::create([
                'public_code' => $this->uniqueCode($plan->key), 'plan_key_at_landing' => $plan->key, 'origin' => 'landing', 'utm' => $this->cleanUtm($utm),
                'visitor_id' => $visitorId, 'status' => WlFunnelStates::LANDING, 'draft_step' => 1, 'draft' => [], 'expires_at' => now()->addDays(self::draftTtlDays()),
            ]);
            $this->log($inst, null, WlFunnelStates::LANDING, 'user', ['plan' => $plan->key]);
            if ($user) {
                $this->claim($inst, $user);
            } else {
                $this->transition($inst, WlFunnelStates::REGISTERING, 'user');
            }

            return $inst->refresh();
        });
    }

    /** The visitor became (or logged in as) a person: bind the instance to them. One instance per person: an existing open one wins. */
    public function claim(WlFunnelInstance $inst, User $user): WlFunnelInstance
    {
        $mine = WlFunnelInstance::where('user_id', $user->id)->where('id', '!=', $inst->id)->whereNotIn('status', [WlFunnelStates::EXPIRED, WlFunnelStates::REJECTED, WlFunnelStates::REFUNDED])->orderByDesc('id')->first();
        if ($mine) {
            return $mine;
        }
        if (! $inst->user_id) {
            $inst->forceFill(['user_id' => $user->id])->save();
        }
        if (in_array($inst->status, [WlFunnelStates::LANDING, WlFunnelStates::REGISTERING], true)) {
            $this->transition($inst, WlFunnelStates::REGISTERED, 'user', ['user' => $user->id]);
        }

        return $inst->refresh();
    }

    /** At registration or login: bind whatever instance this browser started. Never throws into the sign-up. */
    public function attachByVisitor(User $user, ?string $visitorId): ?WlFunnelInstance
    {
        try {
            if (! $visitorId || ! preg_match('/^[0-9a-f]{32}$/', $visitorId)) {
                return null;
            }
            $inst = WlFunnelInstance::where('visitor_id', $visitorId)->whereNull('user_id')->whereIn('status', [WlFunnelStates::LANDING, WlFunnelStates::REGISTERING])->orderByDesc('id')->first();

            return $inst ? $this->claim($inst, $user) : null;
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    public function forUser(User $user): ?WlFunnelInstance
    {
        return WlFunnelInstance::where('user_id', $user->id)->whereNotIn('status', [WlFunnelStates::EXPIRED, WlFunnelStates::REJECTED, WlFunnelStates::REFUNDED])->orderByDesc('id')->first();
    }

    public function transition(WlFunnelInstance $inst, string $to, string $actor = 'system', array $meta = []): bool
    {
        $from = $inst->status;
        if ($from === $to) {
            return false;
        }
        if (! WlFunnelStates::can($from, $to)) {
            throw new \DomainException("The funnel cannot go from {$from} to {$to}.");
        }
        $inst->forceFill(['status' => $to])->save();
        $this->log($inst, $from, $to, $actor, $meta);

        return true;
    }

    // ---- the wizard --------------------------------------------------------------------------------------------------------------------------

    /**
     * Save one wizard step (autosave). Validates only that step's fields and merges them into the draft; the draft holds no secrets (hosting logins are collected
     * after payment, never here). @return array<string, mixed> the cleaned values saved
     *
     * @throws ValidationException
     */
    public function saveStep(WlFunnelInstance $inst, string $step, array $in, ?User $user = null): array
    {
        if (! in_array($step, self::STEPS, true) || $step === 'review') {
            throw new \InvalidArgumentException('Unknown step.');
        }
        $clean = match ($step) {
            'brand' => $this->vBrand($inst, $in),
            'palette' => $this->vPalette($in),
            'hosting' => $this->vHosting($in),
            'contact' => $this->vContact($in),
            'billing' => $this->vBilling($in, $user),
            'plan' => $this->vPlan($in),
        };
        $draft = array_merge((array) $inst->draft, $clean);
        $next = min(count(self::STEPS), array_search($step, self::STEPS, true) + 2);
        $inst->forceFill([
            'draft' => $draft, 'draft_step' => max($inst->draft_step, $next),
            'hosting_choice' => $draft['hosting_choice'] ?? $inst->hosting_choice, 'selected_plan_key' => $draft['plan_key'] ?? $inst->selected_plan_key,
        ])->save();
        if (in_array($inst->status, [WlFunnelStates::REGISTERED, WlFunnelStates::DRAFT_SAVED, WlFunnelStates::PREVIEW_EXPIRED, WlFunnelStates::ABANDONED], true)) {
            $this->transition($inst, WlFunnelStates::WIZARD, 'user', ['step' => $step]);
        }

        return $clean;
    }

    /** Which steps still lack answers (the review step lists them). @return list<string> */
    public function missing(WlFunnelInstance $inst): array
    {
        $d = (array) $inst->draft;
        $need = ['brand' => 'brand_name', 'palette' => 'palette_key', 'hosting' => 'hosting_choice', 'contact' => 'contact_preference', 'billing' => 'billing_name', 'plan' => 'plan_key'];

        return array_values(array_keys(array_filter($need, fn ($k) => empty($d[$k]))));
    }

    /** Save as a draft: the buyer can leave and come back with their ID. */
    public function saveDraft(WlFunnelInstance $inst, ?User $user = null): void
    {
        $inst->forceFill(['draft_saved_at' => now(), 'expires_at' => now()->addDays(self::draftTtlDays())])->save();
        $this->transition($inst, WlFunnelStates::DRAFT_SAVED, 'user');
        if ($user) {
            Notify::event($user, 'wl.funnel_saved', ['code' => $inst->public_code], 'wl-funnel-saved-'.$inst->id.'-'.now()->format('YmdH'));
        }
    }

    /** What the review step shows: the choices and the real price (from the plan row) plus the balance a Basic or Medium buyer would owe to reach Extended. */
    public function review(WlFunnelInstance $inst): array
    {
        $d = (array) $inst->draft;
        $plan = WhiteLabelLicensePlan::where('key', $d['plan_key'] ?? $inst->plan_key_at_landing)->first();

        return [
            'code' => $inst->public_code, 'draft' => $d, 'missing' => $this->missing($inst), 'plan' => $plan,
            'price_usd' => $plan ? (float) $plan->price_usd : null,
            'balance_to_extended' => $plan ? WhiteLabelLicensePlan::balanceToExtended($plan) : null,
            'hosting_terms' => $this->hostingTerms(),
        ];
    }

    /** The free-hosting wording. Owner-supplied (Setting); until then a neutral line that promises nothing we have not written down. */
    public function hostingTerms(): string
    {
        $t = trim((string) Setting::getValue('wl.funnel.hosting_terms', ''));

        return $t !== '' ? $t : 'Supreme Ideas Agency can host your White Label for you. The included period and what hosting costs afterwards are confirmed in writing before you pay.';
    }

    // ---- validation per step -----------------------------------------------------------------------------------------------------------------

    private function v(array $in, array $rules): array
    {
        return Validator::make($in, $rules)->validate();
    }

    private function vBrand(WlFunnelInstance $inst, array $in): array
    {
        $d = $this->v($in, ['brand_name' => ['required', 'string', 'min:2', 'max:60', "regex:/^[\p{L}\p{N} &.'\\-]+$/u"]]);
        $name = trim(preg_replace('/\s+/', ' ', $d['brand_name']));
        $taken = WhiteLabelProjectIntake::whereRaw('lower(desired_brand_name) = ?', [mb_strtolower($name)])->exists()
            || WlFunnelInstance::where('id', '!=', $inst->id)->whereNotIn('status', [WlFunnelStates::ABANDONED, WlFunnelStates::EXPIRED])->get(['draft'])
                ->contains(fn ($o) => mb_strtolower((string) ($o->draft['brand_name'] ?? '')) === mb_strtolower($name));
        if ($taken) {
            throw ValidationException::withMessages(['brand_name' => 'Someone is already setting up a White Label with that name. Try a variation.']);
        }

        return ['brand_name' => $name];
    }

    private function vPalette(array $in): array
    {
        $d = $this->v($in, ['palette_key' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9_-]+$/'], 'primary' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'], 'accent' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/']]);
        if ($d['palette_key'] === 'custom' && (empty($d['primary']) || empty($d['accent']))) {
            throw ValidationException::withMessages(['primary' => 'Pick a main colour and an accent colour for your custom palette.']);
        }
        if ($d['palette_key'] !== 'custom' && ! \App\Support\ThemePreset::all()->contains(fn ($p) => $p['slug'] === $d['palette_key'])) {
            throw ValidationException::withMessages(['palette_key' => 'That palette is not available.']);
        }

        return ['palette_key' => $d['palette_key'], 'primary' => $d['primary'] ?? null, 'accent' => $d['accent'] ?? null];
    }

    private function vHosting(array $in): array
    {
        $d = $this->v($in, ['hosting_choice' => ['required', 'in:supreme_ideas_server,own_vps,own_shared'], 'disclaimer' => ['nullable', 'boolean']]);
        if ($d['hosting_choice'] !== 'supreme_ideas_server' && empty($d['disclaimer'])) {
            throw ValidationException::withMessages(['disclaimer' => 'Please confirm you have read the server requirements.']);
        }

        return ['hosting_choice' => $d['hosting_choice'], 'hosting_disclaimer_ack' => $d['hosting_choice'] === 'supreme_ideas_server' ? null : now()->toIso8601String()];
    }

    private function vContact(array $in): array
    {
        $d = $this->v($in, ['contact_preference' => ['required', 'in:notification,email,whatsapp'], 'whatsapp' => ['nullable', 'string', 'max:32'], 'whatsapp_consent' => ['nullable', 'boolean']]);
        $e164 = null;
        if (! empty($d['whatsapp'])) {
            $e164 = $this->e164($d['whatsapp']);
            if ($e164 === null) {
                throw ValidationException::withMessages(['whatsapp' => 'Enter the number with its country code, for example +2348012345678.']);
            }
            if (empty($d['whatsapp_consent'])) {
                throw ValidationException::withMessages(['whatsapp_consent' => 'Tick the box to let us contact you on WhatsApp, or remove the number.']);
            }
        }
        if ($d['contact_preference'] === 'whatsapp' && $e164 === null) {
            throw ValidationException::withMessages(['whatsapp' => 'Add your WhatsApp number to be contacted there.']);
        }

        return ['contact_preference' => $d['contact_preference'], 'whatsapp' => $e164, 'whatsapp_consent' => $e164 !== null];
    }

    private function vBilling(array $in, ?User $user): array
    {
        $in['billing_email'] = $in['billing_email'] ?? $user?->email;
        $d = $this->v($in, ['billing_name' => ['required', 'string', 'min:2', 'max:120'], 'billing_address' => ['required', 'string', 'min:5', 'max:240'], 'billing_country' => ['required', 'regex:/^[A-Za-z]{2}$/'], 'billing_tax_id' => ['nullable', 'string', 'max:40'], 'billing_email' => ['required', 'email', 'max:190']]);
        $d['billing_country'] = strtoupper($d['billing_country']);

        return array_map(fn ($v) => is_string($v) ? trim($v) : $v, $d);
    }

    private function vPlan(array $in): array
    {
        $d = $this->v($in, ['plan_key' => ['required', 'string']]);
        if (! WhiteLabelLicensePlan::where('key', $d['plan_key'])->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['plan_key' => 'That plan is not available.']);
        }

        return ['plan_key' => $d['plan_key']];
    }

    private function e164(string $raw): ?string
    {
        try {
            $util = PhoneNumberUtil::getInstance();
            $n = $util->parse(trim($raw), str_starts_with(trim($raw), '+') ? null : 'NG');

            return $util->isValidNumber($n) ? $util->format($n, \libphonenumber\PhoneNumberFormat::E164) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    // ---- internals ---------------------------------------------------------------------------------------------------------------------------

    private function uniqueCode(string $planKey): string
    {
        for ($i = 0; $i < 8; $i++) {
            $c = WlFunnelCode::generate($planKey);
            if (! WlFunnelInstance::where('public_code', $c)->exists()) {
                return $c;
            }
        }
        throw new \RuntimeException('Could not issue a funnel code.');
    }

    private function log(WlFunnelInstance $inst, ?string $from, string $to, string $actor, array $meta = []): void
    {
        WlFunnelEvent::create(['instance_id' => $inst->id, 'from_status' => $from, 'to_status' => $to, 'actor' => $actor, 'meta' => $meta ?: null, 'created_at' => now()]);
    }

    /** Only well-formed campaign tags are kept, and never click ids (those need consent handling that arrives with analytics). */
    private function cleanUtm(array $utm): ?array
    {
        $out = [];
        foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'] as $k) {
            if (isset($utm[$k]) && is_string($utm[$k]) && preg_match('/^[\w.\- ]{1,80}$/u', $utm[$k])) {
                $out[$k] = $utm[$k];
            }
        }

        return $out ?: null;
    }

    public static function resumeUrl(WlFunnelInstance $inst): string
    {
        return route('wl-funnel.purchase');
    }

    public static function randomHex(): string
    {
        return Str::lower(bin2hex(random_bytes(16)));
    }
}
