<?php

return [
    // Selecting one of these categories means the citizen knows the broad group,
    // but the exact device is not represented in the catalogue yet.
    'group_fallbacks' => [
        'mobile-computing' => 'other-mobile-computing',
        'accessories-power' => 'other-accessories-power',
        'small-household' => 'other-small-electronics', // legacy code retained for existing assets
        'large-household' => 'other-large-household',
        'office-peripheral' => 'other-office-peripheral',
        'audio-video' => 'other-audio-video',
        'gaming-entertainment' => 'other-gaming-electronics',
        'personal-care' => 'other-personal-care',
        'lighting-tools' => 'other-lighting-tools',
        'other-electronics' => 'uncategorized-electronics',
    ],

    'uncategorized_code' => 'uncategorized-electronics',
];
