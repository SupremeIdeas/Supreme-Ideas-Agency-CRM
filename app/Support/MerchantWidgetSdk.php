<?php

namespace App\Support;

/**
 * Embed-snippet and Subresource-Integrity helper for the Naara Widgets SDK (MASTER-ONLY). The snippet a merchant pastes is generated
 * here, once, so the dashboard, the admin page and the WordPress plugin can never drift apart.
 */
class MerchantWidgetSdk
{
    public const PATH = 'widgets/merchant-widget.js';

    public static function url(): string
    {
        return rtrim((string) config('app.url'), '/').'/'.self::PATH;
    }

    /** sha384 of the shipped file, base64 — pin it with `integrity=` to refuse a tampered copy. Null if the file is missing. */
    public static function integrity(): ?string
    {
        $file = public_path(self::PATH);

        return is_file($file) ? 'sha384-'.base64_encode(hash_file('sha384', $file, true)) : null;
    }

    /** The copy-paste embed for one key. Only the PUBLIC key is ever placed in a page. */
    public static function snippet(string $publicKey, string $kind = '', string $mode = 'modal', bool $withIntegrity = false): string
    {
        $attrs = ' src="'.e(self::url()).'" data-naara-key="'.e($publicKey).'"';
        if (in_array($kind, ['esim', 'number', 'giftcard'], true)) {
            $attrs .= ' data-product-type="'.$kind.'"';
        }
        if ($mode === 'redirect') {
            $attrs .= ' data-mode="redirect"';
        }
        if ($withIntegrity && ($sri = self::integrity()) !== null) {
            $attrs .= ' integrity="'.$sri.'" crossorigin="anonymous"';
        }

        return '<div id="naara-widget"></div>'."\n".'<script'.$attrs.' defer></script>';
    }
}
