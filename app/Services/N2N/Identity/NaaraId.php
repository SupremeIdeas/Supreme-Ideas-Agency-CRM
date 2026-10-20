<?php

namespace App\Services\N2N\Identity;

use App\Models\User;

/**
 * Naara ID (N2N, MASTER-ONLY): `NR-` + 10 random Crockford-base32 characters + 1 check character. Random (never sequential), so it is
 * not guessable or enumerable; the check character catches a mistyped ID before any lookup. Immutable once assigned and never reused.
 */
final class NaaraId
{
    private const ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    private const CHECK = '0123456789ABCDEFGHJKMNPQRSTVWXYZ*~$=U';   // Crockford's 37-symbol check alphabet

    /**
     * The one read path for a person's Naara ID: returns it, issuing one first if they have none. Idempotent and race-safe: the write only
     * lands while the column is still NULL, a collision on the unique index just draws a new candidate, and an existing ID is never overwritten.
     */
    public static function for(User $user): string
    {
        if (is_string($user->naara_id) && $user->naara_id !== '') {
            return $user->naara_id;
        }

        return self::assign($user);
    }

    public static function assign(User $user): string
    {
        for ($attempt = 0; $attempt < 8; $attempt++) {
            $candidate = self::generate();
            try {
                $won = User::query()->whereKey($user->id)->whereNull('naara_id')->update(['naara_id' => $candidate]) === 1;
            } catch (\Illuminate\Database\UniqueConstraintViolationException) {
                continue;   // another request took this candidate between our check and our write: draw again
            }
            // Either we issued it, or a concurrent request issued one first: in both cases the stored value is the truth.
            $stored = (string) User::query()->whereKey($user->id)->value('naara_id');
            $user->setAttribute('naara_id', $stored);
            $user->syncOriginalAttribute('naara_id');
            if ($won) {
                \App\Support\Notifications\Notify::event($user, 'identity.naara_id_issued', ['id' => $stored], dedupe: 'issued');   // the bell keeps the ID; never blocks
            }

            return $stored;
        }

        throw new \RuntimeException('Could not issue a Naara ID after several attempts.');
    }

    public static function generate(): string
    {
        do {
            $body = '';
            for ($i = 0; $i < 10; $i++) {
                $body .= self::ALPHABET[random_int(0, 31)];
            }
            $id = 'NR-'.$body.self::check($body);
        } while (User::query()->where('naara_id', $id)->exists());

        return $id;
    }

    public static function check(string $body): string
    {
        $n = 0;
        foreach (str_split($body) as $c) {
            $n = $n * 32 + (int) strpos(self::ALPHABET, $c);
        }

        return self::CHECK[$n % 37];
    }

    /** Normalise typing noise (case, spaces, dashes, O/I/L confusions) then verify the shape and the check character. */
    public static function normalise(string $input): ?string
    {
        $s = strtoupper(preg_replace('/[\s\-]+/', '', trim($input)) ?? '');
        if (! str_starts_with($s, 'NR')) {
            return null;
        }
        $s = strtr(substr($s, 2), ['O' => '0', 'I' => '1', 'L' => '1']);
        if (preg_match('/^[0-9ABCDEFGHJKMNPQRSTVWXYZ]{10}[0-9ABCDEFGHJKMNPQRSTVWXYZ*~$=U]$/', $s) !== 1) {
            return null;
        }

        return self::check(substr($s, 0, 10)) === $s[10] ? 'NR-'.$s : null;
    }
}
