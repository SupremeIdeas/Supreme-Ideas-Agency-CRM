<?php

namespace App\Support\MasterMarketing;

/**
 * MASTER-ONLY. A small, dependency-free syntax highlighter for the developer docs. It returns escaped HTML with `<span class="dv-t-*">` tokens that the page colours
 * with the three brand colours (Deep Teal, Warm Gold, Midnight Navy). Every character of the input is HTML-escaped exactly once, so code can never inject markup.
 *
 * Token classes: com (comment), str (string), key (JSON key), num, kw (keyword), fn (call), var (variable), flag (CLI flag), url, op.
 */
final class DeveloperCodeHighlighter
{
    private const STR_DQ = '"(?:[^"\\\\]|\\\\.)*"';

    private const STR_SQ = "'(?:[^'\\\\]|\\\\.)*'";

    private const STR_BT = '`(?:[^`\\\\]|\\\\.)*`';

    private const NUM = '-?\b\d+(?:\.\d+)?\b';

    private const FN = '\b[A-Za-z_][A-Za-z0-9_]*(?=\()';

    public static function highlight(string $code, string $lang): string
    {
        $lang = strtolower($lang);
        $rules = self::rules($lang);
        $pattern = '~'.implode('|', array_map(fn ($cls, $re) => '(?<'.$cls.'>'.$re.')', array_keys($rules), $rules)).'~us';

        $out = '';
        $pos = 0;
        if (preg_match_all($pattern, $code, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach ($m as $hit) {
                foreach (array_keys($rules) as $cls) {
                    if (isset($hit[$cls]) && $hit[$cls][1] >= 0 && $hit[$cls][0] !== '') {
                        [$text, $offset] = $hit[$cls];
                        $out .= self::e(substr($code, $pos, $offset - $pos)).'<span class="dv-t-'.$cls.'">'.self::e($text).'</span>';
                        $pos = $offset + strlen($text);
                        break;
                    }
                }
            }
        }

        return $out.self::e(substr($code, $pos));
    }

    /** @return array<string,string> class => regex, in priority order */
    private static function rules(string $lang): array
    {
        return match ($lang) {
            'json' => [
                'key' => self::STR_DQ.'(?=\s*:)',
                'str' => self::STR_DQ,
                'kw' => '\b(?:true|false|null)\b',
                'num' => self::NUM,
            ],
            'bash', 'shell', 'sh' => [
                'com' => '(?<![\w$])#[^\n]*',
                'str' => self::STR_DQ.'|'.self::STR_SQ,
                'var' => '\$\{[^}]+\}|\$[A-Za-z_][A-Za-z0-9_]*',
                'url' => 'https?://[^\s\'"\\\\]+',
                'flag' => '(?<![\w-])--?[A-Za-z][\w-]*',
                'kw' => '\b(?:curl|export|echo)\b',
                'num' => self::NUM,
            ],
            'php' => [
                'com' => '//[^\n]*|/\*.*?\*/',
                'str' => self::STR_DQ.'|'.self::STR_SQ,
                'var' => '\$[A-Za-z_][A-Za-z0-9_]*',
                'kw' => '<\?php|\b(?:use|new|echo|require|function|return|if|else|foreach|as|true|false|null|array)\b',
                'num' => self::NUM,
                'fn' => self::FN,
            ],
            'js', 'javascript', 'node' => [
                'com' => '//[^\n]*|/\*.*?\*/',
                'str' => self::STR_DQ.'|'.self::STR_SQ.'|'.self::STR_BT,
                'kw' => '\b(?:const|let|var|await|async|function|return|if|else|new|import|from|true|false|null|undefined)\b',
                'num' => self::NUM,
                'fn' => self::FN,
            ],
            'python', 'py' => [
                'com' => '#[^\n]*',
                'str' => '[fF]?'.self::STR_DQ.'|[fF]?'.self::STR_SQ,
                'kw' => '\b(?:import|from|as|def|return|if|else|for|in|print|True|False|None)\b',
                'num' => self::NUM,
                'fn' => self::FN,
            ],
            'html' => [
                'com' => '<!--.*?-->',
                'str' => self::STR_DQ.'|'.self::STR_SQ,
                'kw' => '</?[A-Za-z][A-Za-z0-9-]*',
                'key' => '\b[a-z][a-z0-9-]*(?==)',
            ],
            default => [],
        };
    }

    private static function e(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
