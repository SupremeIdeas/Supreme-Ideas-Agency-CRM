<?php

namespace Tests\Feature\N2N;

use App\Support\SchedulerHealth;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Schema;


/**
 * N2N B14 fitness guards (MASTER-ONLY). They fail when someone adds a table or a scheduled job without deciding how it is erased, retained or monitored,
 * which is how personal data and silent failures slip in later.
 */
class HardeningTest extends N2NTestCase
{
    /** Every n2n_* table and what happens to it when a member is erased. erased = deleted/blanked by N2NErasure; retained = kept on purpose (named reason). */
    private const DATA_POLICY = [
        // deleted or blanked by N2NErasure::erase
        'n2n_user_phones' => 'erased', 'n2n_phone_verifications' => 'erased', 'n2n_profile_pictures' => 'erased', 'n2n_attachments' => 'erased (objects deleted from storage)',
        'n2n_message_reactions' => 'erased', 'n2n_message_hides' => 'erased', 'n2n_stars' => 'erased', 'n2n_scheduled_messages' => 'erased (cancelled and blanked)', 'n2n_events' => 'erased (events the member created are deleted; shared cards then read Event removed)', 'n2n_event_rsvps' => 'erased', 'n2n_followups' => 'erased (private to the member; notes about an erased person lose the link)', 'n2n_live_locations' => 'erased (position wiped on stop or expiry and on account erasure; no trail kept)',
        'n2n_chat_notice_seen' => 'erased', 'n2n_chat_notices' => 'admin-written text, not personal data', 'n2n_poll_votes' => 'erased', 'n2n_polls' => 'retained: part of the other person\'s chat history', 'n2n_poll_options' => 'retained: part of the other person\'s chat history', 'n2n_follows' => 'erased', 'n2n_brand_profile_visits' => 'erased (the member\'s visit rows; guest rows hold only a one-way hash and are pruned at 90 days)', 'n2n_update_views' => 'erased', 'n2n_update_reactions' => 'erased', 'n2n_invites' => 'erased', 'n2n_presence' => 'erased',
        // retained on purpose
        'n2n_usage_ledger' => 'retained: financial record', 'n2n_usage_wallets' => 'retained: financial record', 'n2n_usage_holds' => 'retained: financial record', 'n2n_credit_gifts' => 'retained: financial record',
        'n2n_messages' => 'retained: the other person\'s chat history (edit_log erased)', 'n2n_conversations' => 'retained: the other person\'s chat history', 'n2n_participants' => 'retained: membership (personal prefs reset)',
        'n2n_message_receipts' => 'retained: delivery state of the other person\'s chat', 'n2n_attachment_hides' => 'retained: a view filter only', 'n2n_blocks' => 'retained: protects the blocker',
        'n2n_reports' => 'retained: safety and legal record', 'n2n_calls' => 'retained: call and billing record', 'n2n_call_ratings' => 'erased (their after-call star answers go on account erasure)', 'n2n_call_participants' => 'retained: call and billing record',
        'n2n_call_participant_intervals' => 'retained: billing record', 'n2n_group_calls' => 'retained: call and billing record', 'n2n_call_links' => 'retained: expire on their own',
        // not personal data of a member
        'n2n_realtime_events' => 'system: short-lived queue, pruned hourly', 'n2n_status_posts' => 'erased (stored object deleted at 24 hours, on owner delete and on account erasure)', 'n2n_status_views' => 'erased', 'n2n_status_reactions' => 'erased (the viewer\'s reactions; the owner\'s posts take theirs with them)', 'n2n_status_hidden' => 'erased (both as owner and as hidden member)', 'n2n_status_mentions' => 'erased (the tagged member\'s rows; the owner\'s posts take theirs with them)', 'n2n_gesture_stats' => 'system: anonymous daily totals, no user id', 'n2n_brand_album_links' => 'brand-owned', 'n2n_brand_albums' => 'brand-owned', 'n2n_brand_embeds' => 'brand-owned',
        'n2n_brand_music_links' => 'brand-owned', 'n2n_brand_social_links' => 'brand-owned', 'n2n_thumbnails' => 'brand-owned',
    ];

    public function test_every_n2n_table_has_a_declared_erasure_policy(): void
    {
        $tables = collect(Schema::getTables())->pluck('name')->filter(fn ($n) => str_starts_with($n, 'n2n_'))->values()->all();
        $this->assertSame([], array_values(array_diff($tables, array_keys(self::DATA_POLICY))), 'A new n2n_ table needs an entry in HardeningTest::DATA_POLICY (and the erasure/export code it names).');
        $this->assertSame([], array_values(array_diff(array_keys(self::DATA_POLICY), $tables)), 'DATA_POLICY names a table that no longer exists.');
    }

    public function test_every_scheduled_n2n_command_is_watched_by_the_scheduler_health_monitor(): void
    {
        $scheduled = collect(app(Schedule::class)->events())->map(fn ($e) => SchedulerHealth::commandName((string) $e->command))->filter(fn ($c) => $c && str_starts_with($c, 'n2n:'))->unique()->values()->all();
        $this->assertNotEmpty($scheduled);
        $this->assertSame([], array_values(array_diff($scheduled, array_keys(SchedulerHealth::tasks()))), 'A scheduled n2n command is not registered with SchedulerHealth, so a silent stop would go unnoticed.');
    }

    public function test_the_chat_routes_sit_behind_auth_and_the_write_routes_are_throttled(): void
    {
        foreach (app('router')->getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'n2n') || ! array_intersect(['POST', 'PUT', 'PATCH', 'DELETE'], $route->methods())) {
                continue;
            }
            $mw = implode(',', $route->gatherMiddleware());
            $this->assertStringContainsString('auth', $mw, $route->uri().' accepts writes without auth');
            $this->assertMatchesRegularExpression('/throttle|livewire/i', $mw.$route->uri(), $route->uri().' accepts writes without a rate limit');
        }
    }
}
