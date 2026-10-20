<?php

namespace App\Services\N2N\Identity;

use App\Models\BrandPartner;
use App\Models\User;
use App\Support\Auditor;
use App\Support\N2N\N2NSettings;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** Username rules, claim and suggestion (N2N, MASTER-ONLY). Cooldown and reserved list are admin settings, never constants. */
class UsernamePolicy
{
    public static function normalise(string $input): string
    {
        return strtolower(ltrim(trim($input), '@'));
    }

    /** @return string|null a human reason the name is unacceptable, or null when it is well-formed and not reserved */
    public function problem(string $name, ?User $claimant = null): ?string
    {
        $min = (int) config('n2n.username.min', 3);
        $max = (int) config('n2n.username.max', 20);
        if (strlen($name) < $min || strlen($name) > $max) {
            return "Usernames are {$min} to {$max} characters.";
        }
        if (preg_match('/^[a-z0-9]([a-z0-9_.]*[a-z0-9])?$/', $name) !== 1 || str_contains($name, '..') || str_contains($name, '__') || str_contains($name, '._') || str_contains($name, '_.')) {
            return 'Use letters, numbers, dots and underscores, starting and ending with a letter or number.';
        }
        if (preg_match('/^nr-?[0-9a-z]{8,}$/', $name) === 1) {
            return 'That looks like a Naara ID. Choose something else.';
        }
        $flat = $this->flat($name);
        foreach (N2NSettings::reservedUsernames() as $reserved) {
            if ($flat === $this->flat($reserved)) {
                return 'That username is reserved.';
            }
        }
        // A brand name belongs to the brand that owns it: nobody else may squat it.
        foreach (BrandPartner::query()->pluck('brand_name', 'owner_user_id') as $owner => $brand) {
            if ($flat === $this->flat((string) $brand) && (int) $owner !== (int) ($claimant?->id ?? 0)) {
                return 'That username belongs to a brand.';
            }
        }

        return null;
    }

    /**
     * Claim or change a username. A concurrent claim for the same name loses to the unique index and gets a friendly message.
     *
     * @return array{ok: bool, message: string}
     */
    public function claim(User $user, string $input): array
    {
        $name = self::normalise($input);
        if ($name === (string) $user->username) {
            return ['ok' => true, 'message' => 'That is already your username.'];
        }
        if (($why = $this->problem($name, $user)) !== null) {
            return ['ok' => false, 'message' => $why];
        }
        $days = N2NSettings::usernameCooldownDays();
        if ($user->username !== null && $days > 0 && $user->username_changed_at !== null && Carbon::parse($user->username_changed_at)->gt(Carbon::now()->subDays($days))) {
            return ['ok' => false, 'message' => 'You can change your username again on '.Carbon::parse($user->username_changed_at)->addDays($days)->toFormattedDateString().'.'];
        }
        try {
            DB::transaction(function () use ($user, $name) {
                $old = $user->username;
                $user->forceFill(['username' => $name, 'username_changed_at' => now()])->save();
                Auditor::log('n2n.username_set', User::class, $user->id, ['from' => $old, 'to' => $name]);
            });
        } catch (QueryException $e) {
            // 23000 / 1062: someone else took it between our check and the write.
            return ['ok' => false, 'message' => 'That username was just taken. Try another.'];
        }

        return ['ok' => true, 'message' => 'Username saved.'];
    }

    /** Admin rename: bypasses the cooldown only, never the format rules. */
    public function forceRename(User $user, string $input, User $admin): array
    {
        $name = self::normalise($input);
        if (($why = $this->problem($name, $user)) !== null) {
            return ['ok' => false, 'message' => $why];
        }
        try {
            $old = $user->username;
            $user->forceFill(['username' => $name, 'username_changed_at' => now()])->save();
            Auditor::log('n2n.username_forced', User::class, $user->id, ['from' => $old, 'to' => $name, 'by' => $admin->id]);
        } catch (QueryException $e) {
            return ['ok' => false, 'message' => 'That username is already taken.'];
        }

        return ['ok' => true, 'message' => 'Username changed.'];
    }

    /** Auto-suggest from the person's name; appends digits until a free, acceptable name is found. */
    public function suggest(User $user): string
    {
        $base = strtolower(preg_replace('/[^a-z0-9]+/i', '', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', (string) $user->name) ?: '') ?? '');
        $base = substr($base !== '' ? $base : 'naara', 0, 14);
        if (strlen($base) < 3) {
            $base .= 'naara';
        }
        for ($i = 0; $i < 25; $i++) {
            $candidate = $i === 0 ? $base : $base.random_int(10, 9999);
            if ($this->problem($candidate, $user) === null && ! User::query()->where('username', $candidate)->exists()) {
                return $candidate;
            }
        }

        return 'naara'.random_int(10000, 99999);
    }

    private function flat(string $s): string
    {
        return strtolower(preg_replace('/[^a-z0-9]/i', '', $s) ?? '');
    }
}
