<?php

namespace App\Support\Verify\Checks;

use App\Models\BrandPartner;
use App\Services\Brands\BrandVisibility;
use App\Support\Verify\Check;
use App\Support\Verify\CheckResult;
use Throwable;

/** MASTER-ONLY. `naara:verify brand`: no listed brand is hidden from itself. Every brand the directory lists is "live" by its own reason, and none is listed unpublished. */
class BrandVisibilitySound implements Check
{
    public function area(): string
    {
        return 'brand';
    }

    public function name(): string
    {
        return 'Listed brands are published and entitled';
    }

    public function run(): CheckResult
    {
        $unpublished = [];
        $notLive = [];
        try {
            BrandPartner::query()->where('listing_status', BrandPartner::STATUS_ACTIVE)->where('is_active', true)->with(['subscription', 'handles'])->orderBy('id')->chunkById(200, function ($rows) use (&$unpublished, &$notLive) {
                foreach ($rows as $b) {
                    if ((int) $b->gate_version >= BrandVisibility::GATE_V2 && $b->published_at === null) {
                        $unpublished[] = $b->id;
                    }
                    if (BrandVisibility::reason($b) !== 'live') {
                        $notLive[] = $b->id.':'.BrandVisibility::reason($b);
                    }
                }
            });
        } catch (Throwable $e) {
            return CheckResult::fail('Brand visibility could not be checked: '.$e->getMessage());
        }
        if ($unpublished !== []) {
            return CheckResult::fail(count($unpublished).' listed brand(s) were never published by their owner (soft gate bypassed): '.implode(', ', array_slice($unpublished, 0, 8)).'.', ['unpublished' => $unpublished]);
        }

        return $notLive !== []
            ? CheckResult::warn(count($notLive).' listed brand(s) are not "live" by their own reason (billing or content changed since): '.implode(', ', array_slice($notLive, 0, 6)).'.', ['not_live' => $notLive])
            : CheckResult::pass('Every listed brand is published and entitled.');
    }
}
