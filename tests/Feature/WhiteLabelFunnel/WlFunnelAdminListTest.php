<?php

namespace Tests\Feature\WhiteLabelFunnel;

use App\Livewire\Admin\WlFunnelOverview;
use App\Models\User;
use App\Models\WlFunnelInstance;
use App\Models\WlInvoice;
use App\Services\WhiteLabel\Funnel\WlFunnel;
use App\Services\WhiteLabel\Funnel\WlFunnelStates;
use Database\Seeders\RoleSeeder;
use Database\Seeders\WhiteLabelLicensePlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** The admin Purchases list at scale: search, sort, segments with counts, owing and overdue, notes, resend, pagination. */
class WlFunnelAdminListTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(WhiteLabelLicensePlanSeeder::class);
    }

    private function admin(): User
    {
        return User::factory()->create()->assignRole('admin');
    }

    private function buyer(string $brand, string $status, string $plan = 'basic', ?string $email = null): WlFunnelInstance
    {
        $u = User::factory()->create(['email' => $email ?? strtolower(str_replace(' ', '', $brand)).'@example.com', 'name' => $brand.' Owner']);
        $i = app(WlFunnel::class)->start($plan, bin2hex(random_bytes(8)), $u);
        $i->forceFill(['user_id' => $u->id, 'status' => $status, 'selected_plan_key' => $plan, 'draft' => ['brand_name' => $brand, 'plan_key' => $plan]])->save();

        return $i->fresh();
    }

    private function invoice(WlFunnelInstance $i, string $status, float $total, float $balance, ?\DateTimeInterface $due = null): WlInvoice
    {
        $n = WlInvoice::count() + 1;

        return WlInvoice::create(['doc_type' => 'invoice', 'series' => 'INV', 'number' => 'INV-T-'.$n, 'source_key' => 'adm:'.$n, 'funnel_instance_id' => $i->id, 'user_id' => $i->user_id, 'status' => $status, 'currency' => 'USD', 'subtotal' => $total, 'tax_total' => 0,
            'total' => $total, 'amount_paid' => $total - $balance, 'balance_due' => $balance, 'issued_at' => now(), 'due_at' => $due, 'public_token_hash' => WlInvoice::hashToken('t'.$n), 'seller_snapshot' => [], 'buyer_snapshot' => [],
            'lines' => [['kind' => 'x', 'description' => 'x', 'qty' => 1, 'unit_price' => $total, 'amount' => $total]], 'payments' => []]);
    }

    public function test_search_finds_by_brand_email_and_code_and_segments_count_correctly(): void
    {
        $a = $this->buyer('Zenith Cloud', WlFunnelStates::LICENSE_ISSUED);
        $this->invoice($a, 'paid', 1500, 0);
        $this->invoice($a, 'issued', 3500, 3500, now()->subDays(3));
        $b = $this->buyer('Kora Mobile', WlFunnelStates::PAYMENT_REVIEW, 'extended');
        $c = $this->buyer('Dune Telecom', WlFunnelStates::DRAFT_SAVED);
        $d = $this->buyer('Lost Co', WlFunnelStates::ABANDONED);

        $t = Livewire::actingAs($this->admin())->test(WlFunnelOverview::class);
        $t->assertSee('Zenith Cloud')->assertSee('Kora Mobile')->assertSee('Owes $3,500.00');
        $t->set('search', 'kora')->assertSee('Kora Mobile')->assertDontSee('Zenith Cloud');
        $t->set('search', $c->public_code)->assertSee('Dune Telecom')->assertDontSee('Kora Mobile');
        $t->set('search', 'zenithcloud@example.com')->assertSee('Zenith Cloud')->assertDontSee('Dune Telecom');
        $t->set('search', '');
        $t->call('setFilter', 'overdue')->assertSee('Zenith Cloud')->assertDontSee('Kora Mobile');
        $t->call('setFilter', 'review')->assertSee('Kora Mobile')->assertDontSee('Zenith Cloud');
        $t->call('setFilter', 'drafts')->assertSee('Dune Telecom')->assertDontSee('Lost Co')->assertDontSee('Zenith Cloud');
        $t->call('setFilter', 'abandoned')->assertSee('Lost Co')->assertDontSee('Dune Telecom');
        $t->call('setFilter', 'needs_details')->assertSee('Zenith Cloud');
        $t->set('planFilter', 'extended')->call('setFilter', 'all')->assertSee('Kora Mobile')->assertDontSee('Zenith Cloud');
    }

    public function test_sort_by_most_owed_and_pagination_at_scale(): void
    {
        $big = $this->buyer('Big Debtor', WlFunnelStates::LICENSE_ISSUED);
        $this->invoice($big, 'issued', 3500, 3500, now()->addDays(5));
        $small = $this->buyer('Small Debtor', WlFunnelStates::LICENSE_ISSUED);
        $this->invoice($small, 'issued', 200, 200, now()->addDays(9));
        $t = Livewire::actingAs($this->admin())->test(WlFunnelOverview::class)->set('sort', 'owed');
        $t->assertSeeInOrder(['Big Debtor', 'Small Debtor']);
        $t->set('sort', 'due')->assertSeeInOrder(['Big Debtor', 'Small Debtor']);
        for ($k = 0; $k < 25; $k++) {
            $this->buyer('Filler '.$k, WlFunnelStates::DRAFT_SAVED);
        }
        $rows = Livewire::actingAs($this->admin())->test(WlFunnelOverview::class)->viewData('rows');
        $this->assertSame(20, $rows->count());
        $this->assertSame(27, $rows->total());
    }

    public function test_notes_are_private_to_the_team_and_resend_issues_a_fresh_link(): void
    {
        $a = $this->buyer('Zenith Cloud', WlFunnelStates::LICENSE_ISSUED);
        $doc = $this->invoice($a, 'issued', 3500, 3500, now()->addDays(5));
        $old = $doc->public_token_hash;
        $admin = $this->admin();
        $t = Livewire::actingAs($admin)->test(WlFunnelOverview::class)->call('view', $a->id)->set('noteBody', 'Called, will pay Friday')->call('addNote')->assertSee('Called, will pay Friday');
        $this->assertSame(1, $a->notes()->count());
        $t->call('resendDocument', $doc->id);
        $this->assertNotSame($old, $doc->fresh()->public_token_hash, 'the old public link stops working');
        // a buyer-facing page never carries the note
        $this->actingAs($a->user)->get(route('wl-funnel.pay'))->assertDontSee('Called, will pay Friday');
    }

    public function test_only_admins_reach_the_page(): void
    {
        $this->actingAs(User::factory()->create())->get('/'.config('app.admin_path', env('ADMIN_PATH', 'adminmaster')).'/white-label/funnel')->assertNotFound();
    }
}
