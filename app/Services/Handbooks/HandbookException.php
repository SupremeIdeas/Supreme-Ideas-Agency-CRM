<?php

namespace App\Services\Handbooks;

/** A handbook action the person can fix or understand; the message is safe to show them (MASTER-ONLY). */
class HandbookException extends \RuntimeException
{
}
