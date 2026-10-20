<?php

namespace App\Services\N2N\Identity;

use RuntimeException;

/** Too many lookups: anti-enumeration throttle tripped (N2N, MASTER-ONLY). */
class ResolveThrottled extends RuntimeException {}
