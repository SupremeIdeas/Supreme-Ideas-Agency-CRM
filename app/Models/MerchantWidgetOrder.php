<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The masked wrapper around a widget purchase (MASTER-ONLY, blueprint §4.2). Mirrors `api_orders`: it links to the real fulfilment
 * record but is the only thing ever exposed back to a widget. It never stores cost or supplier.
 */
class MerchantWidgetOrder extends Model
{
    public const PENDING = 'pending_payment';

    public const FULFILLING = 'fulfilling';

    public const COMPLETED = 'completed';

    public const FAILED = 'failed';

    public const REFUNDED = 'refunded';

    public const HELD = 'held';

    protected $fillable = [
        'merchant_widget_key_id', 'merchant_id', 'buyer_user_id', 'buyer_email', 'kind', 'product_id', 'product_ref', 'sms_order_id', 'earned_at', 'face_value', 'buyer_fields', 'reference',
        'session_token_hash', 'session_expires_at', 'session_used_at', 'status', 'retail_price_usd', 'merchant_price_usd', 'currency',
        'payment_gateway', 'payment_reference', 'esim_order_id', 'gift_card_order_id', 'result', 'failure_reason', 'origin_domain',
    ];

    protected $hidden = ['session_token_hash'];

    protected function casts(): array
    {
        return [
            'face_value' => 'decimal:4',
            'buyer_fields' => 'array',
            'retail_price_usd' => 'decimal:4',
            'merchant_price_usd' => 'decimal:4',
            'result' => 'array',
            'session_expires_at' => 'datetime',
            'session_used_at' => 'datetime',
            'earned_at' => 'datetime',
        ];
    }

    public function key(): BelongsTo
    {
        return $this->belongsTo(MerchantWidgetKey::class, 'merchant_widget_key_id');
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_user_id');
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [self::COMPLETED, self::FAILED, self::REFUNDED], true);
    }
}
