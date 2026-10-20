<?php

namespace App\Services\Inbox;

/** A reply could not be sent on its channel. The message is safe to show the person who tried. */
class ChannelSendException extends \RuntimeException
{
}
