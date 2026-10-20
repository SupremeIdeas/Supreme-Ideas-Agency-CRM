<?php

namespace App\Services\Omni\Adapters;

use App\Models\ChannelConnection;

/** Instagram Messaging (MASTER-ONLY, blueprint Phase 3). See MessagingPlatformAdapter. */
final class InstagramAdapter extends MessagingPlatformAdapter
{
    public function channel(): string
    {
        return ChannelConnection::INSTAGRAM;
    }

    protected function label(): string
    {
        return 'Instagram';
    }
}
