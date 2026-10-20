<?php

namespace App\Services\Omni\Kb;

use App\Models\Omni\OmniKbChunk;
use App\Models\Omni\OmniKbSource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The workspace's knowledge base (MASTER-ONLY, blueprint section 5): FAQs, pasted text and fetched web pages, cut into small chunks and searched with
 * MySQL FULLTEXT (ADR S6), falling back to keyword scoring where FULLTEXT does not exist. Every read and write is scoped to the workspace.
 */
class KnowledgeBase
{
    public const MAX_SOURCES = 100;

    public const MAX_VOICE_SOURCES = 3;

    public function __construct(private readonly SafeFetcher $fetcher, private readonly ?DocumentReader $reader = null)
    {
    }

    /** @throws \RuntimeException */
    public function addText(int $ws, string $kind, string $title, string $text): OmniKbSource
    {
        $text = trim($text);
        if ($text === '' || mb_strlen($text) > 60000) {
            throw new \RuntimeException('Add some text, up to 60,000 characters.');
        }
        $this->room($ws);
        $s = OmniKbSource::create(['workspace_id' => $ws, 'kind' => $kind === 'faq' ? 'faq' : 'text', 'title' => mb_substr(trim($title) ?: 'Untitled', 0, 160), 'content' => $text, 'status' => 'ready', 'fetched_at' => now()]);
        $this->reindex($s);

        return $s;
    }

    /** @throws \RuntimeException */
    public function addUrl(int $ws, string $url): OmniKbSource
    {
        $this->room($ws);
        $url = trim($url);
        $this->fetcher->assertPublic($url);
        $s = OmniKbSource::create(['workspace_id' => $ws, 'kind' => 'url', 'url' => mb_substr($url, 0, 500), 'title' => mb_substr(parse_url($url, PHP_URL_HOST).' '.(parse_url($url, PHP_URL_PATH) ?: ''), 0, 160), 'status' => 'fetching']);
        $this->refresh($s);

        return $s->fresh();
    }

    /** Re-reads a url source. A failure is stored on the source, never thrown into the page. */
    public function refresh(OmniKbSource $s): void
    {
        if ($s->kind !== 'url') {
            return;
        }
        try {
            $html = $this->fetcher->get((string) $s->url);
            $title = preg_match('~<title[^>]*>(.*?)</title>~is', $html, $m) ? trim(html_entity_decode(strip_tags($m[1]))) : null;
            $s->forceFill(['content' => $this->plain($html), 'title' => $title ? mb_substr($title, 0, 160) : $s->title, 'status' => 'ready', 'error' => null, 'fetched_at' => now()])->save();
            $this->reindex($s);
        } catch (\RuntimeException $e) {
            $s->forceFill(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 240)])->save();
        }
    }

    /**
     * Learn from an uploaded .txt, .md or .docx. Everything is validated first (see DocumentReader), a document already learned by this workspace is refused, the text is cut into
     * passages, and a learning check proves the assistant can actually find what is in the file. 'voice' files are style samples: kept, never searched as facts.
     *
     * @throws DocumentRejected
     */
    public function addDocument(int $ws, string $path, string $originalName, string $role = 'facts'): OmniKbSource
    {
        $reader = $this->reader ?? new DocumentReader;
        $role = $role === 'voice' ? 'voice' : 'facts';
        try {
            $this->room($ws);
        } catch (\RuntimeException $e) {
            throw new DocumentRejected($e->getMessage());
        }
        $doc = $reader->read($path, $originalName);
        $hash = hash('sha256', mb_strtolower(preg_replace('/\s+/u', ' ', $doc['text']) ?? $doc['text']));
        if (OmniKbSource::query()->forWorkspace($ws)->where('file_hash', $hash)->exists()) {
            throw new DocumentRejected('Your assistant has already learned this document.');
        }
        if ($role === 'voice') {
            if (OmniKbSource::query()->forWorkspace($ws)->where('role', 'voice')->count() >= self::MAX_VOICE_SOURCES) {
                throw new DocumentRejected('You can keep up to '.self::MAX_VOICE_SOURCES.' brand voice files. Remove one first.');
            }
            $doc['text'] = mb_substr($doc['text'], 0, 6000);
        }
        $facts = DocumentFacts::extract($doc['text']);
        $title = Str::limit(trim((string) preg_replace('/[_-]+/', ' ', pathinfo($originalName, PATHINFO_FILENAME))) ?: 'Document', 150, '');
        $s = OmniKbSource::create(['workspace_id' => $ws, 'kind' => 'file', 'role' => $role, 'title' => $title, 'content' => $doc['text'], 'status' => 'ready', 'fetched_at' => now(),
            'file_name' => mb_substr(basename($originalName), 0, 190), 'file_hash' => $hash, 'word_count' => $facts['words'], 'facts' => $facts, 'notes' => $doc['notes'] ?: null]);
        if ($role === 'facts') {
            $this->reindex($s);
            $this->recallCheck($s);
        }

        return $s->fresh();
    }

    /**
     * The learning check: take passages from the source, build a question out of each passage's own distinctive words, and test that a search of THIS workspace brings that exact
     * passage back. Passing everything means the document was split and indexed so the assistant can find what is in it. Stores and returns passed/total.
     *
     * @return array{passed:int,total:int,failed:list<string>}
     */
    public function recallCheck(OmniKbSource $s): array
    {
        $ws = (int) $s->workspace_id;
        $chunks = OmniKbChunk::query()->forWorkspace($ws)->where('source_id', $s->id)->orderBy('ord')->get();
        $total = 0;
        $passed = 0;
        $failed = [];
        if ($chunks->isNotEmpty()) {
            $step = max(1, (int) floor($chunks->count() / 5));
            foreach ($chunks->values()->filter(fn ($c, $i) => $i % $step === 0)->take(5) as $c) {
                $q = $this->probeQuery($c->content);
                if ($q === '') {
                    continue;
                }
                $total++;
                $hit = collect($this->search($ws, $q, 4))->contains(fn ($r) => (int) $r['id'] === (int) $c->id);
                $hit ? $passed++ : $failed[] = Str::limit($c->content, 80);
            }
        }
        $s->forceFill(['check_passed' => $passed, 'check_total' => $total, 'checked_at' => now(), 'chunk_count' => $chunks->count()])->save();

        return ['passed' => $passed, 'total' => $total, 'failed' => $failed];
    }

    /** A search phrase made from the 6 longest distinctive words of a passage: what a customer asking about it would plausibly say. */
    private function probeQuery(string $passage): string
    {
        $terms = $this->terms($passage);
        usort($terms, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));

        return implode(' ', array_slice($terms, 0, 6));
    }

    public function remove(int $ws, int $id): void
    {
        $s = OmniKbSource::query()->forWorkspace($ws)->find($id);
        if ($s) {
            OmniKbChunk::query()->forWorkspace($ws)->where('source_id', $s->id)->delete();
            $s->delete();
        }
    }

    private function room(int $ws): void
    {
        $max = DocumentLimits::maxSources();
        if (OmniKbSource::query()->forWorkspace($ws)->count() >= $max) {
            throw new \RuntimeException('You can keep up to '.$max.' knowledge sources. Remove one first.');
        }
    }

    /** Page markup to readable text: scripts, styles and navigation chrome dropped, whitespace collapsed. */
    public function plain(string $html): string
    {
        $html = preg_replace('~<(script|style|noscript|svg|nav|footer|header|form)\b.*?</\1>~is', ' ', $html) ?? $html;
        $html = preg_replace('~<(br|/p|/div|/li|/h[1-6]|/tr)\s*/?>~i', "\n", $html) ?? $html;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("~[ \t]+~", ' ', $text) ?? $text;
        $text = preg_replace("~\n\s*\n+~", "\n\n", $text) ?? $text;

        return trim($text);
    }

    /** @return list<string> pieces of about 600 characters, cut on paragraph and sentence boundaries */
    public function chunk(string $text): array
    {
        $out = [];
        $buf = '';
        foreach (preg_split("~\n\s*\n~", trim($text)) ?: [] as $para) {
            foreach (preg_split('~(?<=[.!?])\s+~u', trim($para)) ?: [] as $sentence) {
                if ($buf !== '' && mb_strlen($buf) + mb_strlen($sentence) > 600) {
                    $out[] = $buf;
                    $buf = '';
                }
                $buf .= ($buf === '' ? '' : ' ').$sentence;
            }
            $buf .= "\n";
        }
        if (trim($buf) !== '') {
            $out[] = trim($buf);
        }

        return array_values(array_filter(array_map('trim', $out), fn ($c) => $c !== ''));
    }

    public function reindex(OmniKbSource $s): void
    {
        DB::transaction(function () use ($s) {
            OmniKbChunk::query()->forWorkspace((int) $s->workspace_id)->where('source_id', $s->id)->delete();
            foreach ($this->chunk((string) $s->content) as $i => $c) {
                OmniKbChunk::create(['workspace_id' => $s->workspace_id, 'source_id' => $s->id, 'ord' => $i, 'content' => $c]);
            }
        });
    }

    /**
     * Best passages for a question, always inside one workspace.
     *
     * @return list<array{id:int,source_id:int,title:string,content:string,score:float}>
     */
    public function search(int $ws, string $query, int $limit = 4): array
    {
        $terms = $this->terms($query);
        if ($terms === []) {
            return [];
        }
        $rows = DB::getDriverName() === 'mysql' ? $this->fulltext($ws, $terms, $limit) : [];
        if ($rows === []) {
            $rows = $this->scored($ws, $terms, $limit);
        }
        $titles = OmniKbSource::query()->forWorkspace($ws)->whereIn('id', array_column($rows, 'source_id'))->pluck('title', 'id');

        return array_map(fn ($r) => $r + ['title' => (string) ($titles[$r['source_id']] ?? '')], $rows);
    }

    /** @return list<string> */
    private function terms(string $q): array
    {
        $stop = ['the', 'and', 'for', 'you', 'your', 'are', 'can', 'how', 'what', 'when', 'does', 'with', 'this', 'that', 'have', 'has', 'was', 'will', 'from', 'but', 'not', 'any', 'get', 'please', 'hello', 'thanks'];
        preg_match_all('/[\p{L}\p{N}]{3,}/u', mb_strtolower($q), $m);

        return array_values(array_unique(array_diff($m[0], $stop)));
    }

    private function fulltext(int $ws, array $terms, int $limit): array
    {
        $against = implode(' ', array_map(fn ($t) => $t.'*', array_map(fn ($t) => preg_replace('/[^\p{L}\p{N}]/u', '', $t), $terms)));
        $rows = DB::select('SELECT id, source_id, content, MATCH(content) AGAINST (? IN BOOLEAN MODE) AS score FROM omni_kb_chunks WHERE workspace_id = ? AND MATCH(content) AGAINST (? IN BOOLEAN MODE) ORDER BY score DESC LIMIT '.(int) $limit, [$against, $ws, $against]);

        return array_map(fn ($r) => ['id' => (int) $r->id, 'source_id' => (int) $r->source_id, 'content' => (string) $r->content, 'score' => (float) $r->score], $rows);
    }

    private function scored(int $ws, array $terms, int $limit): array
    {
        $q = OmniKbChunk::query()->forWorkspace($ws)->where(function ($w) use ($terms) {
            foreach ($terms as $t) {
                $w->orWhere('content', 'like', '%'.str_replace(['%', '_'], ['\\%', '\\_'], $t).'%');
            }
        })->limit(200)->get();
        $scored = [];
        foreach ($q as $c) {
            $hay = mb_strtolower($c->content);
            $score = 0;
            foreach ($terms as $t) {
                $score += substr_count($hay, $t);
            }
            if ($score > 0) {
                $scored[] = ['id' => (int) $c->id, 'source_id' => (int) $c->source_id, 'content' => (string) $c->content, 'score' => (float) $score];
            }
        }
        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($scored, 0, $limit);
    }
}
