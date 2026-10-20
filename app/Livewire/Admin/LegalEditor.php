<?php

namespace App\Livewire\Admin;

use App\Models\Setting;
use App\Support\Auditor;
use App\Support\LegalContent;
use App\Support\LegalReview;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Admin → Legal (Module 30). No-code editor for the five legal documents.
 * Loads the current (merged) title + body for a doc, saves an override, or
 * resets back to the shipped best-practice default. Body uses the safe light
 * markup (## headings + blank-line paragraphs) rendered escaped on the site.
 */
#[Layout('components.layouts.admin')]
class LegalEditor extends Component
{
    public string $slug = 'privacy';

    public string $title = '';

    public string $body = '';

    public ?string $saved = null;

    public function mount(): void
    {
        $this->load();
    }

    public function updatedSlug(): void
    {
        $this->saved = null;
        $this->load();
    }

    private function load(): void
    {
        $doc = LegalContent::doc($this->slug);
        $this->title = $doc['title'];
        $this->body = $doc['body'];
    }

    public function save(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);
        abort_unless(LegalContent::exists($this->slug), 404);

        $this->validate([
            'title' => 'required|string|max:120',
            'body' => 'required|string|max:20000',
        ]);

        Setting::setValue("legal.{$this->slug}", [
            'title' => trim($this->title),
            'body' => trim($this->body),
            'updated' => now()->toDateString(),
        ], 'legal');

        LegalContent::flush();
        LegalReview::clear($this->slug); // the reviewed words are no longer the live words
        Auditor::log('legal.updated', Setting::class, null, ['slug' => $this->slug]);
        $this->saved = 'Saved. The public page now shows your version.';
        $this->dispatch('nx-toast', type: 'success', message: 'Legal page saved.');
    }

    /** Record that counsel has reviewed the CURRENT text of this document. Super admin only; audited. */
    public function markReviewed(): void
    {
        abort_unless(Auth::user()->hasRole('super_admin'), 403);
        abort_unless(LegalContent::exists($this->slug), 404);

        LegalReview::mark($this->slug, (string) (Auth::user()->name ?? 'admin'));
        Auditor::log('legal.reviewed', Setting::class, null, ['slug' => $this->slug]);
        $this->dispatch('nx-toast', type: 'success', message: 'Recorded as reviewed by counsel.');
    }

    public function resetToDefault(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['super_admin', 'admin']), 403);

        // Delete via the model so Setting's per-key cache is busted (the
        // deleted model event forgets it); a query-builder delete would not.
        Setting::where('key', "legal.{$this->slug}")->get()->each->delete();
        LegalContent::flush();
        LegalReview::clear($this->slug);
        Auditor::log('legal.reset', Setting::class, null, ['slug' => $this->slug]);
        $this->load();
        $this->saved = 'Reset to the original best-practice copy.';
        $this->dispatch('nx-toast', type: 'info', message: 'Legal page reset to default.');
    }

    public function render()
    {
        return view('livewire.admin.legal-editor', [
            'docs' => LegalContent::all(),
        ]);
    }
}
