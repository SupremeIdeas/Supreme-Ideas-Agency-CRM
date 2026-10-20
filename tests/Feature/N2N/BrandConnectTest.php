<?php

namespace Tests\Feature\N2N;

use App\Livewire\BrandConnections;
use App\Models\BrandConnectRequest;
use App\Models\BrandPartner;
use App\Models\BrandPartnerFollower;
use App\Models\N2N\Conversation;
use App\Models\N2N\Message;
use App\Models\Setting;
use App\Models\User;
use App\Services\N2N\Credit\UsageCredit;
use App\Services\N2N\Identity\NaaraId;
use App\Services\Social\BrandFollowService;
use App\Services\Wallet\WalletService;
use Livewire\Livewire;

/** Brand-Growth Phase 6 (MASTER-ONLY): Connect, the owner's accept or decline, and the welcome message paid from the owner's NaaraCredit. Off unless the admin turns it on. */
class BrandConnectTest extends N2NTestCase
{
    private function member(string $name, float $credit = 0): User
    {
        $u = $this->user(['name' => $name, 'email_verified_at' => now()]);
        $u->forceFill(['naara_id' => NaaraId::generate(), 'created_at' => now()->subDays(30)])->save();
        if ($credit > 0) {
            app(WalletService::class)->credit($u, $credit, 'USD', ['reference' => 'f'.$u->id.uniqid()]);
            app(UsageCredit::class)->purchase($u, $credit, 'b'.uniqid());
        }

        return $u->fresh();
    }

    private function brand(User $owner, array $over = []): BrandPartner
    {
        $b = BrandPartner::create(['owner_user_id' => $owner->id, 'brand_name' => $over['brand_name'] ?? 'Ada Bakes', 'category' => 'Food', 'listing_status' => BrandPartner::STATUS_ACTIVE,
            'is_featured' => false, 'background_color' => '#0A6E6E', 'is_active' => true]);
        $b->forceFill(['intro_enabled' => true, 'intro_text' => 'Welcome to Ada Bakes!', 'connect_mode' => $over['connect_mode'] ?? 'open'])->save();

        return $b->fresh();
    }

    private function connect(User $person, BrandPartner $b): void
    {
        app(BrandFollowService::class)->toggle($person, $b);
    }

    public function test_nothing_happens_while_the_switch_is_off(): void
    {
        $owner = $this->member('Ada', 5);
        $b = $this->brand($owner);
        $this->connect($this->member('Bola'), $b);

        $this->assertSame(0, BrandConnectRequest::count());
        $this->assertSame(0, Message::count());
    }

    public function test_open_mode_sends_one_welcome_message_from_the_owner_paid_in_credit(): void
    {
        Setting::setValue('brand.connect.enabled', true);
        $owner = $this->member('Ada', 5);
        $b = $this->brand($owner);
        $bola = $this->member('Bola');
        $before = app(\App\Services\N2N\Credit\UsageMeter::class)->available($owner);

        $this->connect($bola, $b);
        $req = BrandConnectRequest::first();

        $this->assertSame('accepted', $req->status);
        $this->assertSame('sent', $req->fresh()->intro_status);
        $m = Message::first();
        $this->assertSame($owner->id, (int) $m->sender_id);
        $this->assertSame('Welcome to Ada Bakes!', $m->body);
        $this->assertSame(Conversation::REQUEST, Conversation::first()->status, 'it arrives as a message request they can accept or decline');
        $this->assertLessThan($before, app(\App\Services\N2N\Credit\UsageMeter::class)->available($owner->fresh()), 'the owner paid');

        $this->connect($bola, $b);   // disconnect
        $this->connect($bola, $b);   // reconnect
        $this->assertSame(1, Message::count(), 'once per person per brand');
    }

    public function test_no_credit_means_no_message_and_the_owner_is_told_once_without_touching_the_wallet(): void
    {
        Setting::setValue('brand.connect.enabled', true);
        $owner = $this->member('Ada');
        app(WalletService::class)->credit($owner, 20, 'USD', ['reference' => 'w1']);
        $b = $this->brand($owner);

        $this->connect($this->member('Bola'), $b);

        $this->assertSame('no_credit', BrandConnectRequest::first()->intro_status);
        $this->assertSame(0, Message::count());
        $this->assertEqualsWithDelta(20.0, (float) $owner->fresh()->wallet->usd_balance, 0.0001, 'the wallet is never used for chat');
    }

    public function test_approval_mode_waits_then_accept_sends_the_welcome_and_decline_removes_the_connection_quietly(): void
    {
        Setting::setValue('brand.connect.enabled', true);
        $owner = $this->member('Ada', 5);
        $b = $this->brand($owner, ['connect_mode' => 'approve']);
        $bola = $this->member('Bola');
        $chidi = $this->member('Chidi');
        $this->connect($bola, $b);
        $this->connect($chidi, $b);

        $this->assertSame(['pending', 'pending'], BrandConnectRequest::orderBy('id')->pluck('status')->all());
        $this->assertSame(0, Message::count(), 'nothing is sent before the owner decides');
        $this->assertSame(2, (int) $b->fresh()->naara_followers_count);

        $lw = Livewire::actingAs($owner)->test(BrandConnections::class);
        $lw->call('accept', BrandConnectRequest::where('user_id', $bola->id)->value('id'))->assertSee('Accepted.');
        $this->assertSame(1, Message::count());
        $lw->call('decline', BrandConnectRequest::where('user_id', $chidi->id)->value('id'))->assertSee('Declined.');

        $this->assertSame('declined', BrandConnectRequest::where('user_id', $chidi->id)->value('status'));
        $this->assertFalse(BrandPartnerFollower::where('user_id', $chidi->id)->exists());
        $this->assertSame(1, (int) $b->fresh()->naara_followers_count);
        $this->assertSame(1, Message::count(), 'a declined person is never messaged');
    }

    public function test_only_the_owner_can_decide_and_the_page_is_hidden_while_off(): void
    {
        $owner = $this->member('Ada', 5);
        $b = $this->brand($owner, ['connect_mode' => 'approve']);
        $this->actingAs($owner)->get(route('brand.connections'))->assertNotFound();

        Setting::setValue('brand.connect.enabled', true);
        $this->connect($this->member('Bola'), $b);
        $other = $this->member('Eve', 5);
        $this->brand($other, ['brand_name' => 'Eve Co']);
        $id = BrandConnectRequest::first()->id;

        try {
            Livewire::actingAs($other)->test(BrandConnections::class)->call('accept', $id);   // someone else's request
            $this->fail('another brand owner must not be able to decide it');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            $this->assertSame('pending', BrandConnectRequest::find($id)->status);
        }
    }

    public function test_settings_are_validated_including_no_links(): void
    {
        Setting::setValue('brand.connect.enabled', true);
        $owner = $this->member('Ada', 5);
        $b = $this->brand($owner);

        Livewire::actingAs($owner)->test(BrandConnections::class)->set('introText', 'Visit https://spam.example now')->call('save')->assertSet('flash', null)->assertNotSet('error', null)
            ->set('introEnabled', true)->set('introText', '')->call('save')->assertSet('error', 'Write the welcome message first, or turn it off.')
            ->set('introText', 'Thanks for connecting!')->set('mode', 'approve')->call('save')->assertSet('flash', 'Saved.');
        $this->assertSame('approve', $b->fresh()->connect_mode);
        $this->assertSame('Thanks for connecting!', $b->fresh()->intro_text);
    }
}
