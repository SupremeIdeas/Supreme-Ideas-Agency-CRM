<?php

namespace App\Support\MasterMarketing;

/** Naara's own official Trustpilot profile (MASTER-ONLY, never shipped to a white label). Used only as the default when an admin has not saved their own link. */
final class NaaraTrustpilotProfile
{
    public const URL = 'https://www.trustpilot.com/review/naara.app';

    /** Naara's Trustpilot invitation-script key. It is a public client-side id, kept here (master only) so it never ships to a white label. */
    public const INTEGRATION_KEY = 'A5H7RxzB3RWdbggz';
}
