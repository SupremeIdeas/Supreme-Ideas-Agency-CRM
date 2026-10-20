<?php

namespace App\Livewire;

use App\Models\ChannelConnection;
use App\Models\WalletGroup;
use App\Services\Inbox\ChannelConnector;
use App\Services\Inbox\ChannelSendException;
use App\Support\Business\WorkspaceAdminAccess;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Business Suite > Channels (MASTER-ONLY, Prompt 16): connect a workspace's own Telegram bot, WhatsApp number and Instagram account to the shared inbox.
 * Owner and admins only. Secrets are write-only: they are encrypted when saved and never shown again; the only secret ever displayed is the webhook verify
 * token the workspace pastes into its own Meta dashboard.
 */
#[Layout('components.layouts.business')]
class BusinessChannels extends Component
{
    public string $telegramToken = '';

    public string $waNumberId = '';

    public string $waToken = '';

    public string $waSecret = '';

    public string $igAccountId = '';

    public string $igToken = '';

    public string $igSecret = '';

    public string $msPageId = '';

    public string $msToken = '';

    public string $msSecret = '';

    public string $emLabel = '';

    public string $emFrom = '';

    public string $emReplyTo = '';

    public string $wcLabel = '';

    public string $wcOrigins = '';

    public string $wcGreeting = '';

    public string $wcColor = '#0A6E6E';

    public ?string $error = null;

    public ?string $ok = null;

    private function group(): WalletGroup
    {
        $g = WorkspaceAdminAccess::groupFor(auth()->user());
        abort_unless($g !== null, 404);

        return $g;
    }

    public function connectTelegram(ChannelConnector $connector): void
    {
        $this->attempt(fn () => $connector->connectTelegram($this->group(), $this->telegramToken), 'Telegram bot connected.', ['telegramToken']);
    }

    public function connectEmail(ChannelConnector $connector): void
    {
        $this->attempt(fn () => $connector->connectEmail($this->group(), $this->emLabel, $this->emFrom, $this->emReplyTo), 'Email connected. Now point your inbound mail at the address shown below.', ['emLabel', 'emFrom', 'emReplyTo']);
    }

    public function connectWebChat(ChannelConnector $connector): void
    {
        $origins = preg_split('/[\s,]+/', trim($this->wcOrigins)) ?: [];
        $this->attempt(fn () => $connector->connectWebChat($this->group(), $this->wcLabel, $origins, $this->wcGreeting, $this->wcColor), 'Web chat created. Copy the snippet below onto your website.', ['wcLabel', 'wcOrigins', 'wcGreeting']);
    }

    public function connectWhatsApp(ChannelConnector $connector): void
    {
        $this->attempt(fn () => $connector->connectWhatsApp($this->group(), $this->waNumberId, $this->waToken, $this->waSecret), 'WhatsApp number connected. Now paste the webhook details into Meta.', ['waNumberId', 'waToken', 'waSecret']);
    }

    public function connectInstagram(ChannelConnector $connector): void
    {
        $this->attempt(fn () => $connector->connectInstagram($this->group(), $this->igAccountId, $this->igToken, $this->igSecret), 'Instagram account connected. Now paste the webhook details into Meta.', ['igAccountId', 'igToken', 'igSecret']);
    }

    public function connectSms(int $numberId, ChannelConnector $connector): void
    {
        $this->attempt(fn () => $connector->connectSms($this->group(), $numberId), 'SMS number connected. Texts to it now arrive in your inbox and replies are sent from it.', []);
    }

    public function connectMessenger(ChannelConnector $connector): void
    {
        $this->attempt(fn () => $connector->connectMessenger($this->group(), $this->msPageId, $this->msToken, $this->msSecret), 'Messenger page connected. Now paste the webhook details into Meta.', ['msPageId', 'msToken', 'msSecret']);
    }

    /** Called by the Meta popup (Embedded Signup) with the code and the number the business picked. */
    public function finishMetaSignup(string $code, string $phoneNumberId, string $wabaId): void
    {
        abort_unless(class_exists(\App\Services\Omni\Meta\EmbeddedSignup::class), 404);
        $this->attempt(fn () => app(\App\Services\Omni\Meta\EmbeddedSignup::class)->finish($this->group(), $code, $phoneNumberId, $wabaId), 'WhatsApp number connected through Meta. Messages will arrive in your inbox.', []);
    }

    public function metaSignupFailed(string $reason = ''): void
    {
        $this->ok = null;
        $this->error = $reason === 'cancelled' ? 'The Meta window was closed before the number was connected. Press the button to try again.' : 'Meta could not complete the sign-in. Try again in a minute.';
    }

    public function pause(int $id): void
    {
        $c = ChannelConnection::where('wallet_group_id', $this->group()->id)->find($id);
        $c?->forceFill(['status' => $c->isActive() ? ChannelConnection::PAUSED : ChannelConnection::ACTIVE])->save();
    }

    public function disconnect(int $id, ChannelConnector $connector): void
    {
        $c = ChannelConnection::where('wallet_group_id', $this->group()->id)->find($id);
        if ($c) {
            $connector->disconnect($c);
            $this->dispatch('nx-toast', type: 'success', message: 'Channel disconnected.');
        }
    }

    /** @param list<string> $clear fields to wipe on success so a secret is never left sitting in the page */
    private function attempt(\Closure $run, string $success, array $clear): void
    {
        $this->error = $this->ok = null;
        try {
            $run();
        } catch (ChannelSendException $e) {
            $this->error = $e->getMessage();

            return;
        }
        $this->reset($clear);
        $this->ok = $success;
    }

    public function render()
    {
        $g = $this->group();

        return view('livewire.business-channels', [
            'group' => $g,
            'emailAvailable' => class_exists(\App\Services\Omni\Adapters\EmailAdapter::class) && \App\Support\Business\BusinessSettings::omniLive(),
            'pluginInfo' => class_exists(\App\Services\Omni\WordPress\WebChatPluginPackager::class) ? \App\Services\Omni\WordPress\WebChatPluginPackager::info() : null,
            'metaSignup' => class_exists(\App\Services\Omni\Meta\EmbeddedSignup::class) && \App\Support\Business\BusinessSettings::omniLive() && \App\Services\Omni\Meta\EmbeddedSignup::available() ? ['appId' => (string) config('omni.meta.app_id'), 'configId' => (string) config('omni.meta.config_id'), 'version' => (string) config('services.whatsapp.graph_version', 'v25.0')] : null,
            'smsNumbers' => class_exists(\App\Services\Omni\Adapters\SmsAdapter::class) && \App\Support\Business\BusinessSettings::omniLive()
                ? \App\Models\VirtualNumber::query()->where('wallet_group_id', $g->id)->where('status', 'active')->orderBy('id')->get(['id', 'phone_number', 'capabilities'])
                    ->filter(fn ($n) => ! array_key_exists('sms', (array) $n->capabilities) || (bool) ((array) $n->capabilities)['sms'])
                    ->reject(fn ($n) => ChannelConnection::where('channel', ChannelConnection::SMS)->where('external_id', $n->phone_number)->exists())
                    ->map(fn ($n) => ['id' => $n->id, 'number' => $n->phone_number])->values()->all()
                : [],
            'messengerAvailable' => class_exists(\App\Services\Omni\Adapters\MessengerAdapter::class) && \App\Support\Business\BusinessSettings::omniLive(),
            'webchatAvailable' => class_exists(\App\Services\Omni\Adapters\WebChatAdapter::class) && \App\Support\Business\BusinessSettings::omniLive(),
            // Plain arrays on purpose: the model carries encrypted credentials and must never be handed to the browser.
            'connections' => ChannelConnection::where('wallet_group_id', $g->id)->orderBy('channel')->orderBy('id')->get()->map(fn (ChannelConnection $c) => [
                'id' => $c->id, 'channel' => $c->channel, 'name' => ChannelConnection::label($c->channel), 'label' => $c->label, 'status' => $c->status,
                'error' => $c->last_error, 'last' => $c->last_event_at, 'meta' => ! in_array($c->channel, [ChannelConnection::TELEGRAM, ChannelConnection::WEBCHAT, ChannelConnection::EMAIL, ChannelConnection::SMS], true) && ($c->credentials['signup'] ?? '') !== 'embedded',
                'embedded' => ($c->credentials['signup'] ?? '') === 'embedded',
                'inbound' => $c->channel === ChannelConnection::EMAIL && $c->public_id ? route('omni.webhook', ['channel' => 'email', 'publicId' => $c->public_id]) : null,
                'inboundSecret' => $c->channel === ChannelConnection::EMAIL ? (string) $c->webhook_secret : null,
                'plugin' => $c->channel === ChannelConnection::WEBCHAT && $c->public_id && \Illuminate\Support\Facades\Route::has('omni.webchat.plugin') ? route('omni.webchat.plugin', ['connection' => $c->id]) : null,
                'snippet' => $c->channel === ChannelConnection::WEBCHAT && $c->public_id ? '<script async src="'.asset('js/omni-webchat.js').'" data-site="'.$c->public_id.'"></script>' : null,
                'callback' => ! in_array($c->channel, [ChannelConnection::TELEGRAM, ChannelConnection::WEBCHAT, ChannelConnection::EMAIL, ChannelConnection::SMS], true) ? ($c->public_id ? route('omni.webhook', ['channel' => $c->channel, 'publicId' => $c->public_id]) : route('webhooks.meta', ['connection' => $c->id])) : null,
                'verify' => ! in_array($c->channel, [ChannelConnection::TELEGRAM, ChannelConnection::WEBCHAT, ChannelConnection::EMAIL, ChannelConnection::SMS], true) ? (string) $c->webhook_secret : null,
            ]),
        ]);
    }
}
