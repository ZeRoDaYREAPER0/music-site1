<?php
/**
 * Copy this file to config.php and adjust values for your environment.
 */
declare(strict_types=1);

return [
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'meloverse',
        'user' => 'root',
        'pass' => 'Admin@123',
        'charset' => 'utf8mb4',
    ],
    'app' => [
        'base_url' => '', // e.g. http://localhost/music-site — leave empty to auto-detect
        'session_name' => 'MELOVERSESESSID',
    ],
    /**
     * local: files stored under /uploads (served as static files)
     * For S3/CDN: set public_base_url to your bucket public URL; save_audio() still writes local
     * unless you extend Storage (see includes/storage.php).
     */
    'storage' => [
        'driver' => 'local',
        'public_base_url' => '', // optional CDN prefix for audio/avatar URLs
    ],
];
