<?php

namespace App\Services\N2N\Messaging;

use App\Models\N2N\Participant;
use App\Models\Setting;
use App\Models\User;
use App\Support\Auditor;
use Illuminate\Support\Facades\Hash;

/**
 * Chat lock (N2N, MASTER-ONLY). A member sets a PIN (4 to 8 digits, stored only as a hash) and can lock individual chats. A locked chat disappears from every list,
 * search and count until the PIN is entered; entering it opens a short session window (default 5 minutes, then it locks again by itself). Five wrong PINs pause
 * attempts for 15 minutes. This protects a chat from someone glancing at an unlocked phone or browser; it is not a substitute for the device's own screen lock, and
 * Naara can still access chat content to run safety and moderation, so it is not an end-to-end privacy feature.
 */
class ChatLock
{
    public const SESSION_KEY = 'n2n_unlock_until';

    public const MAX_FAILS = 5;

    public const PAUSE_MINUTES = 15;

    public static function minutes(): int
    {
        return min(60, max(1, (int) Setting::getValue('n2n.lock_minutes', 5)));
    }

    public static function hasPin(?User $user): bool
    {
        return $user !== null && ! empty($user->n2n_lock_hash);
    }

    public static function unlocked(): bool
    {
        return (int) session(self::SESSION_KEY, 0) > now()->timestamp;
    }

    public static function relock(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    private static function validPin(string $pin): void
    {
        if (preg_match('/^\d{4,8}$/', $pin) !== 1) {
            throw new MessagingException('invalid', 'Use 4 to 8 digits for your PIN.');
        }
        if (preg_match('/^(\d)\1+$/', $pin) === 1 || in_array($pin, ['1234', '0123', '12345', '123456', '1234567', '12345678', '4321', '1212'], true)) {
            throw new MessagingException('invalid', 'That PIN is too easy to guess. Choose another.');
        }
    }

    /** @throws MessagingException */
    private function checkPin(User $user, string $pin): void
    {
        if ($user->n2n_lock_until !== null && $user->n2n_lock_until->isFuture()) {
            throw new MessagingException('rate_limited', 'Too many wrong PINs. Try again in '.max(1, (int) ceil(now()->diffInMinutes($user->n2n_lock_until, false))).' minute(s).');
        }
        if (! self::hasPin($user) || ! Hash::check($pin, (string) $user->n2n_lock_hash)) {
            $fails = (int) $user->n2n_lock_fails + 1;
            $pause = $fails >= self::MAX_FAILS;
            $user->forceFill(['n2n_lock_fails' => $pause ? 0 : $fails, 'n2n_lock_until' => $pause ? now()->addMinutes(self::PAUSE_MINUTES) : $user->n2n_lock_until])->saveQuietly();
            Auditor::log('n2n.lock_pin_failed', User::class, $user->id, ['fails' => $fails]);
            throw new MessagingException('invalid', $pause ? 'Too many wrong PINs. Attempts are paused for '.self::PAUSE_MINUTES.' minutes.' : 'That PIN is not right.');
        }
        $user->forceFill(['n2n_lock_fails' => 0, 'n2n_lock_until' => null])->saveQuietly();
    }

    /** First PIN, or a change (the current PIN is required once one exists). @throws MessagingException */
    public function setPin(User $user, string $pin, ?string $current = null): void
    {
        self::validPin($pin);
        if (self::hasPin($user)) {
            $this->checkPin($user, (string) $current);
        }
        $user->forceFill(['n2n_lock_hash' => Hash::make($pin)])->saveQuietly();
        $this->open();
        Auditor::log('n2n.lock_pin_set', User::class, $user->id);
    }

    /** Removing the PIN unlocks every chat again. @throws MessagingException */
    public function removePin(User $user, string $current): void
    {
        $this->checkPin($user, $current);
        $user->forceFill(['n2n_lock_hash' => null])->saveQuietly();
        Participant::query()->where('user_id', $user->id)->update(['locked_at' => null]);
        self::relock();
        Auditor::log('n2n.lock_pin_removed', User::class, $user->id);
    }

    /** @throws MessagingException */
    public function unlock(User $user, string $pin): void
    {
        $this->checkPin($user, $pin);
        $this->open();
    }

    private function open(): void
    {
        session([self::SESSION_KEY => now()->addMinutes(self::minutes())->timestamp]);
    }

    /**
     * Lock or unlock one chat. Locking needs a PIN; unlocking a chat needs the session to be open (the PIN entered a moment ago).
     *
     * @throws MessagingException
     */
    public function setChat(User $user, int $conversationId, bool $on, ConversationPrefs $prefs): void
    {
        if (! self::hasPin($user)) {
            throw new MessagingException('invalid', 'Set a PIN first.');
        }
        if (! $on && ! self::unlocked()) {
            throw new MessagingException('not_participant', 'Enter your PIN to unlock this chat.');
        }
        $p = $prefs->participant($user, $conversationId);
        $p->forceFill(['locked_at' => $on ? ($p->locked_at ?? now()) : null, 'pinned_at' => $on ? null : $p->pinned_at])->save();
    }
}
