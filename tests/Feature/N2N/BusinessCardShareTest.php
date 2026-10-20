<?php

namespace Tests\Feature\N2N;

use App\Livewire\BusinessCardShare;
use App\Livewire\N2N\Chat;
use App\Models\BrandPartner;
use App\Models\N2N\Message;
use App\Models\User;
use App\Services\Brands\BusinessCardShareService;
use App\Services\N2N\Credit\UsageCredit;
use App\Services\N2N\Identity\NaaraId;
use App\Services\N2N\Messaging\MessagingException;
use App\Services\Wallet\WalletService;
use Livewire\Livewire;

/** Brand-Growth Phase 5 (MASTER-ONLY): a Business Card shared into a Naara chat as a card, never as a link. */
class BusinessCardShareTest extends N2NTestCase
{
    private function member(string $name, float $credit = 5): User
    {
        $u = $this->user(['name' => $name, 'email_verified_at' => now()]);
        $u->forceFill(['naara_id' => NaaraId::generate(), 'created_at' => now()->subDays(30)])->save();
        if ($credit > 0) {
            app(WalletService::class)->credit($u, $credit, 'USD', ['reference' => 'f'.$u->id.uniqid()]);
            app(UsageCredit::class)->purchase($u, $credit, 'b'.uniqid());
        }

        return $u->fresh();
    }

    private function brand(string $status = BrandPartner::STATUS_ACTIVE): BrandPartner
    {
        $owner = $this->member('Ada', 0);

        return BrandPartner::create(['owner_user_id' => $owner->id, 'brand_name' => 'Ada Bakes', 'short_description' => 'Fresh bread in Onitsha.', 'category' => 'Food', 'listing_status' => $status,
            'is_featured' => false, 'background_color' => '#0A6E6E', 'is_active' => true, 'logo_path' => '/images/brand-partners/ada/logo.webp']);
    }

    public function test_the_card_arrives_as_a_card_with_no_link_in_the_text_and_costs_the_normal_price(): void
    {
        $b = $this->brand();
        $a = $this->member('Alice');
        $bob = $this->member('Bob');

        $r = app(BusinessCardShareService::class)->share($a, (string) $bob->naara_id, $b->id, 'share-card-0001');
        $m = Message::find($r['message']->id);

        $this->assertSame('Business Card: Ada Bakes', $m->body);
        $this->assertSame('brand_card', $m->quote_json['source']);
        $this->assertSame(NaaraId::for($b->owner), $m->quote_json['address']);
        $this->assertSame('/images/brand-partners/ada/logo.webp', $m->quote_json['media']);
        $this->assertGreaterThan(0, (float) $m->cost_credits, 'a normal message: the sender pays the chat price');
        $this->assertSame(1, Message::count());
    }

    public function test_the_chat_bubble_draws_the_brand_and_an_open_link_built_from_the_address(): void
    {
        $b = $this->brand();
        $a = $this->member('Alice');
        $bob = $this->member('Bob');
        $r = app(BusinessCardShareService::class)->share($a, (string) $bob->naara_id, $b->id, 'share-card-0002');

        Livewire::actingAs($a)->test(Chat::class)->call('select', $r['conversation']->id)
            ->assertSee('data-brand-card', false)->assertSee('Ada Bakes')->assertSee('Fresh bread in Onitsha.')->assertSee('Open card')
            ->assertSee(route('business-card.show', NaaraId::for($b->owner)), false);
    }

    public function test_a_tampered_address_is_not_turned_into_a_link(): void
    {
        $b = $this->brand();
        $a = $this->member('Alice');
        $bob = $this->member('Bob');
        $r = app(BusinessCardShareService::class)->share($a, (string) $bob->naara_id, $b->id, 'share-card-0003');
        $m = Message::find($r['message']->id);
        $q = $m->quote_json;
        $q['address'] = 'javascript:alert(1)';
        $q['media'] = 'https://evil.example/x.png';
        $m->forceFill(['quote_json' => $q])->save();

        $html = Livewire::actingAs($a)->test(Chat::class)->call('select', $r['conversation']->id)->html();
        $this->assertStringNotContainsString('javascript:alert', $html);
        $this->assertStringNotContainsString('evil.example', $html);
    }

    public function test_only_a_listed_brand_can_be_shared_and_the_card_is_idempotent(): void
    {
        $paused = $this->brand(BrandPartner::STATUS_PAUSED);
        $a = $this->member('Alice');
        $bob = $this->member('Bob');
        try {
            app(BusinessCardShareService::class)->share($a, (string) $bob->naara_id, $paused->id, 'share-card-0004');
            $this->fail('an unlisted card must not be shareable');
        } catch (MessagingException $e) {
            $this->assertSame('That card is not available.', $e->getMessage());
        }

        $live = $this->brand();
        app(BusinessCardShareService::class)->share($a, (string) $bob->naara_id, $live->id, 'share-card-0005');
        app(BusinessCardShareService::class)->share($a, (string) $bob->naara_id, $live->id, 'share-card-0005');
        $this->assertSame(1, Message::count());
    }

    public function test_the_card_page_lets_a_member_send_it_and_hides_the_box_from_the_brand_owner(): void
    {
        $b = $this->brand();
        $a = $this->member('Alice');
        $bob = $this->member('Bob');
        $code = NaaraId::for($b->owner);

        $this->actingAs($a)->get('/b/'.$code)->assertOk()->assertSee('data-card-share', false);
        $this->actingAs($b->owner)->get('/b/'.$code)->assertOk()->assertDontSee('data-card-share', false);
        $this->get('/b/'.$code)->assertOk()->assertDontSee('data-card-share', false);

        Livewire::actingAs($a)->test(BusinessCardShare::class, ['brandId' => $b->id])->set('to', (string) $bob->naara_id)
            ->call('send')->assertSee('Sent to');
        $this->assertSame(1, Message::count());
    }
}
