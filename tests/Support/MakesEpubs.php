<?php

namespace Tests\Support;

use ZipArchive;

/** Builds small, valid (or deliberately broken) EPUB files for the handbook tests. */
trait MakesEpubs
{
    /** @param array{imprint?:bool, drm?:bool, badSpine?:bool, scripts?:bool, mimetype?:string, ncx?:bool} $o */
    protected function makeEpub(array $o = []): string
    {
        $path = tempnam(sys_get_temp_dir(), 'ep').'.epub';
        $z = new ZipArchive;
        $z->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $z->addFromString('mimetype', $o['mimetype'] ?? 'application/epub+zip');
        $z->setCompressionName('mimetype', ZipArchive::CM_STORE);
        $z->addFromString('META-INF/container.xml', '<?xml version="1.0"?><container version="1.0" xmlns="urn:oasis:names:tc:opendocument:xmlns:container"><rootfiles><rootfile full-path="OEBPS/content.opf" media-type="application/oebps-package+xml"/></rootfiles></container>');
        if (! empty($o['drm'])) {
            $z->addFromString('META-INF/encryption.xml', '<encryption xmlns="urn:oasis:names:tc:opendocument:xmlns:container"><EncryptedData><EncryptionMethod Algorithm="http://www.w3.org/2001/04/xmlenc#aes128-cbc"/></EncryptedData></encryption>');
        }
        $ncx = ! empty($o['ncx']);
        $manifest = '<item id="c1" href="ch1.xhtml" media-type="application/xhtml+xml"/><item id="c2" href="ch2.xhtml" media-type="application/xhtml+xml"/>'
            .($ncx ? '<item id="ncx" href="toc.ncx" media-type="application/x-dtbncx+xml"/>' : '<item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>');
        $spine = '<itemref idref="c1"/>'.(empty($o['badSpine']) ? '<itemref idref="c2"/>' : '<itemref idref="ghost"/>');
        $z->addFromString('OEBPS/content.opf', '<?xml version="1.0"?><package xmlns="http://www.idpf.org/2007/opf" version="'.($ncx ? '2.0' : '3.0').'" unique-identifier="id"><metadata xmlns:dc="http://purl.org/dc/elements/1.1/"><dc:title>Ghana Handbook</dc:title><dc:identifier id="id">urn:uuid:1</dc:identifier><dc:language>en</dc:language></metadata><manifest>'.$manifest.'</manifest><spine'.($ncx ? ' toc="ncx"' : '').'>'.$spine.'</spine></package>');
        $imprint = ($o['imprint'] ?? true) ? '<p>Naara.app · Naara Family — Naara Connect Technologies Limited</p>' : '';
        $script = ! empty($o['scripts']) ? '<script>alert(1)</script>' : '';
        $z->addFromString('OEBPS/ch1.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Housing</h1>'.$imprint.$script.'<p>'.str_repeat('Rent is paid monthly. ', 200).'</p></body></html>');
        $z->addFromString('OEBPS/ch2.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><body><h1>Visas</h1><p>'.str_repeat('Apply early. ', 200).'</p></body></html>');
        if ($ncx) {
            $z->addFromString('OEBPS/toc.ncx', '<?xml version="1.0"?><ncx xmlns="http://www.daisy.org/z3986/2005/ncx/"><navMap><navPoint id="a"><navLabel><text>Housing</text></navLabel><content src="ch1.xhtml"/></navPoint><navPoint id="b"><navLabel><text>Visas</text></navLabel><content src="ch2.xhtml"/><navPoint id="b1"><navLabel><text>Student visa</text></navLabel><content src="ch2.xhtml#s"/></navPoint></navPoint></navMap></ncx>');
        } else {
            $z->addFromString('OEBPS/nav.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops"><body><nav epub:type="toc"><ol><li><a href="ch1.xhtml">Housing</a></li><li><a href="ch2.xhtml">Visas</a><ol><li><a href="ch2.xhtml#s">Student visa</a></li></ol></li></ol></nav></body></html>');
        }
        $z->close();

        return $path;
    }

    /** A 2:3 cover image of the given width (JPEG). */
    protected function makeCover(int $w = 1600, ?int $h = null): string
    {
        $h ??= (int) round($w * 1.5);
        $im = imagecreatetruecolor($w, $h);
        imagefill($im, 0, 0, imagecolorallocate($im, 10, 110, 110));
        $p = tempnam(sys_get_temp_dir(), 'cv').'.jpg';
        imagejpeg($im, $p, 60);

        return $p;
    }
}
