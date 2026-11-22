<?php
/**
 * Application settings for DonorNexa.
 * Business-rule values that admins can change live in the `settings` table instead.
 */
return [
    'name'         => 'DonorNexa',
    'tagline'      => 'Connecting Donors. Bridging Lives. Saving Hope',
    'timezone'     => 'Asia/Karachi',
    'tz_offset'    => '+05:00',          // used for the MySQL session time zone
    'session_name' => 'donornexa_sid',
    'session_idle' => 1800,               // seconds of inactivity before logout
    'remember_days' => 30,
    'per_page'     => 10,

    // Uploads: size limits in bytes and allowed MIME types
    'upload' => [
        'photo_max'   => 2 * 1024 * 1024,
        'licence_max' => 5 * 1024 * 1024,
        'photo_mimes' => ['image/jpeg' => 'jpg', 'image/png' => 'png'],
        'licence_mimes' => ['application/pdf' => 'pdf'],
    ],

    // Ranking weights used by DefaultScoringStrategy (max total = 100)
    'scoring' => [
        'exact_group' => 40,
        'same_city'   => 25,
        'rest_days'   => 20,   // scaled by days since last donation
        'rest_cap'    => 180,  // days after which no extra points are given
        'response'    => 15,   // scaled by donor response rate
    ],

    // How wide the donor pool is for each urgency level
    'urgency' => [
        'Normal'   => ['scope' => 'city',     'multiplier' => 1],
        'Urgent'   => ['scope' => 'province', 'multiplier' => 2],
        'Critical' => ['scope' => 'all',      'multiplier' => 3],
    ],

    'max_open_critical' => 3,
    'min_trust_to_post' => 20,
    'cancel_penalty'    => 5,
];
