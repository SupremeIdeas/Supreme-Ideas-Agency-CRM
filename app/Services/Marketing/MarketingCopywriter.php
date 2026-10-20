<?php

namespace App\Services\Marketing;

use App\Services\AI\AnthropicClient;
use App\Support\CopyFields;
use App\Support\MarketingBrief;

/**
 * Turns a page-section (or a whole page's worth of sections) into several fresh
 * copy variations in the brand's own voice, using Claude. It only ever sends and
 * receives the human-readable COPY (via CopyFields) — never a URL, image or
 * structural key — so an applied variation can change words but never break a
 * link or a layout.
 *
 * Admin-triggered and synchronous by design: the admin is actively waiting on the
 * result to review it, so there is nothing to background. It is not a
 * customer-request path and touches no money, so the money-safety "every external
 * call is a queued job" rule (which guards the request cycle) does not apply.
 */
class MarketingCopywriter
{
    public function __construct(private AnthropicClient $ai) {}

    public function enabled(): bool
    {
        return $this->ai->enabled();
    }

    /**
     * N copy variations for one section. Each returned item is a full config blob
     * (structure preserved) with the copy rewritten. Returns [] when the section
     * has no copy to rewrite.
     *
     * @return list<array<string,mixed>>
     */
    public function sectionVariations(string $type, array $config, int $n = 3): array
    {
        $copy = CopyFields::extract($config);
        if ($copy === []) {
            return [];
        }

        $system = $this->systemPrompt();
        $user = "Section type: {$type}\n"
            ."Rewrite the COPY below into {$n} distinct variations, in the brand voice. "
            ."Keep each field roughly its current length and purpose (a headline stays a headline, a CTA stays 1–3 words). "
            ."Do NOT add or remove keys. Return STRICT JSON of the shape "
            .'{"variations":[{"<path>":"<new text>", ...}, ...]} with exactly '."{$n} variations, "
            ."each object using the SAME keys as the input.\n\n"
            ."COPY (JSON path => current text):\n".json_encode($copy, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $data = $this->ai->completeJson($system, [['role' => 'user', 'content' => $user]], 3000);
        $variations = $data['variations'] ?? [];
        if (! is_array($variations)) {
            return [];
        }

        $out = [];
        foreach ($variations as $vary) {
            if (! is_array($vary)) {
                continue;
            }
            // Keep only known paths, then splice onto a clone of the original.
            $clean = array_intersect_key($vary, $copy);
            $out[] = CopyFields::apply($config, $clean);
        }

        return $out;
    }

    /**
     * N copy variations for an announcement's title + body (content/billing/
     * hot-menu blueprint Phase B). A flat pair, not a section config blob —
     * announcements have no structural keys to preserve, only these two
     * free-text fields — so this mirrors sectionVariations()'s AI-call shape
     * without reusing CopyFields, which has nothing to extract here.
     *
     * @return list<array{title:string, body:string}>
     */
    public function announcementVariations(string $title, string $body, int $n = 3): array
    {
        if (trim($title) === '' && trim($body) === '') {
            return [];
        }

        $system = $this->systemPrompt();
        $user = "Rewrite the announcement COPY below into {$n} distinct variations, in the brand voice. "
            ."The title is a short, punchy notification headline; the body is 1-2 sentences. "
            .'Return STRICT JSON of the shape {"variations":[{"title":"...","body":"..."}, ...]} with exactly '
            ."{$n} variations.\n\n"
            ."Current title: {$title}\nCurrent body: {$body}";

        $data = $this->ai->completeJson($system, [['role' => 'user', 'content' => $user]], 1500);
        $variations = $data['variations'] ?? [];
        if (! is_array($variations)) {
            return [];
        }

        $out = [];
        foreach ($variations as $vary) {
            if (! is_array($vary) || ! isset($vary['title'], $vary['body'])) {
                continue;
            }
            $out[] = ['title' => (string) $vary['title'], 'body' => (string) $vary['body']];
        }

        return $out;
    }

    /**
     * N copy variations for a broadcast email's subject + body (content/
     * billing/hot-menu blueprint Phase C). Like announcementVariations(), a
     * flat pair rather than a section config blob — but the body here is
     * rich HTML (Trix output: inline images, links). Sending arbitrary HTML
     * to the model and trusting it to echo valid markup back is fragile, so
     * this only ever rewrites the plain-text COPY (tags stripped before the
     * call) and returns simple paragraphs the admin re-applies — any
     * existing images/links in the body are left alone unless the admin
     * chooses to apply a suggestion, which replaces the body text.
     *
     * @return list<array{subject:string, body:string}>
     */
    public function emailVariations(string $subject, string $bodyHtml, int $n = 3): array
    {
        $plainBody = trim(strip_tags($bodyHtml));
        if (trim($subject) === '' && $plainBody === '') {
            return [];
        }

        $system = $this->systemPrompt();
        $user = "Rewrite the broadcast EMAIL copy below into {$n} distinct variations, in the brand voice. "
            ."The subject is a short, compelling email subject line; the body is a few short plain-text "
            .'paragraphs (no markup — the admin adds images/links separately). Return STRICT JSON of the '
            .'shape {"variations":[{"subject":"...","body":"..."}, ...]} with exactly '
            ."{$n} variations, body paragraphs separated by a blank line.\n\n"
            ."Current subject: {$subject}\nCurrent body:\n{$plainBody}";

        $data = $this->ai->completeJson($system, [['role' => 'user', 'content' => $user]], 2000);
        $variations = $data['variations'] ?? [];
        if (! is_array($variations)) {
            return [];
        }

        $out = [];
        foreach ($variations as $vary) {
            if (! is_array($vary) || ! isset($vary['subject'], $vary['body'])) {
                continue;
            }
            $paragraphs = preg_split('/\n\s*\n/', trim((string) $vary['body'])) ?: [];
            $html = implode('', array_map(
                fn ($p) => '<p>'.e(trim($p)).'</p>',
                array_filter($paragraphs, fn ($p) => trim($p) !== '')
            ));
            $out[] = ['subject' => (string) $vary['subject'], 'body' => $html];
        }

        return $out;
    }

    /**
     * Draft a user-facing changelog entry for one shipped version (content/
     * billing/hot-menu blueprint Phase E) — turns a packager's raw changelog
     * text (which may read like a git log / dev-facing notes) into a short
     * headline summary plus a slightly fuller notes paragraph, both in plain
     * customer-facing language. Returns null when there's nothing to work
     * from or the model returns nothing usable — the admin always falls back
     * to writing it by hand.
     *
     * @return array{summary:string, notes:string}|null
     */
    public function changelogDraft(string $version, string $rawChangelog): ?array
    {
        $rawChangelog = trim($rawChangelog);
        if ($rawChangelog === '') {
            return null;
        }

        $system = $this->systemPrompt();
        $user = "Rewrite the raw update notes below for version {$version} into a customer-facing "
            .'"What\'s New" changelog entry. Explain what changed in plain language — never mention '
            .'file names, git commits, internal class names, or anything a non-technical user wouldn\'t '
            .'understand. Return STRICT JSON of the shape {"summary":"...", "notes":"..."}: "summary" is '
            .'one short headline sentence (max ~120 characters); "notes" is 1-3 short sentences of extra '
            ."detail (or repeat the summary if there's nothing more to add).\n\n"
            ."Raw update notes:\n{$rawChangelog}";

        $data = $this->ai->completeJson($system, [['role' => 'user', 'content' => $user]], 800);
        if (! isset($data['summary'], $data['notes']) || ! is_string($data['summary']) || ! is_string($data['notes'])) {
            return null;
        }

        return ['summary' => trim($data['summary']), 'notes' => trim($data['notes'])];
    }

    private function systemPrompt(): string
    {
        return "You are a senior brand copywriter producing marketing website copy.\n\n"
            .MarketingBrief::forPrompt()."\n\n"
            ."Rules:\n"
            ."- Write in the brand's voice; use the brand name where natural, never a competitor's.\n"
            ."- Punchy, concrete, benefit-led. No clichés, no emoji, no markdown.\n"
            ."- Respect each field's role and length; never invent links, prices, or claims.\n"
            ."- Output ONLY the requested JSON, nothing else.";
    }
}
