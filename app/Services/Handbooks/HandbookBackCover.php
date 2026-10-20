<?php

namespace App\Services\Handbooks;

use App\Models\Handbook;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;
use Throwable;
use ZipArchive;

/**
 * The back cover of a handbook (MASTER-ONLY): the closing page about the book and the Naara Family. It is closed into the EPUB (a last page in the reading order, so the in-app
 * reader and the offline copy end on it) and into the PDF (a last page), before the buyer's licence stamp goes on. The stored source files are never changed; the closed copy is
 * a derived file kept next to them and rebuilt when the source or the back cover changes. If a file cannot be rewritten the plain original is served, never an error.
 */
class HandbookBackCover
{
    public function __construct(private readonly HandbookStorage $storage)
    {
    }

    public static function forget(Handbook $b): void
    {
        Storage::disk(HandbookStorage::disk())->deleteDirectory('handbooks/'.$b->id.'/derived');
    }

    /** The disk path to serve for the EPUB: the copy closed with the back cover, or the original when there is none or it could not be built. */
    public function epubPath(Handbook $b): string
    {
        return $this->derived($b, (string) $b->epub_path, 'epub', fn (string $src, string $img, string $ext) => $this->closeEpub($src, $img, $ext));
    }

    public function pdfPath(Handbook $b): string
    {
        return $this->derived($b, (string) $b->pdf_path, 'pdf', fn (string $src, string $img, string $ext) => $this->closePdf($src, $img, $ext));
    }

    private function derived(Handbook $b, string $source, string $kind, callable $build): string
    {
        if ($source === '' || ! $b->back_cover_path || ! $this->storage->exists($b->back_cover_path) || ! $this->storage->exists($source)) {
            return $source;
        }
        $disk = Storage::disk(HandbookStorage::disk());
        $dir = 'handbooks/'.$b->id.'/derived';
        $target = $dir.'/'.substr(hash('sha256', $source.'|'.$b->back_cover_path), 0, 24).'.'.$kind;
        if ($disk->exists($target)) {
            return $target;
        }
        $src = $this->storage->tempCopy($source);
        $img = $this->storage->tempCopy($b->back_cover_path);
        try {
            $bytes = $build($src, $img, strtolower(pathinfo($b->back_cover_path, PATHINFO_EXTENSION)));
        } catch (Throwable) {
            $bytes = null;
        } finally {
            @unlink($src);
            @unlink($img);
        }
        if ($bytes === null) {
            return $source;
        }
        foreach ($disk->files($dir) as $old) {      // a stale closed copy of the same kind is of no use any more
            if (str_ends_with($old, '.'.$kind)) {
                $disk->delete($old);
            }
        }
        $disk->put($target, $bytes);

        return $target;
    }

    /** @return string|null the EPUB bytes with the back cover as the last page, or null when the package cannot be rewritten */
    public function closeEpub(string $epubFile, string $imageFile, string $ext): ?string
    {
        $mime = match ($ext) {
            'png' => 'image/png', 'webp' => 'image/webp', default => 'image/jpeg',
        };
        $ext = $ext === 'jpeg' ? 'jpg' : $ext;
        $tmp = tempnam(sys_get_temp_dir(), 'bc').'.epub';
        copy($epubFile, $tmp);
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
            $xp = new DOMXPath($opf);
            $manifest = $xp->query('//*[local-name()="manifest"]')->item(0);
            $spine = $xp->query('//*[local-name()="spine"]')->item(0);
            if (! $manifest || ! $spine) {
                return null;
            }
            if ($xp->query('//*[@id="naara-back-cover"]')->length > 0) {
                return (string) file_get_contents($tmp);       // already closed
            }
            $ns = 'http://www.idpf.org/2007/opf';
            $zip->addFromString($dir.'naara-back-cover.xhtml', '<?xml version="1.0" encoding="UTF-8"?><html xmlns="http://www.w3.org/1999/xhtml"><head><meta charset="utf-8"/><title>Back cover</title><style>html,body{margin:0;padding:0;background:#0D1B2A;text-align:center}img{display:block;margin:0 auto;max-width:100%;max-height:100vh;height:auto}</style></head><body><img src="naara-back-cover.'.$ext.'" alt="Back cover: about this book and the Naara Family"/></body></html>');
            $zip->addFile($imageFile, $dir.'naara-back-cover.'.$ext);
            $img = $opf->createElementNS($ns, 'item');
            $img->setAttribute('id', 'naara-back-cover-img');
            $img->setAttribute('href', 'naara-back-cover.'.$ext);
            $img->setAttribute('media-type', $mime);
            $manifest->appendChild($img);
            $page = $opf->createElementNS($ns, 'item');
            $page->setAttribute('id', 'naara-back-cover');
            $page->setAttribute('href', 'naara-back-cover.xhtml');
            $page->setAttribute('media-type', 'application/xhtml+xml');
            $manifest->appendChild($page);
            $ref = $opf->createElementNS($ns, 'itemref');
            $ref->setAttribute('idref', 'naara-back-cover');
            $spine->appendChild($ref);
            $zip->addFromString($opfPath, (string) $opf->saveXML());
            $zip->close();
            $bytes = (string) file_get_contents($tmp);

            return $bytes === '' ? null : $bytes;
        } catch (Throwable) {
            return null;
        } finally {
            try {
                $zip->close();     // closing twice throws; the success path has already closed it
            } catch (Throwable) {
            }
            @unlink($tmp);
        }
    }

    /** @return string|null the PDF bytes with the back cover as the last page, or null when the free FPDI reader cannot parse the file */
    public function closePdf(string $pdfFile, string $imageFile, string $ext): ?string
    {
        $jpg = $this->asJpeg($imageFile, $ext);
        if ($jpg === null) {
            return null;
        }
        try {
            $pdf = new Fpdi;
            $pdf->SetAutoPageBreak(false);
            $pdf->SetMargins(0, 0, 0);
            $pages = $pdf->setSourceFile($pdfFile);
            $w = $h = 0.0;
            for ($i = 1; $i <= $pages; $i++) {
                $tpl = $pdf->importPage($i);
                $size = $pdf->getTemplateSize($tpl);
                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($tpl);
                [$w, $h] = [(float) $size['width'], (float) $size['height']];
            }
            $pdf->AddPage($w > $h ? 'L' : 'P', [$w, $h]);
            $pdf->SetFillColor(13, 27, 42);
            $pdf->Rect(0, 0, $w, $h, 'F');
            [$iw, $ih] = getimagesize($jpg);
            $scale = min($w / $iw, $h / $ih);
            $pdf->Image($jpg, ($w - $iw * $scale) / 2, ($h - $ih * $scale) / 2, $iw * $scale, $ih * $scale, 'JPG');

            return $pdf->Output('S');
        } catch (Throwable) {
            return null;
        } finally {
            if ($jpg !== $imageFile) {
                @unlink($jpg);
            }
        }
    }

    private function asJpeg(string $file, string $ext): ?string
    {
        if (in_array($ext, ['jpg', 'jpeg'], true)) {
            return $file;
        }
        $im = match ($ext) {
            'png' => function_exists('imagecreatefrompng') ? @imagecreatefrompng($file) : false,
            'webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($file) : false,
            default => false,
        };
        if (! $im) {
            return null;
        }
        $out = tempnam(sys_get_temp_dir(), 'bcj').'.jpg';
        imagejpeg($im, $out, 90);
        imagedestroy($im);

        return $out;
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
