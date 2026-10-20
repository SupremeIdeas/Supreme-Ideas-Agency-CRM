<?php

namespace App\Services\Omni\Kb;

/** What the reader found in a document, shown to the owner as proof of what was understood (MASTER-ONLY): contacts, prices, hours, headings and the questions it answers. Deterministic, no AI. */
class DocumentFacts
{
    /** @return array{emails:list<string>,phones:list<string>,links:list<string>,prices:list<string>,hours:list<string>,headings:list<string>,questions:list<string>,words:int,paragraphs:int} */
    public static function extract(string $text): array
    {
        $grab = function (string $re, int $limit = 8) use ($text): array {
            preg_match_all($re, $text, $m);

            return array_slice(array_values(array_unique(array_map('trim', $m[0]))), 0, $limit);
        };
        $lines = array_values(array_filter(array_map('trim', explode("\n", $text)), fn ($l) => $l !== ''));
        $headings = [];
        $questions = [];
        foreach ($lines as $l) {
            if (preg_match('/^#{1,6}\s+(.{2,100})$/', $l, $m)) {
                $headings[] = $m[1];
            } elseif (preg_match('/^(?:Q[:.)]\s*)?(.{8,140}\?)\s*$/u', $l, $m)) {
                $questions[] = rtrim($m[1]);
            }
        }

        return [
            'emails' => $grab('/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/'),
            'phones' => $grab('/(?<![\d])\+?\d[\d\s().-]{8,16}\d(?![\d])/'),
            'links' => $grab('#https?://[^\s<>"\')]+#i'),
            'prices' => $grab('/(?:[$€£₦]|NGN|USD|GBP|EUR|KES|GHS|ZAR)\s?\d[\d,]*(?:\.\d{1,2})?|\d[\d,]*(?:\.\d{1,2})?\s?(?:USD|NGN|GBP|EUR|KES|GHS|ZAR)\b/u'),
            'hours' => $grab('/\b(?:mon|tue|wed|thu|fri|sat|sun)[a-z]*\.?(?:\s*(?:to|-|–)\s*(?:mon|tue|wed|thu|fri|sat|sun)[a-z]*\.?)?[^.\n]{0,40}\d{1,2}(?::\d{2})?\s?(?:am|pm)?\s*(?:to|-|–)\s*\d{1,2}(?::\d{2})?\s?(?:am|pm)?/i', 5),
            'headings' => array_slice(array_values(array_unique($headings)), 0, 10),
            'questions' => array_slice(array_values(array_unique($questions)), 0, 10),
            'words' => (int) preg_match_all('/[\p{L}\p{N}][\p{L}\p{N}\'-]*/u', $text),
            'paragraphs' => count($lines),
        ];
    }
}
