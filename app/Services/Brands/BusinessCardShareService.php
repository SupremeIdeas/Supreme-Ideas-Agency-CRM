<?php

namespace App\Services\Brands;

use App\Models\BrandPartner;
use App\Models\User;
use App\Services\N2N\Messaging\MessageService;
use App\Services\N2N\Messaging\MessagingException;

/**
 * Share a brand's Business Card into a Naara chat as a card (Brand-Growth Phase 5, MASTER-ONLY). Chat blocks links in message text, so the card is its own message shape:
 * an ordinary N2N message (normal price, normal request rules) whose body is a short label and whose quote carries what the bubble draws (name, blurb, logo, card address).
 * Only a listed brand can be shared; the card address is rebuilt by the server and never taken from the caller.
 */
class BusinessCardShareService
{
    public function __construct(private readonly BusinessCardService $cards, private readonly MessageService $messages)
    {
    }

    /** @return array<string, mixed> the quote the chat bubble renders */
    public function quote(BrandPartner $brand): array
    {
        $f = $this->cards->fields($brand);
        $logo = (string) ($f['logo'] ?? '');

        return [
            'kind' => 'brand_card', 'source' => 'brand_card', 'brand_id' => $brand->id, 'brand' => mb_substr($f['name'], 0, 80),
            'caption' => $f['blurb'] ? mb_substr(strip_tags((string) $f['blurb']), 0, 120) : null,
            'media' => str_starts_with($logo, '/') && ! str_contains($logo, '..') ? $logo : null,   // host-relative only, so a domain change never breaks an old card
            'address' => $brand->card_slug ?: $this->cards->code($brand),
        ];
    }

    /**
     * @return array the MessageService result
     *
     * @throws MessagingException when the brand is not listed, or the normal chat rules refuse the message
     */
    public function share(User $sender, string $toNaaraId, int $brandId, string $clientId, ?string $ip = null): array
    {
        $brand = BrandPartner::listed()->find($brandId);
        if (! $brand) {
            throw new MessagingException('not_found', 'That card is not available.');
        }

        return $this->messages->send($sender, ['to' => $toNaaraId], $clientId, 'Business Card: '.preg_replace('/[^\p{L}\p{N} \-&\']+/u', '', (string) $brand->brand_name), $ip, [], $this->quote($brand));
    }
}
