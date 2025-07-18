<?php
/**
 * Storage Configuration
 * 
 * @package ZipPicks\Foundation
 * @since 1.0.0
 */

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default Storage Disk
    |--------------------------------------------------------------------------
    */
    'default' => env('STORAGE_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Storage Disks
    |--------------------------------------------------------------------------
    */
    'disks' => [
        'local' => [
            'driver' => 'local',
            'root' => ZIPPICKS_FOUNDATION_PATH . '/storage',
            'permissions' => [
                'file' => [
                    'public' => 0644,
                    'private' => 0600,
                ],
                'dir' => [
                    'public' => 0755,
                    'private' => 0700,
                ],
            ],
        ],
        
        'uploads' => [
            'driver' => 'local',
            'root' => defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR . '/uploads/zippicks' : ZIPPICKS_FOUNDATION_PATH . '/uploads',
            'url' => defined('WP_CONTENT_URL') ? WP_CONTENT_URL . '/uploads/zippicks' : null,
            'visibility' => 'public',
        ],
        
        'logs' => [
            'driver' => 'local',
            'root' => ZIPPICKS_FOUNDATION_PATH . '/logs',
            'visibility' => 'private',
        ],
        
        'temp' => [
            'driver' => 'local',
            'root' => sys_get_temp_dir() . '/zippicks',
            'visibility' => 'private',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    */
    'links' => [
        // Define symbolic links here if needed
    ],
];