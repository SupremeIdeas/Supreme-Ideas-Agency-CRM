<?php

namespace App\Livewire\Admin;

use App\Models\FaqCategory;
use App\Models\FaqItem;
use App\Support\Auditor;
use App\Support\FaqContent;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Admin -> FAQ (dedicated /faq page, master-only like the page itself). Two
 * panels: topics (FaqCategory) on the left, the selected topic's questions
 * (FaqItem) on the right. Reordering is a simple swap-with-neighbour on
 * `sort_order` (no drag-and-drop) — the same trade-off this codebase already
 * makes elsewhere for admin list ordering. An answer may contain a small set
 * of inline `<strong>`/`<a>` tags and `{{token}}` placeholders (see
 * `App\Support\FaqTokens`); it's admin-trusted HTML, not escaped.
 */
#[Layout('components.layouts.admin')]
class FaqManager extends Component
{
    public ?int $selectedCategoryId = null;

    // Category form (add or edit)
    public ?int $editingCategoryId = null;

    public string $catTitle = '';

    public string $catEyebrow = '';

    public string $catBlurb = '';

    public string $catTagLabel = '';

    public string $catTagRoute = '';

    // Item form (add or edit)
    public ?int $editingItemId = null;

    public string $itemQuestion = '';

    public string $itemAnswer = '';

    public function mount(): void
    {
        $this->authorizeAdmin();
        $this->selectedCategoryId = FaqCategory::orderBy('sort_order')->value('id');
    }

    private function authorizeAdmin(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);
    }

    public function selectCategory(int $id): void
    {
        $this->selectedCategoryId = $id;
        $this->cancelItemEdit();
    }

    public function editCategory(int $id): void
    {
        $cat = FaqCategory::findOrFail($id);
        $this->editingCategoryId = $cat->id;
        $this->catTitle = $cat->title;
        $this->catEyebrow = (string) $cat->eyebrow;
        $this->catBlurb = (string) $cat->blurb;
        $this->catTagLabel = (string) $cat->tag_label;
        $this->catTagRoute = (string) $cat->tag_route;
    }

    public function cancelCategoryEdit(): void
    {
        $this->reset(['editingCategoryId', 'catTitle', 'catEyebrow', 'catBlurb', 'catTagLabel', 'catTagRoute']);
    }

    public function saveCategory(): void
    {
        $this->authorizeAdmin();

        $data = $this->validate([
            'catTitle' => 'required|string|max:120',
            'catEyebrow' => 'nullable|string|max:60',
            'catBlurb' => 'nullable|string|max:300',
            'catTagLabel' => 'nullable|string|max:60',
            'catTagRoute' => 'nullable|string|max:60',
        ]);

        if ($this->editingCategoryId) {
            $cat = FaqCategory::findOrFail($this->editingCategoryId);
            $cat->update([
                'title' => $data['catTitle'],
                'eyebrow' => $data['catEyebrow'] ?: null,
                'blurb' => $data['catBlurb'] ?: null,
                'tag_label' => $data['catTagLabel'] ?: null,
                'tag_route' => $data['catTagRoute'] ?: null,
            ]);
            Auditor::log('faq.category.updated', FaqCategory::class, $cat->id, ['id' => $cat->id]);
        } else {
            $slug = \Illuminate\Support\Str::slug($data['catTitle']).'-'.\Illuminate\Support\Str::random(4);
            $nextOrder = ((int) FaqCategory::max('sort_order')) + 1;
            $cat = FaqCategory::create([
                'slug' => $slug,
                'title' => $data['catTitle'],
                'eyebrow' => $data['catEyebrow'] ?: null,
                'blurb' => $data['catBlurb'] ?: null,
                'tag_label' => $data['catTagLabel'] ?: null,
                'tag_route' => $data['catTagRoute'] ?: null,
                'sort_order' => $nextOrder,
                'is_active' => true,
            ]);
            Auditor::log('faq.category.created', FaqCategory::class, $cat->id, ['id' => $cat->id]);
            $this->selectedCategoryId = $cat->id;
        }

        FaqContent::flush();
        $this->cancelCategoryEdit();
        $this->dispatch('nx-toast', type: 'success', message: 'FAQ topic saved.');
    }

    public function toggleCategory(int $id): void
    {
        $this->authorizeAdmin();
        $cat = FaqCategory::findOrFail($id);
        $cat->update(['is_active' => ! $cat->is_active]);
        Auditor::log('faq.category.toggled', FaqCategory::class, $cat->id, ['active' => $cat->is_active]);
        FaqContent::flush();
    }

    public function deleteCategory(int $id): void
    {
        $this->authorizeAdmin();
        $cat = FaqCategory::findOrFail($id);
        Auditor::log('faq.category.deleted', FaqCategory::class, $cat->id, ['title' => $cat->title]);
        $cat->delete();
        if ($this->selectedCategoryId === $id) {
            $this->selectedCategoryId = FaqCategory::orderBy('sort_order')->value('id');
        }
        FaqContent::flush();
        $this->dispatch('nx-toast', type: 'success', message: 'FAQ topic deleted.');
    }

    public function moveCategory(int $id, string $direction): void
    {
        $this->authorizeAdmin();
        $this->swapSortOrder(FaqCategory::query(), $id, $direction);
        FaqContent::flush();
    }

    public function editItem(int $id): void
    {
        $item = FaqItem::findOrFail($id);
        $this->editingItemId = $item->id;
        $this->itemQuestion = $item->question;
        $this->itemAnswer = $item->answer;
    }

    public function cancelItemEdit(): void
    {
        $this->reset(['editingItemId', 'itemQuestion', 'itemAnswer']);
    }

    public function saveItem(): void
    {
        $this->authorizeAdmin();
        abort_unless($this->selectedCategoryId, 422);

        $data = $this->validate([
            'itemQuestion' => 'required|string|max:255',
            'itemAnswer' => 'required|string|max:2000',
        ]);

        if ($this->editingItemId) {
            $item = FaqItem::findOrFail($this->editingItemId);
            $item->update(['question' => $data['itemQuestion'], 'answer' => $data['itemAnswer']]);
            Auditor::log('faq.item.updated', FaqItem::class, $item->id, ['id' => $item->id]);
        } else {
            $nextOrder = ((int) FaqItem::where('faq_category_id', $this->selectedCategoryId)->max('sort_order')) + 1;
            $item = FaqItem::create([
                'faq_category_id' => $this->selectedCategoryId,
                'question' => $data['itemQuestion'],
                'answer' => $data['itemAnswer'],
                'sort_order' => $nextOrder,
                'is_active' => true,
            ]);
            Auditor::log('faq.item.created', FaqItem::class, $item->id, ['id' => $item->id]);
        }

        FaqContent::flush();
        $this->cancelItemEdit();
        $this->dispatch('nx-toast', type: 'success', message: 'Question saved.');
    }

    public function toggleItem(int $id): void
    {
        $this->authorizeAdmin();
        $item = FaqItem::findOrFail($id);
        $item->update(['is_active' => ! $item->is_active]);
        Auditor::log('faq.item.toggled', FaqItem::class, $item->id, ['active' => $item->is_active]);
        FaqContent::flush();
    }

    public function deleteItem(int $id): void
    {
        $this->authorizeAdmin();
        $item = FaqItem::findOrFail($id);
        Auditor::log('faq.item.deleted', FaqItem::class, $item->id, ['question' => $item->question]);
        $item->delete();
        FaqContent::flush();
        $this->dispatch('nx-toast', type: 'success', message: 'Question deleted.');
    }

    public function moveItem(int $id, string $direction): void
    {
        $this->authorizeAdmin();
        $item = FaqItem::findOrFail($id);
        $this->swapSortOrder(FaqItem::where('faq_category_id', $item->faq_category_id), $id, $direction);
        FaqContent::flush();
    }

    /** Swaps `sort_order` between the row `$id` and its up/down neighbour within `$scope`. */
    private function swapSortOrder(\Illuminate\Database\Eloquent\Builder $scope, int $id, string $direction): void
    {
        $ordered = (clone $scope)->orderBy('sort_order')->get();
        $index = $ordered->search(fn ($row) => $row->id === $id);
        if ($index === false) {
            return;
        }

        $swapWith = $direction === 'up' ? $index - 1 : $index + 1;
        if (! $ordered->has($swapWith)) {
            return;
        }

        $current = $ordered->get($index);
        $neighbour = $ordered->get($swapWith);
        [$currentOrder, $neighbourOrder] = [$current->sort_order, $neighbour->sort_order];
        $current->update(['sort_order' => $neighbourOrder]);
        $neighbour->update(['sort_order' => $currentOrder]);
    }

    public function render()
    {
        return view('livewire.admin.faq-manager', [
            'categories' => FaqCategory::orderBy('sort_order')->withCount('items')->get(),
            'items' => $this->selectedCategoryId
                ? FaqItem::where('faq_category_id', $this->selectedCategoryId)->orderBy('sort_order')->get()
                : collect(),
        ]);
    }
}
