<?php

return [
    'service_name' => env('MEDITRACK_SERVICE_NAME', 'MediTrack'),
    'service_key' => env('MEDITRACK_SERVICE_KEY', 'meditrack-service'),
    'event_module' => env('MEDITRACK_EVENT_MODULE', 'MediTrack'),
    'service_url' => env('MEDITRACK_SERVICE_URL', env('APP_URL', 'http://localhost')),
    'api_version' => env('MEDITRACK_API_VERSION', 'v1'),
    'trusted_portal_url' => env('DEORIS_PORTAL_URL', env('APP_PORTAL_URL', 'https://deoris.test')),
    'event_secret' => env('MEDITRACK_EVENT_SECRET', env('APP_KEY', 'change-me')),
    'search_token' => env('MEDITRACK_SEARCH_TOKEN'),
    'event_hub_url' => env('DEORIS_EVENT_HUB_URL', rtrim(env('APP_PORTAL_URL', 'https://deoris.test'), '/').'/api/v1/events'),
    'redis_channels' => [
        'medical_events' => env('MEDITRACK_REDIS_MEDICAL_EVENTS', 'medical.events'),
        'clinic_notifications' => env('MEDITRACK_REDIS_CLINIC_NOTIFICATIONS', 'clinic.notifications'),
        'emergency_alerts' => env('MEDITRACK_REDIS_EMERGENCY_ALERTS', 'clinic.emergency-alerts'),
    ],
    'queue_names' => [
        'medical' => env('MEDITRACK_QUEUE_MEDICAL', 'medical'),
        'notifications' => env('MEDITRACK_QUEUE_NOTIFICATIONS', 'notifications'),
        'alerts' => env('MEDITRACK_QUEUE_ALERTS', 'alerts'),
        'events' => env('MEDITRACK_QUEUE_EVENTS', 'events'),
    ],
    'event_schema_version' => env('MEDITRACK_EVENT_SCHEMA_VERSION', '1.0'),
    'portal_token_header' => env('MEDITRACK_PORTAL_TOKEN_HEADER', 'X-Portal-Token'),
    'role_header' => env('MEDITRACK_ROLE_HEADER', 'X-DEORIS-Role'),
    'user_header' => env('MEDITRACK_USER_HEADER', 'X-DEORIS-User'),
    
    /*
    |--------------------------------------------------------------------------
    | Event Bus Configuration
    |--------------------------------------------------------------------------
    |
    | Inbound event processing from DEORIS event hub.
    |
    */
    'event_bus' => [
        'enabled' => (bool) env('MEDITRACK_EVENT_BUS_ENABLED', true),
        'redis_channel' => env('MEDITRACK_REDIS_EVENTS', 'deoris.events'),
        'max_age_seconds' => (int) env('MEDITRACK_MAX_EVENT_AGE', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | Module Secrets for Inbound Event Validation
    |--------------------------------------------------------------------------
    |
    | Secrets from other modules publishing to DEORIS event bus.
    | Used to verify signature on inbound events.
    |
    */
    'trusted_modules' => [
        'Portal' => env('DEORIS_SECRET_PORTAL'),
        'EnrollEase' => env('DEORIS_SECRET_ENROLLEASE'),
        'AssessPay' => env('DEORIS_SECRET_ASSESSPAY'),
        'GradeTrack' => env('DEORIS_SECRET_GRADETRACK'),
        'LibrarySys' => env('DEORIS_SECRET_LIBRARYSYS'),
        'ClearCheck' => env('DEORIS_SECRET_CLEARCHECK'),
    ],
];
