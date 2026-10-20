<?php

namespace Tests\Feature\N2N;

use App\Support\N2N\ChatFormat;
use Tests\TestCase;

/** @mentions in a 1:1 chat are a highlight only: escape-first, never stored, never a notification path (MASTER-ONLY). */
class MentionsTest extends TestCase
{
    public function test_tokens_are_the_naara_id_the_full_name_and_the_first_name(): void
    {
        $this->assertEqualsCanonicalizing(['NA-123', 'Ada Nwosu', 'Ada'], ChatFormat::mentionTokens('NA-123', 'Ada  Nwosu'));
        $this->assertSame([], ChatFormat::mentionTokens(null, ''));
    }

    public function test_a_mention_is_highlighted_longest_token_first_and_only_as_a_whole_word(): void
    {
        $t = ChatFormat::mentionTokens('NA-123', 'Ada Nwosu');
        $this->assertStringContainsString('<span class="ncf-mention">@Ada Nwosu</span>', (string) ChatFormat::render('hi @Ada Nwosu, ready?', $t));
        $this->assertStringContainsString('<span class="ncf-mention">@ada</span>', (string) ChatFormat::render('hi @ada', $t));
        $this->assertStringNotContainsString('ncf-mention', (string) ChatFormat::render('hi @adaeze', $t), 'a longer word is not a mention');
        $this->assertStringNotContainsString('ncf-mention', (string) ChatFormat::render('mail me a@ada.com', $t), 'an e-mail-like token is not a mention');
        $this->assertStringContainsString('ncf-mention', (string) ChatFormat::render('**@Ada** look', $t), 'works inside formatting');
    }

    public function test_user_text_cannot_inject_markup_through_a_mention_or_a_name(): void
    {
        $t = ChatFormat::mentionTokens(null, '<img src=x onerror=alert(1)> Bob');
        $html = (string) ChatFormat::render('hello @<img src=x onerror=alert(1)> and <b>x</b>', $t);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringNotContainsString('<b>', $html);
    }

    public function test_without_tokens_rendering_is_unchanged(): void
    {
        $this->assertSame((string) ChatFormat::render('hi @ada'), (string) ChatFormat::render('hi @ada', []));
    }
}
