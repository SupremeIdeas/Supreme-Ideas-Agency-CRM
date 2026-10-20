<?php
/**
 * Naara Chat + merchant demo data for the master marketing mockups (MASTER-ONLY, like the modules it fills). Throwaway sqlite database only.
 * Run after seed-demo.php:  DB_CONNECTION=sqlite DB_DATABASE=/tmp/mockdemo.sqlite php scripts/mockups/seed-chat-demo.php
 * Invents UK/US people who chat with Emily Carter, an event and a poll in a thread, and a small reseller business with clients, invoices and
 * earnings. Nothing here is a real person or a real price.
 */
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Contact;
use App\Models\Merchant;
use App\Models\MerchantClient;
use App\Models\MerchantEarning;
use App\Models\MerchantInvoice;
use App\Models\N2N\Message;
use App\Models\Setting;
use App\Models\User;
use App\Services\N2N\Credit\UsageCredit;
use App\Services\N2N\Identity\NaaraId;
use App\Services\N2N\Messaging\MessageService;
use App\Services\Wallet\WalletService;
use App\Support\N2N\N2NSettings;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

if (config('database.default') !== 'sqlite') { fwrite(STDERR, "Refusing: not a sqlite demo database.\n"); exit(1); }

Setting::setValue(N2NSettings::FLAG, true);
config()->set('n2n.otp.driver', 'log');

$member = function (string $name, string $email, string $country, int $daysOld = 40): User {
    $u = User::firstOrNew(['email' => $email]);
    $u->fill(['name' => $name, 'country_code' => $country, 'display_currency' => $country === 'GB' ? 'GBP' : 'USD'])
        ->forceFill(['password' => Hash::make('password'), 'email_verified_at' => now(), 'is_active' => true, 'created_at' => now()->subDays($daysOld), 'n2n_read_receipts' => true])->save();
    if (! $u->naara_id) { $u->forceFill(['naara_id' => NaaraId::generate()])->save(); }
    try {
        app(WalletService::class)->credit($u, 20, 'USD', ['reference' => 'demo-w-'.$u->id]);
        app(UsageCredit::class)->purchase($u, 12, 'demo-c-'.$u->id);
    } catch (\Throwable $e) { /* already funded on a re-run */ }

    return $u->fresh();
};

$emily = $member('Emily Carter', 'emily@naara.test', 'US');
$people = [
    'sophie' => $member('Sophie Turner', 'sophie@naara.test', 'GB'),
    'michael' => $member('Michael Johnson', 'michael@naara.test', 'US'),
    'olivia' => $member('Olivia Bennett', 'olivia@naara.test', 'GB'),
    'jack' => $member('Jack Thompson', 'jack@naara.test', 'GB'),
    'emma' => $member('Emma Davis', 'emma@naara.test', 'US'),
];
// Everyone knows Emily (and she them), so the chats land as chats, not as message requests.
foreach ($people as $p) {
    Contact::firstOrCreate(['user_id' => $p->id, 'phone_number' => '07700'.str_pad((string) $p->id, 6, '0', STR_PAD_LEFT)], ['name' => 'Emily Carter'])->forceFill(['linked_user_id' => $emily->id])->saveQuietly();
    Contact::firstOrCreate(['user_id' => $emily->id, 'phone_number' => '07701'.str_pad((string) $p->id, 6, '0', STR_PAD_LEFT)], ['name' => $p->name])->forceFill(['linked_user_id' => $p->id])->saveQuietly();
}

$svc = app(MessageService::class);
$n = 0;
$say = function (User $from, User $to, string $text, int $minutesAgo, ?array $extra = null) use ($svc, &$n): ?Message {
    Carbon::setTestNow(now()->subMinutes($minutesAgo));
    RateLimiter::clear('n2n-send:'.$from->id);
    try {
        $r = $svc->send($from, ['to' => $to->naara_id], 'demo-'.(++$n).'-'.$from->id.'-'.substr(md5($text), 0, 8), $text, '127.0.0.1', [], null, null, $extra['poll'] ?? null, null, $extra['event'] ?? null);

        return $r['message'];
    } catch (\Throwable $e) {
        fwrite(STDERR, 'skip: '.$e->getMessage()."\n");

        return null;
    } finally {
        Carbon::setTestNow();
    }
};

// Sophie: planning a trip, the main thread shown in the screenshots.
$say($people['sophie'], $emily, 'Hi Emily! Landing in Lisbon on the 14th. Did you sort data for the trip?', 190);
$say($emily, $people['sophie'], 'Yes, a 5 GB eSIM for Portugal, installed before I left home. Took two minutes.', 184);
$say($people['sophie'], $emily, 'Perfect, I will do the same. Shall we get coffee near the hotel that evening?', 176);
$say($emily, $people['sophie'], 'Love that. Let me put it in the planner so we both have it.', 170);
$say($emily, $people['sophie'], 'x', 169, ['event' => ['title' => 'Coffee near the hotel', 'description' => 'Pastel de nata first, plans after.', 'date' => now()->addDays(3)->format('Y-m-d'), 'time' => '18:00', 'tz' => 'Europe/London']]);;
$say($people['sophie'], $emily, 'Booked in. Also, vote on dinner so I can reserve?', 150);
$say($people['sophie'], $emily, 'x', 149, ['poll' => ['question' => 'Where should we eat on Friday?', 'options' => ['Seafood by the river', 'A tasca in Alfama', 'Rooftop tapas'], 'multiple' => false]]);
$say($emily, $people['sophie'], 'Alfama, please. I want the full Lisbon experience.', 140);
// Michael: a brand relationship.
$say($people['michael'], $emily, 'Thanks for following Carter Travel Co. Could we talk about a partnership next week?', 75);
$say($emily, $people['michael'], 'Happy to. Tuesday morning works. I can share what our members are asking for.', 70);
$say($people['michael'], $emily, 'Great, I will send an invite. Your Naara ID is easier than swapping numbers.', 66);
// Olivia, Jack, Emma: a few more rows for the list.
$say($people['olivia'], $emily, 'Did the Manchester order arrive? Mine shows delivered.', 45);
$say($people['jack'], $emily, 'Sending the wholesale price list over now.', 32);
$say($people['emma'], $emily, 'Thanks, received. Will pay tonight.', 12);
$say($people['olivia'], $emily, 'Brilliant, thank you!', 5);

// Calls: switched on for the demo (dev mode skips the TURN/Reverb checks) with a believable recent history.
Setting::setValue('n2n.calls_enabled', true);
Setting::setValue('n2n.calls_dev_mode', true);
foreach ([['sophie', 'out', 'ended', 372, 95], ['michael', 'in', 'ended', 845, 180], ['olivia', 'in', 'missed', 0, 20], ['jack', 'out', 'ended', 128, 60]] as $i => [$who, $dir, $state, $secs, $mins]) {
    $c = $dir === 'out' ? [$emily->id, $people[$who]->id] : [$people[$who]->id, $emily->id];
    \App\Models\N2N\Call::query()->updateOrCreate(['caller_id' => $c[0], 'callee_id' => $c[1], 'created_at' => now()->subMinutes($mins * ($i + 1))], [
        'state' => $state, 'end_reason' => $state === 'missed' ? 'no_answer' : 'hangup', 'rate_cents' => 4, 'increment_s' => 60,
        'answered_at' => $state === 'ended' ? now()->subMinutes($mins * ($i + 1)) : null, 'ended_at' => now()->subMinutes($mins * ($i + 1))->addSeconds($secs),
        'billed_seconds' => $secs, 'billed_cents' => (int) ceil($secs / 60) * 4,
    ]);
}

// Merchant: a small reseller business owned by Emily.
$m = Merchant::updateOrCreate(['owner_user_id' => $emily->id], [
    'business_name' => 'Carter Travel Co', 'slug' => 'carter-travel', 'brand_color' => '#0A6E6E', 'status' => Merchant::ACTIVE, 'tier' => Merchant::TIER_V2,
    'reseller_margin_pct' => 12.5, 'reviewed_at' => now(),
]);
$clients = [['Sophie Turner', 'sophie@naara.test'], ['Michael Johnson', 'michael@naara.test'], ['Olivia Bennett', 'olivia@naara.test'], ['Jack Thompson', 'jack@naara.test'], ['Emma Davis', 'emma@naara.test']];
$cids = [];
foreach ($clients as $i => [$cn, $ce]) {
    $cids[$i] = MerchantClient::updateOrCreate(['merchant_id' => $m->id, 'email' => $ce], ['name' => $cn, 'contact' => $cn, 'device' => 'iPhone 15', 'device_os' => 'iOS', 'is_active' => true])->id;
}
foreach ([['Portugal 5 GB eSIM', 12.90, 'paid', 0], ['Spain 10 GB eSIM', 21.50, 'paid', 1], ['US verification numbers x5', 9.00, 'sent', 2], ['Italy 3 GB eSIM', 8.40, 'paid', 3], ['UK 20 GB eSIM', 34.00, 'sent', 4]] as $i => [$d, $amt, $st, $ci]) {
    MerchantInvoice::updateOrCreate(['merchant_id' => $m->id, 'reference' => 'INV-DEMO-'.($i + 1)], [
        'merchant_client_id' => $cids[$ci], 'description' => $d, 'amount' => $amt, 'status' => $st, 'due_at' => now()->addDays(7), 'sent_at' => now()->subDays(2),
        'paid_at' => $st === 'paid' ? now()->subDay() : null, 'public_token' => 'demo'.$i.substr(md5((string) $i), 0, 12),
    ]);
}
$bal = 0.0;
foreach ([['esim', 1.61, 'Portugal 5 GB eSIM'], ['esim', 2.69, 'Spain 10 GB eSIM'], ['number', 1.12, 'US verification numbers'], ['esim', 1.05, 'Italy 3 GB eSIM'], ['esim', 4.25, 'UK 20 GB eSIM'], ['esim', 2.31, 'France 5 GB eSIM']] as $i => [$src, $amt, $desc]) {
    $bal += $amt;
    MerchantEarning::updateOrCreate(['reference' => 'demo-earn-'.($i + 1)], ['merchant_id' => $m->id, 'type' => 'accrual', 'amount' => $amt, 'balance_after' => round($bal, 4), 'currency' => 'USD',
        'source_type' => $src, 'source_user_id' => $emily->id, 'description' => $desc, 'created_at' => now()->subDays(6 - $i)]);
}
echo "Chat + merchant demo seeded: ".Message::count()." messages, merchant #{$m->id} {$m->business_name}\n";
