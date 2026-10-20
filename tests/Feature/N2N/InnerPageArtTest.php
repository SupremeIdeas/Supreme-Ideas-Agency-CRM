<?php

namespace Tests\Feature\N2N;

use App\Models\User;
use App\Services\N2N\Credit\UsageCredit;
use App\Services\N2N\Identity\NaaraId;
use App\Services\Wallet\WalletService;

/** Naara chat inner pages: a drawn illustration on every empty state and header, and one chip row that reaches the sibling pages (MASTER-ONLY). */
class InnerPageArtTest extends N2NTestCase
{
    public function test_every_chat_illustration_file_exists(): void
    {
        foreach (['chats', 'people', 'updates', 'calls', 'planner', 'followups', 'credit', 'identity', 'profile', 'gestures'] as $k) {
            $this->assertFileExists(public_path("images/illustrations/nc-$k.svg"));
        }
    }

    public function test_settings_pages_carry_the_section_tabs_and_header_art(): void
    {
        $u = $this->member();
        foreach (['n2n.identity' => 'nc-identity', 'n2n.profile.settings' => 'nc-profile', 'n2n.gestures' => 'nc-gestures', 'n2n.credits' => 'nc-credit'] as $route => $art) {
            $this->actingAs($u)->get(route($route))->assertOk()->assertSee('data-n2n-section-tabs', false)->assertSee($art.'.svg', false);
        }
        $this->actingAs($u)->get(route('n2n.planner'))->assertOk()->assertSee('data-n2n-section-tabs', false)->assertSee('nc-planner.svg', false);
    }

    public function test_empty_states_show_their_illustration(): void
    {
        $u = $this->member();
        $this->actingAs($u)->get(route('n2n.people'))->assertOk()->assertSee('nc-people.svg', false);
        $this->actingAs($u)->get(route('n2n.updates'))->assertOk()->assertSee('nc-updates.svg', false);
        $this->actingAs($u)->get(route('n2n.chat'))->assertOk()->assertSee('nc-chats.svg', false);
    }

    private function member(): User
    {
        $u = $this->user(['email_verified_at' => now()]);
        $u->forceFill(['naara_id' => NaaraId::generate(), 'created_at' => now()->subDays(30)])->save();
        app(WalletService::class)->credit($u, 20, 'USD', ['reference' => 'f'.$u->id]);
        app(UsageCredit::class)->purchase($u, 20, 'b'.$u->id);

        return $u->fresh();
    }
}
