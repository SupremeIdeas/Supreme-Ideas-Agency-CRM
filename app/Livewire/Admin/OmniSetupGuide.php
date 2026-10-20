<?php

namespace App\Livewire\Admin;

use App\Services\Omni\Kb\DocumentLimits;
use App\Services\Omni\OmniCredentials;
use App\Support\Omni\CredentialFormatChecker;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Admin > Respond setup guide (MASTER-ONLY): the complete, ordered, plain-language setup for Meta (WhatsApp, Instagram, Messenger), Telegram, web chat, email, the AI key
 * and the go-live checks, with this install's real callback addresses, what each credential looks like (fake samples), the mistakes that break each step, and a checker for
 * a pasted value. Content lives in config/omni_guides.php so it can be reworded when a provider moves its menus. Nothing typed into the checker is stored or logged.
 */
#[Layout('components.layouts.admin')]
class OmniSetupGuide extends Component
{
    #[Url(as: 'section')]
    public string $section = 'basics';

    public string $probe = '';

    /** @var array<string,int|string> the upload rules form */
    public array $ruleValues = [];

    public ?string $rulesSaved = null;

    /** @var array<string,mixed>|null */
    public ?array $result = null;

    public function mount(): void
    {
        $this->ruleValues = DocumentLimits::all();
    }

    public function saveRules(): void
    {
        $this->validate([
            'ruleValues.min_bytes' => 'required|integer|min:0|max:50000',
            'ruleValues.min_words' => 'required|integer|min:5|max:500',
            'ruleValues.max_mb' => 'required|integer|min:1|max:5',
            'ruleValues.max_files' => 'required|integer|min:1|max:20',
            'ruleValues.max_sources' => 'required|integer|min:10|max:200',
        ]);
        DocumentLimits::save($this->ruleValues);
        $this->ruleValues = DocumentLimits::all();
        $this->rulesSaved = 'Saved. The new rules apply to the next upload.';
    }

    public function open(string $key): void
    {
        $this->section = array_key_exists($key, (array) config('omni_guides.sections')) ? $key : 'basics';
    }

    public function checkValue(): void
    {
        $this->result = CredentialFormatChecker::check($this->probe);
        $this->probe = '';   // the pasted value never stays on the page
    }

    /** @return array<string,string> placeholders for the addresses to paste into provider dashboards */
    private function urls(): array
    {
        $base = rtrim((string) config('app.url'), '/');

        return [
            'whatsapp_platform' => $base.'/webhooks/whatsapp',
            'whatsapp_workspace' => $base.'/webhooks/meta/{connection number shown on the channel card}',
        ];
    }

    public function render()
    {
        $guides = (array) config('omni_guides');
        $key = array_key_exists($this->section, $guides['sections']) ? $this->section : 'basics';
        $app = rtrim((string) config('app.url'), '/');

        return view('livewire.admin.omni-setup-guide', [
            'order' => $guides['order'],
            'sections' => $guides['sections'],
            'current' => $guides['sections'][$key],
            'currentKey' => $key,
            'samples' => $guides['samples'],
            'verifiedOn' => $guides['verified_on'],
            'urls' => $this->urls(),
            'status' => OmniCredentials::status(),
            'https' => str_starts_with($app, 'https://'),
            'appUrl' => $app,
        ]);
    }
}
