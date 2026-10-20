<?php

namespace App\Services\Inbox;

/**
 * Turns the composer's closed markdown dialect (**bold** _italic_ ~strike~ `code`, # headings, > quotes, - / 1. / [ ] lists, ``` blocks, ::: banners) into
 * what each channel understands (MASTER-ONLY, Business Suite inbox):
 *   - whatsapp:  its own markers (*bold* _italic_ ~strike~ `code` ```block``` > quote), plain text for everything else;
 *   - telegram:  parse_mode HTML with a fixed set of tags; every piece of user text is escaped BEFORE a tag is produced, so markup cannot be injected;
 *   - instagram: plain text (Instagram messaging has no formatting), the markers are simply removed.
 * A message with no markers goes through unchanged (apart from HTML escaping for Telegram).
 */
class ChannelFormat
{
    public const LIMITS = ['whatsapp' => 4096, 'telegram' => 4096, 'instagram' => 1000];

    /** Caption limits where a channel has them (Instagram has no captions: the text is sent as its own message). */
    public const CAPTION_LIMITS = ['whatsapp' => 1024, 'telegram' => 1024];

    /** @return array{text: string, parse_mode: ?string} */
    public static function convert(string $text, string $channel): array
    {
        $text = trim(str_replace(["\r\n", "\r"], "\n", $text));
        $html = $channel === 'telegram';
        $lines = explode("\n", $text);
        $out = [];
        $quote = [];
        $flushQuote = function () use (&$out, &$quote, $html, $channel) {
            if ($quote !== []) {
                $body = implode("\n", array_map(fn ($l) => self::inline($l, $channel), $quote));
                $out[] = $html ? '<blockquote>'.$body.'</blockquote>' : ($channel === 'whatsapp' ? implode("\n", array_map(fn ($l) => '> '.self::inline($l, $channel), $quote)) : $body);
                $quote = [];
            }
        };

        for ($i = 0, $n = count($lines); $i < $n; $i++) {
            $line = $lines[$i];

            if (str_starts_with($line, '```')) {
                $flushQuote();
                $code = [];
                for ($i++; $i < $n && ! str_starts_with($lines[$i], '```'); $i++) {
                    $code[] = $lines[$i];
                }
                $block = implode("\n", $code);
                $out[] = $html ? '<pre>'.self::esc($block).'</pre>' : ($channel === 'whatsapp' ? "```{$block}```" : $block);

                continue;
            }
            if (preg_match('/^:::(red|orange|yellow|blue|purple|pink|green|grey)\s*$/', $line)) {
                $flushQuote();   // a banner is presentation only: its lines are sent as ordinary lines
                continue;
            }
            if (trim($line) === ':::') {
                continue;
            }
            if (preg_match('/^>\s?(.*)$/', $line, $m)) {
                $quote[] = $m[1];

                continue;
            }
            $flushQuote();

            if (preg_match('/^#{1,3}\s+(.*)$/', $line, $m)) {
                $inner = self::inline($m[1], $channel);
                $out[] = $html ? "<b>{$inner}</b>" : ($channel === 'whatsapp' ? "*{$inner}*" : $inner);
            } elseif (preg_match('/^(\s*)[-*]\s+\[([ xX])\]\s+(.*)$/', $line, $m) || preg_match('/^(\s*)\[([ xX])\]\s+(.*)$/', $line, $m)) {
                $out[] = $m[1].'['.(trim($m[2]) === '' ? ' ' : 'x').'] '.self::inline($m[3], $channel);
            } elseif (preg_match('/^(\s*)[-*]\s+(.*)$/', $line, $m)) {
                $out[] = $m[1].($html ? '• ' : '- ').self::inline($m[2], $channel);
            } else {
                $out[] = self::inline($line, $channel);
            }
        }
        $flushQuote();

        $result = trim(implode("\n", $out));

        return ['text' => mb_substr($result, 0, self::LIMITS[$channel] ?? 4096), 'parse_mode' => $html ? 'HTML' : null];
    }

    private static function esc(string $s): string
    {
        return htmlspecialchars($s, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** Inline markers for one line. Inline code is lifted out first so nothing inside it is touched. */
    private static function inline(string $line, string $channel): string
    {
        $html = $channel === 'telegram';
        $codes = [];
        $line = preg_replace_callback('/`([^`\n]+)`/u', function ($m) use (&$codes, $html, $channel) {
            $codes[] = $html ? '<code>'.self::esc($m[1]).'</code>' : ($channel === 'whatsapp' ? '`'.$m[1].'`' : $m[1]);

            return "\u{E000}".(count($codes) - 1)."\u{E001}";
        }, $line) ?? $line;

        if ($html) {
            $line = self::esc($line);
        }

        $tag = fn (string $open, string $close) => fn ($m) => $open.$m[1].$close;
        if ($channel === 'telegram') {
            $line = preg_replace_callback('/\*\*(.+?)\*\*/u', $tag('<b>', '</b>'), $line) ?? $line;
            $line = preg_replace_callback('/(?<![\p{L}\p{N}_])_(.+?)_(?![\p{L}\p{N}_])/u', $tag('<i>', '</i>'), $line) ?? $line;
            $line = preg_replace_callback('/(?<![\p{L}\p{N}~])~(.+?)~(?![\p{L}\p{N}~])/u', $tag('<s>', '</s>'), $line) ?? $line;
        } elseif ($channel === 'whatsapp') {
            $line = preg_replace('/\*\*(.+?)\*\*/u', '*$1*', $line) ?? $line;
        } else {
            $line = preg_replace('/\*\*(.+?)\*\*/u', '$1', $line) ?? $line;
            $line = preg_replace('/(?<![\p{L}\p{N}_])_(.+?)_(?![\p{L}\p{N}_])/u', '$1', $line) ?? $line;
            $line = preg_replace('/(?<![\p{L}\p{N}~])~(.+?)~(?![\p{L}\p{N}~])/u', '$1', $line) ?? $line;
        }

        return preg_replace_callback("/\u{E000}(\d+)\u{E001}/u", fn ($m) => $codes[(int) $m[1]] ?? '', $line) ?? $line;
    }

    /** Plain-text version of any dialect text (used for previews and for channels where a caption is not possible). */
    public static function plain(string $text): string
    {
        return ChannelFormat::convert($text, 'instagram')['text'];
    }
}
