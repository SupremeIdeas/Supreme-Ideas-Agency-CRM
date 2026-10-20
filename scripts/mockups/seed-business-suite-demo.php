<?php
/**
 * Business Suite demo data for the onboarding slides (MASTER-ONLY, like the module). Throwaway sqlite database only. Run after seed-demo.php:
 *   DB_CONNECTION=sqlite DB_DATABASE=/tmp/mockdemo.sqlite php scripts/mockups/seed-business-suite-demo.php
 * Invents one small travel business ("Carter Travel Co") with a shared inbox across WhatsApp, Instagram, Telegram, web chat and SMS. Nobody here is a real person.
 */
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ChannelConnection;
use App\Models\Omni\OmniContact;
use App\Models\Omni\OmniConversation;
use App\Models\Omni\OmniMessage;
use App\Models\Setting;
use App\Models\User;
use App\Models\WalletGroup;
use App\Services\Permissions\WorkspaceRoleService;
use App\Support\Permissions\BusinessRole;
use App\Support\Business\BusinessSettings;
use App\Support\Business\BusinessSuiteSettings;

if (config('database.default') !== 'sqlite') { fwrite(STDERR, "Refusing: not a sqlite demo database.\n"); exit(1); }

BusinessSuiteSettings::set(true);
Setting::setValue(BusinessSettings::ACCESS.'model', 'open');
foreach (['inbox', 'omni', 'call_flows', 'recording', 'ai_voice'] as $m) { Setting::setValue(BusinessSettings::MODULE.$m, true, 'business'); }

$emily = User::where('email', 'emily@naara.test')->firstOrFail();
$ws = WalletGroup::firstOrCreate(['owner_user_id' => $emily->id, 'kind' => WalletGroup::KIND_BUSINESS], ['name' => 'Carter Travel Co']);
app(WorkspaceRoleService::class)->assignRole($ws, $emily, BusinessRole::OWNER);

$conn = [];
foreach ([['whatsapp', 'Carter Travel WhatsApp'], ['instagram', 'carter.travel'], ['telegram', 'Carter Travel bot'], ['webchat', 'Website chat'], ['sms', 'Business line']] as [$ch, $label]) {
    $conn[$ch] = ChannelConnection::firstOrCreate(['wallet_group_id' => $ws->id, 'channel' => $ch, 'external_id' => 'demo-'.$ch], ['status' => 'active', 'label' => $label, 'credentials' => [], 'webhook_secret' => 'demo-secret-0123456789abc']);
}
$threads = [
    ['whatsapp', 'Sophie Turner', 'Hi! Do you have a Ghana eSIM for two weeks?', 3, 4],
    ['instagram', 'Michael Johnson', 'Saw your story. Can I add a UK number to my plan?', 1, 9],
    ['telegram', 'Olivia Bennett', 'Thanks, the code came through straight away.', 0, 22],
    ['webchat', 'Jack Thompson', 'Is there a team plan for five phones?', 2, 31],
    ['sms', 'Emma Davis', 'Please call me back about my booking.', 1, 47],
    ['whatsapp', 'Daniel Okafor', 'Can I pay with my wallet balance?', 0, 75],
];
foreach ($threads as [$ch, $name, $preview, $unread, $mins]) {
    $contact = OmniContact::firstOrCreate(['workspace_id' => $ws->id, 'name' => $name]);
    $c = OmniConversation::firstOrCreate(['workspace_id' => $ws->id, 'contact_id' => $contact->id, 'channel' => $ch], [
        'connection_id' => $conn[$ch]->id, 'status' => 'open', 'last_preview' => $preview, 'last_message_at' => now()->subMinutes($mins), 'last_inbound_at' => now()->subMinutes($mins), 'unread' => $unread,
    ]);
    OmniMessage::firstOrCreate(['workspace_id' => $ws->id, 'conversation_id' => $c->id, 'direction' => 'in'], ['status' => 'received', 'body' => $preview]);
}
echo "Business Suite demo seeded: workspace #{$ws->id} ".count($threads)." conversations\n";
