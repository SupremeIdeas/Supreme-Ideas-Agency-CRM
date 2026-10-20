<?php

namespace App\Support\Verify\Checks;

use App\Support\MailSettings;
use App\Support\Verify\Check;
use App\Support\Verify\CheckResult;

class EmailSecretsAreMasked implements Check
{
    public function area(): string
    {
        return 'email';
    }

    public function name(): string
    {
        return 'Mail settings and secret masking';
    }

    public function run(): CheckResult
    {
        $preview = MailSettings::passwordPreview();
        if ($preview !== null && $preview !== str_repeat('•', 8)) {
            return CheckResult::fail('The saved SMTP password preview reveals part of the secret.');
        }
        if (! MailSettings::isConfigured()) {
            return CheckResult::warn('No real mail transport is configured (log/array), so customers get no email.', ['mailer' => MailSettings::get('mailer', 'log')]);
        }

        return CheckResult::pass('A mail transport is configured and the saved password is fully masked.', ['mailer' => MailSettings::get('mailer')]);
    }
}
