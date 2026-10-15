<?php

namespace App\Support;

final class DatabaseSafety
{
    public static function isDisposableTestConnection(string $environment, string $driver, array $connection): bool
    {
        if ($environment !== 'testing') {
            return false;
        }

        $database = self::databaseName($connection);

        return ($driver === 'sqlite' && $database === ':memory:')
            || str_ends_with($database, '_test');
    }

    public static function databaseName(array $connection): string
    {
        $url = $connection['url'] ?? null;

        if (is_string($url) && $url !== '') {
            $path = parse_url($url, PHP_URL_PATH);

            return is_string($path) ? ltrim($path, '/') : '';
        }

        $database = $connection['database'] ?? '';

        return is_string($database) ? $database : '';
    }
}
