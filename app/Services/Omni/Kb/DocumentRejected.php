<?php

namespace App\Services\Omni\Kb;

/** A document the knowledge base refuses, with a message that is safe to show to the person who uploaded it (MASTER-ONLY). */
class DocumentRejected extends \RuntimeException
{
}
