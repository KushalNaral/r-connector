<?php

namespace App\Helpers;

class Logger
{
    public static function log(string $message): void
    {
        $logPath = \App\Config\AppConfig::get('paths')['logs'] . '/app.log';
        $timestamp = date('Y-m-d H:i:s');
        $line = "[$timestamp] $message" . PHP_EOL;
        file_put_contents($logPath, $line, FILE_APPEND | LOCK_EX);
    }
}
