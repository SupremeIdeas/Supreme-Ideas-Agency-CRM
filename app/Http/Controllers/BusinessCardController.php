<?php

namespace App\Http\Controllers;

use App\Services\Brands\BusinessCardService;
use App\Services\Brands\ConnectionIntentService;
use Illuminate\Http\Response;

/** Business Card (Brand-Growth Phase 5, MASTER-ONLY). Public, no sign-in. An unknown or unlisted brand is a plain 404, indistinguishable from "no such card". */
class BusinessCardController extends Controller
{
    public function __construct(private readonly BusinessCardService $cards, private readonly ConnectionIntentService $intents)
    {
    }

    public function show(string $code)
    {
        $brand = $this->cards->find($code);
        if ($brand === null) {
            // A well-formed address that is not live: a calm, non-indexed page rather than a bare 404 (the QR and contact-card downloads stay 404).
            return response()->view('business-card.unavailable', [], 200)->header('X-Robots-Tag', 'noindex, nofollow');
        }
        $this->cards->record($brand, request()->query('s') === 'qr' ? 'qr_scan' : 'view');
        if (! auth()->check()) {
            $this->intents->remember($brand);   // a signed-out visitor: remember the brand until they sign up
        }

        return view('business-card.show', [
            'ref' => $this->intents->referralCode($brand),
            'brand' => $brand,
            'code' => $this->cards->code($brand),
            'f' => $this->cards->fields($brand),
            'url' => $this->cards->url($brand),
            'qr' => $this->cards->svg($this->cards->url($brand).'?s=qr', 280),
        ]);
    }

    public function qr(string $code, string $format)
    {
        $brand = $this->cards->find($code) ?? abort(404);
        $url = $this->cards->url($brand).'?s=qr';   // the printed code carries a marker so a scan is counted apart from a link click
        $png = $format === 'png';
        $body = $png ? $this->cards->png($url) : $this->cards->svg($url, 640);

        return new Response($body, 200, [
            'Content-Type' => $png ? 'image/png' : 'image/svg+xml',
            'Content-Disposition' => 'attachment; filename="naara-card-'.strtolower($code).'.'.($png ? 'png' : 'svg').'"',
            'Cache-Control' => 'public, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function pdf(string $code)
    {
        $brand = $this->cards->find($code) ?? abort(404);

        return new Response($this->cards->pdf($brand), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="naara-card-'.strtolower($code).'.pdf"',
            'Cache-Control' => 'public, max-age=600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function vcard(string $code)
    {
        $brand = $this->cards->find($code) ?? abort(404);
        $this->cards->record($brand, 'vcard');

        return new Response($this->cards->vcard($brand), 200, [
            'Content-Type' => 'text/vcard; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="naara-'.strtolower($code).'.vcf"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
