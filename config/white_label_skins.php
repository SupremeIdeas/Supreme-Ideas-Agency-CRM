<?php

/*
 * MASTER ONLY. How many dashboard skins a white-label licence unlocks (Prompt 22). The forks never read this file: they receive the
 * resulting number in the entitlement payload and obey it (docs/architecture/WHITE-LABEL-LICENSE-BOUNDARY.md). A fork fitness test fails
 * the build if this file ever appears there.
 */
return [
    // Keyed by licence TIER (not plan, not entitlement level): Extended and Extended V2 share one tier and therefore one allowance.
    // The default skin counts toward the allowance (2 = default + one switch skin; 5 = default + four).
    'allowance' => ['normal' => 2, 'extended' => 5],

    // No licence is ever given less than this (a fork with no skins at all would have no dashboard look).
    'minimum' => 1,
];
