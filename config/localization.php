<?php

declare(strict_types=1);

return [
    // Default is set via APP_LOCALE (.env). Users switch via the cabinet UI.
    'supported' => [
        'en'      => ['label' => 'English',        'native' => 'English',        'flag' => '🇬🇧'],
        'ru'      => ['label' => 'Russian',        'native' => 'Русский',        'flag' => '🇷🇺'],
        'uz'      => ['label' => 'Uzbek (Latin)',  'native' => "O‘zbekcha",       'flag' => '🇺🇿'],
        'uz_Cyrl' => ['label' => 'Uzbek (Cyrillic)', 'native' => 'Ўзбекча',       'flag' => '🇺🇿'],
        'kaa'     => ['label' => 'Karakalpak',     'native' => 'Qaraqalpaqsha',  'flag' => '🇺🇿'],
    ],
];
