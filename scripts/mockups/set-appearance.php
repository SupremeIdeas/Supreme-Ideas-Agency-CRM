<?php
/**
 * Switch the demo member's skin for the marketing screenshots:  php scripts/mockups/set-appearance.php <skin> [accent] [mode]
 * Throwaway sqlite demo database only. Enables the skin preset first, so a Pro skin renders for the demo member.
 */
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\UserAppearance;
use App\Support\Appearance\AppearanceResolver;
use Illuminate\Support\Facades\DB;

if (config('database.default') !== 'sqlite') { fwrite(STDERR, "Refusing: not a sqlite demo database.\n"); exit(1); }
[$skin, $accent, $mode] = [$argv[1] ?? 'surface', $argv[2] ?? 'teal', $argv[3] ?? 'light'];
DB::table('appearance_presets')->where('kind', 'skin')->update(['enabled' => 1, 'access' => 'free']);
$u = User::where('email', 'emily@naara.test')->firstOrFail();
UserAppearance::query()->updateOrCreate(['user_id' => $u->id], ['skin_key' => $skin, 'accent_key' => $accent, 'mode' => $mode]);
AppearanceResolver::forget($u->id);
AppearanceResolver::forgetPlatform();
echo "skin=$skin accent=$accent mode=$mode\n";
