<?php

namespace App\Services\Omni\WordPress;

use RuntimeException;
use ZipArchive;

/**
 * Builds the `naara-respond-chat` WordPress plugin zip on demand (MASTER-ONLY). The source lives in resources/omni/wordpress/naara-respond-chat; the only generated file is
 * `defaults.php`, which bakes this install's address and the chat's PUBLIC site key into the download so setup is paste-free. No secret is ever written into the zip.
 */
class WebChatPluginPackager
{
    public const SOURCE = 'resources/omni/wordpress/naara-respond-chat';

    public static function version(): string
    {
        $head = (string) file_get_contents(base_path(self::SOURCE.'/naara-respond-chat.php'));

        return preg_match('/^\s*\*\s*Version:\s*([0-9.]+)/m', $head, $m) === 1 ? $m[1] : '0.0.0';
    }

    /** What each file in the download is for, in plain words (shown to the person before they download). */
    public const FILES = [
        'naara-respond-chat.php' => 'The plugin itself: a settings page and the one line that loads your chat bubble.',
        'defaults.php' => 'Your Naara address and this chat\'s public site key, so setup is paste-free. Contains no password or secret.',
        'assets/chat-flow.svg' => 'The picture on the settings page.',
        'readme.txt' => 'The WordPress plugin description and install steps.',
        'uninstall.php' => 'Removes the plugin\'s one saved setting when you delete the plugin.',
    ];

    /** @return array{name:string,version:string,files:array<string,string>,kb:int,fingerprint:string,requires:array{wp:string,php:string}} everything shown on the download banner */
    public static function info(): array
    {
        $root = base_path(self::SOURCE);
        $hash = hash_init('sha256');
        $bytes = 0;
        $paths = [];
        if (is_dir($root)) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)) as $f) {
                if ($f->isFile()) {
                    $paths[] = $f->getPathname();
                }
            }
        }
        sort($paths);
        foreach ($paths as $path) {
            hash_update($hash, str_replace('\\', '/', substr($path, strlen($root) + 1))."\n");
            hash_update($hash, (string) file_get_contents($path));
            $bytes += (int) filesize($path);
        }

        return ['name' => 'Naara Respond Chat', 'version' => self::version(), 'files' => self::FILES, 'kb' => max(1, (int) ceil(($bytes + 200) / 1024)), 'fingerprint' => hash_final($hash), 'requires' => ['wp' => '5.8', 'php' => '7.4']];
    }

    /** @return string path to a temporary zip the caller must delete after sending */
    public function build(string $siteKey): string
    {
        $root = base_path(self::SOURCE);
        if (! is_dir($root) || ! class_exists(ZipArchive::class)) {
            throw new RuntimeException('The WordPress plugin cannot be packaged on this server.');
        }
        $tmp = tempnam(sys_get_temp_dir(), 'nrc');
        $zip = new ZipArchive;
        if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create the plugin archive.');
        }
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->isFile()) {
                $zip->addFile($file->getPathname(), 'naara-respond-chat/'.str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1)));
            }
        }
        $defaults = ['host' => rtrim((string) config('app.url'), '/'), 'site' => preg_match('/^[0-9a-f-]{36}$/', $siteKey) === 1 ? $siteKey : ''];
        $zip->addFromString('naara-respond-chat/defaults.php', "<?php\nif (! defined('ABSPATH')) { exit; }\nreturn ".var_export($defaults, true).";\n");
        $zip->close();

        return $tmp;
    }
}
