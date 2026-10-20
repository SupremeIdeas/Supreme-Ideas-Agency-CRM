<?php

namespace App\Services\Omni;

/** A free-form reply was refused because the customer has not written in the last 24 hours (MASTER-ONLY): an approved template is the only way in. */
class WindowClosedException extends \RuntimeException
{
}
