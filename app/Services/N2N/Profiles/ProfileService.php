<?php

namespace App\Services\N2N\Profiles;

use App\Models\N2N\ProfilePicture;
use App\Models\User;
use App\Services\N2N\Brands\BrandLimitException;
use App\Services\N2N\Media\ImageSanitizer;
use App\Services\N2N\Media\MediaSniffer;
use App\Support\MediaStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** A member's own profile settings and their two lightweight pictures (N2N Batch 8, MASTER-ONLY). */
class ProfileService
{
    private const MAX_UPLOAD = 3_000_000;

    private const MAX_STORED = 150_000;      // "lightweight": pictures are re-encoded small

    public function save(User $user, string $visibility, array $reveal, bool $showYear, ?string $bio): void
    {
        if (! in_array($visibility, ProfileVisibility::VISIBILITIES, true)) {
            throw new BrandLimitException('visibility', 'Choose public, connections or private.');
        }
        $user->forceFill([
            'n2n_profile_visibility' => $visibility,
            'n2n_profile_reveal' => array_values(array_intersect(ProfileVisibility::REVEALABLE, $reveal)),
            'n2n_dob_show_year' => $showYear,
            'bio' => $bio !== null ? Str::limit(trim(strip_tags($bio)), 280, '') : $user->bio,
        ])->save();
    }

    /** @throws BrandLimitException */
    public function setPicture(User $user, int $slot, UploadedFile $file): ProfilePicture
    {
        if (! in_array($slot, [1, 2], true)) {
            throw new BrandLimitException('slot', 'You can keep two pictures.');
        }
        $path = $file->getRealPath();
        $size = (int) filesize($path);
        if ($size <= 0 || $size > self::MAX_UPLOAD) {
            throw new BrandLimitException('too_large', 'That picture is too large (3 MB maximum).');
        }
        $raw = (string) file_get_contents($path);
        $sn = MediaSniffer::sniff(substr($raw, 0, 64), 'image');
        if ($sn === null || $sn['kind'] !== 'image' || ! in_array($sn['mime'], ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw new BrandLimitException('type', 'Use a JPEG, PNG or WebP picture.');
        }
        $clean = ImageSanitizer::clean($raw, $sn['mime']);
        if ($clean === null) {
            throw new BrandLimitException('type', 'That picture could not be read.');
        }
        // Profile pictures are shown small: always the thumbnail-sized JPEG, so each stays tiny.
        $bytes = $clean['thumb'];
        if (strlen($bytes) > self::MAX_STORED) {
            throw new BrandLimitException('too_large', 'That picture is too detailed. Try a simpler one.');
        }
        $disk = MediaStorage::privateDisk();
        $key = 'n2n/profile/'.$user->id.'/'.Str::random(32).'.jpg';
        Storage::disk($disk)->put($key, $bytes, ['visibility' => 'private']);

        $old = ProfilePicture::query()->where('user_id', $user->id)->where('slot', $slot)->first();
        $row = ProfilePicture::query()->updateOrCreate(['user_id' => $user->id, 'slot' => $slot], ['disk' => $disk, 'storage_key' => $key, 'bytes' => strlen($bytes)]);
        if ($old !== null) {
            Storage::disk($old->disk)->delete($old->storage_key);
        }

        return $row;
    }

    public function removePicture(User $user, int $slot): void
    {
        $row = ProfilePicture::query()->where('user_id', $user->id)->where('slot', $slot)->first();
        if ($row !== null) {
            Storage::disk($row->disk)->delete($row->storage_key);
            $row->delete();
        }
    }

    /** Erasure: every picture object goes, then the rows. */
    public function eraseAll(User $user): void
    {
        foreach (ProfilePicture::query()->where('user_id', $user->id)->get() as $p) {
            Storage::disk($p->disk)->delete($p->storage_key);
        }
        ProfilePicture::query()->where('user_id', $user->id)->delete();
    }

    /** Resolve a profile by username or Naara ID. Anything unknown is simply null (the caller answers one uniform 404). */
    public static function find(string $handle): ?User
    {
        $h = trim($handle);
        $id = \App\Services\N2N\Identity\NaaraId::normalise($h);
        $q = User::query();

        return ($id !== null ? $q->where('naara_id', $id) : $q->where('username', strtolower(ltrim($h, '@'))))->first();
    }
}
