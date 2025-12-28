<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Log;

class LogHelper
{
    /**
     * Логирование только в режиме разработки
     */
    public static function log(string $level, string $message, array $context = []): void
    {
        $typeProject = env('TYPE_PROJECT', 'production');
        
        // Логируем только если TYPE_PROJECT=developers
        if ($typeProject === 'developers') {
            Log::{$level}($message, $context);
        }
    }

    /**
     * Логирование информации
     */
    public static function info(string $message, array $context = []): void
    {
        self::log('info', $message, $context);
    }

    /**
     * Логирование предупреждений
     */
    public static function warning(string $message, array $context = []): void
    {
        self::log('warning', $message, $context);
    }

    /**
     * Логирование ошибок
     */
    public static function error(string $message, array $context = []): void
    {
        self::log('error', $message, $context);
    }

    /**
     * Логирование отладки
     */
    public static function debug(string $message, array $context = []): void
    {
        self::log('debug', $message, $context);
    }
}

