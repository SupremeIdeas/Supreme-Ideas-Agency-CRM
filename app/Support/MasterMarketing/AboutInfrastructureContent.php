<?php

namespace App\Support\MasterMarketing;

/**
 * MASTER-ONLY. The "infrastructure" statement on the About page (Site Editor page `about`, section `infrastructure`), with a real desktop capture of the eSIM store and a phone
 * beside it. The wording is the owner's; every word, number and image is editable from Admin > Marketing site > About. Forks never carry this section (the class does not
 * exist there, so SiteContent never merges it).
 */
final class AboutInfrastructureContent
{
    /** @return array<string, string> */
    public static function defaults(): array
    {
        return [
            'eyebrow' => 'The infrastructure',
            'quote_before' => 'Naara is the largest connectivity infrastructure in the world, with the ',
            'quote_accent' => 'orchestration that only you and your business need',
            'quote_after' => ' to stay ahead of the curve.',
            'attribution' => 'The Naara promise',
            'subtext' => 'Behind one simple app sits a network of data and number providers, routed by country and by need, with a backup always waiting. You never see the switching. You only see a connection that works.',
            's1_value' => '190+', 's1_label' => 'Countries covered',
            's2_value' => '24/7', 's2_label' => 'NaaraCare support',
            's3_value' => '1', 's3_label' => 'Wallet for every product',
            's4_value' => '35', 's4_label' => 'Dashboard skins',
            'desk_image' => '/images/marketing/mockups/about/infra-desktop.webp',
            'desk_alt' => 'The Naara eSIM store open on a desktop',
            'phone_image' => '/images/marketing/mockups/home/step-2-plan.webp',
            'phone_alt' => 'A country plan list on a phone',
        ];
    }
}
