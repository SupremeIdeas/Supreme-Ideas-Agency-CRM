<?php

namespace App\Console\Commands;

use App\Models\Contact;
use App\Models\User;
use App\Services\N2N\Identity\NaaraId;
use App\Services\N2N\Identity\PhoneNormalizer;
use Illuminate\Console\Command;

/** Chunked, resumable: gives every existing user a Naara ID and every contact its E.164 (N2N, MASTER-ONLY). Safe to re-run. */
class N2NBackfillIdentity extends Command
{
    protected $signature = 'n2n:backfill-identity {--chunk=500}';

    /** @var list<string> the name the Brand-Growth blueprint gave it */
    protected $aliases = ['naara-id:backfill'];

    protected $description = 'Assign Naara IDs to existing users and normalise contact numbers (N2N, master only)';

    public function handle(): int
    {
        $chunk = max(50, (int) $this->option('chunk'));
        $ids = 0;
        User::query()->whereNull('naara_id')->orderBy('id')->chunkById($chunk, function ($users) use (&$ids) {
            foreach ($users as $u) {
                NaaraId::assign($u);
                $ids++;
            }
        });
        $contacts = 0;
        Contact::query()->whereNull('e164')->with('user:id,country_code')->orderBy('id')->chunkById($chunk, function ($rows) use (&$contacts) {
            foreach ($rows as $c) {
                $e = PhoneNormalizer::normalise((string) $c->phone_number, $c->user?->country_code)['e164'] ?? null;
                if ($e !== null) {
                    $c->forceFill(['e164' => $e])->saveQuietly();
                    $contacts++;
                }
            }
        });
        $this->info("Naara IDs assigned: {$ids}. Contacts normalised: {$contacts}.");

        return self::SUCCESS;
    }
}
