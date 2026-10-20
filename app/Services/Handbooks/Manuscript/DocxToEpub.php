<?php

namespace App\Services\Handbooks\Manuscript;

use DOMDocument;
use DOMElement;
use DOMXPath;
use ZipArchive;

/**
 * Word (.docx) to EPUB 3 in plain PHP (MASTER-ONLY), so it runs on shared cPanel hosting with no converter installed. Headings, paragraphs, bold/italic/underline,
 * bullet and numbered lists, tables, links and embedded pictures are kept; a new chapter starts at every top-level heading. Page layout, headers, footers, comments and
 * tracked changes are left out on purpose: an e-book reflows. The Naara imprint is added to the front matter so the book passes the store's checklist.
 */
class DocxToEpub
{
    private const W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    private const R = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    private const A = 'http://schemas.openxmlformats.org/drawingml/2006/main';

    /** @var array<string, string> rel id => target */
    private array $rels = [];

    /** @var array<string, string> rel id => external url */
    private array $links = [];

    /** @var array<string, array{name:string, bytes:string, type:string}> rel id => image */
    private array $images = [];

    /** @var list<array{title:string, html:string}> */
    private array $chapters = [];

    private int $imgCount = 0;

    /** @return array{ok:bool, errors:list<string>, warnings:list<string>, chapters:int, images:int} */
    public function convert(string $docxPath, string $epubPath, ?string $titleOverride = null, string $language = 'en'): array
    {
        $r = ['ok' => false, 'errors' => [], 'warnings' => [], 'chapters' => 0, 'images' => 0];
        $zip = new ZipArchive;
        if (! is_file($docxPath) || $zip->open($docxPath, ZipArchive::RDONLY) !== true) {
            $r['errors'][] = 'This is not a readable Word file.';

            return $r;
        }
        try {
            if ($zip->numFiles > 6000) {
                $r['errors'][] = 'The Word file has an unexpected number of parts.';

                return $r;
            }
            $docXml = $zip->getFromName('word/document.xml');
            $doc = $docXml === false ? null : $this->load($docXml);
            if (! $doc) {
                $r['errors'][] = 'This is not a valid .docx (no readable document).';

                return $r;
            }
            $this->readRels($zip);
            $title = $titleOverride ?: $this->coreTitle($zip) ?: 'Untitled handbook';
            $this->walk($doc, $zip, $title, $r);
        } finally {
            $zip->close();
        }
        if ($this->chapters === []) {
            $r['errors'][] = 'The document has no text to turn into a book.';

            return $r;
        }
        $this->write($epubPath, $title, $language);
        $r['ok'] = true;
        $r['chapters'] = count($this->chapters);
        $r['images'] = count($this->images);

        return $r;
    }

    private function load(string $xml): ?DOMDocument
    {
        $d = new DOMDocument;
        $prev = libxml_use_internal_errors(true);
        $ok = $d->loadXML($xml, LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        return $ok ? $d : null;
    }

    private function coreTitle(ZipArchive $zip): ?string
    {
        $raw = $zip->getFromName('docProps/core.xml');
        $d = $raw === false ? null : $this->load($raw);
        $t = $d ? trim((string) (new DOMXPath($d))->evaluate('string(//*[local-name()="title"])')) : '';

        return $t !== '' ? mb_substr($t, 0, 200) : null;
    }

    private function readRels(ZipArchive $zip): void
    {
        $raw = $zip->getFromName('word/_rels/document.xml.rels');
        $d = $raw === false ? null : $this->load($raw);
        if (! $d) {
            return;
        }
        foreach ($d->getElementsByTagName('Relationship') as $rel) {
            $id = $rel->getAttribute('Id');
            if ($rel->getAttribute('TargetMode') === 'External') {
                if (preg_match('#^https?://#i', $rel->getAttribute('Target'))) {
                    $this->links[$id] = $rel->getAttribute('Target');
                }

                continue;
            }
            $this->rels[$id] = ltrim(str_starts_with($rel->getAttribute('Target'), '/') ? substr($rel->getAttribute('Target'), 1) : 'word/'.$rel->getAttribute('Target'), '/');
        }
    }

    private function walk(DOMDocument $doc, ZipArchive $zip, string $title, array &$r): void
    {
        $body = $doc->getElementsByTagNameNS(self::W, 'body')->item(0);
        if (! $body) {
            return;
        }
        $cur = ['title' => 'Introduction', 'html' => ''];
        $list = null;   // 'ul' | 'ol' | null
        $flush = function () use (&$cur) {
            if (trim(strip_tags($cur['html'], '<img>')) !== '') {
                $this->chapters[] = $cur;
            }
        };
        $closeList = function () use (&$list, &$cur) {
            if ($list) {
                $cur['html'] .= "</$list>\n";
                $list = null;
            }
        };
        foreach ($body->childNodes as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }
            if ($node->localName === 'tbl') {
                $closeList();
                $cur['html'] .= $this->table($node, $zip, $r);

                continue;
            }
            if ($node->localName !== 'p') {
                continue;
            }
            $style = $this->styleOf($node);
            $inner = $this->runs($node, $zip, $r);
            $plain = trim(strip_tags($inner));
            $hasImg = str_contains($inner, '<img');
            if ($plain === '' && ! $hasImg) {
                $closeList();

                continue;
            }
            if (preg_match('/^(heading|titre|überschrift)\s*([1-6])$/i', $style, $m) || $style === 'Title') {
                $closeList();
                $level = $style === 'Title' ? 1 : (int) $m[2];
                if ($level === 1) {
                    $flush();
                    $cur = ['title' => mb_substr($plain, 0, 160), 'html' => ''];
                }
                $cur['html'] .= '<h'.min(6, $level + ($level === 1 ? 0 : 0)).'>'.$inner.'</h'.min(6, $level).">\n";

                continue;
            }
            $isList = $this->hasNumPr($node) || str_contains(strtolower($style), 'list');
            if ($isList) {
                $kind = $this->numKind($node);
                if ($list !== $kind) {
                    $closeList();
                    $cur['html'] .= "<$kind>\n";
                    $list = $kind;
                }
                $cur['html'] .= '<li>'.$inner."</li>\n";

                continue;
            }
            $closeList();
            $cur['html'] .= '<p>'.$inner."</p>\n";
        }
        $closeList();
        $flush();
        if (count($this->chapters) === 1) {
            $this->chapters[0]['title'] = $title;   // a document with no headings reads as one chapter titled like the book
        }
    }

    private function styleOf(DOMElement $p): string
    {
        $s = (new DOMXPath($p->ownerDocument))->query('./*[local-name()="pPr"]/*[local-name()="pStyle"]/@*[local-name()="val"]', $p)->item(0);

        return $s ? (string) $s->nodeValue : '';
    }

    private function hasNumPr(DOMElement $p): bool
    {
        return (new DOMXPath($p->ownerDocument))->query('./*[local-name()="pPr"]/*[local-name()="numPr"]', $p)->length > 0;
    }

    private function numKind(DOMElement $p): string
    {
        $fmt = (new DOMXPath($p->ownerDocument))->query('./*[local-name()="pPr"]/*[local-name()="numPr"]/*[local-name()="numId"]/@*[local-name()="val"]', $p)->item(0);

        return ($fmt && in_array((int) $fmt->nodeValue, [2, 4, 6, 8], true)) ? 'ol' : 'ul';
    }

    private function runs(DOMElement $p, ZipArchive $zip, array &$r): string
    {
        $out = '';
        foreach ($p->childNodes as $c) {
            if (! $c instanceof DOMElement) {
                continue;
            }
            if ($c->localName === 'r') {
                $out .= $this->run($c, $zip, $r);
            } elseif ($c->localName === 'hyperlink') {
                $inner = '';
                foreach ($c->childNodes as $rr) {
                    if ($rr instanceof DOMElement && $rr->localName === 'r') {
                        $inner .= $this->run($rr, $zip, $r);
                    }
                }
                $rid = $c->getAttributeNS(self::R, 'id');
                $out .= isset($this->links[$rid]) ? '<a href="'.htmlspecialchars($this->links[$rid], ENT_QUOTES).'">'.$inner.'</a>' : $inner;
            }
        }

        return $out;
    }

    private function run(DOMElement $run, ZipArchive $zip, array &$r): string
    {
        $x = new DOMXPath($run->ownerDocument);
        $has = fn (string $tag) => $x->query('./*[local-name()="rPr"]/*[local-name()="'.$tag.'"]', $run)->length > 0
            && ($x->query('./*[local-name()="rPr"]/*[local-name()="'.$tag.'"]/@*[local-name()="val"]', $run)->item(0)?->nodeValue ?? 'true') !== 'false'
            && ($x->query('./*[local-name()="rPr"]/*[local-name()="'.$tag.'"]/@*[local-name()="val"]', $run)->item(0)?->nodeValue ?? 'true') !== '0';
        $text = '';
        foreach ($run->childNodes as $c) {
            if (! $c instanceof DOMElement) {
                continue;
            }
            if ($c->localName === 't') {
                $text .= htmlspecialchars($c->textContent, ENT_XML1);
            } elseif ($c->localName === 'tab') {
                $text .= ' ';
            } elseif ($c->localName === 'br') {
                $text .= '<br/>';
            } elseif ($c->localName === 'drawing' || $c->localName === 'pict') {
                $text .= $this->picture($c, $zip, $r);
            }
        }
        if ($text === '') {
            return '';
        }
        if ($has('b')) {
            $text = "<strong>$text</strong>";
        }
        if ($has('i')) {
            $text = "<em>$text</em>";
        }
        if ($has('u')) {
            $text = "<u>$text</u>";
        }

        return $text;
    }

    private function picture(DOMElement $node, ZipArchive $zip, array &$r): string
    {
        $blip = $node->getElementsByTagNameNS(self::A, 'blip')->item(0);
        $rid = $blip ? $blip->getAttributeNS(self::R, 'embed') : '';
        if ($rid === '' || ! isset($this->rels[$rid])) {
            return '';
        }
        if (! isset($this->images[$rid])) {
            $bytes = $zip->getFromName($this->rels[$rid]);
            $ext = strtolower(pathinfo($this->rels[$rid], PATHINFO_EXTENSION));
            $type = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'webp' => 'image/webp'][$ext] ?? null;
            if ($bytes === false || $type === null) {
                $r['warnings'][] = 'A picture in an unsupported format ('.$ext.') was left out.';

                return '';
            }
            $this->imgCount++;
            $this->images[$rid] = ['name' => 'img'.str_pad((string) $this->imgCount, 3, '0', STR_PAD_LEFT).'.'.($ext === 'jpeg' ? 'jpg' : $ext), 'bytes' => $bytes, 'type' => $type];
        }
        $alt = '';
        $docPr = $node->getElementsByTagName('docPr')->item(0) ?? null;
        foreach ($node->getElementsByTagNameNS('*', 'docPr') as $d) {
            $alt = $d->getAttribute('descr') ?: $d->getAttribute('title');
            break;
        }

        return '<img src="../images/'.$this->images[$rid]['name'].'" alt="'.htmlspecialchars((string) $alt, ENT_QUOTES).'"/>';
    }

    private function table(DOMElement $tbl, ZipArchive $zip, array &$r): string
    {
        $html = "<table>\n";
        foreach ($tbl->getElementsByTagNameNS(self::W, 'tr') as $tr) {
            $html .= '<tr>';
            foreach ($tr->childNodes as $tc) {
                if ($tc instanceof DOMElement && $tc->localName === 'tc') {
                    $cell = '';
                    foreach ($tc->childNodes as $p) {
                        if ($p instanceof DOMElement && $p->localName === 'p') {
                            $cell .= $this->runs($p, $zip, $r).' ';
                        }
                    }
                    $html .= '<td>'.trim($cell).'</td>';
                }
            }
            $html .= "</tr>\n";
        }

        return $html."</table>\n";
    }

    private function xhtml(string $title, string $body): string
    {
        return '<?xml version="1.0" encoding="utf-8"?>'."\n".'<!DOCTYPE html>'."\n".'<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops"><head><meta charset="utf-8"/><title>'.htmlspecialchars($title, ENT_XML1).'</title><link rel="stylesheet" type="text/css" href="../css/book.css"/></head><body>'.$body.'</body></html>';
    }

    private function write(string $path, string $title, string $language): void
    {
        $imprint = (array) config('handbooks.imprint', ['Naara.app', 'Naara Connect Technologies Limited']);
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('mimetype', 'application/epub+zip');
        $zip->setCompressionName('mimetype', ZipArchive::CM_STORE);
        $zip->addFromString('META-INF/container.xml', '<?xml version="1.0"?><container version="1.0" xmlns="urn:oasis:names:tc:opendocument:xmlns:container"><rootfiles><rootfile full-path="OEBPS/content.opf" media-type="application/oebps-package+xml"/></rootfiles></container>');
        $zip->addFromString('OEBPS/css/book.css', "body{font-family:Georgia,serif;line-height:1.6;margin:0 5%}h1,h2,h3{line-height:1.25;margin:1.4em 0 .5em}img{max-width:100%;height:auto}table{border-collapse:collapse;width:100%}td{border:1px solid #bbb;padding:.35em}p{margin:.7em 0}\n");
        $front = '<h1>'.htmlspecialchars($title, ENT_XML1).'</h1><p>'.htmlspecialchars(implode(' · ', $imprint), ENT_XML1).'</p>';
        $zip->addFromString('OEBPS/text/front.xhtml', $this->xhtml($title, $front));
        $manifest = '<item id="css" href="css/book.css" media-type="text/css"/><item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/><item id="front" href="text/front.xhtml" media-type="application/xhtml+xml"/>';
        $spine = '<itemref idref="front"/>';
        $nav = '<li><a href="text/front.xhtml">'.htmlspecialchars($title, ENT_XML1).'</a></li>';
        foreach ($this->chapters as $i => $ch) {
            $id = 'c'.($i + 1);
            $file = 'text/'.$id.'.xhtml';
            $zip->addFromString('OEBPS/'.$file, $this->xhtml($ch['title'], $ch['html']));
            $manifest .= '<item id="'.$id.'" href="'.$file.'" media-type="application/xhtml+xml"/>';
            $spine .= '<itemref idref="'.$id.'"/>';
            $nav .= '<li><a href="'.$file.'">'.htmlspecialchars($ch['title'], ENT_XML1).'</a></li>';
        }
        $n = 0;
        foreach ($this->images as $img) {
            $n++;
            $zip->addFromString('OEBPS/images/'.$img['name'], $img['bytes']);
            $manifest .= '<item id="i'.$n.'" href="images/'.$img['name'].'" media-type="'.$img['type'].'"/>';
        }
        $zip->addFromString('OEBPS/nav.xhtml', '<?xml version="1.0" encoding="utf-8"?>'."\n".'<!DOCTYPE html><html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops"><head><meta charset="utf-8"/><title>Contents</title></head><body><nav epub:type="toc" id="toc"><h1>Contents</h1><ol>'.$nav.'</ol></nav></body></html>');
        $uid = 'urn:uuid:'.\Illuminate\Support\Str::uuid();
        $zip->addFromString('OEBPS/content.opf', '<?xml version="1.0" encoding="utf-8"?><package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="uid"><metadata xmlns:dc="http://purl.org/dc/elements/1.1/"><dc:identifier id="uid">'.$uid.'</dc:identifier><dc:title>'.htmlspecialchars($title, ENT_XML1).'</dc:title><dc:language>'.htmlspecialchars($language, ENT_XML1).'</dc:language><meta property="dcterms:modified">'.gmdate('Y-m-d\TH:i:s\Z').'</meta></metadata><manifest>'.$manifest.'</manifest><spine>'.$spine.'</spine></package>');
        $zip->close();
    }
}
