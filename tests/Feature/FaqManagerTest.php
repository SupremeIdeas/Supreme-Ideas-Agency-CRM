<?php

namespace Tests\Feature;

use App\Livewire\Admin\FaqManager;
use App\Models\FaqCategory;
use App\Models\FaqItem;
use App\Models\User;
use App\Support\FaqContent;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Admin -> FAQ (dedicated /faq page's topics + questions, rebuilt 2026-09-21
 * from the owner's design handoff onto FaqCategory/FaqItem). Master-only,
 * like the page itself.
 */
class FaqManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        FaqContent::flush();
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');

        return $user;
    }

    public function test_a_non_admin_cannot_open_the_manager(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(FaqManager::class)
            ->assertForbidden();
    }

    public function test_admin_can_create_a_topic(): void
    {
        Livewire::actingAs($this->admin())->test(FaqManager::class)
            ->set('catTitle', 'Billing')
            ->set('catEyebrow', 'Section XIII')
            ->set('catBlurb', 'Invoices and receipts.')
            ->call('saveCategory')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('faq_categories', ['title' => 'Billing', 'is_active' => true]);
    }

    public function test_admin_can_add_a_question_to_a_topic(): void
    {
        $category = FaqCategory::create(['slug' => 'start', 'title' => 'Getting Started', 'sort_order' => 1, 'is_active' => true]);

        Livewire::actingAs($this->admin())->test(FaqManager::class)
            ->call('selectCategory', $category->id)
            ->set('itemQuestion', 'What is Naara?')
            ->set('itemAnswer', 'A connectivity platform.')
            ->call('saveItem')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('faq_items', [
            'faq_category_id' => $category->id,
            'question' => 'What is Naara?',
        ]);
    }

    public function test_admin_can_edit_a_question(): void
    {
        $category = FaqCategory::create(['slug' => 'start', 'title' => 'Getting Started', 'sort_order' => 1, 'is_active' => true]);
        $item = FaqItem::create([
            'faq_category_id' => $category->id, 'question' => 'Old question?', 'answer' => 'Old answer.',
            'sort_order' => 1, 'is_active' => true,
        ]);

        Livewire::actingAs($this->admin())->test(FaqManager::class)
            ->call('selectCategory', $category->id)
            ->call('editItem', $item->id)
            ->set('itemQuestion', 'New question?')
            ->call('saveItem');

        $this->assertSame('New question?', $item->fresh()->question);
    }

    public function test_admin_can_delete_a_question(): void
    {
        $category = FaqCategory::create(['slug' => 'start', 'title' => 'Getting Started', 'sort_order' => 1, 'is_active' => true]);
        $item = FaqItem::create([
            'faq_category_id' => $category->id, 'question' => 'Delete me?', 'answer' => 'Bye.',
            'sort_order' => 1, 'is_active' => true,
        ]);

        Livewire::actingAs($this->admin())->test(FaqManager::class)
            ->call('selectCategory', $category->id)
            ->call('deleteItem', $item->id);

        $this->assertDatabaseMissing('faq_items', ['id' => $item->id]);
    }

    public function test_hiding_a_question_removes_it_from_the_public_page_but_keeps_it_in_admin(): void
    {
        $category = FaqCategory::create(['slug' => 'start', 'title' => 'Getting Started', 'sort_order' => 1, 'is_active' => true]);
        $item = FaqItem::create([
            'faq_category_id' => $category->id, 'question' => 'Toggle me?', 'answer' => 'OK.',
            'sort_order' => 1, 'is_active' => true,
        ]);

        Livewire::actingAs($this->admin())->test(FaqManager::class)
            ->call('toggleItem', $item->id);

        $this->assertFalse($item->fresh()->is_active);
        $this->get('/faq')->assertOk()->assertDontSee('Toggle me?');
    }

    public function test_moving_an_item_up_swaps_its_sort_order_with_its_neighbour(): void
    {
        $category = FaqCategory::create(['slug' => 'start', 'title' => 'Getting Started', 'sort_order' => 1, 'is_active' => true]);
        $first = FaqItem::create(['faq_category_id' => $category->id, 'question' => 'First?', 'answer' => 'A.', 'sort_order' => 1, 'is_active' => true]);
        $second = FaqItem::create(['faq_category_id' => $category->id, 'question' => 'Second?', 'answer' => 'B.', 'sort_order' => 2, 'is_active' => true]);

        Livewire::actingAs($this->admin())->test(FaqManager::class)
            ->call('selectCategory', $category->id)
            ->call('moveItem', $second->id, 'up');

        $this->assertSame(1, $second->fresh()->sort_order);
        $this->assertSame(2, $first->fresh()->sort_order);
    }

    public function test_deleting_a_topic_deletes_its_questions_too(): void
    {
        $category = FaqCategory::create(['slug' => 'start', 'title' => 'Getting Started', 'sort_order' => 1, 'is_active' => true]);
        $item = FaqItem::create(['faq_category_id' => $category->id, 'question' => 'Q?', 'answer' => 'A.', 'sort_order' => 1, 'is_active' => true]);

        Livewire::actingAs($this->admin())->test(FaqManager::class)
            ->call('deleteCategory', $category->id);

        $this->assertDatabaseMissing('faq_categories', ['id' => $category->id]);
        $this->assertDatabaseMissing('faq_items', ['id' => $item->id]);
    }
}
