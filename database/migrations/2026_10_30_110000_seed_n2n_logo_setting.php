<?php

use App\Models\Setting;
use App\Support\N2N\N2NSettings;
use Illuminate\Database\Migrations\Migration;

/** Seeds the Naara chat logo as an ordinary, admin-editable setting (Admin > N2N) instead of a path written into views. Never overwrites an admin's choice. */
return new class extends Migration
{
    public function up(): void
    {
        if (! class_exists(N2NSettings::class) || trim((string) Setting::getValue('n2n.logo_url', '')) !== '') {
            return;
        }
        Setting::setValue('n2n.logo_url', N2NSettings::DEFAULT_LOGO, 'n2n', 'Naara chat logo (asset path or uploaded URL)');
    }

    public function down(): void
    {
    }
};
