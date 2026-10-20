<?php

namespace Tests\Feature\N2N;

use App\Models\Contact;
use App\Models\N2N\Message;
use App\Models\N2N\Report;
use App\Models\User;
use App\Services\N2N\Credit\UsageCredit;
use App\Services\N2N\Identity\NaaraId;
use App\Services\N2N\Messaging\MessageService;
use App\Services\N2N\Messaging\MessagingException;
use App\Services\N2N\Messaging\Moderation;
use App\Services\Wallet\WalletService;
use Livewire\Livewire;

/** N2N moderation (MASTER-ONLY): the staff report queue, restrictions that stop sending and calling only, and the "needs attention" flag that never acts alone. */
class ModerationTest extends N2NTestCase
{
    private User $a;

    private User $b;

    private int $cid;

    protected function setUp(): void
    {
        parent::setUp();
        [$this->a, $this->b] = [$this->member(), $this->member()];
        Contact::create(['user_id' => $this->b->id, 'name' => 'A', 'phone_number' => '0803'.random_int(1000000, 9999999)])->forceFill(['linked_user_id' => $this->a->id])->saveQuietly();
        $this->cid = (int) $this->actingAs($this->a)->post('/n2n/api/messages?to='.$this->b->naara_id, ['meta' => json_encode(['text' => 'hello'])], ['Idempotency-Key' => 'mod-first-01', 'Accept' => 'application/json'])->assertCreated()->json('conversation_id');
    }

    private function member(): User
    {
        $u = $this->user(['email_verified_at' => now()]);
        $u->forceFill(['naara_id' => NaaraId::generate(), 'created_at' => now()->subDays(30)])->save();
        app(WalletService::class)->credit($u, 20, 'USD', ['reference' => 'f'.$u->id]);
        app(UsageCredit::class)->purchase($u, 20, 'b'.$u->id);

        return $u->fresh();
    }

    private function staff(): User
    {
        $s = $this->user(['email_verified_at' => now()]);
        $s->assignRole(\Spatie\Permission\Models\Role::findOrCreate('admin', 'web'));

        return $s;
    }

    private function report(): Report
    {
        return app(MessageService::class)->report($this->b, $this->cid, 'abuse', 'rude', false);
    }

    public function test_a_report_alone_restricts_nobody_and_staff_can_restrict_with_an_audit_trail(): void
    {
        $r = $this->report();
        $this->assertFalse(Moderation::restricted($this->a->fresh()));
        app(Moderation::class)->resolve($r, $staff = $this->staff(), 'restricted_24h', 'confirmed');
        $this->assertTrue(Moderation::restricted($this->a->fresh()));
        $this->assertSame('actioned', $r->fresh()->status);
        $this->assertSame($staff->id, $r->fresh()->reviewed_by);
        $this->assertSame('confirmed', $r->fresh()->staff_note);
    }

    public function test_a_restricted_member_cannot_send_or_call_but_can_still_read(): void
    {
        app(Moderation::class)->resolve($this->report(), $this->staff(), 'restricted_7d');
        $a = $this->a->fresh();
        try {
            app(MessageService::class)->send($a, ['conversation_id' => $this->cid], 'mod-after-0001', 'still here', null);
            $this->fail('a restricted member sent a message');
        } catch (MessagingException $e) {
            $this->assertSame('restricted', $e->reason);
        }
        $this->assertSame(0, Message::query()->where('client_id', 'mod-after-0001')->count());
        $this->actingAs($a)->post(route('n2n.api.send', ['conversationId' => $this->cid]), ['meta' => json_encode(['text' => 'x'])], ['Idempotency-Key' => 'mod-after-0002', 'Accept' => 'application/json'])->assertForbidden();
        $this->actingAs($a)->get('/n2n/chat?c='.$this->cid)->assertOk();
        $this->expectException(\App\Services\N2N\Calls\CallException::class);
        app(\App\Services\N2N\Calls\CallService::class)->start($a, (string) $this->b->naara_id);
    }

    public function test_dismiss_and_warn_do_not_restrict_and_lifting_restores_sending(): void
    {
        $mod = app(Moderation::class);
        $mod->resolve($this->report(), $staff = $this->staff(), 'dismissed');
        $mod->resolve($this->report(), $staff, 'warned');
        $this->assertFalse(Moderation::restricted($this->a->fresh()));
        $mod->resolve($this->report(), $staff, 'restricted_permanent');
        $this->assertTrue(Moderation::restricted($this->a->fresh()));
        $mod->lift($this->a->fresh(), $staff);
        $this->assertFalse(Moderation::restricted($this->a->fresh()));
        app(MessageService::class)->send($this->a->fresh(), ['conversation_id' => $this->cid], 'mod-after-0003', 'back', null);
        $this->assertSame(1, Message::query()->where('client_id', 'mod-after-0003')->count());
    }

    public function test_an_unknown_action_is_refused(): void
    {
        $this->expectException(MessagingException::class);
        app(Moderation::class)->resolve($this->report(), $this->staff(), 'ban_forever');
    }

    public function test_the_admin_page_is_staff_only_and_shows_the_excerpt_and_attention_flag(): void
    {
        $this->report();
        foreach ([$this->member(), $this->member()] as $o) {
            Report::query()->create(['reporter_id' => $o->id, 'reported_id' => $this->a->id, 'conversation_id' => $this->cid, 'reason' => 'spam', 'status' => 'open']);
        }
        $this->actingAs($this->b)->get(route('admin.n2n.reports'))->assertNotFound();
        $staff = $this->staff();
        $this->actingAs($staff)->get(route('admin.n2n.reports'))->assertOk()->assertSee('hello')->assertSee('Needs attention');
        Livewire::actingAs($staff)->test(\App\Livewire\Admin\N2N\Reports::class)->call('resolve', Report::query()->orderBy('id')->value('id'), 'restricted_24h')->assertSet('message', 'Report #'.Report::query()->orderBy('id')->value('id').' closed (restricted 24h).');
        $this->assertTrue(Moderation::restricted($this->a->fresh()));
        Livewire::actingAs($this->b)->test(\App\Livewire\Admin\N2N\Reports::class)->assertForbidden();
    }

    private function staffMember(bool $scope): User
    {
        $s = $this->user(['email_verified_at' => now()]);
        $s->assignRole(\Spatie\Permission\Models\Role::findOrCreate('staff', 'web'));
        $s->forceFill(['two_factor_secret' => encrypt('SECRETKEY'), 'two_factor_confirmed_at' => now()])->save();
        \Spatie\Permission\Models\Permission::findOrCreate('n2n.moderate', 'web');
        if ($scope) {
            $s->givePermissionTo('n2n.moderate');
        }

        return $s;
    }

    public function test_staff_without_the_scope_cannot_open_the_queue_and_with_it_they_can_pause_access_for_a_week(): void
    {
        $r = $this->report();
        $no = $this->staffMember(false);
        $this->actingAs($no)->get(route('admin.n2n.reports'))->assertForbidden();
        try {
            app(Moderation::class)->resolve($r, $no, 'restricted_24h');
            $this->fail('staff without the scope moderated');
        } catch (MessagingException) {
            $this->assertFalse(Moderation::restricted($this->a->fresh()));
        }
        $yes = $this->staffMember(true);
        $this->actingAs($yes)->get(route('admin.n2n.reports'))->assertOk();
        app(Moderation::class)->resolve($r, $yes, 'restricted_7d', 'repeat spam');
        $this->assertTrue(Moderation::restricted($this->a->fresh()));
        $this->assertSame($yes->id, $r->fresh()->reviewed_by);
    }

    public function test_staff_cannot_make_it_permanent_cannot_touch_other_staff_or_admins_and_cannot_lift_an_admins_restriction(): void
    {
        $mod = app(Moderation::class);
        $yes = $this->staffMember(true);
        try {
            $mod->resolve($this->report(), $yes, 'restricted_permanent');
            $this->fail('staff set a permanent restriction');
        } catch (MessagingException) {
            $this->assertFalse(Moderation::restricted($this->a->fresh()));
        }
        // another staff member as the target
        $this->a->assignRole(\Spatie\Permission\Models\Role::findOrCreate('staff', 'web'));
        try {
            $mod->resolve($this->report(), $yes, 'restricted_24h');
            $this->fail('staff restricted a staff member');
        } catch (MessagingException) {
            $this->assertFalse(Moderation::restricted($this->a->fresh()));
        }
        // admin permanent, staff cannot lift
        $admin = $this->staff();
        $mod->resolve($this->report(), $admin, 'restricted_permanent');
        try {
            $mod->lift($this->a->fresh(), $yes);
            $this->fail('staff lifted a permanent restriction');
        } catch (MessagingException) {
            $this->assertTrue(Moderation::restricted($this->a->fresh()));
        }
        $mod->lift($this->a->fresh(), $admin);
        $this->assertFalse(Moderation::restricted($this->a->fresh()));
        // nobody restricts an admin
        $target = $this->staff();
        $rep = Report::query()->create(['reporter_id' => $this->b->id, 'reported_id' => $target->id, 'reason' => 'abuse', 'status' => 'open']);
        $this->expectException(MessagingException::class);
        $mod->resolve($rep, $admin, 'restricted_24h');
    }

    public function test_an_admin_switches_a_staff_member_on_and_off_and_staff_cannot_change_who_moderates(): void
    {
        $mod = app(Moderation::class);
        $admin = $this->staff();
        $s = $this->staffMember(false);
        $mod->setModerator($admin, $s, true);
        $this->assertTrue(Moderation::canModerate($s->fresh()));
        $mod->setModerator($admin, $s, false);
        $this->assertFalse(Moderation::canModerate($s->fresh()));
        $other = $this->staffMember(true);
        try {
            $mod->setModerator($other, $s, true);
            $this->fail('staff granted moderation');
        } catch (MessagingException) {
            $this->assertFalse(Moderation::canModerate($s->fresh()));
        }
        try {
            $mod->setModerator($admin, $admin, true);   // not a staff member
            $this->fail('toggled an admin');
        } catch (MessagingException) {
            $this->assertTrue(true);
        }
        Livewire::actingAs($admin)->test(\App\Livewire\Admin\N2N\Reports::class)->set('tab', 'moderators')->call('setModerator', $s->id, true)->assertSet('message', 'They can now work chat reports.');
        $this->assertTrue(Moderation::canModerate($s->fresh()));
        $this->assertContains('n2n.moderate', \App\Support\StaffScopes::all());
    }
}
