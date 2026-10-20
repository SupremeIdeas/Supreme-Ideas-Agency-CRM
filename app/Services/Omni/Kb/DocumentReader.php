<?php

namespace App\Services\Omni\Kb;

/**
 * Reads an uploaded .txt, .md or .docx into clean text, or refuses it with a plain reason (MASTER-ONLY, Naara Respond). The checks, in order:
 * allowed extension; size; real file type (a .docx must be a real zip with word/document.xml, not encrypted, no macros, not a zip bomb, no XML entities; a text file
 * must be real text, not binary); enough real words; not garbled; no secrets (card numbers, API keys, private keys, "password: ...") that must never be learned.
 * Lines that try to give instructions to the AI ("ignore your rules") are removed and reported, not learned. Nothing is executed and the original file is not kept.
 */
class DocumentReader
{
    public const MAX_BYTES = 2_097_152;          // 2 MB per file

    public const MAX_CHARS = 200_000;

    public const MIN_WORDS = 25;

    private const MAX_ZIP_ENTRIES = 500;

    private const MAX_UNZIPPED = 20_971_520;     // 20 MB total once unpacked: a zip bomb is refused

    private const MAX_XML = 10_485_760;

    /** @return array{text:string,type:string,notes:list<string>} */
    public function read(string $path, string $originalName): array
    {
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $this->assertAllowed($ext);
        $size = @filesize($path);
        if ($size === false || $size === 0) {
            throw new DocumentRejected('That file is empty.');
        }
        if ($size < DocumentLimits::minBytes()) {
            throw new DocumentRejected('That file is too small ('.$size.' bytes). Files must be at least '.DocumentLimits::minBytes().' bytes so there is real information to learn from. Add more detail and upload it again.');
        }
        if ($size > DocumentLimits::maxBytes()) {
            throw new DocumentRejected('That file is larger than '.(DocumentLimits::maxBytes() / 1048576).' MB. Split it into smaller files.');
        }
        $text = $ext === 'docx' ? $this->docx($path) : $this->plain($path);
        $text = $this->normalise($text);
        $notes = [];
        $this->assertNoSecrets($text);
        [$text, $removed] = $this->stripInstructions($text);
        if ($removed > 0) {
            $notes[] = $removed.' line'.($removed === 1 ? '' : 's').' that tried to give instructions to the AI '.($removed === 1 ? 'was' : 'were').' removed. A knowledge document should state facts only.';
        }
        if (mb_strlen($text) > self::MAX_CHARS) {
            $text = mb_substr($text, 0, self::MAX_CHARS);
            $notes[] = 'The file was longer than '.number_format(self::MAX_CHARS).' characters, so only the first part was learned. Split long files and upload them separately.';
        }
        $this->assertQuality($text);

        return ['text' => $text, 'type' => $ext, 'notes' => $notes];
    }

    private function assertAllowed(string $ext): void
    {
        if (in_array($ext, ['txt', 'md', 'docx'], true)) {
            return;
        }
        throw new DocumentRejected(match ($ext) {
            'doc' => 'Old Word files (.doc) cannot be read safely. Open it in Word and choose Save As, then Word Document (.docx).',
            'pdf' => 'PDF files are not supported yet. Copy the text into a .txt file, or save your document as .docx.',
            'rtf', 'odt', 'pages' => 'That format is not supported. Save it as .docx or .txt and upload again.',
            'xlsx', 'xls', 'csv' => 'Spreadsheets are not supported. Copy the rows into a .txt file, one fact per line.',
            'exe', 'js', 'php', 'sh', 'bat', 'zip', 'docm', 'xlsm' => 'That kind of file is not allowed.',
            default => 'Upload a .txt, .md or .docx file.',
        });
    }

    private function plain(string $path): string
    {
        $raw = (string) file_get_contents($path);
        if (str_contains($raw, "\0") && ! str_starts_with($raw, "\xFF\xFE") && ! str_starts_with($raw, "\xFE\xFF")) {
            throw new DocumentRejected('That looks like a binary file, not text. Save it as a plain .txt file and upload again.');
        }
        if (str_starts_with($raw, "\xFF\xFE")) {
            $raw = mb_convert_encoding(substr($raw, 2), 'UTF-8', 'UTF-16LE');
        } elseif (str_starts_with($raw, "\xFE\xFF")) {
            $raw = mb_convert_encoding(substr($raw, 2), 'UTF-8', 'UTF-16BE');
        } elseif (str_starts_with($raw, "\xEF\xBB\xBF")) {
            $raw = substr($raw, 3);
        }
        if (! mb_check_encoding($raw, 'UTF-8')) {
            $raw = mb_convert_encoding($raw, 'UTF-8', 'Windows-1252');   // an older Windows text file
        }

        return $raw;
    }

    private function docx(string $path): string
    {
        $zip = new \ZipArchive;
        if ($zip->open($path) !== true) {
            throw new DocumentRejected('That is not a real Word (.docx) file, or it is password-protected. Remove the password, or save a copy as .docx or .txt.');
        }
        try {
            if ($zip->numFiles > self::MAX_ZIP_ENTRIES) {
                throw new DocumentRejected('That Word file has too many parts to open safely.');
            }
            $total = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $st = $zip->statIndex($i);
                $name = (string) ($st['name'] ?? '');
                $total += (int) ($st['size'] ?? 0);
                if (str_contains($name, '..') || str_starts_with($name, '/')) {
                    throw new DocumentRejected('That Word file is not safe to open.');
                }
                if (stripos($name, 'vbaProject.bin') !== false) {
                    throw new DocumentRejected('That Word file contains macros, so it is refused. Save a copy without macros as a normal .docx.');
                }
            }
            if ($total > self::MAX_UNZIPPED) {
                throw new DocumentRejected('That Word file expands to an unsafe size and was refused.');
            }
            if ($zip->locateName('[Content_Types].xml') === false || $zip->locateName('word/document.xml') === false) {
                throw new DocumentRejected('That is not a real Word (.docx) file. Open it in Word and save it again as .docx.');
            }
            $st = $zip->statName('word/document.xml');
            if (($st['size'] ?? 0) > self::MAX_XML) {
                throw new DocumentRejected('That Word file is too large to read. Split it into smaller documents.');
            }
            $xml = (string) $zip->getFromName('word/document.xml');
        } finally {
            $zip->close();
        }
        if (preg_match('/<!DOCTYPE|<!ENTITY/i', $xml)) {
            throw new DocumentRejected('That Word file contains instructions that are not allowed and was refused.');
        }
        $prev = libxml_use_internal_errors(true);
        $dom = new \DOMDocument;
        $ok = $dom->loadXML($xml, LIBXML_NONET | LIBXML_NOBLANKS | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        if (! $ok) {
            throw new DocumentRejected('That Word file is damaged and could not be read. Open it in Word and save it again.');
        }

        return $this->docxText($dom);
    }

    private function docxText(\DOMDocument $dom): string
    {
        $out = [];
        $xp = new \DOMXPath($dom);
        $xp->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        foreach ($xp->query('//w:body/*') ?: [] as $node) {
            if ($node->localName === 'p') {
                $out[] = $this->paragraph($xp, $node);
            } elseif ($node->localName === 'tbl') {
                foreach ($xp->query('.//w:tr', $node) ?: [] as $row) {
                    $cells = [];
                    foreach ($xp->query('./w:tc', $row) ?: [] as $cell) {
                        $cells[] = trim(implode(' ', array_map(fn ($p) => $this->paragraph($xp, $p), iterator_to_array($xp->query('.//w:p', $cell) ?: []))));
                    }
                    $out[] = implode(' | ', array_filter($cells, fn ($c) => $c !== ''));
                }
            }
        }

        return implode("\n", $out);
    }

    private function paragraph(\DOMXPath $xp, \DOMNode $p): string
    {
        $t = '';
        foreach ($xp->query('.//w:t | .//w:tab | .//w:br', $p) ?: [] as $n) {
            $t .= $n->localName === 't' ? $n->textContent : ($n->localName === 'tab' ? "\t" : "\n");
        }
        $style = $xp->query('./w:pPr/w:pStyle/@w:val', $p)->item(0)?->nodeValue ?? '';
        if (preg_match('/^heading\s*([1-6])$/i', $style, $m) && trim($t) !== '') {
            $t = str_repeat('#', (int) $m[1]).' '.trim($t);
        }

        return $t;
    }

    private function normalise(string $t): string
    {
        $t = str_replace(["\r\n", "\r", "\u{2028}", "\u{2029}"], "\n", $t);
        $t = preg_replace('/[^\P{C}\n\t]/u', '', $t) ?? $t;          // control characters out, newlines and tabs kept
        $t = preg_replace("/[ \t]+/", ' ', $t) ?? $t;
        $t = preg_replace("/\n{3,}/", "\n\n", $t) ?? $t;

        return trim($t);
    }

    private function assertNoSecrets(string $t): void
    {
        $patterns = [
            '/-----BEGIN (?:RSA |EC |OPENSSH |DSA )?PRIVATE KEY-----/' => 'a private key',
            '/\bsk-ant-[A-Za-z0-9_-]{20,}/' => 'an API key',
            '/\bAKIA[0-9A-Z]{16}\b/' => 'a cloud access key',
            '/\bEAA[A-Za-z0-9]{100,}/' => 'a Meta access token',
            '/\b\d{6,12}:[A-Za-z0-9_-]{35}\b/' => 'a Telegram bot token',
            '/\b(?:password|passwd|pwd|passcode)\s*(?:is|:|=)\s*\S{6,}/i' => 'a password',
            '/\b(?:cvv|cvc|pin)\s*(?:is|:|=)\s*\d{3,6}\b/i' => 'a card security code or PIN',
        ];
        foreach ($patterns as $re => $what) {
            if (preg_match($re, $t)) {
                throw new DocumentRejected("This document seems to contain {$what}. It was NOT learned. Remove it from the file and upload again: customers could be shown anything the assistant learns.");
            }
        }
        if (preg_match_all('/\b(?:\d[ -]?){13,19}\b/', $t, $m)) {
            foreach ($m[0] as $cand) {
                $digits = preg_replace('/\D/', '', $cand);
                if (strlen($digits) >= 13 && strlen($digits) <= 19 && $this->luhn($digits)) {
                    throw new DocumentRejected('This document seems to contain a payment card number. It was NOT learned. Remove it from the file and upload again.');
                }
            }
        }
    }

    private function luhn(string $d): bool
    {
        $sum = 0;
        $alt = false;
        for ($i = strlen($d) - 1; $i >= 0; $i--) {
            $n = (int) $d[$i];
            if ($alt) {
                $n *= 2;
                $n = $n > 9 ? $n - 9 : $n;
            }
            $sum += $n;
            $alt = ! $alt;
        }

        return $sum % 10 === 0;
    }

    /** @return array{0:string,1:int} text without instruction-style lines, and how many were removed */
    private function stripInstructions(string $t): array
    {
        $re = '/(ignore|disregard|forget)\s+(all\s+|your\s+|the\s+|any\s+)?(previous|prior|above|earlier)?\s*(instructions|rules|prompt)|system\s+prompt|you\s+are\s+now\s+|act\s+as\s+(a|an)\s+|reveal\s+(your|the)\s+(prompt|instructions)|developer\s+mode|do\s+not\s+follow\s+(your|the)\s+(rules|instructions)/i';
        $kept = [];
        $removed = 0;
        foreach (explode("\n", $t) as $line) {
            if (preg_match($re, $line)) {
                $removed++;

                continue;
            }
            $kept[] = $line;
        }

        return [trim(implode("\n", $kept)), $removed];
    }

    private function assertQuality(string $t): void
    {
        $words = preg_match_all('/[\p{L}\p{N}][\p{L}\p{N}\'-]*/u', $t);
        if ($words < DocumentLimits::minWords()) {
            throw new DocumentRejected('There is too little text to learn from ('.$words.' words). Add at least '.DocumentLimits::minWords().' words of real information.');
        }
        $nonSpace = preg_replace('/\s+/u', '', $t) ?? '';
        $good = preg_match_all('/[\p{L}\p{N}.,;:!?\'"()%$€£₦\/&@#+-]/u', $nonSpace);
        $len = mb_strlen($nonSpace);
        if ($len > 0 && $good / $len < 0.9) {
            throw new DocumentRejected('The text looks garbled (many unreadable symbols). Open the file, check it reads normally, and save it again as UTF-8 text or .docx.');
        }
        $letters = preg_match_all('/\p{L}/u', $nonSpace);
        if ($len > 0 && $letters / $len < 0.35) {
            throw new DocumentRejected('This is mostly numbers or symbols. Add sentences that explain the information in words so the assistant can answer in words.');
        }
    }
}
