<?php

namespace App\Services\WhiteLabel\Funnel;

use Illuminate\Support\Facades\DB;

/** Gapless, concurrency-safe document numbers per series and year (MASTER-ONLY). Call inside the issuing transaction: the sequence row is locked until it commits, so a rollback never burns a number. */
final class WlInvoiceNumbering
{
    public static function next(string $series): string
    {
        $year = (int) now()->format('Y');
        DB::table('wl_invoice_sequences')->insertOrIgnore(['series' => $series, 'year' => $year, 'last_number' => 0]);
        $row = DB::table('wl_invoice_sequences')->where(['series' => $series, 'year' => $year])->lockForUpdate()->first();
        $n = (int) $row->last_number + 1;
        DB::table('wl_invoice_sequences')->where('id', $row->id)->update(['last_number' => $n]);

        return self::render(WlInvoiceSettings::format($series), $year, $n);
    }

    public static function render(string $format, int $year, int $n): string
    {
        $out = str_replace('{YYYY}', (string) $year, $format);

        return (string) preg_replace_callback('/\{(0+)\}/', fn ($m) => str_pad((string) $n, strlen($m[1]), '0', STR_PAD_LEFT), $out);
    }
}
