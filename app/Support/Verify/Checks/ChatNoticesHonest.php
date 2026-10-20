<?php

namespace App\Support\Verify\Checks;

use App\Models\N2N\ChatNotice;
use App\Services\N2N\Messaging\ChatNotices;
use App\Support\Verify\Check;
use App\Support\Verify\CheckResult;
use Illuminate\Support\Facades\Schema;

/** Blueprint 4.8: no live chat notice may make a privacy claim the platform cannot back (end-to-end encryption while it is off). */
class ChatNoticesHonest implements Check
{
    public function area(): string
    {
        return 'chat';
    }

    public function name(): string
    {
        return 'Live chat notices make no false privacy claim';
    }

    public function run(): CheckResult
    {
        if (! Schema::hasTable('n2n_chat_notices')) {
            return CheckResult::fail('The chat notices table is missing; run the migrations.');
        }
        $guard = app(ChatNotices::class);
        $bad = [];
        foreach (ChatNotice::query()->live()->get() as $n) {
            if ($guard->problems($n->title, $n->body) !== []) {
                $bad[] = $n->id;
            }
        }

        return $bad === []
            ? CheckResult::pass('Every live chat notice passes the honesty guard.')
            : CheckResult::fail('Live notices claim end-to-end privacy while it is not enabled: ids '.implode(', ', $bad), ['ids' => $bad]);
    }
}
