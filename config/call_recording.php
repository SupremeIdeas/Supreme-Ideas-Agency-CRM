<?php

/**
 * Call recording policy data (MASTER-ONLY, Business Suite Prompt 15). A starting point from docs/legal/CALL-RECORDING-CONSENT-STEER.md:
 * counsel must verify every row before production. Strictest-wins: if either end of a call is "consent", the call is "consent".
 *
 *   notice  = announce at connect, then record (everyone is told)
 *   consent = announce, and the caller must press 1 (when the platform consent mode is "keypad"); no press means the call goes unrecorded
 *
 * A country that is not listed is treated as "consent".
 */
return [
    /** Bump when the spoken wording changes. Stored with every recording as evidence of what was said. */
    'announcement_version' => 'v1',

    'announcement' => 'This call may be recorded for quality and training.',
    'consent_prompt' => 'This call will be recorded for quality and training. Press 1 to agree and continue, or hang up.',
    'agent_notice' => 'This call is being recorded.',

    'default_regime' => 'consent',

    'regimes' => [
        // Africa
        'NG' => 'notice', 'GH' => 'notice', 'KE' => 'notice', 'ZA' => 'notice',
        // UK + Canada
        'GB' => 'notice', 'CA' => 'notice',
        // EU: notice in most states, keypad in the stricter ones. [COUNSEL]
        'IE' => 'notice', 'NL' => 'notice', 'BE' => 'notice', 'PT' => 'notice', 'PL' => 'notice', 'SE' => 'notice', 'DK' => 'notice', 'FI' => 'notice',
        'DE' => 'consent', 'AT' => 'consent', 'CH' => 'consent', 'FR' => 'consent', 'ES' => 'consent', 'IT' => 'consent',
        // US: roughly a dozen states need every party's consent and the state is not resolved here, so the whole country is strict.
        'US' => 'consent',
    ],

    /** Numbers that are never recorded. Prefix match on the digits after "+". */
    'never_record_prefixes' => ['112', '911', '999', '199', '122'],
];
