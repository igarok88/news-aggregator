<?php

// Link count options
$limitOptions = [
    5  => '5 articles (Fast)',
    10 => '10 articles (Medium)',
    20 => '20 articles (Detailed)'
];

// Time periods list
$periods = [
    '1h' => 'Last hour',
    '1d' => 'Past 24 hours',
    '7d' => 'Past week',
    ''   => 'Relevance (Any time)'
];

// Default values
$selectedCountry = 'us';
$selectedPeriod = '7d';
$selectedLimit = 5;
$selectedOutputLang = 'en';


$countries = [
    // === 🌎 AMERICAS (West) ===
    'ca' => ['name' => 'Canada (En)',      'gl' => 'CA', 'hl' => 'en-CA', 'ceid' => 'CA:en'],
    'us' => ['name' => 'USA',              'gl' => 'US', 'hl' => 'en-US', 'ceid' => 'US:en'],
    'mx' => ['name' => 'Mexico',           'gl' => 'MX', 'hl' => 'es-419', 'ceid' => 'MX:es-419'],
    'co' => ['name' => 'Colombia',         'gl' => 'CO', 'hl' => 'es-419', 'ceid' => 'CO:es-419'],
    'br' => ['name' => 'Brazil',           'gl' => 'BR', 'hl' => 'pt-BR', 'ceid' => 'BR:pt-419'],
    'ar' => ['name' => 'Argentina',        'gl' => 'AR', 'hl' => 'es-419', 'ceid' => 'AR:es-419'],

    // === 🇪🇺 WESTERN & CENTRAL EUROPE ===
    'gb' => ['name' => 'United Kingdom',   'gl' => 'GB', 'hl' => 'en-GB', 'ceid' => 'GB:en'],
    'nl' => ['name' => 'Netherlands',      'gl' => 'NL', 'hl' => 'nl',    'ceid' => 'NL:nl'],
    'fr' => ['name' => 'France',           'gl' => 'FR', 'hl' => 'fr',    'ceid' => 'FR:fr'],
    'es' => ['name' => 'Spain',            'gl' => 'ES', 'hl' => 'es',    'ceid' => 'ES:es'],
    'ch' => ['name' => 'Switzerland',      'gl' => 'CH', 'hl' => 'de',    'ceid' => 'CH:de'],
    'de' => ['name' => 'Germany',          'gl' => 'DE', 'hl' => 'de',    'ceid' => 'DE:de'],
    'it' => ['name' => 'Italy',            'gl' => 'IT', 'hl' => 'it',    'ceid' => 'IT:it'],
    'se' => ['name' => 'Sweden',           'gl' => 'SE', 'hl' => 'sv',    'ceid' => 'SE:sv'],
    'pl' => ['name' => 'Poland',           'gl' => 'PL', 'hl' => 'pl',    'ceid' => 'PL:pl'],

    // === 🌍 AFRICA & MIDDLE EAST ===
    'eg' => ['name' => 'Egypt',            'gl' => 'EG', 'hl' => 'ar',    'ceid' => 'EG:ar'],
    'za' => ['name' => 'South Africa',     'gl' => 'ZA', 'hl' => 'en-ZA', 'ceid' => 'ZA:en'],
    'tr' => ['name' => 'Turkey',           'gl' => 'TR', 'hl' => 'tr',    'ceid' => 'TR:tr'],
    'il' => ['name' => 'Israel',           'gl' => 'IL', 'hl' => 'he',    'ceid' => 'IL:he'],
    'sa' => ['name' => 'Saudi Arabia',     'gl' => 'SA', 'hl' => 'ar',    'ceid' => 'SA:ar'],
    'ae' => ['name' => 'UAE',              'gl' => 'AE', 'hl' => 'ar',    'ceid' => 'AE:ar'],

    // === 🏔 EASTERN EUROPE & EURASIA ===
    'ua' => ['name' => 'Ukraine',          'gl' => 'UA', 'hl' => 'uk',    'ceid' => 'UA:uk'],
    'ru' => ['name' => 'Russia',           'gl' => 'RU', 'hl' => 'ru',    'ceid' => 'RU:ru'],

    // === 🌏 ASIA & EAST ===
    'in' => ['name' => 'India',            'gl' => 'IN', 'hl' => 'en-IN', 'ceid' => 'IN:en'],
    'id' => ['name' => 'Indonesia',        'gl' => 'ID', 'hl' => 'id',    'ceid' => 'ID:id'],
    'cn' => ['name' => 'China',            'gl' => 'CN', 'hl' => 'zh-CN', 'ceid' => 'CN:zh-Hans'],
    'kr' => ['name' => 'South Korea',      'gl' => 'KR', 'hl' => 'ko',    'ceid' => 'KR:ko'],
    'jp' => ['name' => 'Japan',            'gl' => 'JP', 'hl' => 'ja',    'ceid' => 'JP:ja'],

    // === 🇦🇺 AUSTRALIA ===
    'au' => ['name' => 'Australia',        'gl' => 'AU', 'hl' => 'en-AU', 'ceid' => 'AU:en'],
];


// Key = language code.
// name = AI name (in English).
// label = User name (in the interface).

$outputLanguages = [
    // === WESTERN EUROPE ===
    'pt' => ['name' => 'Portuguese', 'label' => 'Português'],
    'es' => ['name' => 'Spanish',   'label' => 'Español'],
    'en' => ['name' => 'English',   'label' => 'English'],
    'fr' => ['name' => 'French',    'label' => 'Français'],
    'nl' => ['name' => 'Dutch',     'label' => 'Nederlands'],

    // === CENTRAL EUROPE ===
    'de' => ['name' => 'German',    'label' => 'Deutsch'],
    'it' => ['name' => 'Italian',   'label' => 'Italiano'],
    'pl' => ['name' => 'Polish',    'label' => 'Polski'],

    // === EASTERN EUROPE AND EURASIA ===
    'uk' => ['name' => 'Ukrainian', 'label' => 'Українська'],
    'tr' => ['name' => 'Turkish',   'label' => 'Türkçe'],
    'ru' => ['name' => 'Russian',   'label' => 'Русский'],

    // === MIDDLE EAST AND SOUTH ASIA ===
    'ar' => ['name' => 'Arabic',    'label' => 'العربية'],
    'hi' => ['name' => 'Hindi',     'label' => 'हिन्दी'],

    // === FAR EAST ===
    'zh' => ['name' => 'Chinese',   'label' => '中文'],
    'ko' => ['name' => 'Korean',    'label' => '한국어'],
    'ja' => ['name' => 'Japanese',  'label' => '日本語'],
];
