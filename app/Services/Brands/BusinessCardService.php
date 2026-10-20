<?php

namespace App\Services\Brands;

use App\Models\BrandLink;
use App\Models\BrandPartner;
use App\Models\User;
use App\Services\N2N\Identity\NaaraId;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;

/**
 * Business Card (Brand-Growth Phase 5, MASTER-ONLY): one shareable public page per listed brand at /b/{Naara ID}, with a QR code that opens it.
 * The address is the owner's Naara ID, which never changes, so a printed card or QR stays valid for good. Only a listed brand has a card, and the
 * page carries public fields only (name, logo, blurb, approved links, follower count); nothing private or priced.
 */
class BusinessCardService
{
    /** Words a card address may never be (they would read as a platform page or an official account). */
    private const RESERVED = ['admin', 'adminmaster', 'api', 'app', 'naara', 'naarasim', 'support', 'help', 'login', 'register', 'wallet', 'official', 'staff', 'team', 'security', 'billing', 'status', 'www', 'mail', 'about', 'terms', 'privacy', 'null', 'undefined'];

    /** Short-code alphabet: no 0/O/1/I/L, so a code read aloud or printed small is never mistyped. */
    private const CODE_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    /** The card address for a brand: its readable name when it has one, else its permanent Naara ID. Both always work. Null when no address can be made. */
    public function url(BrandPartner $brand): ?string
    {
        $address = $brand->card_slug ?: $this->code($brand);

        return $address ? route('business-card.show', $address) : null;
    }

    /** The short link (six characters) for tight spaces such as a social bio. Created on first use and never changes. */
    public function shortUrl(BrandPartner $brand): string
    {
        return route('business-card.show', $this->shortCode($brand));
    }

    public function shortCode(BrandPartner $brand): string
    {
        if (filled($brand->card_code)) {
            return (string) $brand->card_code;
        }
        for ($i = 0; $i < 20; $i++) {
            $code = '';
            for ($j = 0; $j < 6; $j++) {
                $code .= self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];
            }
            if (! BrandPartner::query()->where('card_code', $code)->exists() && ! User::query()->where('naara_id', $code)->exists()) {
                $brand->forceFill(['card_code' => $code])->save();

                return $code;
            }
        }
        throw new \RuntimeException('Could not make a short code.');
    }

    /**
     * Set the readable address (3 to 30 of a-z, 0-9 and hyphens, starting with a letter). Refused when taken, reserved, or when it could be mistaken for a Naara ID or short
     * code. An empty value clears it (the Naara ID address keeps working). The old readable address stops working at once, so say so in the UI.
     *
     * @throws \InvalidArgumentException with a message safe to show the owner
     */
    public function setSlug(BrandPartner $brand, ?string $slug): void
    {
        $slug = $slug === null ? '' : strtolower(trim($slug));
        if ($slug === '') {
            $brand->forceFill(['card_slug' => null])->save();

            return;
        }
        if (preg_match('/^[a-z][a-z0-9-]{2,29}$/', $slug) !== 1 || str_ends_with($slug, '-') || str_contains($slug, '--')) {
            throw new \InvalidArgumentException('Use 3 to 30 letters, numbers or single hyphens, starting with a letter.');
        }
        if (in_array($slug, self::RESERVED, true)) {
            throw new \InvalidArgumentException('That name is reserved. Pick another.');
        }
        $upper = strtoupper($slug);
        if (User::query()->where('naara_id', $upper)->exists() || BrandPartner::query()->where('card_code', $upper)->exists()) {
            throw new \InvalidArgumentException('That name is too close to a Naara ID or short code. Pick another.');
        }
        if (BrandPartner::query()->where('card_slug', $slug)->whereKeyNot($brand->id)->exists()) {
            throw new \InvalidArgumentException('That address is already taken.');
        }
        $brand->forceFill(['card_slug' => $slug])->save();
    }

    /** Ready-to-paste lines for a profile bio, each within the platform's usual limit, using the short link. @return list<array{label:string,limit:int,text:string}> */
    public function snippets(BrandPartner $brand): array
    {
        $url = $this->shortUrl($brand);
        $name = (string) $brand->brand_name;
        $make = fn (string $label, int $limit, string $lead) => ['label' => $label, 'limit' => $limit, 'text' => $this->fit($lead, $url, $limit)];

        return [
            $make('Instagram bio', 150, "{$name} on Naara. Follow, connect and earn credits:"),
            $make('X / Twitter bio', 160, "{$name} is on Naara. Connect with us:"),
            $make('TikTok bio', 80, "{$name} on Naara:"),
            $make('LinkedIn headline or About', 220, "Find {$name} on Naara, where brands and people connect:"),
            $make('WhatsApp status', 200, "{$name} is now on Naara. Connect with us here:"),
        ];
    }

    /** The lead line trimmed so lead + space + link never goes over the limit; the link is never cut. */
    private function fit(string $lead, string $url, int $limit): string
    {
        $room = $limit - mb_strlen($url) - 1;

        return $room >= 12 ? rtrim(mb_strimwidth($lead, 0, $room, '…')).' '.$url : $url;
    }

    public function code(BrandPartner $brand): ?string
    {
        $owner = $brand->owner;
        if (! $owner instanceof User) {
            return null;
        }
        try {
            return NaaraId::for($owner);
        } catch (\Throwable) {
            return null;
        }
    }

    /** The listed brand behind a card address, or null (an unknown, unlisted or paused brand all look the same: absent). */
    /** Count one view, QR scan or contact-card save for today. The owner's own visits, crawlers and HEAD requests are not counted. Never throws into the page. */
    public function record(BrandPartner $brand, string $metric): void
    {
        try {
            $r = request();
            $ua = strtolower((string) $r->userAgent());
            if (! in_array($metric, ['view', 'qr_scan', 'vcard'], true) || $r->isMethod('HEAD') || ($r->user()?->id !== null && (int) $r->user()->id === (int) $brand->owner_user_id)
                || ($ua === '' || preg_match('/bot|crawl|spider|slurp|preview|facebookexternalhit|whatsapp|curl|wget|python|headless/', $ua))) {
                return;
            }
            $where = ['brand_partner_id' => $brand->id, 'day' => now()->toDateString(), 'metric' => $metric];
            \Illuminate\Support\Facades\DB::table('businesscard_stats')->insertOrIgnore($where + ['total' => 0]);
            \Illuminate\Support\Facades\DB::table('businesscard_stats')->where($where)->increment('total');
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** @return array{view: int, qr_scan: int, vcard: int} totals over the last N days (the owner's own panel). */
    public function stats(BrandPartner $brand, int $days = 30): array
    {
        $rows = \Illuminate\Support\Facades\DB::table('businesscard_stats')->where('brand_partner_id', $brand->id)->where('day', '>=', now()->subDays($days - 1)->toDateString())->selectRaw('metric, sum(total) as n')->groupBy('metric')->pluck('n', 'metric');

        return ['view' => (int) ($rows['view'] ?? 0), 'qr_scan' => (int) ($rows['qr_scan'] ?? 0), 'vcard' => (int) ($rows['vcard'] ?? 0)];
    }

    public function find(string $code): ?BrandPartner
    {
        $raw = trim($code);
        $upper = strtoupper($raw);
        if (! preg_match('/^[0-9A-Z*~$=-]{3,30}$/', $upper)) {
            return null;
        }
        // 1. The permanent Naara ID address, 2. the six-character short code, 3. the readable address. All three reach the same listed brand.
        $owner = preg_match('/^[0-9A-Z*~$=-]{4,16}$/', $upper) ? User::where('naara_id', $upper)->first() : null;
        if ($owner) {
            return BrandPartner::listed()->where('owner_user_id', $owner->id)->first();
        }
        if (strlen($upper) === 6 && ($byCode = BrandPartner::listed()->where('card_code', $upper)->first())) {
            return $byCode;
        }

        return BrandPartner::listed()->where('card_slug', strtolower($raw))->first();
    }

    /** @return array{name:string, blurb:?string, category:?string, logo:?string, hero:?string, color:string, followers:int, pro:bool, links:list<array{label:string,url:string,host:string}>} */
    public function fields(BrandPartner $brand): array
    {
        $links = BrandLink::approved()->where('brand_partner_id', $brand->id)->orderBy('sort_order')->orderBy('id')->get()
            ->map(fn (BrandLink $l) => ['label' => $l->label, 'url' => $l->normalized_url ?: $l->url, 'host' => $l->host])->values()->all();

        return [
            'name' => (string) $brand->brand_name,
            'blurb' => $brand->short_description ?: null,
            'category' => $brand->category ?: null,
            'logo' => $brand->logo_path ?: null,
            'hero' => $brand->hero_image_path ?: $brand->fallback_image ?: null,
            'color' => preg_match('/^#[0-9a-fA-F]{6}$/', (string) $brand->background_color) ? $brand->background_color : '#0A6E6E',
            'followers' => (int) $brand->naara_followers_count,
            'pro' => $brand->isNaaraPro(),
            'links' => $links,
        ];
    }

    /** A print-ready A6 card (PDF): brand colour band, logo, name, blurb, approved links and the QR code (which carries the scan marker). Rendered from stored data only, no remote fetches. */
    public function pdf(BrandPartner $brand): string
    {
        $f = $this->fields($brand);
        $url = (string) $this->url($brand);

        return \Barryvdh\DomPDF\Facade\Pdf::setOptions(['isRemoteEnabled' => false, 'isHtml5ParserEnabled' => true])->setPaper('a6', 'portrait')->loadView('pdf.businesscard', [
            'f' => $f, 'url' => $url, 'qr' => 'data:image/png;base64,'.base64_encode($this->png($url.'?s=qr', 520)), 'logo' => $this->localImageDataUri($f['logo']), 'hero' => $this->localImageDataUri($f['hero']),
        ])->output();
    }

    /** A PNG data URI for a brand image that lives on this server (WebP is converted, because the PDF engine cannot read it). Remote or unreadable images give null. */
    private function localImageDataUri(?string $path): ?string
    {
        if (! $path || ! str_starts_with($path, '/') || str_contains($path, '..') || ! function_exists('imagecreatefromstring')) {
            return null;
        }
        $file = public_path(ltrim($path, '/'));
        if (! is_file($file) || filesize($file) > 4 * 1048576) {
            return null;
        }
        $im = @imagecreatefromstring((string) file_get_contents($file));
        if (! $im) {
            return null;
        }
        $w = imagesx($im);
        $h = imagesy($im);
        if ($w > 900) {
            $im = imagescale($im, 900) ?: $im;
        }
        ob_start();
        imagepng($im);

        return 'data:image/png;base64,'.base64_encode((string) ob_get_clean());
    }

    public function svg(string $url, int $size = 320): string
    {
        return (new SvgWriter)->write($this->qr($url, $size))->getString();
    }

    public function png(string $url, int $size = 640): string
    {
        return (new PngWriter)->write($this->qr($url, $size))->getString();
    }

    /** A contact card (vCard 3.0) that saves the brand into a phone's address book: name, organisation, card page and approved links. No phone or email is ever included. */
    public function vcard(BrandPartner $brand): string
    {
        $f = $this->fields($brand);
        $esc = fn (string $s) => addcslashes(str_replace(["\r", "\n"], ' ', $s), ",;\\");
        $lines = ['BEGIN:VCARD', 'VERSION:3.0', 'FN:'.$esc($f['name']), 'ORG:'.$esc($f['name']), 'URL:'.$this->url($brand)];
        foreach ($f['links'] as $l) {
            $lines[] = 'URL;TYPE='.preg_replace('/[^A-Za-z0-9]/', '', $l['label'] ?: 'web').':'.$l['url'];
        }
        if ($f['blurb']) {
            $lines[] = 'NOTE:'.$esc(mb_substr($f['blurb'], 0, 200));
        }
        $lines[] = 'END:VCARD';

        return implode("\r\n", $lines)."\r\n";
    }

    private function qr(string $data, int $size): QrCode
    {
        return new QrCode(data: $data, errorCorrectionLevel: ErrorCorrectionLevel::Medium, size: $size, margin: 12);
    }
}
