<?php

declare(strict_types=1);

return [
    'app' => [
        'name' => 'Digital Library BI',
        'base_path' => getenv('APP_BASE_PATH') ?: '',
        'environment' => getenv('APP_ENV') ?: 'local',
        'storage_path' => dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'uploads',
    ],
    'circulation' => [
        'loan_days' => 14,
        'extension_days' => 14,
        'daily_fine' => 1000,
    ],
    'database' => [
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => getenv('DB_PORT') ?: '3306',
        'name' => getenv('DB_NAME') ?: 'digital_library_bi',
        'user' => getenv('DB_USER') ?: 'root',
        'password' => getenv('DB_PASSWORD') ?: '',
        'charset' => 'utf8mb4',
    ],
];

