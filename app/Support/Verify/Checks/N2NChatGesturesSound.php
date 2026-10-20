<?php

namespace App\Support\Verify\Checks;

use App\Livewire\N2N\Chat;
use App\Services\N2N\Messaging\ChatGesturePrefs;
use App\Services\N2N\Messaging\MessageActions;
use App\Support\Verify\Check;
use App\Support\Verify\CheckResult;
use Throwable;

/** MASTER-ONLY. `naara:verify chat`: every action a gesture can run exists on the chat component, the default reaction is a real one, and bad stored settings are clamped before they reach the browser. */
class N2NChatGesturesSound implements Check
{
    public function area(): string
    {
        return 'chat';
    }

    public function name(): string
    {
        return 'Chat gestures: every gesture action exists, settings are valid and clamped';
    }

    public function run(): CheckResult
    {
        try {
            $bad = [];
            foreach (['setReply', 'openInfo', 'openForward', 'toggleStar', 'react'] as $m) {
                if (! method_exists(Chat::class, $m)) {
                    $bad[] = "the chat page has no `$m` action (a gesture calls it)";
                }
            }
            $d = ChatGesturePrefs::DEFAULTS;
            if (! array_key_exists($d['double_tap_code'], MessageActions::REACTIONS)) {
                $bad[] = 'the default double-tap reaction is not in the reaction catalogue';
            }
            $c = ChatGesturePrefs::clean(['long_press_ms' => 99999, 'swipe_commit_px' => -5, 'swipe_start' => 'explode', 'double_tap_code' => 'nope']);
            if ($c['long_press_ms'] !== 1000 || $c['swipe_commit_px'] !== 40 || $c['swipe_start'] !== 'info' || $c['double_tap_code'] !== $d['double_tap_code']) {
                $bad[] = 'out-of-range or unknown gesture settings are not being clamped';
            }
            foreach (['resources/js/n2n/gestures.js', 'resources/js/n2n/gesture-config.js'] as $f) {
                if (! is_file(base_path($f))) {
                    $bad[] = "$f is missing";
                }
            }
        } catch (Throwable $e) {
            return CheckResult::fail('Gestures could not be checked: '.$e->getMessage());
        }

        return $bad === [] ? CheckResult::pass('Gesture actions, defaults and clamping are all in place.') : CheckResult::fail(implode('; ', $bad).'.', ['problems' => $bad]);
    }
}
