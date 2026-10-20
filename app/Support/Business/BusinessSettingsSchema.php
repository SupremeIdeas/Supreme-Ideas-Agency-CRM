<?php

namespace App\Support\Business;

use App\Models\Setting;

/**
 * The ONE description of every Business Suite setting an admin can change (MASTER-ONLY): label, plain-English help, type, default and limits.
 * The control panel renders itself from this, so adding a knob is one entry here plus an accessor in BusinessSettings. Every value shown has a
 * visible default and a reset button.
 *
 * A field with `available: false` is a module that has not shipped yet. Its switch is shown (so the owner can see what is coming and the decided
 * default) but cannot be turned on, and BusinessSettings::module() answers false for it whatever is stored.
 *
 * Types: bool | int | float | select | text
 */
class BusinessSettingsSchema
{
    public const GROUP = 'business';

    /** @return array<string, array{title: string, blurb: string, fields: list<array<string, mixed>>}> */
    public static function groups(): array
    {
        return [
            'general' => [
                'title' => 'Basics',
                'blurb' => 'The master switch and who may open a workspace. Switching the Suite off hides it everywhere and deletes nothing.',
                'fields' => [
                    self::f(BusinessSuiteSettings::FLAG, 'Business Suite on', 'Master switch. Off: every Business page shows the friendly "Coming soon" sheet, call flows stop answering and workspace numbers fall back to plain forwarding.', 'bool', false, danger: true),
                    self::f(BusinessSettings::WORKSPACE_CREATION, 'Members can open new workspaces', 'Turn off to freeze sign-ups (for example while you review a wave of new workspaces). Existing workspaces keep working.', 'bool', true),
                ],
            ],
            'access' => [
                'title' => 'Plans and access',
                'blurb' => 'Who can use the Suite, and what Naara Pro plans grant. Assign features to each plan under Admin > Brand directory (only modules that are switched on below are offered there). Nothing here deletes anything: a workspace whose plan ends is paused, and everything comes back when the plan returns.',
                'fields' => [
                    self::f(BusinessSettings::ACCESS.'model', 'Who can use the Suite', '"Naara Pro plans" (recommended for launch): only owners whose plan includes a feature can use it. "Anyone": no plan is needed, which is handy while you are testing. Switching this changes access immediately.', 'select', 'pro', options: ['pro' => 'Naara Pro plans only', 'open' => 'Anyone with an account'], danger: true),
                    self::f(BusinessSettings::ACCESS.'premium_skins', 'Reserve premium skins for Naara Pro', 'On: dashboard skins marked "pro" in Admin > Appearance can only be chosen by members on a plan that includes premium skins. Off: every enabled skin stays open to everyone (today\'s behaviour).', 'bool', false, danger: true),
                    self::f(BusinessSettings::ACCESS.'credit_refill', 'Pay the monthly NaaraCredit refill', 'Plans can include a NaaraCredit refill each billing month. Off: no refill is paid on any plan (nothing is lost; it simply is not issued while this is off).', 'bool', true),
                ],
            ],
            'modules' => [
                'title' => 'Modules',
                'blurb' => 'Each part of the Suite has its own switch. Modules that have not shipped yet stay off and cannot be turned on until they are released. Recording and intelligence are separate on purpose: recording a call and analysing it are different consents.',
                'fields' => [
                    self::f(BusinessSettings::MODULE.'call_flows', 'Call flows', 'Menus, ring groups, business hours and voicemail on a workspace number. Off: the builder shows "Coming soon" and live flows stop answering (callers hear the number\'s normal forwarding).', 'bool', true, danger: true),
                    self::f(BusinessSettings::MODULE.'recording', 'Call recording', 'Lets workspace owners and admins switch recording on for their numbers. Off by default for every workspace, every recorded call is announced, the strictest rule across both ends wins. Counsel sign-off is needed before this goes live in production.', 'bool', false, danger: true),
                    self::f(BusinessSettings::MODULE.'intelligence', 'Call intelligence', 'Transcripts, summaries, sentiment and coaching notes. A separate switch from recording, and each workspace opts in on its own. Needs the recording module.', 'bool', false, danger: true),
                    self::f(BusinessSettings::MODULE.'inbox', 'Shared inbox', 'Lets a workspace connect its own WhatsApp number, Telegram bot and Instagram account, and see those conversations next to SMS in Messages. Off by default. Off: the connection page shows "Coming soon" and incoming channel messages are ignored.', 'bool', false, danger: true),
                    self::f(BusinessSettings::MODULE.'omni', 'Naara Respond inbox', 'The omnichannel inbox built on the Respond core: Telegram, web chat, email, WhatsApp, Instagram and Messenger (WhatsApp can be connected with one click through Meta once the Embedded Signup Config ID is saved). Off by default. Off: the Respond page shows "Coming soon" and nothing is filed from the omni webhooks.', 'bool', false, danger: true),
                    self::f(BusinessSettings::MODULE.'softphone', 'Native softphone', 'Lets workspace members pair the Naara mobile app so calls to the workspace ring the phone itself, even when it is locked or the app is closed (Apple VoIP push and Firebase). Needs the native app, Twilio push credentials and a test on real phones. Off by default. Off: the pairing and token endpoints answer 404 and calls ring the browser dialer as before.', 'bool', false, danger: true),
                    self::f(BusinessSettings::MODULE.'ai_voice', 'AI voice agent', 'An AI receptionist that answers a workspace number, takes callback requests and hands over to people. Needs the voice agent server running (a VPS; a shared cPanel host cannot run it) and its address below. Callers are told they are speaking to an automated assistant.', 'bool', false, danger: true),
                    self::f(BusinessSettings::MODULE.'brain_assist', 'NaaraBrain assistant', 'Adds Draft reply, Summarise and Suggest follow-ups to the shared inbox, written by NaaraBrain and measured per workspace against the plan allowance. It reads the open conversation (recent messages) and sends it to the AI provider, so it is off by default; people always review and send. Needs the Shared inbox.', 'bool', false),
                    self::f(BusinessSettings::MODULE.'integrations', 'Integrations and CRM', 'Lets a workspace owner or admin issue a workspace-bound API key (read-only contacts, calls and messages) and register signed webhooks, which is how Zapier and other tools connect. Off: those keys stop working (the API answers 404) and no webhooks are sent.', 'bool', false, danger: true),
                ],
            ],
            'inbox_sending' => [
                'title' => 'Shared inbox sending',
                'blurb' => 'What a workspace can send from the shared inbox composer: files, GIFs, voice notes, locations, contacts, polls and scheduled messages. Each channel still enforces its own provider limits (WhatsApp 5 MB photos / 16 MB video, Telegram 20 MB, Instagram no documents); these are the platform-wide ceilings on top. The Sending readiness tab checks the server can actually deliver them.',
                'fields' => [
                    self::f(BusinessSettings::INBOX.'attachments', 'Files, voice and GIFs in the inbox', 'Off: the composer only sends text. Existing conversations are not affected.', 'bool', true),
                    self::f(BusinessSettings::INBOX.'scheduling', 'Scheduled messages', 'Lets people queue a reply for later. Needs the scheduler (cron) running: see Sending readiness.', 'bool', true),
                    self::f(BusinessSettings::INBOX.'max_file_mb', 'Largest file', 'A ceiling across every channel; a channel with a smaller limit keeps its own.', 'int', 25, min: 1, max: 100, unit: 'MB'),
                    self::f(BusinessSettings::INBOX.'max_files', 'Files per message', 'Attachments in one send. Each goes out as its own message on the channel.', 'int', 5, min: 1, max: 10),
                    self::f(BusinessSettings::INBOX.'max_schedule_days', 'Furthest a message can be scheduled', 'Messages past this many days ahead are refused. Channels only allow replies within 24 hours of the person writing, so long delays often fail on WhatsApp and Instagram.', 'int', 14, min: 1, max: 90, unit: 'days'),
                    self::f(BusinessSettings::INBOX.'link_minutes', 'How long a file link works', 'Providers fetch each file from a short-lived link. Long enough for a slow provider, short enough that a leaked link expires.', 'int', 30, min: 5, max: 240, unit: 'minutes'),
                    self::f(BusinessSettings::INBOX.'sends_per_minute', 'Sends per person per minute', 'A brake against a stuck script or a runaway browser tab.', 'int', 30, min: 1, max: 200),
                ],
            ],
            'brain_assist' => [
                'title' => 'NaaraBrain assistant (inbox)',
                'blurb' => 'The allowance and depth for the inbox assistant. Every run is measured in the AI cost log against the workspace owner, so Admin > Business Suite > Brain economics shows what each plan earns and what the assistant costs. A plan can override the monthly allowance in its capabilities.',
                'fields' => [
                    self::f(BusinessSettings::BRAIN.'monthly_actions', 'Assistant actions per workspace per month', 'The default allowance for a workspace on a plan that does not set its own. One draft, summary or follow-up list is one action. Counted from the 1st of each month.', 'int', 200, min: 0, max: 100000),
                    self::f(BusinessSettings::BRAIN.'depth', 'Depth', 'Quick is the cheapest and fastest and is plenty for replies; Balanced reads more carefully and costs more per action.', 'select', 'quick', options: ['quick' => 'Quick', 'balanced' => 'Balanced']),
                    self::f(BusinessSettings::BRAIN.'context_messages', 'Messages the assistant reads', 'How many of the latest messages in the open conversation are sent to the AI provider.', 'int', 12, min: 4, max: 40),
                    self::f(BusinessSettings::BRAIN.'per_minute', 'Actions per person per minute', 'A brake against a stuck button or a script.', 'int', 6, min: 1, max: 60),
                ],
            ],
            'limits' => [
                'title' => 'Workspace limits',
                'blurb' => 'Ceilings per workspace. Lowering a limit never removes anything that already exists; it only stops new additions past the limit.',
                'fields' => [
                    self::f(BusinessSettings::LIMIT.'seats', 'Seats per workspace', 'People (including pending invites) one workspace can hold.', 'int', 25, min: 1, max: 1000),
                    self::f(BusinessSettings::LIMIT.'numbers', 'Numbers per workspace', 'Phone lines one workspace can bring in.', 'int', 10, min: 1, max: 500),
                    self::f(BusinessSettings::LIMIT.'ring_groups', 'Ring groups per workspace', 'Teams that can be rung together or in turn.', 'int', 10, min: 1, max: 200),
                    self::f(BusinessSettings::LIMIT.'ring_group_size', 'People per ring group', 'Seats in one ring group.', 'int', 10, min: 1, max: 100),
                    self::f(BusinessSettings::LIMIT.'channels', 'Connected channels per workspace', 'WhatsApp numbers, Telegram bots and Instagram accounts a workspace can connect.', 'int', 5, min: 1, max: 50),
                    self::f(BusinessSettings::LIMIT.'flow_nodes', 'Steps per call flow', 'Menus, queues, forwards and voicemails in one flow.', 'int', 40, min: 5, max: 200),
                ],
            ],
            'recording' => [
                'title' => 'Recording and intelligence',
                'blurb' => 'Platform rules for call recording. Workspaces choose their own settings inside these bounds. Defaults follow the consent steer in docs/legal/CALL-RECORDING-CONSENT-STEER.md.',
                'fields' => [
                    self::f(BusinessSettings::RECORDING.'consent_mode', 'Consent in strict markets', 'Strict markets are the countries marked "consent" in config/call_recording.php, plus any country not listed. "Keypad" asks the caller to press 1 to be recorded and otherwise carries on unrecorded. "Announce only" plays the notice and continues; use it only if counsel agrees.', 'select', 'keypad', options: ['keypad' => 'Caller presses 1 (recommended)', 'announce_only' => 'Announce and continue'], danger: true),
                    self::f(BusinessSettings::RECORDING.'default_retention_days', 'Default retention', 'How long a workspace keeps recordings unless it chooses otherwise.', 'int', 30, min: 1, max: 730, unit: 'days'),
                    self::f(BusinessSettings::RECORDING.'retention_ceiling_days', 'Longest retention a workspace may choose', 'The hard maximum. Recordings are deleted automatically when they expire, unless the workspace has a legal hold.', 'int', 365, min: 1, max: 730, unit: 'days'),
                    self::f(BusinessSettings::RECORDING.'min_balance_usd', 'Wallet balance needed to record', 'A call is only recorded while the workspace owner\'s wallet holds at least this much, so a recording is never made that cannot be paid for.', 'float', 0.25, min: 0, max: 100, unit: 'USD'),
                    self::f(BusinessSettings::RECORDING.'max_minutes', 'Longest call that is recorded', 'Recording stops at this length so a stuck call cannot run up a bill.', 'int', 120, min: 5, max: 480, unit: 'minutes'),
                ],
            ],
            'ai_voice' => [
                'title' => 'AI voice agent',
                'blurb' => 'Platform settings for the voice agent. It runs as its own long-lived process (php artisan business:ai-voice-server) behind a TLS proxy, which Twilio ConversationRelay connects to.',
                'fields' => [
                    self::f(BusinessSettings::AI.'server_url', 'Voice agent server address', 'The public wss:// address Twilio connects to, for example wss://agent.example.com/relay. Leave empty to keep the agent switched off.', 'text', ''),
                    self::f(BusinessSettings::AI.'tts_provider', 'Voice provider', 'The text-to-speech engine ConversationRelay uses.', 'select', 'Google', options: ['Google' => 'Google', 'Amazon' => 'Amazon', 'ElevenLabs' => 'ElevenLabs']),
                    self::f(BusinessSettings::AI.'voice', 'Voice name', 'Optional voice id for the provider. Empty uses the provider default.', 'text', ''),
                    self::f(BusinessSettings::AI.'max_minutes', 'Longest agent call', 'The funded block for one call never exceeds this.', 'int', 20, min: 1, max: 120, unit: 'minutes'),
                    self::f(BusinessSettings::AI.'max_concurrent', 'Calls at once', 'Most simultaneous agent calls the server will take; more are turned away politely.', 'int', 20, min: 1, max: 500),
                ],
            ],
            'costs' => [
                'title' => 'Recording costs (what the suppliers charge us)',
                'blurb' => 'The supplier cost of each step, in USD, from your supplier invoices. Workspaces are charged the price PricingEngine works out from these plus your margin rules, and never see these numbers. Check them against your latest bills.',
                'fields' => [
                    self::f(BusinessSettings::COST.'record_per_min', 'Recording, per recorded minute', 'Telephony recording and storage.', 'float', 0.0025, min: 0, max: 10, unit: 'USD'),
                    self::f(BusinessSettings::COST.'transcribe_per_min', 'Transcription, per audio minute', 'Speech to text.', 'float', 0.01, min: 0, max: 10, unit: 'USD'),
                    self::f(BusinessSettings::COST.'agent_per_min', 'AI voice agent, per call minute', 'Telephony minute plus the conversation service, per minute of an agent call.', 'float', 0.09, min: 0, max: 10, unit: 'USD'),
                    self::f(BusinessSettings::COST.'intelligence_per_run', 'Intelligence, per call', 'One summary run on a transcript.', 'float', 0.02, min: 0, max: 10, unit: 'USD'),
                ],
            ],
            'calls' => [
                'title' => 'Call behaviour',
                'blurb' => 'How the call flow engine behaves. These apply to every workspace and take effect on the next call.',
                'fields' => [
                    self::f(BusinessSettings::CALL.'max_hops', 'Longest path through a flow', 'Steps a single call may pass through before it is politely ended. Protects against loops.', 'int', 12, min: 4, max: 25, unit: 'steps'),
                    self::f(BusinessSettings::CALL.'menu_attempts', 'Menu retries', 'How many times a caller may mistype or stay silent on a menu before the call ends.', 'int', 3, min: 1, max: 5),
                    self::f(BusinessSettings::CALL.'leg_seconds', 'Ring time per person (in turn)', 'How long each person rings in a sequential group before moving to the next.', 'int', 20, min: 10, max: 60, unit: 'seconds'),
                ],
            ],
        ];
    }

    /** @return array<string, array<string, mixed>> key => field */
    public static function flat(): array
    {
        $out = [];
        foreach (self::groups() as $g) {
            foreach ($g['fields'] as $f) {
                $out[$f['key']] = $f;
            }
        }

        return $out;
    }

    public static function field(string $key): ?array
    {
        return self::flat()[$key] ?? null;
    }

    /** The value in effect (stored, else the default). */
    public static function current(array $field): mixed
    {
        return self::cast($field, Setting::getValue($field['key'], $field['default']));
    }

    public static function isCustom(array $field): bool
    {
        return self::current($field) !== self::cast($field, $field['default']);
    }

    public static function cast(array $field, mixed $v): mixed
    {
        return match ($field['type']) {
            'bool' => filter_var($v, FILTER_VALIDATE_BOOLEAN),
            'int' => (int) $v,
            'float' => round((float) $v, 4),
            default => (string) $v,
        };
    }

    public static function rules(array $field): array
    {
        return match ($field['type']) {
            'bool' => ['boolean'],
            'int' => ['required', 'integer', 'min:'.$field['min'], 'max:'.$field['max']],
            'float' => ['required', 'numeric', 'min:'.$field['min'], 'max:'.$field['max']],
            'select' => ['required', 'in:'.implode(',', array_keys($field['options']))],
            default => ['nullable', 'string', 'max:200'],
        };
    }

    private static function f(string $key, string $label, string $help, string $type, mixed $default, int|float|null $min = null, int|float|null $max = null, ?string $unit = null, bool $danger = false, bool $available = true, array $options = []): array
    {
        return compact('key', 'label', 'help', 'type', 'default', 'min', 'max', 'unit', 'danger', 'available', 'options');
    }
}
