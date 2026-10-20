<?php

namespace App\Support\Verify;

/** One verifiable fact about the platform. `run()` must be read-only: no purchase, send, charge or write outside its own result. */
interface Check
{
    /** Area this check belongs to (payouts, wallet, rewards, chat, calls, messages, preloader, media, menus, notifications, email, responsive). */
    public function area(): string;

    public function name(): string;

    public function run(): CheckResult;
}
