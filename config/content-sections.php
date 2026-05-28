<?php

return [
    /**
     * Secciones de contenido disponibles en la aplicación
     * Cada sección tiene configuración de cache y persistencia
     */
    'sections' => [
        'home' => [
            'description' => 'Contenido principal del home del sitio web',
            'cacheable' => true,
            'cache_ttl' => 3600, // 1 hora
            'versioned' => true,
            'fallback_file' => 'storage/app/home.json',
        ],
        'menu' => [
            'description' => 'Menú de navegación del sitio web',
            'cacheable' => true,
            'cache_ttl' => 7200, // 2 horas
            'versioned' => true,
            'fallback_file' => null, // No tiene archivo de fallback
        ],
        'footer' => [
            'description' => 'Pie de página del sitio web',
            'cacheable' => true,
            'cache_ttl' => 7200,
            'versioned' => true,
            'fallback_file' => null,
        ],
        'buenfin' => [
            'description' => 'Configuración de campaña Buen Fin',
            'cacheable' => true,
            'cache_ttl' => 3600,
            'versioned' => false, // No guardar versiones de campañas
            'fallback_file' => 'storage/app/buenfin.json',
        ],
    ],

    /**
     * Configuración global de cache
     */
    'cache' => [
        'driver' => 'redis', // redis, memcached, array, database, file
        'default_ttl' => 3600,
        'tags_enabled' => true, // Usar cache tags para invalidación eficiente
    ],

    /**
     * Configuración de versionado
     */
    'versioning' => [
        'enabled' => true,
        'max_versions' => 20, // Mantener máximo 20 versiones
        'cleanup_older_than_days' => 30, // Limpiar versiones más antiguas de 30 días
    ],

    /**
     * Configuración de sincronización con archivos (fallback)
     */
    'file_sync' => [
        'enabled' => true,
        'storage_path' => 'app/content',
        'auto_sync_on_update' => true,
    ],
];
