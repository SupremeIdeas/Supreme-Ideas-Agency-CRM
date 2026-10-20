<?php

namespace App\Livewire\Admin;

use App\Jobs\SendEmailBroadcastJob;
use App\Models\EmailBroadcast as EmailBroadcastModel;
use App\Services\Marketing\MarketingCopywriter;
use App\Support\Auditor;
use App\Support\BroadcastAudience;
use App\Support\DeepLinkLibrary;
use App\Support\HtmlSanitizer;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Admin → Email Studio broadcast (NAARA-BUILD-20 §3.2). Compose a subject + body,
 * pick an audience (all / role / account type / segment), see the real recipient
 * count, confirm, and queue a batched send. Every send is logged (Auditor + a
 * history row). super_admin/admin only.
 */
#[Layout('components.layouts.admin')]
class EmailBroadcast extends Component
{
    public string $subject = '';

    public string $body = '';

    public string $audienceType = 'all';

    public ?string $audienceValue = null;

    /** Two-step send: compose -> confirm (shows the real count) -> dispatch. */
    public bool $confirming = false;

    public ?string $sent = null;

    /** AI-drafted subject/body pairs from the last "Refine with Claude" call. */
    public array $variations = [];

    public ?string $aiError = null;

    public function booted(): void
    {
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']), 403);
    }

    /** Content/billing/hot-menu blueprint Phase C — AI-assisted composer. */
    public function refine(MarketingCopywriter $writer): void
    {
        $this->aiError = null;
        $this->variations = [];

        if (! $writer->enabled()) {
            $this->aiError = 'Add an Anthropic API key (Integrations) to generate copy.';

            return;
        }
        if (trim($this->subject) === '' && trim(strip_tags($this->body)) === '') {
            $this->aiError = 'Write a subject or message first — there’s nothing to refine yet.';

            return;
        }

        $this->variations = $writer->emailVariations($this->subject, $this->body);
        if ($this->variations === []) {
            $this->aiError = 'Claude couldn’t generate variations — try again.';
        }
    }

    public function applyVariation(int $i): void
    {
        if (! isset($this->variations[$i])) {
            return;
        }
        $this->subject = $this->variations[$i]['subject'];
        $this->body = $this->variations[$i]['body'];
        $this->variations = [];
    }

    public function updatedAudienceType(): void
    {
        $this->audienceValue = null;
        $this->confirming = false;
    }

    public function updatedAudienceValue(): void
    {
        $this->confirming = false;
    }

    protected function rules(): array
    {
        return [
            'subject' => 'required|string|max:150',
            'body' => 'required|string|max:20000',
            'audienceType' => 'required|in:all,role,account,segment',
            'audienceValue' => 'nullable|string|max:40',
        ];
    }

    public function recipientCount(): int
    {
        return BroadcastAudience::count($this->audienceType, $this->audienceValue);
    }

    /** Validate + move to the confirmation step (never sends yet). */
    public function review(): void
    {
        $this->validate();
        if ($this->audienceType !== 'all' && ! $this->audienceValue) {
            $this->addError('audienceValue', 'Pick who this goes to.');

            return;
        }
        $this->confirming = true;
    }

    public function send(): void
    {
        $this->validate();
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']), 403);

        $count = $this->recipientCount();
        if ($count === 0) {
            $this->addError('audienceValue', 'That audience has no recipients.');
            $this->confirming = false;

            return;
        }

        $broadcast = EmailBroadcastModel::create([
            'subject' => $this->subject,
            'body_html' => HtmlSanitizer::clean($this->body),
            'audience_type' => $this->audienceType,
            'audience_value' => $this->audienceValue,
            'audience_label' => BroadcastAudience::label($this->audienceType, $this->audienceValue),
            'recipient_count' => $count,
            'status' => 'queued',
            'sent_by' => Auth::id(),
        ]);

        SendEmailBroadcastJob::dispatch($broadcast->id);
        Auditor::log('mail.broadcast_sent', null, null, [
            'id' => $broadcast->id, 'audience' => $broadcast->audience_label, 'count' => $count,
        ]);

        $this->reset(['subject', 'body', 'audienceValue', 'confirming', 'variations', 'aiError']);
        $this->audienceType = 'all';
        $this->sent = "Queued to {$count} ".\Illuminate\Support\Str::plural('recipient', $count).'.';
        $this->dispatch('nx-toast', type: 'success', message: $this->sent);
    }

    public function render(MarketingCopywriter $writer)
    {
        return view('livewire.admin.email-broadcast', [
            'types' => BroadcastAudience::types(),
            'roles' => BroadcastAudience::roles(),
            'accounts' => BroadcastAudience::accounts(),
            'segments' => BroadcastAudience::segments(),
            'history' => EmailBroadcastModel::with('sender')->latest()->limit(15)->get(),
            'aiEnabled' => $writer->enabled(),
            'deepLinks' => DeepLinkLibrary::all(),
        ]);
    }
}
