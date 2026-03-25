<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Google Drive configuration
    |--------------------------------------------------------------------------
    |
    | Use this config file to reference environment variables for the
    | Google Drive integration. Access via config('google_drive.credentials')
    | and config('google_drive.folder_id') from your services.
    |
    */

    'credentials' => env('GOOGLE_DRIVE_CREDENTIALS', 'storage/app/google/credentials.json'),

    'folder_id' => env('GOOGLE_DRIVE_FOLDER_ID', ''),

    // Tiempo de cache por defecto (minutos) para llamadas a Drive en servicios
    'cache_minutes' => env('GOOGLE_DRIVE_CACHE_MINUTES', 60),
];
