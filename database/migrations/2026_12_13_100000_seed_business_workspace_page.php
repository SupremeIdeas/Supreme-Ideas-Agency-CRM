<?php

use App\Models\Setting;
use App\Support\Business\WorkspaceHero;
use Illuminate\Database\Migrations\Migration;

/** Seeds the editable Business workspace page content (title, four rotating words, description, picture) so it is visible and changeable in Admin > Business Suite > Workspace page. Existing values are never overwritten. */
return new class extends Migration
{
    public function up(): void
    {
        $seed = ['title' => WorkspaceHero::DEFAULT_TITLE, 'description' => WorkspaceHero::DEFAULT_DESCRIPTION, 'image' => ''];
        foreach (WorkspaceHero::DEFAULT_WORDS as $i => $w) {
            $seed['word_'.($i + 1)] = $w;
        }
        foreach ($seed as $key => $value) {
            if (Setting::query()->where('key', WorkspaceHero::KEY.$key)->doesntExist()) {
                Setting::setValue(WorkspaceHero::KEY.$key, $value, WorkspaceHero::GROUP, 'Business workspace page: '.$key);
            }
        }
    }

    public function down(): void
    {
        Setting::query()->where('key', 'like', WorkspaceHero::KEY.'%')->delete();
    }
};
