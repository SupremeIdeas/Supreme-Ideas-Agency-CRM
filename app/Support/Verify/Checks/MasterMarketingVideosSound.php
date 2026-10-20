<?php

namespace App\Support\Verify\Checks;

use App\Models\MasterMarketingVideoSection;
use App\Support\MasterMarketing\VideoSlots;
use App\Support\MasterMarketing\VideoSource;
use App\Support\MediaStorage;
use App\Support\Verify\Check;
use App\Support\Verify\CheckResult;
use Throwable;

/** MASTER-ONLY. Marketing video sections point at a known slot, a playable source, and (for uploads) a file that still exists; visible uploads without a poster are flagged. */
class MasterMarketingVideosSound implements Check
{
    public function area(): string
    {
        return 'media';
    }

    public function name(): string
    {
        return 'Marketing video sections are playable and have posters';
    }

    public function run(): CheckResult
    {
        $problems = [];
        $noPoster = 0;
        try {
            foreach (MasterMarketingVideoSection::query()->get() as $v) {
                if (! VideoSlots::known($v->slot)) {
                    $problems[] = "#{$v->id}: unknown slot {$v->slot}";
                }
                if ($v->source_type === 'upload') {
                    $local = MediaStorage::localPublicPath((string) $v->video_url);
                    if ($local !== null && ! is_file($local)) {
                        $problems[] = "#{$v->id}: uploaded file is missing";
                    }
                } elseif (VideoSource::parseLink((string) $v->video_url) === null) {
                    $problems[] = "#{$v->id}: link is not a playable YouTube, Vimeo or video file link";
                }
                if ($v->is_visible && $v->source_type === 'upload' && ! filled($v->poster_url)) {
                    $noPoster++;
                }
            }
        } catch (Throwable $e) {
            return CheckResult::fail('Could not read marketing video sections: '.$e->getMessage());
        }

        if ($problems !== []) {
            return CheckResult::fail(count($problems).' marketing video problem(s): '.implode('; ', array_slice($problems, 0, 5)), ['problems' => $problems]);
        }

        return $noPoster > 0
            ? CheckResult::warn("$noPoster visible uploaded video(s) have no poster image; visitors see a blank frame until they press play.")
            : CheckResult::pass('Every marketing video points at a known slot and a playable source.');
    }
}
