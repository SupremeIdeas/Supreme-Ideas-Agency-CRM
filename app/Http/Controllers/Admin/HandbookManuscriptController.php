<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Handbooks\Manuscript\EpubPackage;
use App\Services\Handbooks\Manuscript\ManuscriptStaging;
use Symfony\Component\HttpFoundation\Response;

/** The manuscript studio's preview frames (MASTER-ONLY): a staged manuscript's chapters and pictures, rebuilt safely, admin only. Never cached, never indexed. */
class HandbookManuscriptController extends Controller
{
    private function pkg(string $token): array
    {
        abort_unless(auth()->user()?->hasAnyRole(['super_admin', 'admin']), 403);
        abort_unless(ManuscriptStaging::validToken($token), 404);
        $local = app(ManuscriptStaging::class)->localCopy($token);
        try {
            return [new EpubPackage($local), $local];
        } catch (\Throwable) {
            @unlink($local);
            abort(404);
        }
    }

    public function chapter(string $token, int $n): Response
    {
        [$pkg, $local] = $this->pkg($token);
        try {
            $html = $pkg->chapterHtml($n, fn (string $href) => route('admin.handbooks.manuscript.asset', ['token' => $token]).'?p='.rawurlencode($href));
        } finally {
            @unlink($local);
        }
        $page = '<!doctype html><html lang="'.e($pkg->language()).'"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex">'
            .'<style>:root{color-scheme:light dark}body{font:17px/1.7 Georgia,"Times New Roman",serif;margin:0;padding:22px 20px 60px;max-width:44rem;margin-inline:auto;background:#fff;color:#1f2937}'
            .'@media(prefers-color-scheme:dark){body{background:#0f1720;color:#e5e7eb}}h1,h2,h3{font-family:system-ui,sans-serif;line-height:1.25;margin:1.4em 0 .5em}h1{font-size:1.7rem}img{max-width:100%;height:auto;border-radius:8px}'
            .'table{border-collapse:collapse;width:100%;font-size:.92em}td,th{border:1px solid #9ca3af66;padding:.4em}a{color:#0a6e6e}</style></head><body>'.$html.'</body></html>';

        return response($page, 200, ['Content-Type' => 'text/html; charset=utf-8', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'", 'X-Robots-Tag' => 'noindex']);
    }

    public function asset(string $token): Response
    {
        [$pkg, $local] = $this->pkg($token);
        try {
            $a = $pkg->asset((string) request()->query('p', ''));
        } finally {
            @unlink($local);
        }
        abort_if($a === null, 404);

        return response($a['bytes'], 200, ['Content-Type' => $a['type'], 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
