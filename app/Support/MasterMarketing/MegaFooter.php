<?php

namespace App\Support\MasterMarketing;

use App\Support\SiteChrome;
use Illuminate\Support\Facades\Route;

/**
 * MASTER ONLY. The link groups of the Naara mega footer. Built from route names so a link can never point at a page that does not exist (a missing route simply drops out),
 * and the admin's own footer columns (Admin > Site chrome) are appended as an extra group when they differ from the defaults, so nothing the admin added is lost.
 */
final class MegaFooter
{
    /** @return list<array{heading: string, links: list<array{label: string, url: string}>}> */
    public static function groups(): array
    {
        $r = fn (string $label, string $route, array $params = []) => Route::has($route) ? ['label' => $label, 'url' => route($route, $params)] : null;
        $groups = [
            ['heading' => 'Products', 'links' => [
                $r('eSIM data plans', 'catalogue'), $r('Virtual numbers', 'numbers'), $r('SMS verification', 'numbers'), $r('Internet calls', 'numbers.dialer'),
                $r('Gift cards', 'gift-cards'), $r('Travel handbooks', 'handbooks.store'), $r('Data estimator', 'data-estimator'), $r('Pricing', 'pricing'),
            ]],
            ['heading' => 'The Naara family', 'links' => [
                $r('Naara Chat', 'n2n.landing'), $r('NaaraBrain', 'brain.landing'), $r('Business Suite', 'business'), $r('Naara Gift', 'gift.landing'),
                $r('White Label', 'white-label.landing'), $r('Developers and API', 'developers'), $r('List your brand', 'brand.get-listed'), $r('All products', 'products'),
            ]],
            ['heading' => 'Resources', 'links' => [
                $r('How it works', 'how-it-works'), $r('Help center', 'help.center'), $r('User guides', 'guide'), $r('FAQ', 'faq'),
                $r('Blog', 'blog'), $r('Reviews', 'reviews'), $r('Download the apps', 'download'), $r('System status', 'status'),
            ]],
            ['heading' => 'Company', 'links' => [
                $r('About us', 'about'), $r('Contact', 'contact'), $r('Legal and policies', 'legal'), $r('Terms', 'legal.show', ['slug' => 'terms']),
                $r('Privacy', 'legal.show', ['slug' => 'privacy']), $r('Refunds', 'legal.show', ['slug' => 'refund']), $r('Cookie settings', 'cookie-preferences'),
            ]],
        ];

        $custom = self::customColumns();
        foreach ($groups as &$g) {
            $g['links'] = array_values(array_filter($g['links']));
        }
        unset($g);

        return array_values(array_filter(array_merge($groups, $custom), fn ($g) => $g['links'] !== []));
    }

    /** Columns the admin changed in Site chrome (anything other than the shipped Product and Company defaults). */
    private static function customColumns(): array
    {
        $now = SiteChrome::footerColumns();

        return $now === SiteChrome::defaultColumns() ? [] : array_map(fn ($c) => ['heading' => (string) $c['heading'], 'links' => array_values($c['links'])], $now);
    }

    /** @return list<array{label: string, url: string}> eSIM destinations people ask for most */
    public static function destinations(): array
    {
        if (! Route::has('catalogue')) {
            return [];
        }
        $out = [];
        foreach (['NG' => 'Nigeria', 'GH' => 'Ghana', 'KE' => 'Kenya', 'ZA' => 'South Africa', 'EG' => 'Egypt', 'MA' => 'Morocco', 'GB' => 'United Kingdom', 'US' => 'United States', 'CA' => 'Canada',
            'AE' => 'United Arab Emirates', 'TR' => 'Turkey', 'FR' => 'France', 'DE' => 'Germany', 'ES' => 'Spain', 'IT' => 'Italy', 'IN' => 'India', 'CN' => 'China', 'JP' => 'Japan',
            'TH' => 'Thailand', 'BR' => 'Brazil'] as $iso => $name) {
            $out[] = ['label' => $name, 'url' => route('catalogue', ['view' => 'local', 'country' => $iso])];
        }

        return $out;
    }
}
