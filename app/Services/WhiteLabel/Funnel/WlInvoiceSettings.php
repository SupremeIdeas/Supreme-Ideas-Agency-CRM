<?php

namespace App\Services\WhiteLabel\Funnel;

use App\Models\Setting;

/** Admin-editable invoice settings (MASTER-ONLY). Nothing about the seller is hard-coded: the legal name, address, tax ids, terms and number formats all live here. */
final class WlInvoiceSettings
{
    public const DEFAULT_FORMATS = ['INV' => 'INV-{YYYY}-{000000}', 'RCT' => 'RCT-{YYYY}-{000000}', 'PRO' => 'PRO-{YYYY}-{000000}', 'CN' => 'CN-{YYYY}-{000000}'];

    public static function get(string $k, mixed $d = ''): mixed
    {
        return Setting::getValue('wl.invoice.'.$k, $d);
    }

    public static function put(string $k, mixed $v): void
    {
        Setting::setValue('wl.invoice.'.$k, $v, 'white_label');
    }

    public static function sellerName(): string
    {
        return trim((string) self::get('seller_name', ''));
    }

    public static function paymentTermsDays(): int
    {
        return max(1, min(120, (int) self::get('payment_terms_days', 14)));
    }

    public static function autoSend(): bool
    {
        return (bool) self::get('auto_send', true);
    }

    public static function format(string $series): string
    {
        $f = trim((string) self::get('format_'.$series, ''));

        return $f !== '' && str_contains($f, '{0') ? $f : (self::DEFAULT_FORMATS[$series] ?? 'DOC-{YYYY}-{000000}');
    }

    /** @return array{name:string,address:string,tax_id:string,registration:string,email:string,terms:string,notes:string} */
    public static function seller(): array
    {
        return [
            'name' => self::sellerName() ?: (string) config('app.name'), 'address' => trim((string) self::get('seller_address', '')), 'tax_id' => trim((string) self::get('seller_tax_id', '')),
            'registration' => trim((string) self::get('seller_registration', '')), 'email' => trim((string) self::get('seller_email', '')),
            'terms' => trim((string) self::get('terms', '')), 'notes' => trim((string) self::get('notes', '')),
        ];
    }

    /** The tax label and percent applied to a new invoice's lines (default none). @return array{label:string,rate:float} */
    public static function tax(): array
    {
        return ['label' => trim((string) self::get('tax_label', 'Tax')) ?: 'Tax', 'rate' => max(0.0, min(100.0, (float) self::get('tax_rate', 0)))];
    }
}
