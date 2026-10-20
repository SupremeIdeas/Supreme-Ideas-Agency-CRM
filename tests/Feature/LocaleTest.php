<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Locale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Localization Phase A: locale resolution reuses the existing (previously
 * dead) users.language column, mirrors LocaleCurrency's priority chain
 * exactly, and only ever resolves to a locale with real, shipped
 * translations — never a "coming soon" one, even if session/DB data is
 * tampered with directly.
 */
class LocaleTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        session()->forget(Locale::SESSION_KEY);
        parent::tearDown();
    }

    public function test_resolves_to_english_with_no_signals_at_all(): void
    {
        $this->assertSame('en', Locale::resolve());
    }

    public function test_an_unshipped_locale_never_resolves_even_from_a_tampered_session(): void
    {
        session([Locale::SESSION_KEY => 'xx']);

        $this->assertSame('en', Locale::resolve());
    }

    public function test_session_choice_wins_over_the_users_saved_language(): void
    {
        $user = User::factory()->create(['language' => 'en']);
        session([Locale::SESSION_KEY => 'en']);

        $this->assertSame('en', Locale::resolve($user));
    }

    public function test_a_tampered_user_language_column_never_resolves_if_unavailable(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['language' => 'xx'])->save();

        $this->assertSame('en', Locale::resolve($user));
    }

    public function test_country_code_guess_is_ignored_while_that_locale_is_unavailable(): void
    {
        // Mozambique maps to Portuguese, which is beta: never auto-guessed from a country.
        $user = User::factory()->create(['language' => null, 'country_code' => 'MZ']);

        $this->assertSame('en', Locale::resolve($user));
    }

    /** UX/localization/platform-reach blueprint Phase H, Track 1. */
    public function test_country_code_guess_now_resolves_to_french_now_that_it_has_shipped(): void
    {
        $user = User::factory()->create(['language' => null, 'country_code' => 'SN']);

        $this->assertSame('fr', Locale::resolve($user));
    }

    public function test_choose_persists_to_session_and_the_users_language_column(): void
    {
        $user = User::factory()->create();

        $resolved = Locale::choose($user, 'en');

        $this->assertSame('en', $resolved);
        $this->assertSame('en', session(Locale::SESSION_KEY));
        $this->assertSame('en', $user->fresh()->language);
    }

    public function test_choose_downgrades_an_unavailable_locale_to_english(): void
    {
        $user = User::factory()->create();

        $resolved = Locale::choose($user, 'xx');

        $this->assertSame('en', $resolved);
        $this->assertSame('en', $user->fresh()->language);
    }

    public function test_available_only_lists_shipped_locales(): void
    {
        $available = Locale::available();

        $this->assertArrayHasKey('en', $available);
        $this->assertArrayHasKey('fr', $available);
        $this->assertArrayHasKey('ar', $available);
        $this->assertArrayHasKey('sw', $available);
        foreach (['pt', 'ha', 'yo', 'ig', 'zu', 'am', 'es', 'de', 'hi', 'zh'] as $code) {
            $this->assertArrayHasKey($code, $available);
            $this->assertTrue($available[$code]['beta']);
        }
    }

    public function test_options_lists_the_full_roadmap_for_an_honest_switcher(): void
    {
        $options = Locale::options();

        $this->assertArrayHasKey('pt', $options);
        $this->assertTrue($options['pt']['available']);
        $this->assertTrue($options['ar']['available']);
        $this->assertTrue($options['ar']['rtl']);
    }

    public function test_the_middleware_applies_the_resolved_locale_to_the_request(): void
    {
        $user = User::factory()->create(['language' => 'en']);

        $this->actingAs($user)->get(route('account'))->assertOk();

        $this->assertSame('en', app()->getLocale());
    }

    public function test_profile_page_lets_a_user_choose_from_the_available_locales(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(\App\Livewire\Profile::class)
            ->assertSet('language', 'en')
            ->set('language', 'en')
            ->call('save');

        $this->assertSame('en', $user->fresh()->language);
    }

    public function test_profile_page_rejects_a_language_that_has_not_shipped(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(\App\Livewire\Profile::class)
            ->set('language', 'xx')
            ->call('save')
            ->assertHasErrors(['language']);
    }

    /** UX/localization/platform-reach blueprint Phase H, Track 1. */
    public function test_profile_page_accepts_each_newly_shipped_locale(): void
    {
        $user = User::factory()->create();

        foreach (['fr', 'ar', 'sw'] as $locale) {
            Livewire::actingAs($user)->test(\App\Livewire\Profile::class)
                ->set('language', $locale)
                ->call('save')
                ->assertHasNoErrors();

            $this->assertSame($locale, $user->fresh()->language);
        }
    }

    public function test_the_html_tag_carries_rtl_direction_only_for_arabic(): void
    {
        $user = User::factory()->create(['language' => 'ar']);

        $html = $this->actingAs($user)->get(route('account'))->getContent();
        $this->assertMatchesRegularExpression('/<html[^>]*\sdir="rtl"/', $html);

        // The language picker lists Arabic with its own dir="rtl" span, so
        // only the <html> element decides the page direction.
        $userEn = User::factory()->create(['language' => 'en']);
        $htmlEn = $this->actingAs($userEn)->get(route('account'))->getContent();
        $this->assertMatchesRegularExpression('/<html[^>]*\sdir="ltr"/', $htmlEn);
        $this->assertDoesNotMatchRegularExpression('/<html[^>]*\sdir="rtl"/', $htmlEn);
    }

    public function test_a_french_page_renders_real_translated_strings(): void
    {
        $user = User::factory()->create(['language' => 'fr']);

        $html = $this->actingAs($user)->get(route('account'))->getContent();
        $this->assertStringContainsString('Compte et confidentialité', $html);
    }

    /**
     * Every key shipped in lang/en/*.php must exist (and be non-empty) in
     * each shipped translation, recursively — a silently-dropped key falls
     * back to the raw key name at runtime rather than failing loudly.
     */
    public function test_every_shipped_locale_has_every_english_key(): void
    {
        $englishFiles = glob(lang_path('en/*.php'));
        $this->assertNotEmpty($englishFiles);

        foreach (array_keys(Locale::available()) as $locale) {
            if ($locale === 'en') {
                continue;
            }
            foreach ($englishFiles as $file) {
                $basename = basename($file);
                $localeFile = lang_path("{$locale}/{$basename}");
                $this->assertFileExists($localeFile, "Missing {$locale}/{$basename}");

                $english = require $file;
                $translated = require $localeFile;
                $this->assertMissingKeysRecursively($english, $translated, "{$locale}/{$basename}");
            }
        }
    }

    private function assertMissingKeysRecursively(array $english, array $translated, string $context, string $path = ''): void
    {
        foreach ($english as $key => $value) {
            $currentPath = $path === '' ? (string) $key : "{$path}.{$key}";
            $this->assertArrayHasKey($key, $translated, "{$context}: missing key '{$currentPath}'");

            if (is_array($value)) {
                $this->assertIsArray($translated[$key], "{$context}: '{$currentPath}' should be an array");
                $this->assertMissingKeysRecursively($value, $translated[$key], $context, $currentPath);
            } else {
                $this->assertNotSame('', trim((string) $translated[$key]), "{$context}: empty value for '{$currentPath}'");
            }
        }
    }

    /** A translation that drops or renames a :placeholder / {placeholder} breaks the sentence at runtime. */
    public function test_every_locale_keeps_the_english_placeholders(): void
    {
        $flatten = fn (array $a) => \App\Console\Commands\I18nExport::flatten($a);
        $tokens = fn (string $s) => (function () use ($s) {
            preg_match_all('/:[a-z_]+|\{[a-z_]+\}/i', $s, $m);
            sort($m[0]);

            return $m[0];
        })();

        foreach (array_keys(Locale::available()) as $locale) {
            if ($locale === 'en') {
                continue;
            }
            foreach (glob(lang_path('en/*.php')) as $file) {
                $base = basename($file);
                $en = $flatten(require $file);
                $tr = $flatten(require lang_path("{$locale}/{$base}"));
                foreach ($en as $key => $english) {
                    $this->assertSame($tokens($english), $tokens($tr[$key] ?? ''), "{$locale}/{$base}: placeholders differ for '{$key}'");
                }
            }
        }
    }

    public function test_site_translation_files_only_use_known_keys_and_keep_html_tags(): void
    {
        $known = [];
        foreach (\App\Support\SiteContent::defaults() as $page => $sections) {
            foreach (\App\Console\Commands\I18nExport::flatten($sections, $page.'.') as $k => $v) {
                $known[$k] = $v;
            }
        }

        foreach (array_keys(Locale::available()) as $locale) {
            if ($locale === 'en') {
                continue;
            }
            $file = lang_path("{$locale}/site.php");
            $this->assertFileExists($file, "{$locale}/site.php");
            foreach ((array) require $file as $key => $text) {
                $this->assertArrayHasKey($key, $known, "{$locale}/site.php has unknown key {$key}");
                preg_match_all('/<\/?[a-z]+/i', $known[$key], $a);
                preg_match_all('/<\/?[a-z]+/i', $text, $b);
                $this->assertSame($a[0], $b[0], "{$locale}/site.php: HTML tags differ for {$key}");
            }
        }
    }

    public function test_guest_can_switch_language_and_it_persists_in_a_cookie(): void
    {
        $this->post(route('locale.switch'), ['locale' => 'es'])->assertRedirect()->assertCookie(Locale::COOKIE_KEY);

        $this->post(route('locale.switch'), ['locale' => 'xx'])->assertSessionHasErrors('locale');
    }

    public function test_switching_language_saves_it_on_a_members_profile(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('locale.switch'), ['locale' => 'de'])->assertRedirect();

        $this->assertSame('de', $user->fresh()->language);
    }

    public function test_the_switcher_chip_shows_the_current_code_and_lists_every_language(): void
    {
        $html = $this->get('/')->getContent();
        $this->assertStringContainsString('nx-lang-chip', $html);
        $this->assertMatchesRegularExpression('/nx-lang-chip.*?<span>EN<\/span>/s', $html);
        foreach (['Français', 'Yorùbá', 'isiZulu', 'አማርኛ', '简体中文'] as $native) {
            $this->assertStringContainsString($native, $html);
        }
    }
}
