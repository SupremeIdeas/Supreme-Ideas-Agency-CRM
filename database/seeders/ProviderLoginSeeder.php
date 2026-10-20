<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/** Provider login manager defaults (MASTER-ONLY): the dashboards, their login links and the email hints. Idempotent; never overwrites an edit. */
class ProviderLoginSeeder extends Seeder
{
    public function run(): void
    {
        \App\Support\ProviderLogins::seed();
    }
}
