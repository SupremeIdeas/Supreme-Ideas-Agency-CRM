<?php

namespace App\Services\Handbooks;

use App\Models\Handbook;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use setasign\Fpdi\Fpdi;
use ZipArchive;

/**
 * Per-buyer watermarking of downloadable files (MASTER-ONLY). This deters sharing; it is not DRM and the product never claims to be piracy-proof.
 * EPUB: a visible licence page is appended to the reading order, a licence marker is written into the package metadata, and a hidden marker is added to every chapter, so
 * re-saving the file in a library tool does not trivially remove all of it. PDF: every page gets a small licence line in the footer (setasign/fpdi); if the file uses a
 * structure the free reader cannot parse, the caller is told (`null`) so it can serve the original, log it as unstamped and alert the admin.
 */
class HandbookWatermark
{
    public static function token(User $u, Handbook $b, string $ref): string
    {
        return substr(hash_hmac('sha256', $u->id.'|'.$b->id.'|'.$ref, (string) config('app.key')), 0, 24);
    }

    public static function line(User $u, string $ref): string
    {
        return 'Licensed to '.$u->name.' ('.$u->email.') · '.$ref.' · '.now()->format('Y-m-d').' · personal use only';
    }

    /** @return string|null the stamped EPUB bytes, or null when the package could not be rewritten */
    public function stampEpub(string $path, User $u, Handbook $b, string $ref): ?string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'wm').'.epub';
        copy($path, $tmp);
        $zip = new ZipArchive;
        if ($zip->open($tmp) !== true) {
            @unlink($tmp);

            return null;
        }
        try {
            $container = $zip->getFromName('META-INF/container.xml');
            $cd = $container === false ? null : $this->dom($container);
            $opfPath = $cd ? (string) (new DOMXPath($cd))->evaluate('string(//*[local-name()="rootfile"]/@full-path)') : '';
            $opfRaw = $opfPath === '' ? false : $zip->getFromName($opfPath);
            $opf = $opfRaw === false ? null : $this->dom($opfRaw);
            if (! $opf) {
                return null;
            }
            $dir = str_contains($opfPath, '/') ? substr($opfPath, 0, (int) strrpos($opfPath, '/') + 1) : '';
            $token = self::token($u, $b, $ref);
            $xp = new DOMXPath($opf);
            $ns = 'http://www.idpf.org/2007/opf';
            $esc = fn (string $s) => htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8');

            // 1. A visible licence page at the end of the reading order.
            $page = '<?xml version="1.0" encoding="UTF-8"?><html xmlns="http://www.w3.org/1999/xhtml"><head><title>Licence</title></head><body><h2>Licence</h2><p>'.$esc(self::line($u, $ref)).'</p><p>This copy is licensed to one reader. Please do not share it.</p><span class="naara-wm" aria-hidden="true" style="position:absolute;left:-9999px">'.$token.'</span></body></html>';
            $zip->addFromString($dir.'naara-licence.xhtml', $page);
            $manifest = $xp->query('//*[local-name()="manifest"]')->item(0);
            $spine = $xp->query('//*[local-name()="spine"]')->item(0);
            $meta = $xp->query('//*[local-name()="metadata"]')->item(0);
            if (! $manifest || ! $spine || ! $meta) {
                return null;
            }
            $item = $opf->createElementNS($ns, 'item');
            $item->setAttribute('id', 'naara-licence');
            $item->setAttribute('href', 'naara-licence.xhtml');
            $item->setAttribute('media-type', 'application/xhtml+xml');
            $manifest->appendChild($item);
            $ref2 = $opf->createElementNS($ns, 'itemref');
            $ref2->setAttribute('idref', 'naara-licence');
            $spine->appendChild($ref2);
            // 2. Licence marker in the metadata.
            $m = $opf->createElementNS($ns, 'meta', $token);
            $m->setAttribute('name', 'naara:licence');
            $m->setAttribute('content', $token);
            $meta->appendChild($m);
            $zip->addFromString($opfPath, $opf->saveXML());
            // 3. A hidden marker in every chapter.
            foreach ($xp->query('//*[local-name()="spine"]/*[local-name()="itemref"]') as $r) {
                $href = null;
                foreach ($xp->query('//*[local-name()="manifest"]/*[local-name()="item"]') as $it) {
                    if ($it->getAttribute('id') === $r->getAttribute('idref')) {
                        $href = rawurldecode($it->getAttribute('href'));
                    }
                }
                if ($href === null || str_ends_with($href, 'naara-licence.xhtml')) {
                    continue;
                }
                $html = $zip->getFromName($dir.$href);
                if ($html !== false && preg_match('#</body>#i', $html) === 1) {
                    $zip->addFromString($dir.$href, preg_replace('#</body>#i', '<span class="naara-wm" aria-hidden="true" style="position:absolute;left:-9999px">'.$token.'</span></body>', $html, 1));
                }
            }
            $zip->close();
            $bytes = (string) file_get_contents($tmp);

            return $bytes === '' ? null : $bytes;
        } catch (\Throwable) {
            return null;
        } finally {
            @unlink($tmp);
        }
    }

    /** @return string|null the stamped PDF bytes, or null when the free FPDI reader cannot parse this file */
    public function stampPdf(string $path, User $u, string $ref): ?string
    {
        try {
            $pdf = new Fpdi;
            $pdf->SetAutoPageBreak(false);   // the licence line sits in the bottom margin; without this FPDF starts a new page after every page
            $pdf->SetMargins(0, 0, 0);
            $pages = $pdf->setSourceFile($path);
            $line = iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', self::line($u, $ref)) ?: self::line($u, $ref);
            for ($i = 1; $i <= $pages; $i++) {
                $tpl = $pdf->importPage($i);
                $size = $pdf->getTemplateSize($tpl);
                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($tpl);
                $pdf->SetFont('Helvetica', '', 6);
                $pdf->SetTextColor(120, 120, 120);
                $pdf->SetXY(6, $size['height'] - 7);
                $pdf->Cell($size['width'] - 12, 4, $line, 0, 0, 'C');
            }

            return $pdf->Output('S');
        } catch (\Throwable) {
            return null;   // compressed cross-reference streams and similar: the free reader cannot read them
        }
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
}
