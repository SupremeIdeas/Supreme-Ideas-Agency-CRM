<?php

/**
 * NaaraBrain (MASTER-ONLY). Every knob here can be overridden by an admin Setting named `brain.<key>`; nothing is a literal in code. Model ids live in
 * config/ai.php (classes fast / standard / top), never here.
 */
return [
    // Depth a person can pick per conversation -> the model class it runs on (config/ai.php models).
    'depths' => [
        'quick' => ['class' => 'fast', 'label' => 'Quick', 'hint' => 'Fast, short answers'],
        'balanced' => ['class' => 'standard', 'label' => 'Balanced', 'hint' => 'The everyday default'],
        'deep' => ['class' => 'top', 'label' => 'Deep', 'hint' => 'Slower, for hard questions'],
    ],
    'default_depth' => 'balanced',

    // Conversation history sent with each question (turns), reply size, and a per-person daily ceiling until metering (Sprint 6) replaces it.
    'history_turns' => 12,
    'max_tokens' => 1024,
    'daily_messages' => 10,   // FREE members, per day; Naara Pro plans raise it (admin: NaaraBrain, and per plan in the plan editor)
    'max_chars' => 4000,

    // Access tiers. Free members may use NaaraBrain at a small ceiling and the lighter depths; a Naara Pro plan gets the Pro defaults below unless the plan sets its own.
    'free_access' => true,
    'free_depths' => ['quick'],
    'pro_daily' => 100,
    'pro_depths' => ['quick', 'balanced', 'deep'],
    'retention_days' => 365,
];
