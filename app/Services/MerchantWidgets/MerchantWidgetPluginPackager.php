<?php

namespace App\Services\MerchantWidgets;

use RuntimeException;
use ZipArchive;

/**
 * Builds the `naara-widgets` WordPress plugin zip on demand (MASTER-ONLY, blueprint §10). The source lives in
 * resources/merchant-widget/wordpress/naara-widgets; the only thing generated here is `defaults.php`, which bakes this install's address
 * (and, optionally, the merchant's PUBLIC key) into the download so setup is paste-free. No secret is ever written into the zip.
 */
class MerchantWidgetPluginPackager
{
    public const SOURCE = 'resources/merchant-widget/wordpress/naara-widgets';

    public static function version(): string
    {
        $head = (string) file_get_contents(base_path(self::SOURCE.'/naara-widgets.php'));

        return preg_match('/^\s*\*\s*Version:\s*([0-9.]+)/m', $head, $m) === 1 ? $m[1] : '0.0.0';
    }

    /** @return string path to a temporary zip the caller must delete after sending */
    public function build(?string $publicKey = null): string
    {
        $root = base_path(self::SOURCE);
        if (! is_dir($root) || ! class_exists(ZipArchive::class)) {
            throw new RuntimeException('The WordPress plugin cannot be packaged on this server.');
        }
        $tmp = tempnam(sys_get_temp_dir(), 'nwp');
        $zip = new ZipArchive;
        if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create the plugin archive.');
        }
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if ($file->isFile()) {
                $zip->addFile($file->getPathname(), 'naara-widgets/'.str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1)));
            }
        }
        $defaults = ['host' => rtrim((string) config('app.url'), '/'), 'key' => ($publicKey !== null && preg_match('/^wk_[A-Za-z0-9_]+$/', $publicKey) === 1) ? $publicKey : ''];
        $zip->addFromString('naara-widgets/defaults.php', "<?php\nif (! defined('ABSPATH')) { exit; }\nreturn ".var_export($defaults, true).";\n");
        $zip->close();

        return $tmp;
    }
}
