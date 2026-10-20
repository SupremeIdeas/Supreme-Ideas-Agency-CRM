<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\SupportConversation;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Help Center hub (Naara Care overhaul) — one route reachable both signed in
 * and signed out, showing a start-chat CTA, recent articles, and reusing the
 * marketing FAQ content exactly (no second copy of the accordion).
 */
class HelpCenterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_a_guest_can_view_the_help_center(): void
    {
        $this->get('/help')
            ->assertOk()
            ->assertSee('How can we')
            ->assertSee('Start a new chat')
            ->assertSee('Sign in');
    }

    public function test_it_reuses_the_marketing_faq_content_exactly(): void
    {
        // The FAQ modal @includes the same 'home' SiteContent section the
        // marketing FAQ page renders — this is the "as-is, one source" check.
        $this->get('/help')->assertOk()->assertSee('Questions We Hear a Lot');
    }

    public function test_a_signed_in_user_with_an_open_thread_sees_continue_chat(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        $conv = SupportConversation::create(['user_id' => $user->id, 'status' => 'open']);
        $conv->messages()->create(['role' => 'user', 'body' => 'Hi']);

        $this->actingAs($user)->get('/help')
            ->assertOk()
            ->assertSee('You have an open conversation')
            ->assertSee('Continue chat');
    }

    public function test_a_signed_in_user_with_no_thread_sees_start_a_new_chat(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $this->actingAs($user)->get('/help')
            ->assertOk()
            ->assertSee('No conversations yet.');
    }

    public function test_search_filters_recent_articles(): void
    {
        Post::create([
            'title' => 'Zanzibar Roaming Guide', 'slug' => 'zanzibar-roaming-guide', 'category' => 'Travel',
            'excerpt' => 'e', 'body' => 'b', 'status' => 'published', 'published_at' => now(),
        ]);
        Post::create([
            'title' => 'Wallet Top-Up Tips', 'slug' => 'wallet-top-up-tips', 'category' => 'Money',
            'excerpt' => 'e', 'body' => 'b', 'status' => 'published', 'published_at' => now(),
        ]);

        \Livewire\Livewire::test(\App\Livewire\HelpCenter::class)
            ->assertSee('Zanzibar Roaming Guide')
            ->assertSee('Wallet Top-Up Tips')
            ->set('search', 'zanzibar')
            ->assertSee('Zanzibar Roaming Guide')
            ->assertDontSee('Wallet Top-Up Tips');
    }
}
