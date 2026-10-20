<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Business Suite Prompt 14 §1.5 — a weekly opening-hours schedule for one
 * workspace. `windows` is `[{day, start, end}]` (`day` = mon..sun, times
 * `HH:MM` in the schedule's own IANA timezone); `holiday_overrides` is
 * `[{date: Y-m-d, closed: bool, windows?: [{start, end}]}]` and wins over
 * the weekly pattern for that calendar date. An `end` earlier than `start`
 * means the window runs past midnight into the next day.
 */
class BusinessHoursSchedule extends Model
{
    public const DAYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

    protected $fillable = ['wallet_group_id', 'name', 'timezone', 'windows', 'holiday_overrides'];

    protected function casts(): array
    {
        return ['windows' => 'array', 'holiday_overrides' => 'array'];
    }

    public function walletGroup(): BelongsTo
    {
        return $this->belongsTo(WalletGroup::class);
    }

    public function isOpenNow(?CarbonInterface $at = null): bool
    {
        $tz = in_array($this->timezone, \DateTimeZone::listIdentifiers(), true) ? $this->timezone : 'UTC';
        $local = Carbon::instance(($at ?? now())->toDateTime())->setTimezone($tz);
        $minute = $local->hour * 60 + $local->minute;

        // 1. A holiday override for today replaces the weekly pattern outright.
        foreach ((array) $this->holiday_overrides as $override) {
            if (($override['date'] ?? null) === $local->toDateString()) {
                if (! empty($override['closed'])) {
                    return false;
                }

                return $this->anyWindowContains((array) ($override['windows'] ?? []), $minute, null);
            }
        }

        // 2. Weekly pattern, including a window that began yesterday and spills past midnight.
        $today = self::DAYS[$local->dayOfWeekIso - 1];
        $yesterday = self::DAYS[($local->dayOfWeekIso + 5) % 7];

        foreach ((array) $this->windows as $w) {
            $day = $this->normalizeDay($w['day'] ?? null);
            $start = self::toMinutes($w['start'] ?? null);
            $end = self::toMinutes($w['end'] ?? null);
            if ($day === null || $start === null || $end === null) {
                continue;
            }

            if ($end > $start) {
                if ($day === $today && $minute >= $start && $minute < $end) {
                    return true;
                }
            } else {
                // Overnight: [start, midnight) today, or [midnight, end) the morning after.
                if ($day === $today && $minute >= $start) {
                    return true;
                }
                if ($day === $yesterday && $minute < $end) {
                    return true;
                }
            }
        }

        return false;
    }

    /** @param array<int,array<string,mixed>> $windows */
    private function anyWindowContains(array $windows, int $minute, ?string $day): bool
    {
        foreach ($windows as $w) {
            $start = self::toMinutes($w['start'] ?? null);
            $end = self::toMinutes($w['end'] ?? null);
            if ($start === null || $end === null) {
                continue;
            }
            if ($end > $start ? ($minute >= $start && $minute < $end) : $minute >= $start) {
                return true;
            }
        }

        return false;
    }

    private function normalizeDay(mixed $day): ?string
    {
        $d = strtolower(substr((string) $day, 0, 3));

        return in_array($d, self::DAYS, true) ? $d : null;
    }

    private static function toMinutes(mixed $hhmm): ?int
    {
        if (! is_string($hhmm) || ! preg_match('/^(\d{1,2}):(\d{2})$/', $hhmm, $m)) {
            return null;
        }
        $h = (int) $m[1];
        $min = (int) $m[2];

        return ($h > 24 || $min > 59) ? null : $h * 60 + $min;
    }
}
