<?php

namespace App\Services\N2N\Media;

/**
 * Real file-type detection (N2N, MASTER-ONLY): the first bytes and finfo decide, never the file name or the client's Content-Type. A
 * `.exe` renamed `.jpg` has no JPEG signature and is refused. SVG, scripts and containers we cannot identify are refused by default.
 */
class MediaSniffer
{
    /** @return array{kind: string, mime: string, ext: string}|null */
    public static function sniff(string $head, ?string $hintKind = null): ?array
    {
        $len = strlen($head);
        if ($len < 12) {
            return null;
        }
        $d = fn (string $mime, string $kind, string $ext) => ['kind' => $kind, 'mime' => $mime, 'ext' => $ext];
        if (str_starts_with($head, "\xFF\xD8\xFF")) {
            return $d('image/jpeg', 'image', 'jpg');
        }
        if (str_starts_with($head, 'GIF87a') || str_starts_with($head, 'GIF89a')) {
            return $d('image/gif', 'image', 'gif');
        }
        if (str_starts_with($head, "\x89PNG\r\n\x1A\n")) {
            return $d('image/png', 'image', 'png');
        }
        if (substr($head, 0, 4) === 'RIFF' && substr($head, 8, 4) === 'WEBP') {
            return $d('image/webp', 'image', 'webp');
        }
        if (substr($head, 0, 4) === 'RIFF' && substr($head, 8, 4) === 'WAVE') {
            return $d('audio/wav', 'voice', 'wav');
        }
        if (substr($head, 0, 4) === 'OggS') {
            return $d('audio/ogg', 'voice', 'ogg');
        }
        if (str_starts_with($head, "\x1A\x45\xDF\xA3")) {                       // EBML: webm. Audio-only voice notes and video both use it
            return $hintKind === 'voice' ? $d('audio/webm', 'voice', 'webm') : $d('video/webm', 'video', 'webm');
        }
        if (substr($head, 4, 4) === 'ftyp') {                                   // ISO base media: mp4 / m4a / mov
            $brand = substr($head, 8, 4);
            if (in_array($brand, ['M4A ', 'M4B ', 'M4P '], true) || $hintKind === 'voice') {
                return $d('audio/mp4', 'voice', 'm4a');
            }

            return $brand === 'qt  ' ? $d('video/quicktime', 'video', 'mov') : $d('video/mp4', 'video', 'mp4');
        }
        if (str_starts_with($head, 'ID3') || (ord($head[0]) === 0xFF && (ord($head[1]) & 0xE0) === 0xE0)) {
            return $d('audio/mpeg', 'voice', 'mp3');
        }

        return null;
    }

    /**
     * Documents (N2N, MASTER-ONLY): PDF, plain text and macro-free Office files (docx, xlsx, pptx). Everything else is refused: legacy .doc/.xls (macros), anything
     * with a VBA project, archives that do not look like Office packages, scripts and markup posing as text. The bytes decide; the file name is never trusted.
     *
     * @return array{kind: string, mime: string, ext: string}|null
     */
    public static function sniffDocument(string $raw): ?array
    {
        if (strlen($raw) < 12) {
            return null;
        }
        $d = fn (string $mime, string $ext) => ['kind' => 'doc', 'mime' => $mime, 'ext' => $ext];
        if (str_starts_with($raw, '%PDF-')) {
            return $d('application/pdf', 'pdf');
        }
        if (str_starts_with($raw, "PK\x03\x04")) {
            return self::office($raw);
        }
        $sample = substr($raw, 0, 8192);
        if (! str_contains($sample, "\0") && mb_check_encoding($sample, 'UTF-8') && preg_match('/[\x00-\x08\x0E-\x1F]/', $sample) === 0
            && preg_match('/^(<\?|<!doctype|<html|<svg|<script|<xml|#!)/i', ltrim($sample)) !== 1) {
            return $d('text/plain', 'txt');
        }

        return null;
    }

    private const OFFICE = [
        'word/document.xml' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'docx'],
        'xl/workbook.xml' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'xlsx'],
        'ppt/presentation.xml' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'pptx'],
    ];

    private static function office(string $raw): ?array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'n2nd');
        file_put_contents($tmp, $raw);
        $zip = new \ZipArchive;
        $opened = false;
        try {
            $opened = $zip->open($tmp) === true;
            if (! $opened || $zip->numFiles < 3 || $zip->numFiles > 5000 || $zip->locateName('[Content_Types].xml') === false) {
                return null;
            }
            $found = null;
            $size = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $st = $zip->statIndex($i);
                $name = (string) ($st['name'] ?? '');
                $size += (int) ($st['size'] ?? 0);
                if (str_contains($name, '..') || str_starts_with($name, '/') || preg_match('~vbaProject\.bin$~i', $name) || preg_match('~\.(exe|dll|js|vbs|bat|cmd|ps1|sh)$~i', $name)) {
                    return null;   // path tricks, VBA macros, or executables tucked inside the package
                }
                $found ??= self::OFFICE[$name] ?? null;
            }
            $types = (string) $zip->getFromName('[Content_Types].xml');
            if ($found === null || $size > 200 * 1048576 || stripos($types, 'macroEnabled') !== false || stripos($types, 'vbaProject') !== false) {
                return null;
            }

            return ['kind' => 'doc', 'mime' => $found[0], 'ext' => $found[1]];
        } finally {
            if ($opened) {
                $zip->close();
            }
            @unlink($tmp);
        }
    }
}
