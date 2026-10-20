<?php

namespace App\Models\Brain;

use Illuminate\Database\Eloquent\Model;

/** One line of NaaraBrain's content library (MASTER-ONLY): a rotating centre line, a quick-question idea or a capability. */
class BrainPrompt extends Model
{
    public const TAGLINE = 'tagline';

    public const SUGGESTION = 'suggestion';

    public const CAPABILITY = 'capability';

    /** Pack keys in display order, with the name a member sees. */
    public const PACKS = [
        'trips' => 'Trips and data', 'buying' => 'Choosing eSIMs and numbers', 'account' => 'Wallet and credits', 'messaging' => 'Messages and inbox',
        'chat' => 'Naara chat', 'calls' => 'Calls', 'planner' => 'Planner and follow-ups', 'brand' => 'Your brand', 'workspace' => 'Team workspace',
        'phone' => 'Business phone', 'recordings' => 'Call recordings', 'developer' => 'Developers', 'trust' => 'Privacy and safety', 'writing' => 'Writing and translation', 'help' => 'Help and how-to',
    ];

    protected $table = 'brain_prompts';

    protected $guarded = [];

    protected $casts = ['live' => 'boolean', 'active' => 'boolean'];
}
