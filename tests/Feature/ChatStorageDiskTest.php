<?php

namespace Tests\Feature;

use App\Support\MediaStorage;
use Tests\TestCase;

/** Chat files (N2N media, support chat) go to the configured object store, and only fall back to the server's own private disk when none is configured. */
class ChatStorageDiskTest extends TestCase
{
    private function creds(string $disk, array $v): void
    {
        foreach ($v as $k => $val) {
            config(["filesystems.disks.{$disk}.{$k}" => $val]);
        }
    }

    public function test_private_chat_files_use_the_local_private_disk_when_no_cloud_store_is_configured(): void
    {
        $this->creds('r2', ['key' => null, 'secret' => null, 'bucket' => null, 'endpoint' => null]);
        $this->creds('wasabi', ['key' => null, 'secret' => null, 'bucket' => null]);
        $this->assertSame('local', MediaStorage::privateDisk());
    }

    public function test_wasabi_wins_when_only_wasabi_is_configured(): void
    {
        $this->creds('r2', ['key' => null, 'secret' => null, 'bucket' => null, 'endpoint' => null]);
        $this->creds('wasabi', ['key' => 'k', 'secret' => 's', 'bucket' => 'b']);
        $this->assertSame('wasabi', MediaStorage::privateDisk());
    }

    public function test_r2_wins_when_configured(): void
    {
        $this->creds('r2', ['key' => 'k', 'secret' => 's', 'bucket' => 'b', 'endpoint' => 'https://x.r2.cloudflarestorage.com']);
        $this->assertSame('r2', MediaStorage::privateDisk());
    }
}
