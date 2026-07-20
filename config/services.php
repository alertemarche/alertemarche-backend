<?php

return [
    'openai' => [
        'key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4o'),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
    ],

    'brevo' => [
        'key' => env('BREVO_API_KEY'),
        'sender_email' => env('BREVO_SENDER_EMAIL', 'info@alertemarche.com'),
        'sender_name' => env('BREVO_SENDER_NAME', 'AlerteMarché'),
        'base_url' => 'https://api.brevo.com/v3',
    ],

    'whatsapp' => [
        'token' => env('WHATSAPP_TOKEN'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'business_account_id' => env('WHATSAPP_BUSINESS_ACCOUNT_ID'),
        'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),
        'base_url' => 'https://graph.facebook.com/v20.0',
    ],

    'kkpays' => [
        'key' => env('KKPAYS_API_KEY'),
        'secret' => env('KKPAYS_SECRET'),
        'merchant_id' => env('KKPAYS_MERCHANT_ID'),
        'base_url' => env('KKPAYS_BASE_URL', 'https://api.kkpays.com'),
    ],

    'scrapers' => [
        'token' => env('SCRAPERS_API_TOKEN', 'change-me-scraper-token'),
    ],
];
