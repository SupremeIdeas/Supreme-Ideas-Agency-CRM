<?php

namespace App\Support\WhiteLabel;

use App\Models\WhiteLabelInstance;
use App\Support\FeatureLocks;

/**
 * BOUNDARY: master-only. The ONE place that builds the entitlement block a fork receives (activate response + entitlement check-in), so
 * the two endpoints can never drift. Additive and backward compatible: `level` and `locks` are unchanged; `skins.allowance` is new, and
 * a fork that receives no `skins` key (an older master) simply keeps what it had. Must NEVER exist in a white-label build.
 *
 * @see docs/architecture/WHITE-LABEL-LICENSE-BOUNDARY.md
 */
class EntitlementPayload
{
    /** @return array{level: ?string, locks: list<string>, skins: array{allowance: int}} */
    public static function for(WhiteLabelInstance $instance): array
    {
        return FeatureLocks::entitlementFor($instance->entitlement_level) + [
            'skins' => ['allowance' => SkinAllowance::forInstance($instance)],
        ];
    }
}
