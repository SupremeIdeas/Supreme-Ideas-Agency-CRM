<?php

namespace App\Services\ProviderRadar;

use App\Services\ProviderRadar\Contracts\ProviderCapabilityWatcherInterface;

/**
 * Turns two raw snapshots into the SMALL diff that actually feeds Claude
 * (Phase B/C) — never the full new document, so the AI's context stays
 * scoped to what changed, not the entire spec every time.
 */
class CapabilityDiffService
{
    /** A line-level unified-style diff for text/changelog sources. */
    public function textDiff(string $old, string $new): string
    {
        $oldLines = explode("\n", $old);
        $newLines = explode("\n", $new);

        $oldSet = array_flip($oldLines);
        $newSet = array_flip($newLines);

        // array_flip's keys are the line content — array_diff_key compares
        // by key, so array_keys() (not array_values(), which would return
        // the original line INDEX) recovers the actual line text.
        $added = array_keys(array_diff_key($newSet, $oldSet));
        $removed = array_keys(array_diff_key($oldSet, $newSet));

        if ($added === [] && $removed === []) {
            return '';
        }

        $out = [];
        foreach ($removed as $line) {
            $out[] = "- {$line}";
        }
        foreach ($added as $line) {
            $out[] = "+ {$line}";
        }

        return implode("\n", $out);
    }

    /**
     * A structural diff for OpenAPI/response-schema sources — which dotted
     * key paths were added, removed, or changed type. Real, verifiable
     * evidence (new endpoints/fields/enum shapes) rather than prose.
     *
     * @return array{added: list<string>, removed: list<string>, changed: list<string>, changed_detail: array<string, array{from: string, to: string}>}
     */
    public function structuralDiff(string $oldJson, string $newJson): array
    {
        $old = json_decode($oldJson, true);
        $new = json_decode($newJson, true);

        $oldPaths = is_array($old) ? $this->flatten($old) : [];
        $newPaths = is_array($new) ? $this->flatten($new) : [];

        $added = array_keys(array_diff_key($newPaths, $oldPaths));
        $removed = array_keys(array_diff_key($oldPaths, $newPaths));

        $changed = [];
        foreach (array_intersect_key($newPaths, $oldPaths) as $path => $newValue) {
            if ($oldPaths[$path] !== $newValue) {
                $changed[$path] = ['from' => $oldPaths[$path], 'to' => $newValue];
            }
        }

        return [
            'added' => $added,
            'removed' => $removed,
            'changed' => array_keys($changed),
            'changed_detail' => $changed,
        ];
    }

    /** Render a structural diff as plain text, for storage and for Claude's prompt. */
    public function renderStructuralDiff(array $diff): string
    {
        $lines = [];
        foreach ($diff['added'] as $path) {
            $lines[] = "+ {$path}";
        }
        foreach ($diff['removed'] as $path) {
            $lines[] = "- {$path}";
        }
        foreach ($diff['changed'] as $path) {
            $detail = $diff['changed_detail'][$path] ?? null;
            $lines[] = $detail === null
                ? "~ {$path}"
                : "~ {$path}: {$detail['from']} -> {$detail['to']}";
        }

        return implode("\n", $lines);
    }

    public function diffFor(string $sourceType, string $old, string $new): string
    {
        return match ($sourceType) {
            ProviderCapabilityWatcherInterface::CHANGELOG => $this->textDiff($old, $new),
            default => $this->renderStructuralDiff($this->structuralDiff($old, $new)),
        };
    }

    /**
     * @return array<string, string> dotted path => a stable string
     *                                representation of the leaf value
     *                                (never the value's PHP type alone —
     *                                a schema's declared type, e.g. "string"
     *                                vs "integer", is itself a normal JSON
     *                                string value, so comparing only
     *                                get_debug_type() would never see it change)
     */
    private function flatten(array $data, string $prefix = ''): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            $path = $prefix === '' ? (string) $key : "{$prefix}.{$key}";
            if (is_array($value)) {
                $out = [...$out, ...$this->flatten($value, $path)];
            } else {
                $out[$path] = get_debug_type($value).':'.json_encode($value);
            }
        }

        return $out;
    }
}
