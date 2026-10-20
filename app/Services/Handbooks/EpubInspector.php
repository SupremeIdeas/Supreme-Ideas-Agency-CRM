<?php

namespace App\Services\Handbooks;

use DOMDocument;
use DOMXPath;
use ZipArchive;

/**
 * Structural EPUB validation in plain PHP (MASTER-ONLY). epubcheck needs Java, which shared cPanel hosting does not have, so this checks what matters for the store and the
 * reader: a real zip with the right mimetype entry, a container and package document, a spine whose items exist, a navigation (EPUB 3 nav or EPUB 2 NCX) that becomes the
 * table of contents, no DRM encryption, sane size and entry counts (a zip-bomb guard), and the Naara imprint in the front matter. The file is untrusted: it is only ever read,
 * never extracted to disk, and every path read from it is checked.
 */
class EpubInspector
{
    private const MAX_ENTRIES = 6000;

    private const MAX_UNCOMPRESSED = 600 * 1024 * 1024;

    /** @return array{ok:bool, errors:list<string>, warnings:list<string>, title:?string, version:?string, toc:list<array{title:string,href:string,depth:int}>, spine:list<string>, pages:int, has_imprint:bool, scripts:bool} */
    public function inspect(string $path): array
    {
        $r = ['ok' => false, 'errors' => [], 'warnings' => [], 'title' => null, 'version' => null, 'toc' => [], 'spine' => [], 'pages' => 0, 'has_imprint' => false, 'scripts' => false];
        $max = (int) config('handbooks.max_epub_mb', 80) * 1048576;
        if (! is_file($path) || filesize($path) === 0) {
            return $this->fail($r, 'The file is empty or missing.');
        }
        if (filesize($path) > $max) {
            return $this->fail($r, 'The EPUB is larger than '.(int) config('handbooks.max_epub_mb', 80).' MB.');
        }
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            return $this->fail($r, 'This is not a valid EPUB (it is not a readable zip file).');
        }
        try {
            if ($zip->numFiles < 3 || $zip->numFiles > self::MAX_ENTRIES) {
                return $this->fail($r, 'This is not a valid EPUB (unexpected number of files inside).');
            }
            $total = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $st = $zip->statIndex($i);
                $total += (int) ($st['size'] ?? 0);
                if (str_contains((string) $st['name'], '..') || str_starts_with((string) $st['name'], '/')) {
                    return $this->fail($r, 'The EPUB contains an unsafe file path.');
                }
            }
            if ($total > self::MAX_UNCOMPRESSED) {
                return $this->fail($r, 'The EPUB expands to an unreasonable size.');
            }
            $first = $zip->statIndex(0);
            if (($first['name'] ?? '') !== 'mimetype' || trim((string) $zip->getFromIndex(0)) !== 'application/epub+zip') {
                return $this->fail($r, 'The first file in the EPUB must be "mimetype" containing application/epub+zip.');
            }
            if (($first['comp_method'] ?? 0) !== ZipArchive::CM_STORE) {
                $r['warnings'][] = 'The "mimetype" file should be stored uncompressed.';
            }
            $enc = $zip->getFromName('META-INF/encryption.xml');
            if ($enc !== false && preg_match('#xmlenc\#aes|adept|rightsmanagement#i', $enc) === 1) {
                return $this->fail($r, 'The EPUB is DRM-protected. Upload the clean file (the store applies its own buyer watermark).');
            }
            $container = $zip->getFromName('META-INF/container.xml');
            $cdoc = $container === false ? null : $this->dom($container);
            $opfPath = $cdoc ? (new DOMXPath($cdoc))->evaluate('string(//*[local-name()="rootfile"]/@full-path)') : '';
            if (! is_string($opfPath) || $opfPath === '' || str_contains($opfPath, '..')) {
                return $this->fail($r, 'The EPUB has no valid package document (META-INF/container.xml).');
            }
            $opfRaw = $zip->getFromName($opfPath);
            $opf = $opfRaw === false ? null : $this->dom($opfRaw);
            if (! $opf) {
                return $this->fail($r, 'The EPUB package document could not be read.');
            }
            $x = new DOMXPath($opf);
            $r['version'] = $x->evaluate('string(/*[local-name()="package"]/@version)') ?: null;
            $r['title'] = trim((string) $x->evaluate('string(//*[local-name()="metadata"]/*[local-name()="title"][1])')) ?: null;
            if ($r['title'] === null) {
                $r['warnings'][] = 'The EPUB has no title in its metadata.';
            }
            $dir = str_contains($opfPath, '/') ? substr($opfPath, 0, (int) strrpos($opfPath, '/') + 1) : '';
            $manifest = [];
            foreach ($x->query('//*[local-name()="manifest"]/*[local-name()="item"]') as $it) {
                $manifest[$it->getAttribute('id')] = ['href' => rawurldecode($it->getAttribute('href')), 'type' => $it->getAttribute('media-type'), 'props' => $it->getAttribute('properties')];
            }
            foreach ($x->query('//*[local-name()="spine"]/*[local-name()="itemref"]') as $ref) {
                $m = $manifest[$ref->getAttribute('idref')] ?? null;
                if ($m === null) {
                    return $this->fail($r, 'The reading order points at a file that is not listed in the EPUB.');
                }
                if ($zip->locateName($dir.$m['href']) === false) {
                    return $this->fail($r, 'The reading order points at a missing file: '.$m['href'].'.');
                }
                $r['spine'][] = $m['href'];
            }
            if ($r['spine'] === []) {
                return $this->fail($r, 'The EPUB has an empty reading order.');
            }
            $r['toc'] = $this->toc($zip, $x, $manifest, $dir);
            if ($r['toc'] === []) {
                $r['warnings'][] = 'No table of contents was found (the reader will fall back to the reading order).';
            }
            $text = '';
            $chars = 0;
            foreach ($r['spine'] as $n => $href) {
                $html = (string) $zip->getFromName($dir.$href);
                if (! $r['scripts'] && preg_match('#<script\b|javascript:#i', $html) === 1) {
                    $r['scripts'] = true;
                }
                $plain = trim(preg_replace('/\s+/u', ' ', strip_tags($html)) ?? '');
                $chars += mb_strlen($plain);
                if ($n < 4) {
                    $text .= ' '.$plain;
                }
            }
            if ($r['scripts']) {
                $r['warnings'][] = 'The EPUB contains scripts. They will never run in the reader, but remove them if you can.';
            }
            $r['pages'] = max(1, (int) round($chars / 1800));
            $r['has_imprint'] = collect((array) config('handbooks.imprint'))->every(fn ($s) => stripos($text, $s) !== false);
            if (! $r['has_imprint']) {
                $r['warnings'][] = 'The Naara imprint line ("Naara.app · Naara Family — Naara Connect Technologies Limited") was not found in the front matter.';
            }
            $r['ok'] = true;
        } finally {
            $zip->close();
        }

        return $r;
    }

    /** @param array<string, mixed> $r */
    private function fail(array $r, string $why): array
    {
        $r['errors'][] = $why;
        $r['ok'] = false;

        return $r;
    }

    private function dom(string $xml): ?DOMDocument
    {
        $d = new DOMDocument;
        $prev = libxml_use_internal_errors(true);
        $ok = $d->loadXML($xml, LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        return $ok ? $d : null;
    }

    /** @return list<array{title:string,href:string,depth:int}> */
    private function toc(ZipArchive $zip, DOMXPath $opf, array $manifest, string $dir): array
    {
        // EPUB 3: the manifest item with properties="nav".
        foreach ($manifest as $m) {
            if (in_array('nav', preg_split('/\s+/', trim($m['props'])) ?: [], true)) {
                $raw = $zip->getFromName($dir.$m['href']);
                $d = $raw === false ? null : $this->dom($raw);
                if ($d) {
                    $x = new DOMXPath($d);
                    $nav = $x->query('//*[local-name()="nav"][@*[local-name()="type"]="toc"]')->item(0) ?? $x->query('//*[local-name()="nav"]')->item(0);
                    if ($nav) {
                        $out = [];
                        $this->walkNav($nav, 0, $out);
                        if ($out) {
                            return $out;
                        }
                    }
                }
            }
        }
        // EPUB 2: the NCX the spine points at.
        $ncxId = $opf->evaluate('string(//*[local-name()="spine"]/@toc)');
        $ncx = $manifest[$ncxId] ?? null;
        if ($ncx) {
            $raw = $zip->getFromName($dir.$ncx['href']);
            $d = $raw === false ? null : $this->dom($raw);
            if ($d) {
                $out = [];
                $this->walkNcx((new DOMXPath($d))->query('//*[local-name()="navMap"]')->item(0), 0, $out);

                return $out;
            }
        }

        return [];
    }

    private function walkNav(\DOMNode $node, int $depth, array &$out): void
    {
        foreach ($node->childNodes as $c) {
            if (! $c instanceof \DOMElement) {
                continue;
            }
            $name = $c->localName;
            if ($name === 'li') {
                foreach ($c->childNodes as $k) {
                    if ($k instanceof \DOMElement && in_array($k->localName, ['a', 'span'], true)) {
                        $t = trim(preg_replace('/\s+/u', ' ', $k->textContent) ?? '');
                        if ($t !== '') {
                            $out[] = ['title' => mb_substr($t, 0, 160), 'href' => $k->localName === 'a' ? $k->getAttribute('href') : '', 'depth' => $depth];
                        }
                    }
                }
                foreach ($c->childNodes as $k) {
                    if ($k instanceof \DOMElement && $k->localName === 'ol') {
                        $this->walkNav($k, $depth + 1, $out);
                    }
                }
            } else {
                $this->walkNav($c, $depth, $out);
            }
        }
    }

    private function walkNcx(?\DOMNode $node, int $depth, array &$out): void
    {
        if (! $node) {
            return;
        }
        foreach ($node->childNodes as $c) {
            if ($c instanceof \DOMElement && $c->localName === 'navPoint') {
                $label = '';
                $href = '';
                foreach ($c->childNodes as $k) {
                    if ($k instanceof \DOMElement && $k->localName === 'navLabel') {
                        $label = trim(preg_replace('/\s+/u', ' ', $k->textContent) ?? '');
                    }
                    if ($k instanceof \DOMElement && $k->localName === 'content') {
                        $href = $k->getAttribute('src');
                    }
                }
                if ($label !== '') {
                    $out[] = ['title' => mb_substr($label, 0, 160), 'href' => $href, 'depth' => $depth];
                }
                $this->walkNcx($c, $depth + 1, $out);
            }
        }
    }
}
