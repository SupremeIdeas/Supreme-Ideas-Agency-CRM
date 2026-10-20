<?php

namespace App\Services\N2N\Identity;

use App\Jobs\N2N\LinkContactsJob;
use App\Models\Contact;
use App\Models\User;
use App\Support\Auditor;
use App\Support\N2N\N2NSettings;
use Illuminate\Support\Facades\Cache;

/**
 * Address-book import for contact discovery (N2N, MASTER-ONLY): vCard or CSV text, explicit consent, capped per call and per day, and
 * every imported row can be deleted together. Honest privacy note: hashing numbers on the client gives weak protection (the number
 * space is small), so the real protection is consent + rate limits + the discoverability switch.
 */
class ContactImporter
{
    /** @return list<array{name: string, phone: string}> */
    public function parse(string $contents): array
    {
        $contents = str_replace(["\r\n", "\r"], "\n", $contents);
        if (stripos($contents, 'BEGIN:VCARD') !== false) {
            return $this->parseVcf($contents);
        }

        return $this->parseCsv($contents);
    }

    /**
     * @param  list<array{name: string, phone: string}>  $rows
     * @return array{ok: bool, message: string, imported?: int, skipped?: int}
     */
    public function import(User $user, array $rows, bool $consented): array
    {
        if (! $consented) {
            return ['ok' => false, 'message' => 'Please confirm you agree to import your contacts.'];
        }
        if ($rows === []) {
            return ['ok' => false, 'message' => 'No contacts found in that file.'];
        }
        if (count($rows) > N2NSettings::importMaxContacts()) {
            return ['ok' => false, 'message' => 'That file has too many contacts. The limit is '.N2NSettings::importMaxContacts().' per import.'];
        }
        $key = 'n2n-import:'.$user->id.':'.now()->toDateString();
        if ((int) Cache::get($key, 0) >= N2NSettings::importPerDay()) {
            return ['ok' => false, 'message' => 'You have reached the daily import limit. Try again tomorrow.'];
        }
        Cache::put($key, (int) Cache::get($key, 0) + 1, now()->endOfDay());

        $have = Contact::query()->where('user_id', $user->id)->whereNotNull('e164')->pluck('e164')->flip()->all();
        $imported = $skipped = 0;
        foreach ($rows as $r) {
            $parsed = PhoneNormalizer::normalise((string) $r['phone'], $user->country_code);
            if ($parsed === null || isset($have[$parsed['e164']])) {
                $skipped++;

                continue;
            }
            $have[$parsed['e164']] = true;
            Contact::query()->create([
                'user_id' => $user->id, 'name' => mb_substr(trim((string) $r['name']) ?: $parsed['e164'], 0, 120),
                'phone_number' => Contact::normalizePhone($parsed['e164']), 'is_favorite' => false,
            ])->forceFill(['e164' => $parsed['e164'], 'imported_at' => now()])->saveQuietly();
            $imported++;
        }
        Auditor::log('n2n.contacts_imported', User::class, $user->id, ['imported' => $imported, 'skipped' => $skipped]);
        LinkContactsJob::dispatch($user->id);

        return ['ok' => true, 'message' => "Imported {$imported} contact(s).", 'imported' => $imported, 'skipped' => $skipped];
    }

    public function deleteImported(User $user): int
    {
        $n = Contact::query()->where('user_id', $user->id)->whereNotNull('imported_at')->delete();
        Auditor::log('n2n.contacts_import_deleted', User::class, $user->id, ['deleted' => $n]);

        return $n;
    }

    private function parseVcf(string $text): array
    {
        $out = [];
        foreach (preg_split('/BEGIN:VCARD/i', $text) as $card) {
            if (! preg_match('/^FN[^:]*:(.+)$/mi', $card, $fn)) {
                continue;
            }
            if (preg_match_all('/^TEL[^:]*:(.+)$/mi', $card, $tels)) {
                foreach ($tels[1] as $tel) {
                    $out[] = ['name' => trim($fn[1]), 'phone' => trim($tel)];
                }
            }
        }

        return $out;
    }

    private function parseCsv(string $text): array
    {
        $out = [];
        foreach (array_filter(explode("\n", $text), fn ($l) => trim($l) !== '') as $i => $line) {
            $cols = str_getcsv($line);
            if ($i === 0 && preg_match('/name|phone|tel/i', $line) && ! preg_match('/\d{7,}/', $line)) {
                continue;   // header row
            }
            $phone = null;
            $name = '';
            foreach ($cols as $c) {
                if ($phone === null && preg_match('/^\+?[\d\s\-().]{7,}$/', trim($c))) {
                    $phone = trim($c);
                } elseif ($name === '' && trim($c) !== '') {
                    $name = trim($c);
                }
            }
            if ($phone !== null) {
                $out[] = ['name' => $name, 'phone' => $phone];
            }
        }

        return $out;
    }
}
