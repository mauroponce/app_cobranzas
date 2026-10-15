<?php

namespace App\Support;

use RuntimeException;

final class ProtectedDatabaseCommand
{
    private const COMMANDS = [
        'db:seed',
        'db:wipe',
        'migrate:fresh',
        'migrate:refresh',
        'migrate:reset',
        'migrate:rollback',
        'model:prune',
    ];

    public static function ensureAllowed(
        string $command,
        string $environment,
        string $driver,
        array $connection,
        bool $explicitlyAllowed,
    ): void {
        if (! in_array($command, self::COMMANDS, true)) {
            return;
        }

        if ($explicitlyAllowed || DatabaseSafety::isDisposableTestConnection($environment, $driver, $connection)) {
            return;
        }

        throw new RuntimeException(
            "El comando {$command} está protegido porque puede reemplazar o borrar datos. "
            .'Requiere una autorización explícita y ALLOW_DESTRUCTIVE_DATABASE_COMMANDS=true para esta ejecución.',
        );
    }
}
