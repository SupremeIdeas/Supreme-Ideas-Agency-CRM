<?php

namespace Tests\Feature;

use App\Livewire\Admin\BrandTrials;
use App\Livewire\BrandManage;
use App\Models\BrandPartner;
use App\Models\Setting;
use App\Models\User;
use App\Services\Brands\BusinessCardService;
use App\Services\N2N\Identity\NaaraId;
use App\Support\BusinessCardReferralPrecedence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Brand-Growth Phases 5/6 (MASTER-ONLY): a readable card address and a short code beside the Naara ID address, bio lines, and which referral link wins. */
class BusinessCardAddressTest extends TestCase
{
    use RefreshDatabase;

    private function brand(string $name = 'Ada Bakes'): BrandPartner
    {
        $owner = User::factory()->create();

        return BrandPartner::create(['owner_user_id' => $owner->id, 'brand_name' => $name, 'short_description' => 'Fresh bread.', 'category' => 'Food',
            'listing_status' => BrandPartner::STATUS_ACTIVE, 'is_featured' => false, 'background_color' => '#0A6E6E', 'is_active' => true]);
    }

    public function test_one_card_answers_on_its_naara_id_its_short_code_and_its_readable_name(): void
    {
        $b = $this->brand();
        $svc = app(BusinessCardService::class);
        $svc->setSlug($b, 'AdaBakes');
        $short = $svc->shortCode($b->fresh());
        $id = NaaraId::for($b->owner);

        $this->assertMatchesRegularExpression('/^[A-HJKMNP-Z2-9]{6}$/', $short);
        $this->get('/b/'.$id)->assertOk()->assertSee('Ada Bakes');
        $this->get('/b/'.$short)->assertOk()->assertSee('Ada Bakes');
        $this->get('/b/'.strtolower($short))->assertOk()->assertSee('Ada Bakes');
        $this->get('/b/adabakes')->assertOk()->assertSee('Ada Bakes');
        $this->assertSame(route('business-card.show', 'adabakes'), $svc->url($b->fresh()), 'the readable address is the one shown and put in the QR');
        $this->assertSame($short, $svc->shortCode($b->fresh()), 'the short code never changes once made');
    }

    public function test_a_paused_brand_is_unavailable_on_every_address(): void
    {
        $b = $this->brand();
        $svc = app(BusinessCardService::class);
        $svc->setSlug($b, 'ada-bakes');
        $short = $svc->shortCode($b->fresh());
        $b->update(['listing_status' => BrandPartner::STATUS_PAUSED]);

        $this->get('/b/ada-bakes')->assertOk()->assertSee('This card is not available')->assertDontSee('Ada Bakes');
        $this->get('/b/'.$short)->assertOk()->assertSee('This card is not available')->assertDontSee('Ada Bakes');
    }

    public function test_the_readable_name_is_validated_unique_and_never_clashes_with_other_addresses(): void
    {
        $a = $this->brand();
        $b = $this->brand('Bola Cakes');
        $svc = app(BusinessCardService::class);
        $svc->setSlug($a, 'ada-bakes');

        foreach (['ab', '1abc', 'has space', 'trailing-', 'dou--ble', str_repeat('a', 31), 'naara', 'admin', 'ada-bakes'] as $bad) {
            try {
                $svc->setSlug($b, $bad);
                $this->fail("{$bad} should be refused");
            } catch (\InvalidArgumentException) {
                $this->assertNull($b->fresh()->card_slug);
            }
        }
        $short = $svc->shortCode($a->fresh());
        $this->expectException(\InvalidArgumentException::class);   // too close to someone's short code
        $svc->setSlug($b, strtolower($short));
    }

    public function test_changing_or_clearing_the_readable_name_frees_the_old_one_and_keeps_the_id_address(): void
    {
        $b = $this->brand();
        $svc = app(BusinessCardService::class);
        $svc->setSlug($b, 'ada-bakes');
        $svc->setSlug($b->fresh(), 'ada-bread');
        $this->get('/b/ada-bakes')->assertOk()->assertSee('This card is not available');
        $this->get('/b/ada-bread')->assertOk()->assertSee('Ada Bakes');
        $svc->setSlug($b->fresh(), '');
        $this->assertNull($b->fresh()->card_slug);
        $this->get('/b/'.NaaraId::for($b->owner))->assertOk()->assertSee('Ada Bakes');
    }

    public function test_bio_lines_fit_their_platform_and_never_cut_the_link(): void
    {
        $b = $this->brand('A Very Long Brand Name That Goes On And On And On For Quite A While Indeed');
        $svc = app(BusinessCardService::class);
        $short = $svc->shortUrl($b);

        foreach ($svc->snippets($b) as $s) {
            $this->assertLessThanOrEqual($s['limit'], mb_strlen($s['text']), $s['label']);
            $this->assertStringEndsWith($short, $s['text'], $s['label'].' keeps the whole link');
        }
    }

    public function test_the_owner_sets_the_address_from_the_panel_and_sees_errors_in_plain_words(): void
    {
        $b = $this->brand();
        $svc = app(BusinessCardService::class);
        $svc->setSlug($this->brand('Other'), 'taken-name');

        Livewire::actingAs($b->owner)->test(BrandManage::class)->set('cardSlug', 'taken-name')->call('saveCardSlug')->assertSet('error', 'That address is already taken.')
            ->set('cardSlug', 'My-Shop')->call('saveCardSlug')->assertSet('cardSlug', 'my-shop')->assertSet('flash', 'Card address saved.')
            ->assertSee('data-card-address', false)->assertSee('Bio lines to paste');
        $this->assertSame('my-shop', $b->fresh()->card_slug);
    }

    /** Cookies as the browser would send them back: encrypted with the cookie prefix, exactly as EncryptCookies wrote them. */
    private function sealed(array $cookies): array
    {
        $enc = app('encrypter');
        $out = [];
        foreach ($cookies as $name => $value) {
            $out[$name] = $enc->encrypt(\Illuminate\Cookie\CookieValuePrefix::create($name, $enc->getKey()).$value, false);
        }

        return $out;
    }

    /** One app serves every request in a test, so cookies queued by an earlier request would leak into the next response; a real request starts clean. */
    private function fresh(): static
    {
        \Illuminate\Support\Facades\Cookie::flushQueuedCookies();

        return $this;
    }

    public function test_referral_precedence_modes(): void
    {
        Setting::setValue('referral.precedence', 'last');
        $this->assertTrue(BusinessCardReferralPrecedence::replaces(true, 'brand', 'personal'));
        Setting::setValue('referral.precedence', 'first');
        $this->assertFalse(BusinessCardReferralPrecedence::replaces(true, 'personal', 'brand'));
        $this->assertTrue(BusinessCardReferralPrecedence::replaces(false, null, 'personal'));
        Setting::setValue('referral.precedence', 'brand');
        $this->assertFalse(BusinessCardReferralPrecedence::replaces(true, 'brand', 'personal'), 'a personal link never displaces a brand card link');
        $this->assertTrue(BusinessCardReferralPrecedence::replaces(true, 'personal', 'brand'));
        $this->assertTrue(BusinessCardReferralPrecedence::replaces(true, 'brand', 'brand'));
        Setting::setValue('referral.precedence', 'nonsense');
        $this->assertSame('last', BusinessCardReferralPrecedence::mode());
    }

    public function test_the_cookie_follows_the_chosen_precedence(): void
    {
        $friend = User::factory()->create(['referral_code' => 'FRIEND12']);
        $owner = User::factory()->create(['referral_code' => 'BRAND123']);
        $b = BrandPartner::create(['owner_user_id' => $owner->id, 'brand_name' => 'Ada Bakes', 'category' => 'Food', 'listing_status' => BrandPartner::STATUS_ACTIVE, 'is_featured' => false, 'background_color' => '#0A6E6E', 'is_active' => true]);
        $card = '/b/'.NaaraId::for($owner);

        Setting::setValue('referral.precedence', 'brand');
        $r = $this->get($card.'?ref=BRAND123');
        $r->assertCookie('naara_ref', 'BRAND123');
        $r2 = $this->fresh()->withUnencryptedCookies($this->sealed(['naara_ref' => 'BRAND123', 'naara_ref_kind' => 'brand']))->get('/login?ref=FRIEND12');
        $r2->assertCookieMissing('naara_ref');   // the friend's link did not replace the brand card's

        Setting::setValue('referral.precedence', 'last');
        $r3 = $this->fresh()->withUnencryptedCookies($this->sealed(['naara_ref' => 'BRAND123', 'naara_ref_kind' => 'brand']))->get('/login?ref=FRIEND12');
        $r3->assertCookie('naara_ref', 'FRIEND12');
    }

    public function test_the_admin_can_change_the_precedence(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'super_admin'])->save();
        $admin->assignRole(\Spatie\Permission\Models\Role::findOrCreate('super_admin'));

        Livewire::actingAs($admin)->test(BrandTrials::class)->set('precedence', 'first')->call('save')->assertHasNoErrors();
        $this->assertSame('first', BusinessCardReferralPrecedence::mode());
    }
}
