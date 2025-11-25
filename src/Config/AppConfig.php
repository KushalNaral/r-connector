<?php

namespace App\Config;

use Dotenv\Dotenv;

class AppConfig
{
    private static array $config = [];

    public static function all(): array
    {
        return self::$config;
    }

    public static function get(string $key = null, $default = null)
    {
        if ($key === '' || $key === null) {
            return self::$config;
        }

        return self::$config[$key] ?? $default;
    }

    public static function load(string $basePath): void
    {
        $dotenv = Dotenv::createImmutable($basePath);
        $dotenv->load();

        self::$config = [
            'api_url' => $_ENV['API_URL'] ?? 'http://localhost:8075/protected/api/v1/pdf-visualize',
            'client_id' => $_ENV['CLIENT_ID'] ?? 'DOIT',
            'client_secret' => $_ENV['CLIENT_SECRET'] ?? 's3cr3t',
            'static_file_path' => $_ENV['STATIC_FILE_PATH'] ?? '/home/sajak/Downloads/aa.pdf',
            'fallback_session_id' => $_ENV['FALLBACK_SESSION_ID'] ?? '',
            'session_validity_minutes' => (int)($_ENV['SESSION_VALIDITY_MINUTES'] ?? 30),
            'paths' => [
                'rendered' => $basePath . '/storage/rendered',
                'logs' => $basePath . '/storage/logs',
            ]
        ];

        // Ensure directories exist
        foreach (self::$config['paths'] as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }
}
