<?php
/**
 * NaaraBrain demo threads for the marketing screenshots (MASTER-ONLY, throwaway sqlite database only).
 * Run after seed-demo.php:  DB_CONNECTION=sqlite DB_DATABASE=/tmp/brain.sqlite php scripts/mockups/seed-brain-demo.php
 * The replies are written by hand for the screenshot; no model is called.
 */
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Brain\BrainMessage;
use App\Models\Brain\BrainThread;
use App\Models\Setting;
use App\Models\User;
use App\Support\Brain\BrainSettings;

if (config('database.default') !== 'sqlite') { fwrite(STDERR, "Refusing: not a sqlite demo database.\n"); exit(1); }
Setting::setValue(BrainSettings::FLAG, true);
$u = User::where('email', 'emily@naara.test')->firstOrFail();
BrainThread::where('user_id', $u->id)->delete();
$threads = [
    ['Which eSIM plan for Lisbon?', 'balanced', true, 12, [
        ['user', 'I am in Lisbon for a week. Which plan should I get, and will my phone work with it?'],
        ['assistant', "For a week in Lisbon, a 5 GB plan valid for 30 days is a comfortable fit: maps, messaging and a few hours of video.\n\nBefore you buy, run the device check in the app so you know your phone supports eSIM. If you run low mid-trip, you can top up from the app without buying a new plan."],
        ['user', 'And if I want to keep my home number for calls?'],
        ['assistant', "Your home number stays on your regular SIM. The Naara eSIM only handles data, so calls and texts still arrive on your home number while your data runs through Naara."],
    ]],
    ['How do I install my eSIM?', 'quick', true, 190, [['user', 'How do I install my eSIM?'], ['assistant', 'Open My eSIMs, tap the plan, then Show setup. Scan the QR code from another screen, or use the manual install details if you are on the same phone.']]],
    ['Bring my US number to Naara', 'balanced', false, 1500, [['user', 'Can I bring my US number?'], ['assistant', 'Yes, US and Canada numbers can be ported in from the Numbers area. You will be asked for the details of the account you are leaving.']]],
    ['What does an unused plan refund look like?', 'quick', false, 3100, [['user', 'Refund for an unused plan?'], ['assistant', 'The refund policy is shown before you buy. Open the plan and read the refund section for the exact conditions.']]],
    ['A verification number for WhatsApp', 'quick', false, 6200, [['user', 'Which number type for WhatsApp?'], ['assistant', 'Use a verification number for a one-time code, or a rental if you need to receive messages for longer.']]],
];
foreach ($threads as [$title, $depth, $pinned, $minsAgo, $msgs]) {
    $t = BrainThread::create(['user_id' => $u->id, 'title' => $title, 'depth' => $depth, 'length' => 'normal', 'pinned' => $pinned, 'last_message_at' => now()->subMinutes($minsAgo)]);
    foreach ($msgs as $i => [$role, $body]) {
        BrainMessage::create(['thread_id' => $t->id, 'user_id' => $u->id, 'role' => $role, 'body' => $body, 'status' => 'done', 'model' => $role === 'assistant' ? 'demo' : null, 'created_at' => now()->subMinutes($minsAgo - $i)]);
    }
}
echo "Brain demo seeded: ".BrainThread::count()." threads\n";
