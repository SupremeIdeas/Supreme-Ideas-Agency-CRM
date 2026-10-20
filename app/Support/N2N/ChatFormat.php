<?php

namespace App\Support\N2N;

use Illuminate\Support\HtmlString;

/**
 * Message formatting for Naara chat (MASTER-ONLY): a small, closed markdown dialect that the composer's tools write and the bubble renders.
 *
 *   inline   **bold**  _italic_  ~strike~  `code`
 *   line     # / ## / ### heading   > quote   - bullet   1. number   [ ] / [x] task
 *   block    ```code```   and   :::red ... :::   (banner; red orange yellow blue purple pink green grey)
 *
 * Safety: every piece of user text is HTML-escaped BEFORE any tag is produced, and the only tags ever emitted are the fixed ones below, so
 * there is no way to inject markup, attributes or links (a link is never rendered; LinkFilter has already refused them on send). A message
 * with no markers renders exactly as before (escaped text, line breaks kept).
 */
class ChatFormat
{
    public const BANNERS = ['red', 'orange', 'yellow', 'blue', 'purple', 'pink', 'green', 'grey'];

    /**
     * The words that count as @mentioning a person in a 1:1 chat: their Naara ID, first name and full name. A mention is presentation only (a highlighted
     * chip): it never notifies, never bypasses mute and is not stored, so it cannot be used to get around someone's mute or block.
     *
     * @return list<string>
     */
    public static function mentionTokens(?string $naaraId, ?string $name): array
    {
        $name = trim(preg_replace('/\s+/u', ' ', (string) $name) ?? '');
        $tokens = array_filter([$naaraId, $name !== '' ? $name : null, $name !== '' ? explode(' ', $name)[0] : null], fn ($t) => $t !== null && $t !== '' && mb_strlen($t) >= 2);

        return array_values(array_unique($tokens));
    }

    /** @param  list<string>  $mentions tokens from mentionTokens(); longest first so "Ada Nwosu" wins over "Ada" */
    public static function render(string $body, array $mentions = []): HtmlString
    {
        $html = self::renderBody($body);
        if ($mentions === [] || ! str_contains($body, '@')) {
            return $html;
        }
        usort($mentions, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));
        $alt = implode('|', array_map(fn ($t) => preg_quote(e($t), '/'), $mentions));
        $out = preg_replace_callback('/(?<![\p{L}\p{N}_@])@('.$alt.')(?![\p{L}\p{N}_])/iu', fn ($m) => '<span class="ncf-mention">@'.$m[1].'</span>', (string) $html);

        return new HtmlString($out ?? (string) $html);
    }

    private static function renderBody(string $body): HtmlString
    {
        if (! preg_match('/[*_~`#>\-\[:]|^\d+\./m', $body)) {
            return new HtmlString('<span class="whitespace-pre-wrap">'.e($body).'</span>');
        }
        $out = '';
        $para = [];
        $flush = function () use (&$out, &$para) {
            if ($para !== []) {
                $out .= '<span class="ncf-p whitespace-pre-wrap">'.implode("\n", array_map([self::class, 'inline'], $para)).'</span>';
                $para = [];
            }
        };
        $lines = preg_split('/\r\n|\r|\n/', $body) ?: [''];
        $n = count($lines);
        for ($i = 0; $i < $n; $i++) {
            $line = $lines[$i];
            // fenced code
            if (str_starts_with($line, '```')) {
                $flush();
                $code = [];
                for ($i++; $i < $n && ! str_starts_with($lines[$i], '```'); $i++) {
                    $code[] = $lines[$i];
                }
                $out .= '<pre class="ncf-code"><code>'.e(implode("\n", $code)).'</code></pre>';
                continue;
            }
            // banner
            if (preg_match('/^:::(red|orange|yellow|blue|purple|pink|green|grey)\s*$/', $line, $m)) {
                $flush();
                $inner = [];
                for ($i++; $i < $n && trim($lines[$i]) !== ':::'; $i++) {
                    $inner[] = $lines[$i];
                }
                $out .= '<span class="ncf-banner ncf-banner--'.$m[1].' whitespace-pre-wrap">'.implode("\n", array_map([self::class, 'inline'], $inner)).'</span>';
                continue;
            }
            if (preg_match('/^(#{1,3}) (.+)$/', $line, $m)) {
                $flush();
                $out .= '<span class="ncf-h ncf-h'.strlen($m[1]).'">'.self::inline($m[2]).'</span>';
            } elseif (preg_match('/^> ?(.*)$/', $line, $m)) {
                $flush();
                $out .= '<span class="ncf-quote">'.self::inline($m[1]).'</span>';
            } elseif (preg_match('/^[-*] \[( |x|X)\] (.*)$/', $line, $m) || preg_match('/^\[( |x|X)\] (.*)$/', $line, $m)) {
                $flush();
                $done = strtolower($m[1]) === 'x';
                $out .= '<span class="ncf-task'.($done ? ' is-done' : '').'"><i aria-hidden="true"></i><span>'.self::inline($m[2]).'</span></span>';
            } elseif (preg_match('/^[-*] (.+)$/', $line, $m)) {
                $flush();
                $out .= '<span class="ncf-li"><b aria-hidden="true">&bull;</b><span>'.self::inline($m[1]).'</span></span>';
            } elseif (preg_match('/^(\d{1,3})\. (.+)$/', $line, $m)) {
                $flush();
                $out .= '<span class="ncf-li"><b aria-hidden="true">'.(int) $m[1].'.</b><span>'.self::inline($m[2]).'</span></span>';
            } else {
                $para[] = $line;
            }
        }
        $flush();

        return new HtmlString($out);
    }

    /** Escape first, then turn the closed set of markers into fixed tags. */
    public static function inline(string $text): string
    {
        $t = e($text);
        $t = preg_replace_callback('/`([^`\n]+)`/u', fn ($m) => '<code class="ncf-ic">'.$m[1].'</code>', $t) ?? $t;
        // the rest must not touch the inside of inline code
        $parts = preg_split('/(<code class="ncf-ic">.*?<\/code>)/u', $t, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$t];
        foreach ($parts as $k => $p) {
            if (str_starts_with($p, '<code class="ncf-ic">')) {
                continue;
            }
            $p = preg_replace('/\*\*(?=\S)(.+?)(?<=\S)\*\*/u', '<strong>$1</strong>', $p) ?? $p;
            $p = preg_replace('/(?<![\p{L}\p{N}_])_(?=\S)(.+?)(?<=\S)_(?![\p{L}\p{N}_])/u', '<em>$1</em>', $p) ?? $p;
            $p = preg_replace('/(?<![\p{L}\p{N}~])~(?=\S)(.+?)(?<=\S)~(?![\p{L}\p{N}~])/u', '<s>$1</s>', $p) ?? $p;
            $parts[$k] = $p;
        }

        return implode('', $parts);
    }

    /** The message with its markers removed, for one-line previews (reply quotes, the pinned bar, list rows). */
    public static function plain(string $body): string
    {
        $t = preg_replace('/^```.*$/m', '', $body) ?? $body;
        $t = preg_replace('/^:::\w*\s*$/m', '', $t) ?? $t;
        $t = preg_replace('/^(#{1,3} |> ?|[-*] \[[ xX]\] |\[[ xX]\] |[-*] |\d{1,3}\. )/m', '', $t) ?? $t;
        $t = preg_replace('/\*\*(.+?)\*\*|`(.+?)`|(?<![\p{L}\p{N}_])_(.+?)_(?![\p{L}\p{N}_])|(?<![\p{L}\p{N}~])~(.+?)~(?![\p{L}\p{N}~])/u', '$1$2$3$4', $t) ?? $t;

        return trim(preg_replace('/\s+/', ' ', $t) ?? $t);
    }
}
