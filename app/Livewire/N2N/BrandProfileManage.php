<?php

namespace App\Livewire\N2N;

use App\Models\BrandPartner;
use App\Models\BrandPartnerImage;
use App\Models\N2N\BrandAlbum;
use App\Models\N2N\BrandEmbed;
use App\Models\N2N\BrandMusicLink;
use App\Models\N2N\BrandSocialLink;
use App\Services\N2N\Brands\BrandExtras;
use App\Services\N2N\Brands\BrandLimitException;
use App\Services\N2N\Brands\BrandLimits;
use App\Services\N2N\Brands\LinkValidator;
use App\Support\MediaStorage;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Brand owner > profile extras (N2N Batch 8, MASTER-ONLY): music profile request, gallery images, music links, embedded players, albums and
 * social links. The brand is ALWAYS loaded by owner_user_id, never from a posted id, and every limit is enforced by BrandExtras / the model
 * observer on the server (this form only shows the numbers).
 */
#[Layout('components.layouts.customer')]
class BrandProfileManage extends Component
{
    use WithFileUploads;

    public string $musicPlatform = 'spotify';

    public string $musicUrl = '';

    public string $embedCode = '';

    public string $socialPlatform = 'instagram';

    public string $socialUrl = '';

    /** Album studio: null = closed, 0 = a new album, otherwise the album being edited (always one of MY albums). */
    public ?int $albumId = null;

    public string $albumTitle = '';

    public string $albumDescription = '';

    public string $albumRelease = '';

    public string $albumDistributor = '';

    public string $albumSmartUrl = '';

    /** @var list<array{platform: string, url: string}> */
    public array $albumLinks = [];

    public $albumCover = null;

    public ?string $albumCoverUrl = null;

    public bool $albumLive = false;

    public $image = null;

    public ?string $message = null;

    public ?string $error = null;

    public function mount(): void
    {
        abort_unless(BrandPartner::query()->where('owner_user_id', Auth::id())->exists(), 404);
    }

    private function brand(): BrandPartner
    {
        return BrandPartner::query()->where('owner_user_id', Auth::id())->firstOrFail();
    }

    /** Run a mutation, turning a limit/validation refusal into a friendly message. */
    private function guard(callable $fn, string $ok): void
    {
        $this->reset('message', 'error');
        try {
            $fn($this->brand(), app(BrandExtras::class));
            $this->message = $ok;
        } catch (BrandLimitException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function setUpdatesVisibility(string $value): void
    {
        if (in_array($value, ['connections', 'nobody'], true)) {
            $this->brand()->forceFill(['n2n_updates_visibility' => $value])->save();
            $this->reset('error');
            $this->message = 'Saved.';
        }
    }

    public function requestMusic(): void
    {
        $this->guard(fn ($b, $x) => $x->requestMusic($b), 'Requested. We will confirm your music profile shortly.');
    }

    public function addMusic(): void
    {
        $this->guard(function ($b, $x) {
            $x->addMusicLink($b, $this->musicPlatform, $this->musicUrl);
            $this->musicUrl = '';
        }, 'Link added.');
    }

    public function removeMusic(int $id): void
    {
        app(BrandExtras::class)->removeMusicLink($this->brand(), $id);
    }

    public function addEmbed(): void
    {
        $this->guard(function ($b, $x) {
            $x->addEmbed($b, $this->embedCode);
            $this->embedCode = '';
        }, 'Player added.');
    }

    public function removeEmbed(int $id): void
    {
        app(BrandExtras::class)->removeEmbed($this->brand(), $id);
    }

    public function addSocial(): void
    {
        $this->guard(function ($b, $x) {
            $x->addSocialLink($b, $this->socialPlatform, $this->socialUrl);
            $this->socialUrl = '';
        }, 'Link added.');
    }

    public function removeSocial(int $id): void
    {
        app(BrandExtras::class)->removeSocialLink($this->brand(), $id);
    }

    public function newAlbum(): void
    {
        $this->resetAlbumForm();
        $this->albumId = 0;
        $this->albumLinks = [['platform' => 'spotify', 'url' => '']];
    }

    public function editAlbum(int $id): void
    {
        $a = BrandAlbum::query()->where('brand_partner_id', $this->brand()->id)->with('links')->findOrFail($id);   // ownership: scoped to MY brand
        $this->resetAlbumForm();
        $this->albumId = $a->id;
        $this->albumTitle = $a->title;
        $this->albumDescription = (string) $a->description;
        $this->albumRelease = $a->release_date?->toDateString() ?? '';
        $this->albumDistributor = (string) $a->distributor;
        $this->albumSmartUrl = (string) $a->smart_url;
        $this->albumLinks = $a->links->map(fn ($l) => ['platform' => $l->platform, 'url' => $l->url])->values()->all() ?: [['platform' => 'spotify', 'url' => '']];
        $this->albumCoverUrl = $a->cover_path;
        $this->albumLive = $a->published_at !== null;
    }

    public function closeAlbum(): void
    {
        $this->resetAlbumForm();
    }

    public function addAlbumLinkRow(): void
    {
        if (count($this->albumLinks) < 25) {
            $this->albumLinks[] = ['platform' => 'spotify', 'url' => ''];
        }
    }

    public function removeAlbumLinkRow(int $i): void
    {
        unset($this->albumLinks[$i]);
        $this->albumLinks = array_values($this->albumLinks);
    }

    /** Save the album as a draft, or save and publish it (the carousel and the public page only show published albums). */
    public function saveAlbum(bool $publish = false): void
    {
        $this->reset('message', 'error');
        $this->validate(['albumCover' => 'nullable|image|mimes:webp,jpg,jpeg,png|max:5120']);
        try {
            $b = $this->brand();
            $a = app(BrandExtras::class)->saveAlbum($b, $this->albumId ?: null, [
                'title' => $this->albumTitle, 'description' => $this->albumDescription, 'release_date' => $this->albumRelease, 'distributor' => $this->albumDistributor,
                'smart_url' => $this->albumSmartUrl, 'links' => $this->albumLinks, 'publish' => $publish,
            ], $this->albumCover);
            $this->message = $publish ? '"'.$a->title.'" is live on your profile.' : '"'.$a->title.'" saved as a draft.';
            $this->resetAlbumForm();
        } catch (BrandLimitException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function unpublishAlbum(int $id): void
    {
        BrandAlbum::query()->where('brand_partner_id', $this->brand()->id)->whereKey($id)->update(['published_at' => null]);
        $this->message = 'Album taken off your profile. It is kept as a draft.';
    }

    public function moveAlbum(int $id, int $direction): void
    {
        app(BrandExtras::class)->moveAlbum($this->brand(), $id, $direction);
    }

    public function deleteAlbum(int $id): void
    {
        app(BrandExtras::class)->deleteAlbum($this->brand(), $id);
        $this->resetAlbumForm();
    }

    private function resetAlbumForm(): void
    {
        $this->reset('albumId', 'albumTitle', 'albumDescription', 'albumRelease', 'albumDistributor', 'albumSmartUrl', 'albumLinks', 'albumCover', 'albumCoverUrl', 'albumLive');
    }

    public function addImage(): void
    {
        $this->reset('message', 'error');
        $this->validate(['image' => 'required|image|mimes:webp,jpg,jpeg,png|max:2048']);
        try {
            $b = $this->brand();
            BrandPartnerImage::query()->create([
                'brand_partner_id' => $b->id,
                'image_path' => MediaStorage::storePublic($this->image, 'brand-gallery'),
                'sort_order' => (int) BrandPartnerImage::query()->where('brand_partner_id', $b->id)->max('sort_order') + 1,
            ]);
            $this->message = 'Image added.';
        } catch (BrandLimitException $e) {
            $this->error = $e->getMessage();
        }
        $this->image = null;
    }

    public function removeImage(int $id): void
    {
        BrandPartnerImage::query()->where('brand_partner_id', $this->brand()->id)->whereKey($id)->delete();
    }

    public function render()
    {
        $b = $this->brand();

        return view('livewire.n2n.brand-profile-manage', [
            'brand' => $b,
            'tier' => BrandLimits::tier($b),
            'platforms' => LinkValidator::platforms(),
            'limits' => ['images' => BrandLimits::for($b, 'images'), 'music_links' => BrandLimits::for($b, 'music_links'), 'embeds' => BrandLimits::for($b, 'embeds')],
            'imageCount' => BrandPartnerImage::query()->where('brand_partner_id', $b->id)->count(),
            'music' => BrandMusicLink::query()->where('brand_partner_id', $b->id)->orderBy('sort_order')->get(),
            'embeds' => BrandEmbed::query()->where('brand_partner_id', $b->id)->orderBy('sort_order')->get(),
            'social' => BrandSocialLink::query()->where('brand_partner_id', $b->id)->orderBy('sort_order')->get(),
            'albums' => BrandAlbum::query()->where('brand_partner_id', $b->id)->with('links')->orderBy('sort_order')->orderBy('id')->get(),
            'albumCap' => BrandLimits::albumCap($b),
            'distributors' => \App\Support\N2N\MusicLogos::distributorOptions(),
            'gallery' => BrandPartnerImage::query()->where('brand_partner_id', $b->id)->orderBy('sort_order')->get(),
        ]);
    }
}
