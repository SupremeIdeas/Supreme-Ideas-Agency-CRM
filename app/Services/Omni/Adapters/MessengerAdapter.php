<?php

namespace App\Services\Omni\Adapters;

use App\Models\ChannelConnection;

/** Facebook Messenger for a Facebook Page (MASTER-ONLY, blueprint Phase 3). See MessagingPlatformAdapter. */
final class MessengerAdapter extends MessagingPlatformAdapter
{
    public function channel(): string
    {
        return ChannelConnection::MESSENGER;
    }

    protected function label(): string
    {
        return 'Messenger';
    }
}
