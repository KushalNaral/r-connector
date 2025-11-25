<?php

namespace App\Services;

use App\Helpers\Logger;

class ApiClient
{
    public static function callEditorApi(): ?string
    {
        $config = \App\Config\AppConfig::get('');
        $ch = curl_init();

        $debugStream = fopen('php://temp', 'w+');
        curl_setopt_array($ch, [
            CURLOPT_URL => $config['api_url'],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query(['filePath' => $config['static_file_path']]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_VERBOSE => true,
            CURLOPT_STDERR => $debugStream,
            CURLOPT_HTTPHEADER => [
                "X-Client-Id: " . $config['client_id'],
                "X-Client-Secret: " . $config['client_secret'],
                "Content-Type: application/x-www-form-urlencoded",
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $rawHeaders = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);

        rewind($debugStream);
        $verbose = stream_get_contents($debugStream);
        fclose($debugStream);
        preg_match_all('/^> .+/m', $verbose, $matches);
        $sentHeaders = implode("\n", $matches[0] ?? []);

        Logger::log("REQUEST HEADERS SENT:\n$sentHeaders");
        Logger::log("RESPONSE HEADERS:\n$rawHeaders");
        Logger::log("RESPONSE BODY LENGTH: " . strlen($body));
        Logger::log("HTTP CODE: $httpCode");
        Logger::log("CURL ERROR: " . ($error ?: 'none'));

        curl_close($ch);

        if (!$response || $httpCode !== 200) {
            Logger::log("API Error: " . ($error ?: "HTTP $httpCode"));
            return null;
        }

        return $body;
    }
}
