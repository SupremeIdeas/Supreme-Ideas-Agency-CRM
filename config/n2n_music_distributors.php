<?php

/**
 * Music distributors an artist can pick for an album, and the hosts their smart links live on (N2N, MASTER-ONLY). A smart link must be https and
 * its host must EQUAL one of the listed hosts or be a true subdomain of one (same rule as the streaming links), so a look-alike never passes.
 * `logo` is a file under public/images/music/distributors; a distributor without one shows its name on a clean chip. Re-confirm the hosts before
 * launch: distributors change domains. `smart_link_hosts` are the neutral smart-link services any artist may use whatever their distributor.
 */
return [
    'distributors' => [
        'ditto' => ['label' => 'Ditto Music', 'hosts' => ['ditto.fm', 'dittomusic.com'], 'logo' => 'ditto-white.svg', 'dark' => true],
        'distrokid' => ['label' => 'DistroKid', 'hosts' => ['distrokid.com', 'hyperfollow.com'], 'logo' => 'distrokid.webp', 'dark' => false],
        'tunecore' => ['label' => 'TuneCore', 'hosts' => ['tunecore.com', 'tunecore.co.uk'], 'logo' => null, 'dark' => false],
        'cdbaby' => ['label' => 'CD Baby', 'hosts' => ['cdbaby.com'], 'logo' => null, 'dark' => false],
        'amuse' => ['label' => 'Amuse', 'hosts' => ['amuse.io'], 'logo' => null, 'dark' => false],
        'unitedmasters' => ['label' => 'UnitedMasters', 'hosts' => ['unitedmasters.com'], 'logo' => null, 'dark' => false],
        'awal' => ['label' => 'AWAL', 'hosts' => ['awal.com'], 'logo' => null, 'dark' => false],
        'onerpm' => ['label' => 'ONErpm', 'hosts' => ['onerpm.com'], 'logo' => null, 'dark' => false],
        'symphonic' => ['label' => 'Symphonic', 'hosts' => ['symphonic.com'], 'logo' => null, 'dark' => false],
        'other' => ['label' => 'Another distributor', 'hosts' => [], 'logo' => null, 'dark' => false],
    ],

    'smart_link_hosts' => ['ffm.to', 'lnk.to', 'orcd.co', 'songwhip.com', 'song.link', 'album.link', 'odesli.co', 'feature.fm', 'smarturl.it', 'linktr.ee', 'hypeddit.com', 'toneden.io', 'fanlink.to', 'linkfire.com'],
];
