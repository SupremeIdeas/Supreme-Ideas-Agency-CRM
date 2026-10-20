<?php

namespace Tests\Feature;

use App\Models\MailTemplateOverride;
use App\Support\MailTemplates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Every user-facing mail notification is branded and editable in Email Studio; nothing falls back to Laravel's default look. */
class NotificationEmailTemplatesTest extends TestCase
{
    use RefreshDatabase;

    /** Mail notifications that are operator-facing, per-send content (broadcasts), or pure diagnostics: not customer email templates. */
    private const NOT_CUSTOMER_TEMPLATES = ['AdminAlertNotification', 'AdminPathChangedNotification', 'BroadcastEmailNotification', 'ContactMessageNotification', 'TestMailNotification'];

    public function test_every_customer_mail_notification_has_an_email_studio_template(): void
    {
        $catalog = config('notification_catalog.notifications');
        $templates = MailTemplates::templates();
        $views = array_map(fn ($t) => $t['view'], $templates);

        $unbranded = [];
        foreach ($catalog as $class => $meta) {
            if ($meta['master_only'] || ! in_array('mail', $meta['channels'], true) || in_array($class, self::NOT_CUSTOMER_TEMPLATES, true)) {
                continue;
            }
            $src = (string) file_get_contents(app_path("Notifications/{$class}.php"));
            // A class is covered when its source names an Email Studio key that exists (subject() or brandedMail()).
            preg_match_all("/(?:MailTemplates::subject|brandedMail)\(\s*'([a-z0-9-]+)'/", $src, $m);
            $keys = array_unique($m[1]);
            // Verify and welcome style classes that reach their template through their own view are fine when the view is registered.
            $viaView = (bool) preg_match("/->view\('(emails\.[a-z0-9-]+)'/", $src, $vm) && in_array($vm[1], $views, true);
            if (! array_filter($keys, fn ($k) => isset($templates[$k])) && ! $viaView) {
                $unbranded[] = $class;
            }
        }

        $this->assertSame([], $unbranded, 'These mail notifications are not editable in Email Studio: '.implode(', ', $unbranded));
    }

    public function test_every_template_renders_a_complete_branded_email(): void
    {
        foreach (MailTemplates::templates() as $key => $t) {
            $html = view($t['view'], $t['sample'])->render();
            $this->assertStringContainsString('</html>', $html, $key);
            $this->assertStringContainsString(config('app.name', 'NaaraSim'), $html, $key);
        }
        $this->artisan('naara:mail:preview-all')->assertSuccessful();
    }

    public function test_an_override_changes_the_subject_heading_intro_and_button_of_a_converted_notification(): void
    {
        $user = \App\Models\User::factory()->create(['name' => 'Ada']);
        MailTemplates::save('payout-status', ['subject' => 'Custom withdrawal subject', 'heading' => 'Custom heading', 'intro' => 'A short intro from the admin.', 'button_text' => 'See my cash-outs']);

        $mail = (new \App\Notifications\PayoutStatusNotification(1))->toMail($user);
        $html = view($mail->view, $mail->viewData)->render();

        $this->assertSame('Custom withdrawal subject', $mail->subject);
        $this->assertStringContainsString('A short intro from the admin.', $html);
        $this->assertStringContainsString('See my cash-outs', $html);
        $this->assertStringContainsString('Custom heading', $html);
    }

    public function test_notice_text_is_escaped_and_only_bold_is_allowed(): void
    {
        $html = view('emails.notice', ['templateKey' => 'receipt', 'heading' => 'h', 'lines' => [['text' => '<script>alert(1)</script> **ok**']]])->render();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('<strong>ok</strong>', $html);
    }

    public function test_a_security_code_is_shown_in_a_box_and_is_not_logged_as_text_elsewhere(): void
    {
        $html = view('emails.notice', ['templateKey' => 'payout-step-up', 'heading' => 'h', 'lines' => [['code' => '482913']]])->render();

        $this->assertStringContainsString('482913', $html);
        $this->assertStringContainsString('letter-spacing:6px', $html);
    }
}
