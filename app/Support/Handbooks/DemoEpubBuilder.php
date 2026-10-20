<?php

namespace App\Support\Handbooks;

use ZipArchive;

/** Builds a small, valid EPUB 3 with a table of contents, figures and styling, for the demo handbooks (MASTER-ONLY). Sample text only: it never states real rules as fact. */
final class DemoEpubBuilder
{
    /** @param list<array{title:string, paras:list<string>, figure?:array{file:string, caption:string, alt:string}}> $chapters @return string path of the .epub */
    public static function build(string $title, string $uid, array $chapters, string $imagesDir): string
    {
        $path = tempnam(sys_get_temp_dir(), 'hb').'.epub';
        $z = new ZipArchive;
        $z->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $z->addFromString('mimetype', 'application/epub+zip');
        $z->setCompressionName('mimetype', ZipArchive::CM_STORE);
        $z->addFromString('META-INF/container.xml', '<?xml version="1.0"?><container version="1.0" xmlns="urn:oasis:names:tc:opendocument:xmlns:container"><rootfiles><rootfile full-path="OEBPS/content.opf" media-type="application/oebps-package+xml"/></rootfiles></container>');
        $z->addFromString('OEBPS/style.css', 'body{font-family:Georgia,serif;line-height:1.65}h1{font-size:1.7em;margin:1.2em 0 .5em}h2{font-size:1.2em;margin:1.4em 0 .4em}p{margin:.8em 0}figure{margin:1.2em 0;text-align:center}figure img{max-width:100%;height:auto}figcaption{font-size:.85em;opacity:.7;margin-top:.4em}.note{border-left:4px solid #0A6E6E;padding:.4em 1em;background:rgba(10,110,110,.08);border-radius:4px}');
        $manifest = '<item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/><item id="css" href="style.css" media-type="text/css"/>';
        $spine = '';
        $navLis = '';
        $imgs = [];
        foreach ($chapters as $i => $c) {
            $n = $i + 1;
            $body = '<h1>'.e($c['title']).'</h1>';
            if ($n === 1) {
                $body .= '<p class="note">Naara.app · Naara Family — Naara Connect Technologies Limited. This is a sample handbook used to preview the reading experience.</p>';
            }
            foreach ($c['paras'] as $j => $p) {
                $body .= ($j === 1 ? '<h2>In practice</h2>' : '').'<p>'.e($p).'</p>';
            }
            if (isset($c['figure'])) {
                $f = $c['figure'];
                $imgs[$f['file']] = true;
                $body .= '<figure><img src="images/'.e($f['file']).'" alt="'.e($f['alt']).'"/><figcaption>'.e($f['caption']).'</figcaption></figure>';
                $body .= '<p>Always confirm the details with the official source before you travel; rules and prices change.</p>';
            }
            $z->addFromString("OEBPS/ch{$n}.xhtml", '<?xml version="1.0" encoding="utf-8"?><html xmlns="http://www.w3.org/1999/xhtml"><head><title>'.e($c['title']).'</title><link rel="stylesheet" href="style.css"/></head><body>'.$body.'</body></html>');
            $manifest .= '<item id="c'.$n.'" href="ch'.$n.'.xhtml" media-type="application/xhtml+xml"/>';
            $spine .= '<itemref idref="c'.$n.'"/>';
            $navLis .= '<li><a href="ch'.$n.'.xhtml">'.e($c['title']).'</a></li>';
        }
        foreach (array_keys($imgs) as $k => $file) {
            $z->addFile(rtrim($imagesDir, '/').'/'.$file, 'OEBPS/images/'.$file);
            $manifest .= '<item id="img'.$k.'" href="images/'.e($file).'" media-type="image/jpeg"/>';
        }
        $z->addFromString('OEBPS/nav.xhtml', '<?xml version="1.0" encoding="utf-8"?><html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops"><head><title>Contents</title></head><body><nav epub:type="toc"><ol>'.$navLis.'</ol></nav></body></html>');
        $z->addFromString('OEBPS/content.opf', '<?xml version="1.0" encoding="utf-8"?><package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="id"><metadata xmlns:dc="http://purl.org/dc/elements/1.1/"><dc:title>'.e($title).'</dc:title><dc:identifier id="id">urn:uuid:'.e($uid).'</dc:identifier><dc:language>en</dc:language><dc:creator>Naara</dc:creator><meta property="dcterms:modified">'.gmdate('Y-m-d\TH:i:s\Z').'</meta></metadata><manifest>'.$manifest.'</manifest><spine>'.$spine.'</spine></package>');
        $z->close();

        return $path;
    }
}
