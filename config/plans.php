<?php

return [
    'default' => 'free',
    'legacy_paid_fallback' => 'smart',
    'features' => [
        'profile_directory' => 'Perfil y directorio',
        'digital_record' => 'Expediente digital',
        'patients' => 'Pacientes',
        'basic_admin' => 'Administración básica',
        'realtime_schedule' => 'Agenda en tiempo real',
        'advanced_automations' => 'Automatizaciones avanzadas',
        'ai_reports' => 'Reportes con IA',
        'ai_exercises' => 'Actividades con IA',
        'session_copilot' => 'Copiloto de sesión',
        'whatsapp' => 'WhatsApp',
        'advanced_features' => 'Funciones avanzadas',
        'voice_dictation' => 'Dictado por voz',
        'consents' => 'Consentimientos y contratos',
        'file_uploads' => 'Archivos clínicos',
        'questionnaires' => 'Cuestionarios',
    ],
    'catalog' => [
        'free' => [
            'name' => 'Free', 'sort_order' => 10,
            'features' => ['profile_directory' => true, 'digital_record' => true, 'patients' => 5],
        ],
        'essential' => [
            'name' => 'Essential', 'sort_order' => 20,
            'stripe_price_id' => env('STRIPE_ESSENTIAL_PRICE_ID'),
            'stripe_lookup_key' => env('STRIPE_ESSENTIAL_LOOKUP_KEY', 'mindmeet_essential'),
            'features' => ['profile_directory' => true, 'digital_record' => true, 'basic_admin' => true, 'patients' => 50],
        ],
        'pro' => [
            'name' => 'Pro', 'sort_order' => 30,
            'stripe_price_id' => env('STRIPE_PRO_PRICE_ID'),
            'stripe_lookup_key' => env('STRIPE_PRO_LOOKUP_KEY', 'mindmeet_pro'),
            'features' => ['profile_directory' => true, 'digital_record' => true, 'basic_admin' => true, 'patients' => null, 'realtime_schedule' => true, 'advanced_automations' => true],
        ],
        'smart' => [
            'name' => 'Smart', 'sort_order' => 40,
            'stripe_price_id' => env('STRIPE_SMART_PRICE_ID'),
            'stripe_lookup_key' => env('STRIPE_SMART_LOOKUP_KEY', 'mindmeet_smart'),
            'features' => ['profile_directory' => true, 'digital_record' => true, 'basic_admin' => true, 'patients' => null, 'realtime_schedule' => true, 'advanced_automations' => true, 'ai_reports' => true, 'ai_exercises' => true, 'session_copilot' => true, 'whatsapp' => true, 'advanced_features' => true, 'voice_dictation' => true, 'consents' => true, 'file_uploads' => true, 'questionnaires' => true],
        ],
    ],
];
