<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A workspace's OWN channel account for the shared inbox (MASTER-ONLY, Business Suite Prompt 16): its WhatsApp Business number, Telegram bot or
 * Instagram account. Credentials are encrypted at rest and never leave the server: they are write-only in the UI and hidden from serialisation.
 * Inbound messages are routed to the owner of the connection's workspace through (channel, external_id).
 */
class ChannelConnection extends Model
{
    public const WHATSAPP = 'whatsapp';

    public const TELEGRAM = 'telegram';

    public const INSTAGRAM = 'instagram';

    public const WEBCHAT = 'webchat';

    public const EMAIL = 'email';

    public const MESSENGER = 'messenger';

    public const SMS = 'sms';

    public const CHANNELS = [self::WHATSAPP, self::TELEGRAM, self::INSTAGRAM];

    public const ACTIVE = 'active';

    public const PAUSED = 'paused';

    public const ERROR = 'error';

    protected $fillable = ['wallet_group_id', 'channel', 'external_id', 'label', 'credentials', 'webhook_secret', 'status', 'last_error', 'last_event_at'];

    protected $hidden = ['credentials', 'webhook_secret'];

    protected function casts(): array
    {
        return ['credentials' => 'encrypted:array', 'last_event_at' => 'datetime'];
    }

    public function walletGroup(): BelongsTo
    {
        return $this->belongsTo(WalletGroup::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::ACTIVE;
    }

    /** One secret value from the encrypted credentials, or null. */
    public function secret(string $key): ?string
    {
        $v = ($this->credentials ?? [])[$key] ?? null;

        return is_string($v) && $v !== '' ? $v : null;
    }

    public static function forChannel(string $channel, string $externalId): ?self
    {
        return static::query()->where('channel', $channel)->where('external_id', $externalId)->first();
    }

    public static function label(string $channel): string
    {
        return ['whatsapp' => 'WhatsApp', 'telegram' => 'Telegram', 'instagram' => 'Instagram', 'messenger' => 'Messenger', 'webchat' => 'Web chat', 'sms' => 'SMS', 'email' => 'Email'][$channel] ?? ucfirst($channel);
    }
}
