<?php

return [
    'openai' => [
        'key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4o'),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        // OCR/vision : lecture du PDF de l'avis (y compris scanné) pour en
        // extraire la date limite et le montant. Modèle multimodal requis.
        'ocr_enabled' => (bool) env('OPENAI_OCR_ENABLED', true),
        'ocr_model' => env('OPENAI_OCR_MODEL', env('OPENAI_MODEL', 'gpt-4o')),
        // Taille maximale du PDF téléchargé pour OCR (Mo). Au-delà, on ignore
        // pour éviter des coûts et des temps de traitement excessifs.
        'ocr_max_mb' => (int) env('OPENAI_OCR_MAX_MB', 12),
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
        // Numéro expéditeur officiel AlerteMarché (affichage / templates).
        'sender_number' => env('WHATSAPP_SENDER_NUMBER', '+2290198524949'),
        // Modèle (template) approuvé utilisé pour les alertes « à froid ».
        'alert_template' => env('WHATSAPP_ALERT_TEMPLATE', 'alerte_opportunite'),
        'alert_template_lang' => env('WHATSAPP_ALERT_LANG', 'fr'),
        'base_url' => 'https://graph.facebook.com/v20.0',
    ],

    // Passerelle de paiement KKiaPay (Mobile Money + carte — Bénin).
    'kkiapay' => [
        'public_key' => env('KKIAPAY_PUBLIC_KEY'),
        'private_key' => env('KKIAPAY_PRIVATE_KEY'),
        'secret' => env('KKIAPAY_SECRET'),
        'webhook_secret' => env('KKIAPAY_WEBHOOK_SECRET'),
        'sandbox' => (bool) env('KKIAPAY_SANDBOX', true),
        'api_url' => env('KKIAPAY_API_URL', 'https://api.kkiapay.me'),
    ],

    'scrapers' => [
        'token' => env('SCRAPERS_API_TOKEN', 'change-me-scraper-token'),
    ],
];
