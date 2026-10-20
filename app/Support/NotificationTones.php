<?php

namespace App\Support;

use App\Models\NotificationTone;
use App\Models\User;
use App\Models\UserSoundPref;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Notification sound + ringtone library (Batch 2).
 *
 * Honest platform limits, baked into the design: a custom sound can only be played while the app is open in the foreground (the browser
 * and OS own what a closed tab does), and vibration does not exist on iOS Safari. Everything here is the FOREGROUND experience; the
 * settings screen says so in plain words and never promises a custom ringtone for a closed tab.
 *
 * Library rules: unlimited tones per category, exactly one default per (category, slot), a member's own pick wins over the default,
 * and a missing/deleted pick quietly falls back to the default. Uploads are identified by their bytes, never their extension, and capped.
 */
class NotificationTones
{
    public const DIR = 'sounds';

    public const MAX_KB = 1024;

    /** Ringtones loop for a whole ring and may be a short song clip, so they get a larger ceiling (a 30 second clip at 128 kbps is about 470 KB). */
    public const MAX_KB_RINGTONE = 2048;

    public static function maxKb(string $category): int
    {
        return $category === 'ringtone' ? self::MAX_KB_RINGTONE : self::MAX_KB;
    }

    public const MAX_TONES = 300;

    /** category => [label, slots => [slot => label]]. `message` has three slots so a member can tell by ear which kind of message arrived. */
    public const CATEGORIES = [
        'ringtone' => ['label' => 'Ringtone', 'hint' => 'Plays on a loop for an incoming call until it is answered or declined.', 'slots' => ['default' => 'Incoming call']],
        'message' => ['label' => 'Message', 'hint' => 'A new message while the app is open.', 'slots' => ['general' => 'General message', 'naara' => 'From a Naara member', 'naaraline' => 'To a Naara Line (outside number)']],
        'credit_alert' => ['label' => 'Credit alert', 'hint' => 'Wallet top-ups and credits landing.', 'slots' => ['default' => 'Wallet / credit alert']],
        'push' => ['label' => 'Push alert', 'hint' => 'General alerts and announcements.', 'slots' => ['default' => 'Alert']],
        'reward' => ['label' => 'Reward', 'hint' => 'Online bonus, referral and reward notifications.', 'slots' => ['default' => 'Reward']],
        'beep' => ['label' => 'Beep', 'hint' => 'Short beeps: a message you sent, or something that did not go through.', 'slots' => ['send' => 'Message sent', 'error' => 'Something went wrong']],
    ];

    /** The built-in library. file => [category, slot, label, default?, credit?]. A name without an extension is a .wav (original synthesis from scripts/gen_tones.py and gen_beeps.py);
     *  a name with one (.mp3) is a clip cut with scripts/make_ringtone_clip.py. All files live in database/seeders/sounds. */
    public const BUILTIN = [
        'credit-cash-chime' => ['credit_alert', 'default', 'Cash Chime', true],
        'credit-coin-drop' => ['credit_alert', 'default', 'Coin Drop', false],
        'credit-bright-bell' => ['credit_alert', 'default', 'Bright Bell', false],
        'credit-vault-arp' => ['credit_alert', 'default', 'Vault Arpeggio', false],
        'msg-soft-pop' => ['message', 'general', 'Soft Pop', true],
        'msg-droplet' => ['message', 'general', 'Droplet', false],
        'msg-two-tap' => ['message', 'general', 'Two Tap', false],
        'msg-naara-hello' => ['message', 'naara', 'Naara Hello', true],
        'msg-naara-glow' => ['message', 'naara', 'Naara Glow', false],
        'msg-line-ticks' => ['message', 'naaraline', 'Line Ticks', true],
        'msg-line-sms' => ['message', 'naaraline', 'Line SMS', false],
        'push-gentle-alert' => ['push', 'default', 'Gentle Alert', true],
        'push-beacon' => ['push', 'default', 'Beacon', false],
        'push-nudge' => ['push', 'default', 'Nudge', false],
        'reward-level-up' => ['reward', 'default', 'Level Up', true],
        'reward-sparkle' => ['reward', 'default', 'Sparkle', false],
        'reward-mini-fanfare' => ['reward', 'default', 'Mini Fanfare', false],
        'ring-classic' => ['ringtone', 'default', 'Classic Ring', true],
        'ring-marimba-loop' => ['ringtone', 'default', 'Marimba Loop', false],
        'ring-digital-pulse' => ['ringtone', 'default', 'Digital Pulse', false],
        'ring-soft-chime' => ['ringtone', 'default', 'Soft Chime', false],
        'ring-zayon-boss-line.mp3' => ['ringtone', 'default', 'Boss Line', false, 'Zayon Whyte · NAARA Circus (2026)'],
        'ring-zayon-wallet-gang.mp3' => ['ringtone', 'default', 'Wallet Gang', false, 'Zayon Whyte · NAARA Circus (2026)'],
        'ring-zayon-land-and-connect.mp3' => ['ringtone', 'default', 'LAND & CONNECT', false, 'Zayon Whyte · NAARA Circus (2026)'],
        'beep-send-lift' => ['beep', 'send', 'Lift', true],
        'beep-send-tick' => ['beep', 'send', 'Tick', false],
        'beep-send-double' => ['beep', 'send', 'Double Click', false],
        'beep-error-buzz' => ['beep', 'error', 'Low Buzz', true],
        'beep-error-fall' => ['beep', 'error', 'Falling Beep', false],
        'beep-error-triple' => ['beep', 'error', 'Triple Low', false],
    ];

    /** @return list<string> every "category.slot" key the platform plays. */
    public static function keys(): array
    {
        $out = [];
        foreach (self::CATEGORIES as $cat => $def) {
            foreach (array_keys($def['slots']) as $slot) {
                $out[] = $cat.'.'.$slot;
            }
        }

        return $out;
    }

    public static function validKey(string $category, string $slot): bool
    {
        return isset(self::CATEGORIES[$category]['slots'][$slot]);
    }

    public static function forget(): void
    {
        Cache::forget('nx.tones.library');
    }

    /** All tones, grouped by "category.slot", cached (the library changes rarely, and this runs on every dashboard page). */
    public static function library(): array
    {
        return Cache::remember('nx.tones.library', 300, function () {
            $by = [];
            foreach (NotificationTone::orderByDesc('is_default')->orderBy('label')->get() as $t) {
                $by[$t->key()][] = ['id' => $t->id, 'label' => $t->label, 'credit' => $t->credit, 'default' => $t->is_default, 'category' => $t->category, 'slot' => $t->slot];
            }

            return $by;
        });
    }

    /** What the browser needs: per key the tone to play (the member's pick, else the default), plus the global switches. */
    public static function clientConfig(?User $user): array
    {
        $pref = $user ? UserSoundPref::where('user_id', $user->id)->first() : null;
        $picks = (array) ($pref?->tones ?? []);
        $lib = self::library();
        $tones = [];
        foreach (self::keys() as $key) {
            $options = $lib[$key] ?? [];
            if ($options === []) {
                continue;
            }
            $chosen = null;
            $want = $picks[$key] ?? null;
            foreach ($options as $o) {
                if ($want !== null && $o['id'] === (int) $want) {
                    $chosen = $o;
                }
            }
            $chosen ??= collect($options)->firstWhere('default', true) ?? $options[0];
            $tones[$key] = route('sounds.tone', ['tone' => $chosen['id']]);
        }

        return [
            'enabled' => $pref?->sound_enabled ?? true,
            'vibrate' => $pref?->vibrate_enabled ?? true,
            'tones' => $tones,
        ];
    }

    /**
     * Validate (by content) and store an admin upload.
     *
     * @throws \InvalidArgumentException with an admin-readable message
     */
    public static function upload(UploadedFile $file, string $category, string $slot, string $label, ?int $adminId = null, bool $builtin = false, ?string $credit = null): NotificationTone
    {
        if (! self::validKey($category, $slot)) {
            throw new \InvalidArgumentException('Unknown category.');
        }
        $bytes = (string) file_get_contents($file->getRealPath());

        return self::storeBytes($bytes, $category, $slot, $label, $adminId, $builtin, $credit);
    }

    public static function storeBytes(string $bytes, string $category, string $slot, string $label, ?int $adminId = null, bool $builtin = false, ?string $credit = null): NotificationTone
    {
        $label = trim(strip_tags($label));
        if ($label === '') {
            throw new \InvalidArgumentException('Give the tone a name.');
        }
        $max = self::maxKb($category);
        if (strlen($bytes) > $max * 1024) {
            throw new \InvalidArgumentException(($category === 'ringtone' ? 'Ringtones' : 'Tones').' must be under '.$max.' KB'.($category === 'ringtone' ? ' (a 30 second clip is about 470 KB)' : '').'.');
        }
        $kind = self::sniff($bytes);
        if ($kind === null) {
            throw new \InvalidArgumentException('That is not an audio file we can play (WAV, MP3, OGG, M4A or WebM).');
        }
        if (NotificationTone::count() >= self::MAX_TONES && ! $builtin) {
            throw new \InvalidArgumentException('The library is full ('.self::MAX_TONES.' tones). Delete one first.');
        }
        [$ext, $mime] = $kind;
        $disk = MediaStorage::disk();
        $path = self::DIR.'/'.Str::uuid().'.'.$ext;
        Storage::disk($disk)->put($path, $bytes, 'public');

        $credit = $credit !== null ? Str::limit(trim(strip_tags($credit)), 160, '') : null;
        $tone = DB::transaction(function () use ($category, $slot, $label, $path, $disk, $mime, $bytes, $adminId, $builtin, $credit, $ext) {
            $first = ! NotificationTone::where('category', $category)->where('slot', $slot)->exists();

            return NotificationTone::create([
                'category' => $category, 'slot' => $slot, 'label' => Str::limit($label, 80, ''), 'credit' => $credit ?: null, 'file_path' => $path, 'disk' => $disk,
                'mime' => $mime, 'size_bytes' => strlen($bytes), 'duration_ms' => self::durationMs($bytes, $ext),
                'is_default' => $first, 'is_builtin' => $builtin, 'uploaded_by' => $adminId,
            ]);
        });
        self::forget();

        return $tone;
    }

    /** Make this tone THE default for its (category, slot). */
    public static function setDefault(NotificationTone $tone): void
    {
        DB::transaction(function () use ($tone) {
            NotificationTone::where('category', $tone->category)->where('slot', $tone->slot)->update(['is_default' => false]);
            $tone->forceFill(['is_default' => true])->save();
        });
        self::forget();
    }

    public static function rename(NotificationTone $tone, string $label): void
    {
        $label = trim(strip_tags($label));
        if ($label !== '') {
            $tone->forceFill(['label' => Str::limit($label, 80, '')])->save();
            self::forget();
        }
    }

    /** Delete a tone and its file. If it was the default another tone in that slot takes over (a slot never silently loses its default). */
    public static function delete(NotificationTone $tone): void
    {
        DB::transaction(function () use ($tone) {
            $wasDefault = $tone->is_default;
            $cat = $tone->category;
            $slot = $tone->slot;
            try {
                Storage::disk($tone->disk)->delete($tone->file_path);
            } catch (\Throwable) {
                // an already-missing file must not block removing the row
            }
            $tone->delete();
            if ($wasDefault) {
                NotificationTone::where('category', $cat)->where('slot', $slot)->orderBy('id')->first()?->forceFill(['is_default' => true])->save();
            }
        });
        self::forget();
    }

    public static function savePrefs(User $user, array $picks, bool $sound, bool $vibrate): UserSoundPref
    {
        $clean = [];
        foreach ($picks as $key => $id) {
            if (! in_array($key, self::keys(), true) || $id === null || $id === '' || $id === 'default') {
                continue;
            }
            [$cat, $slot] = explode('.', $key, 2);
            if (NotificationTone::whereKey((int) $id)->where('category', $cat)->where('slot', $slot)->exists()) {
                $clean[$key] = (int) $id;
            }
        }

        return UserSoundPref::updateOrCreate(['user_id' => $user->id], ['tones' => $clean, 'sound_enabled' => $sound, 'vibrate_enabled' => $vibrate]);
    }

    /** Idempotent: put the built-in library in place on a fresh install (or after a wipe). Never touches admin-added tones. */
    public static function seedBuiltin(): int
    {
        $dir = database_path('seeders/sounds');
        $made = 0;
        foreach (self::BUILTIN as $file => $def) {
            [$cat, $slot, $label, $default] = $def;
            if (NotificationTone::where('is_builtin', true)->where('label', $label)->where('category', $cat)->where('slot', $slot)->exists()) {
                continue;
            }
            $path = $dir.'/'.$file.(str_contains($file, '.') ? '' : '.wav');
            if (! is_file($path)) {
                continue;
            }
            $tone = self::storeBytes((string) file_get_contents($path), $cat, $slot, $label, null, true, $def[4] ?? null);
            if ($default && ! $tone->is_default && ! NotificationTone::where('category', $cat)->where('slot', $slot)->where('is_default', true)->exists()) {
                self::setDefault($tone);
            }
            $made++;
        }

        return $made;
    }

    /** What an audio file really is, from its bytes: [extension, mime] or null. */
    public static function sniff(string $b): ?array
    {
        if (strlen($b) < 12) {
            return null;
        }
        if (str_starts_with($b, 'RIFF') && substr($b, 8, 4) === 'WAVE') {
            return ['wav', 'audio/wav'];
        }
        if (str_starts_with($b, 'ID3') || (ord($b[0]) === 0xFF && (ord($b[1]) & 0xE0) === 0xE0)) {
            return ['mp3', 'audio/mpeg'];
        }
        if (str_starts_with($b, 'OggS')) {
            return ['ogg', 'audio/ogg'];
        }
        if (substr($b, 4, 4) === 'ftyp') {
            return ['m4a', 'audio/mp4'];
        }
        if (str_starts_with($b, "\x1A\x45\xDF\xA3")) {
            return ['webm', 'audio/webm'];
        }

        return null;
    }

    /** Length in ms from the bytes (WAV header, or MP3 frame walk); null when unknown. Capped at what the column holds (about 65 s). */
    private static function durationMs(string $b, string $ext): ?int
    {
        $ms = $ext === 'mp3' ? self::mp3DurationMs($b) : self::wavDurationMs($b);

        return $ms === null ? null : min($ms, 65535);
    }

    private static function mp3DurationMs(string $b): ?int
    {
        $n = strlen($b);
        $i = 0;
        if (str_starts_with($b, 'ID3') && $n > 10) {
            $i = 10 + (((ord($b[6]) & 127) << 21) | ((ord($b[7]) & 127) << 14) | ((ord($b[8]) & 127) << 7) | (ord($b[9]) & 127));
        }
        $br1 = [0, 32, 40, 48, 56, 64, 80, 96, 112, 128, 160, 192, 224, 256, 320];
        $br2 = [0, 8, 16, 24, 32, 40, 48, 56, 64, 80, 96, 112, 128, 144, 160];
        $sr = [3 => [44100, 48000, 32000], 2 => [22050, 24000, 16000], 0 => [11025, 12000, 8000]];
        $t = 0.0;
        $frames = 0;
        while ($i + 4 <= $n && $frames < 40000) {
            if (ord($b[$i]) === 0xFF && (ord($b[$i + 1]) & 0xE0) === 0xE0) {
                $h = (ord($b[$i]) << 24) | (ord($b[$i + 1]) << 16) | (ord($b[$i + 2]) << 8) | ord($b[$i + 3]);
                $ver = ($h >> 19) & 3;
                $layer = ($h >> 17) & 3;
                $bri = ($h >> 12) & 15;
                $sri = ($h >> 10) & 3;
                $pad = ($h >> 9) & 1;
                if ($layer === 1 && $ver !== 1 && $bri !== 0 && $bri !== 15 && $sri !== 3) {
                    $v1 = $ver === 3;
                    $br = ($v1 ? $br1 : $br2)[$bri] * 1000;
                    $rate = $sr[$ver][$sri];
                    $len = (int) (($v1 ? 144 : 72) * $br / $rate) + $pad;
                    if ($len >= 4) {
                        $t += ($v1 ? 1152 : 576) / $rate;
                        $frames++;
                        $i += $len;

                        continue;
                    }
                }
            }
            $i++;
        }

        return $frames > 0 ? (int) round($t * 1000) : null;
    }

    private static function wavDurationMs(string $b): ?int
    {
        if (! str_starts_with($b, 'RIFF') || strlen($b) < 44) {
            return null;
        }
        $h = unpack('vch/Vrate/Vbyterate', substr($b, 22, 10));
        if (empty($h['byterate'])) {
            return null;
        }

        return (int) round((strlen($b) - 44) / $h['byterate'] * 1000);
    }
}
