<?php

namespace App\Services\Handbooks\Manuscript;

use DOMDocument;
use DOMXPath;
use ZipArchive;

/**
 * A read-only view of one EPUB file for the manuscript studio (MASTER-ONLY): its reading order, chapter titles, images and the safe HTML of any chapter. The file is
 * untrusted: nothing is extracted to disk, every path is normalised and checked, and chapter HTML is rebuilt through the DOM so no script, remote resource or event
 * attribute ever reaches the preview.
 */
class EpubPackage
{
    private ZipArchive $zip;

    private string $dir = '';

    /** @var array<string, array{href:string,type:string,props:string}> */
    private array $manifest = [];

    /** @var list<string> manifest hrefs in reading order */
    private array $spine = [];

    private string $title = '';

    private string $language = 'en';

    public function __construct(private readonly string $path)
    {
        $this->zip = new ZipArchive;
        if ($this->zip->open($path, ZipArchive::RDONLY) !== true) {
            throw new \RuntimeException('This is not a readable EPUB.');
        }
        $container = $this->xml((string) $this->zip->getFromName('META-INF/container.xml'));
        $opfPath = $container ? (string) (new DOMXPath($container))->evaluate('string(//*[local-name()="rootfile"]/@full-path)') : '';
        $opf = $opfPath !== '' ? $this->xml((string) $this->zip->getFromName($opfPath)) : null;
        if (! $opf) {
            throw new \RuntimeException('The EPUB has no readable package document.');
        }
        $this->dir = str_contains($opfPath, '/') ? substr($opfPath, 0, (int) strrpos($opfPath, '/') + 1) : '';
        $x = new DOMXPath($opf);
        $this->title = trim((string) $x->evaluate('string(//*[local-name()="metadata"]/*[local-name()="title"][1])'));
        $this->language = trim((string) $x->evaluate('string(//*[local-name()="metadata"]/*[local-name()="language"][1])')) ?: 'en';
        foreach ($x->query('//*[local-name()="manifest"]/*[local-name()="item"]') as $it) {
            $this->manifest[$it->getAttribute('id')] = ['href' => rawurldecode($it->getAttribute('href')), 'type' => $it->getAttribute('media-type'), 'props' => $it->getAttribute('properties')];
        }
        foreach ($x->query('//*[local-name()="spine"]/*[local-name()="itemref"]') as $ref) {
            if (isset($this->manifest[$ref->getAttribute('idref')])) {
                $this->spine[] = $this->manifest[$ref->getAttribute('idref')]['href'];
            }
        }
    }

    public function __destruct()
    {
        @$this->zip->close();
    }

    public function title(): string
    {
        return $this->title;
    }

    public function language(): string
    {
        return $this->language;
    }

    public function chapterCount(): int
    {
        return count($this->spine);
    }

    /** @return list<array{index:int, href:string, title:string, words:int}> */
    public function chapters(): array
    {
        $out = [];
        foreach ($this->spine as $i => $href) {
            $html = (string) $this->zip->getFromName($this->dir.$href);
            $doc = $this->html($html);
            $title = '';
            foreach (['h1', 'h2', 'h3', 'title'] as $tag) {
                $n = $doc->getElementsByTagName($tag)->item(0);
                if ($n && trim($n->textContent) !== '') {
                    $title = trim(preg_replace('/\s+/u', ' ', $n->textContent) ?? '');
                    break;
                }
            }
            $words = str_word_count(strip_tags($html)) ?: (int) (mb_strlen(strip_tags($html)) / 6);
            $out[] = ['index' => $i, 'href' => $href, 'title' => $title !== '' ? mb_substr($title, 0, 120) : 'Section '.($i + 1), 'words' => $words];
        }

        return $out;
    }

    /** Safe, self-contained body HTML for one chapter; images point at $assetUrl(href-inside-the-epub). */
    public function chapterHtml(int $index, callable $assetUrl): string
    {
        $href = $this->spine[$index] ?? null;
        if ($href === null) {
            return '';
        }
        $doc = $this->html((string) $this->zip->getFromName($this->dir.$href));
        $base = str_contains($href, '/') ? substr($href, 0, (int) strrpos($href, '/') + 1) : '';
        foreach (['script', 'iframe', 'object', 'embed', 'link', 'style', 'form', 'base', 'meta'] as $tag) {
            foreach (iterator_to_array($doc->getElementsByTagName($tag)) as $n) {
                $n->parentNode?->removeChild($n);
            }
        }
        foreach ((new DOMXPath($doc))->query('//*') as $el) {
            foreach (iterator_to_array($el->attributes) as $a) {
                if (str_starts_with(strtolower($a->name), 'on') || in_array(strtolower($a->name), ['style', 'srcset'], true)) {
                    $el->removeAttribute($a->name);
                }
            }
            if ($el->localName === 'a') {
                $h = $el->getAttribute('href');
                if (preg_match('#^\s*(javascript|data):#i', $h)) {
                    $el->removeAttribute('href');
                } elseif ($h !== '' && ! str_starts_with($h, '#')) {
                    $el->setAttribute('href', '#');   // the preview never navigates away
                }
            }
            if ($el->localName === 'img') {
                $src = rawurldecode($el->getAttribute('src'));
                $el->removeAttribute('src');
                $resolved = $this->resolve($base, $src);
                if ($resolved !== null) {
                    $el->setAttribute('src', $assetUrl($resolved));
                }
                $el->setAttribute('loading', 'lazy');
            }
        }
        $body = $doc->getElementsByTagName('body')->item(0);
        $out = '';
        if ($body) {
            foreach ($body->childNodes as $c) {
                $out .= $doc->saveHTML($c);
            }
        }

        return $out;
    }

    /** @return array{bytes:string, type:string}|null an image inside the package (path relative to the package document's folder) */
    public function asset(string $href): ?array
    {
        $href = $this->resolve('', $href);
        if ($href === null) {
            return null;
        }
        $type = null;
        foreach ($this->manifest as $m) {
            if ($m['href'] === $href) {
                $type = $m['type'];
            }
        }
        if ($type === null || ! str_starts_with($type, 'image/') || $type === 'image/svg+xml') {
            return null;
        }
        $bytes = $this->zip->getFromName($this->dir.$href);

        return $bytes === false ? null : ['bytes' => $bytes, 'type' => $type];
    }

    /** @return list<array{href:string, type:string, bytes:int, width:int, height:int}> */
    public function images(): array
    {
        $out = [];
        foreach ($this->manifest as $m) {
            if (! str_starts_with($m['type'], 'image/') || $m['type'] === 'image/svg+xml') {
                continue;
            }
            $st = $this->zip->statName($this->dir.$m['href']);
            $bytes = $this->zip->getFromName($this->dir.$m['href']);
            $info = $bytes !== false ? @getimagesizefromstring($bytes) : false;
            $out[] = ['href' => $m['href'], 'type' => $m['type'], 'bytes' => (int) ($st['size'] ?? 0), 'width' => (int) ($info[0] ?? 0), 'height' => (int) ($info[1] ?? 0)];
        }
        usort($out, fn ($a, $b) => $b['bytes'] <=> $a['bytes']);

        return $out;
    }

    public function dir(): string
    {
        return $this->dir;
    }

    private function resolve(string $base, string $rel): ?string
    {
        if ($rel === '' || preg_match('#^[a-z][a-z0-9+.-]*:#i', $rel) || str_starts_with($rel, '//')) {
            return null;   // remote and data: references never load
        }
        $parts = [];
        foreach (explode('/', $base.strtok($rel, '#?')) as $p) {
            if ($p === '' || $p === '.') {
                continue;
            }
            if ($p === '..') {
                array_pop($parts);

                continue;
            }
            $parts[] = $p;
        }

        return $parts === [] ? null : implode('/', $parts);
    }

    private function xml(string $raw): ?DOMDocument
    {
        $d = new DOMDocument;
        $prev = libxml_use_internal_errors(true);
        $ok = $raw !== '' && $d->loadXML($raw, LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        return $ok ? $d : null;
    }

    private function html(string $raw): DOMDocument
    {
        $d = new DOMDocument;
        $prev = libxml_use_internal_errors(true);
        $d->loadHTML('<?xml encoding="utf-8" ?>'.$raw, LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        return $d;
    }
}
