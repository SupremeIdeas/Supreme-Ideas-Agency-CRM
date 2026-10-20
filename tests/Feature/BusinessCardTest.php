<?php

namespace Tests\Feature;

use App\Models\BrandLink;
use App\Models\BrandPartner;
use App\Models\User;
use App\Services\Brands\BusinessCardService;
use App\Services\N2N\Identity\NaaraId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Brand-Growth Phase 5: the public Business Card page, QR and contact card. */
class BusinessCardTest extends TestCase
{
    use RefreshDatabase;

    private function brand(array $over = []): BrandPartner
    {
        $owner = User::factory()->create();

        return BrandPartner::create($over + [
            'owner_user_id' => $owner->id, 'brand_name' => 'Ada Bakes', 'short_description' => 'Fresh bread in Onitsha.', 'category' => 'Food',
            'listing_status' => BrandPartner::STATUS_ACTIVE, 'is_featured' => false, 'background_color' => '#0A6E6E', 'is_active' => true,
        ]);
    }

    public function test_a_listed_brand_has_a_public_card_with_only_public_fields(): void
    {
        $b = $this->brand();
        BrandLink::create(['brand_partner_id' => $b->id, 'label' => 'Our shop', 'url' => 'https://adabakes.com', 'normalized_url' => 'https://adabakes.com', 'host' => 'adabakes.com', 'registrable_domain' => 'adabakes.com', 'tld' => 'com', 'status' => BrandLink::APPROVED]);
        BrandLink::create(['brand_partner_id' => $b->id, 'label' => 'Hidden', 'url' => 'https://evil.example', 'normalized_url' => 'https://evil.example', 'host' => 'evil.example', 'registrable_domain' => 'evil.example', 'tld' => 'example', 'status' => BrandLink::PENDING]);
        $code = NaaraId::for($b->owner);

        $res = $this->get('/b/'.$code)->assertOk();
        $res->assertSee('Ada Bakes')->assertSee('Fresh bread in Onitsha.')->assertSee('Our shop')->assertSee('<svg', false);
        $res->assertDontSee('Hidden')->assertDontSee($b->owner->email);
    }

    public function test_unlisted_unknown_or_malformed_codes_are_a_plain_404(): void
    {
        $b = $this->brand(['listing_status' => BrandPartner::STATUS_PAUSED]);
        $code = NaaraId::for($b->owner);
        $this->get('/b/'.$code)->assertOk()->assertSee('This card is not available')->assertSee('noindex', false)->assertDontSee($b->brand_name)->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get('/b/ZZZZZZZZZ')->assertOk()->assertSee('This card is not available');
        $this->get('/b/'.$code.'/qr.png')->assertNotFound();
        $this->get('/b/'.$code.'/card.vcf')->assertNotFound();
        $this->get('/b/'.str_repeat('A', 40))->assertNotFound();
    }

    public function test_views_qr_scans_and_saves_are_counted_without_counting_owner_bots_or_head(): void
    {
        $b = $this->brand();
        $code = NaaraId::for($b->owner);
        $svc = app(\App\Services\Brands\BusinessCardService::class);
        $human = ['User-Agent' => 'Mozilla/5.0 (iPhone) Safari'];
        $this->withHeaders($human)->get('/b/'.$code)->assertOk();
        $this->withHeaders($human)->get('/b/'.$code)->assertOk();
        $this->withHeaders($human)->get('/b/'.$code.'?s=qr')->assertOk();
        $this->withHeaders($human)->get('/b/'.$code.'/card.vcf')->assertOk();
        $this->withHeaders(['User-Agent' => 'Googlebot/2.1'])->get('/b/'.$code)->assertOk();
        $this->actingAs($b->owner)->withHeaders($human)->get('/b/'.$code)->assertOk();
        $this->assertSame(['view' => 2, 'qr_scan' => 1, 'vcard' => 1], $svc->stats($b));
    }

    public function test_the_print_card_is_a_pdf_for_a_live_brand_and_404_otherwise(): void
    {
        $b = $this->brand(['logo_path' => '/images/brand-partners/naara/logo.webp', 'hero_image_path' => '/images/brand-partners/naara/cover.webp']);
        $code = NaaraId::for($b->owner);
        $res = $this->get('/b/'.$code.'/card.pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $res->getContent());
        $paused = $this->brand(['listing_status' => BrandPartner::STATUS_PAUSED]);
        $this->get('/b/'.NaaraId::for($paused->owner).'/card.pdf')->assertNotFound();
    }

    public function test_code_is_case_insensitive_and_stable(): void
    {
        $b = $this->brand();
        $code = NaaraId::for($b->owner);
        $this->get('/b/'.strtolower($code))->assertOk();
        $this->assertSame($code, NaaraId::for($b->owner->fresh()));
        $this->assertSame(route('business-card.show', $code), app(BusinessCardService::class)->url($b->fresh()));
    }

    public function test_qr_downloads_are_real_images(): void
    {
        $b = $this->brand();
        $code = NaaraId::for($b->owner);
        $png = $this->get("/b/$code/qr.png")->assertOk();
        $this->assertSame('image/png', $png->headers->get('Content-Type'));
        $this->assertStringStartsWith("\x89PNG", $png->getContent());
        $svg = $this->get("/b/$code/qr.svg")->assertOk();
        $this->assertSame('image/svg+xml', $svg->headers->get('Content-Type'));
        $this->assertStringContainsString('<svg', $svg->getContent());
    }

    public function test_vcard_has_no_private_contact_data(): void
    {
        $b = $this->brand(['brand_name' => 'Ada, Bakes; Co']);
        $b->owner->forceFill(['phone' => '+2348000000000'])->save();
        $code = NaaraId::for($b->owner);
        $v = $this->get("/b/$code/card.vcf")->assertOk()->getContent();
        $this->assertStringContainsString('BEGIN:VCARD', $v);
        $this->assertStringContainsString('FN:Ada\, Bakes\; Co', $v);
        $this->assertStringNotContainsString('+2348000000000', $v);
        $this->assertStringNotContainsString($b->owner->email, $v);
    }

    public function test_owner_sees_the_card_on_the_brand_dashboard(): void
    {
        $b = $this->brand();
        $url = app(BusinessCardService::class)->url($b);
        \Livewire\Livewire::actingAs($b->owner)->test(\App\Livewire\BrandManage::class)->assertSee('Your Business Card')->assertSee($url);
    }

    public function test_card_to_signup_attributes_the_brand_owner_and_prompts_once_without_following(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        $b = $this->brand();
        $code = NaaraId::for($b->owner);

        $res = $this->get('/b/'.$code)->assertOk();
        $res->assertCookie('naara_brand_intent');
        $owner = $b->owner->fresh();
        $this->assertNotEmpty($owner->referral_code);
        $res->assertSee('ref='.$owner->referral_code, false);

        $this->flushSession();
        $b->owner->forceFill(['is_active' => true])->save();
        $this->withCookies(['naara_ref' => $owner->referral_code, 'naara_brand_intent' => (string) $b->id])
            ->post('/register', ['name' => 'New Person', 'email' => 'new@example.test', 'password' => 'Passw0rd!xyz', 'password_confirmation' => 'Passw0rd!xyz', 'terms' => 1]);

        $new = User::where('email', 'new@example.test')->first();
        $this->assertNotNull($new);
        $this->assertSame($owner->id, $new->referred_by);
        $this->assertDatabaseHas('connection_intents', ['user_id' => $new->id, 'brand_partner_id' => $b->id, 'status' => 'pending']);
        $this->assertSame(0, $b->fresh()->followers()->count());   // never an automatic follow
        $this->assertSame(1, \App\Models\ConnectionIntent::count());
    }

    public function test_connecting_settles_the_intent_and_the_owner_is_never_prompted_about_their_own_brand(): void
    {
        $b = $this->brand();
        $fan = User::factory()->create();
        $svc = app(\App\Services\Brands\ConnectionIntentService::class);
        $this->assertNull($svc->claimForUser($b->owner, (string) $b->id));
        $intent = $svc->claimForUser($fan, (string) $b->id);
        $this->assertSame('pending', $intent->status);
        $this->assertNull($svc->claimForUser($fan, 'abc'));
        \Livewire\Livewire::actingAs($fan)->test(\App\Livewire\BrandProfile::class, ['brandPartner' => $b])->call('toggleConnect');
        $this->assertSame('connected', $intent->fresh()->status);
    }
}
