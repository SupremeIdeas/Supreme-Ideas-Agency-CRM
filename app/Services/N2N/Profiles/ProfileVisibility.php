<?php

namespace App\Services\N2N\Profiles;

use App\Models\BrandPartner;
use App\Models\N2N\Block;
use App\Models\N2N\ProfilePicture;
use App\Models\N2N\UserPhone;
use App\Models\User;
use App\Services\N2N\Messaging\Privacy;

/**
 * The ONE place that decides what a viewer may see on a member's local profile (N2N Batch 8, MASTER-ONLY).
 *
 * Rules (also covered cell-by-cell in tests/Feature/N2N/ProfileTest.php):
 *  - Name and username are all a profile ever shows to a stranger; blocked viewers (either direction) and inactive owners get null -> 404.
 *  - `public`: Naara ID, bio, birthday, pictures are visible to any signed-in viewer.
 *  - `connections`: the same, but only to a connected viewer; others see name + username only.
 *  - `private`: only the fields the owner ticked from {naara_id, email, phone, images} (bio and birthday are never shown).
 *  - Email and phone are NEVER shown by visibility alone: they need the owner's explicit tick AND a connected viewer (or the owner).
 *  - Birthday shows day and month only, unless the owner opted in to showing the year.
 *  - A member whose brand listing is Naara Pro (an upgrade) also shows its business card; see business().
 */
class ProfileVisibility
{
    public const REVEALABLE = ['naara_id', 'email', 'phone', 'images'];

    public const VISIBILITIES = ['public', 'connections', 'private'];

    /** @return array{relation: string, fields: list<string>}|null null = the viewer cannot see this profile at all (answer 404) */
    public static function resolve(User $viewer, User $subject): ?array
    {
        if ($viewer->id === $subject->id) {
            return ['relation' => 'self', 'fields' => ['name', 'username', 'naara_id', 'bio', 'birthday', 'images', 'email', 'phone']];
        }
        if (! $subject->is_active || self::blockedEitherWay($viewer->id, $subject->id)) {
            return null;
        }
        $connected = Privacy::connected($viewer->id, $subject->id);
        if ($subject->n2n_hide_me && ! $connected) {
            return null;     // "hide me" removes the profile for people who are not already connected
        }
        $reveal = array_values(array_intersect(self::REVEALABLE, (array) ($subject->n2n_profile_reveal ?? [])));
        $mode = in_array($subject->n2n_profile_visibility, self::VISIBILITIES, true) ? $subject->n2n_profile_visibility : 'connections';

        $fields = ['name', 'username'];
        if ($mode === 'public' || ($mode === 'connections' && $connected)) {
            $fields = array_merge($fields, ['naara_id', 'bio', 'birthday', 'images']);
        } elseif ($mode === 'private') {
            $fields = array_merge($fields, array_values(array_intersect($reveal, ['naara_id', 'images'])));
        }
        if ($connected) {
            foreach (['email', 'phone'] as $sensitive) {
                if (in_array($sensitive, $reveal, true)) {
                    $fields[] = $sensitive;
                }
            }
        }

        return ['relation' => $connected ? 'connected' : 'stranger', 'fields' => array_values(array_unique($fields))];
    }

    /** The data a page may render: only the permitted fields are ever read off the model. */
    public static function payload(User $viewer, User $subject): ?array
    {
        $r = self::resolve($viewer, $subject);
        if ($r === null) {
            return null;
        }
        $f = $r['fields'];
        $out = ['relation' => $r['relation'], 'name' => $subject->name, 'username' => $subject->username];
        if (in_array('naara_id', $f, true)) {
            $out['naara_id'] = $subject->naara_id;
        }
        if (in_array('bio', $f, true) && filled($subject->bio)) {
            $out['bio'] = $subject->bio;
        }
        if (in_array('birthday', $f, true) && $subject->date_of_birth) {
            $out['birthday'] = $subject->n2n_dob_show_year ? $subject->date_of_birth->format('j F Y') : $subject->date_of_birth->format('j F');
        }
        if (in_array('email', $f, true)) {
            $out['email'] = $subject->email;
        }
        if (in_array('phone', $f, true)) {
            $out['phone'] = UserPhone::query()->where('user_id', $subject->id)->whereNotNull('verified_at')->value('e164');
        }
        if (in_array('images', $f, true)) {
            $out['pictures'] = ProfilePicture::query()->where('user_id', $subject->id)->orderBy('slot')->pluck('slot')->all();
        }
        if (($business = self::business($subject)) !== null) {
            $out['business'] = $business;
        }

        return $out;
    }

    /**
     * Upgrade merge: when a member upgrades (their brand listing is Naara Pro, paid or admin-granted) their chat profile carries the business card.
     * Derived on every read from the one brand record, never copied, so editing the brand updates the chat profile and a lapsed upgrade removes the card at once.
     * Only public listing fields are ever read (never plan, cost or subscription detail), and only for a live, active listing.
     *
     * @return array{name: string, category: ?string, about: ?string, image: ?string}|null
     */
    public static function business(User $subject): ?array
    {
        $brand = BrandPartner::query()->with('subscription')->where('owner_user_id', $subject->id)->listed()->first();
        if ($brand === null || ! $brand->isNaaraPro()) {
            return null;
        }

        return ['name' => $brand->brand_name, 'category' => $brand->category, 'about' => $brand->short_description, 'image' => $brand->hero_image_path ?: $brand->fallback_image];
    }

    private static function blockedEitherWay(int $a, int $b): bool
    {
        return Block::query()->where(fn ($q) => $q->where('blocker_id', $a)->where('blocked_id', $b))->orWhere(fn ($q) => $q->where('blocker_id', $b)->where('blocked_id', $a))->exists();
    }
}
