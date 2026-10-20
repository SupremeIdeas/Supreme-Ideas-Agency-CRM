<?php

namespace App\Notifications\Concerns;

use App\Support\MailTemplates;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Builds a notification email in the branded shell (emails.notice) with Email Studio overrides applied, instead of Laravel's
 * unbranded default markdown mail. The notification keeps one `toMail()`; only the construction moves here.
 *
 * $lines entries are plain strings (`**bold**` supported, everything is escaped) or ['code' => '123456'] for a boxed code.
 */
trait BrandedMail
{
    /**
     * @param  list<string|array{code: string}>  $lines
     * @param  array{0: string, 1: string}|null  $action  [button text, url]
     */
    protected function brandedMail(string $templateKey, string $defaultSubject, string $heading, ?string $greeting, array $lines, ?array $action = null, ?string $footnote = null): MailMessage
    {
        return (new MailMessage)
            ->subject(MailTemplates::subject($templateKey, $defaultSubject))
            ->view('emails.notice', [
                'templateKey' => $templateKey,
                'heading' => $heading,
                'greeting' => $greeting,
                'lines' => array_map(fn ($l) => is_array($l) ? $l : ['text' => $l], $lines),
                'actionText' => $action[0] ?? null,
                'actionUrl' => $action[1] ?? null,
                'footnote' => $footnote,
            ]);
    }
}
