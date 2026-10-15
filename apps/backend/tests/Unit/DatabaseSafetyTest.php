<?php

namespace Tests\Unit;

use App\Support\DatabaseSafety;
use App\Support\ProtectedDatabaseCommand;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class DatabaseSafetyTest extends TestCase
{
    public function test_phpunit_forces_isolated_test_services(): void
    {
        $this->assertSame('testing', getenv('APP_ENV'));
        $this->assertSame('pgsql', getenv('DB_CONNECTION'));
        $this->assertSame('app_cobranzas_test', getenv('DB_DATABASE'));
        $this->assertSame('', getenv('DB_URL'));
        $this->assertSame('array', getenv('CACHE_STORE'));
        $this->assertSame('sync', getenv('QUEUE_CONNECTION'));
        $this->assertSame('array', getenv('SESSION_DRIVER'));
        $this->assertSame('array', getenv('MAIL_MAILER'));
    }

    public function test_it_only_recognizes_disposable_test_connections(): void
    {
        $this->assertTrue(DatabaseSafety::isDisposableTestConnection(
            'testing',
            'pgsql',
            ['database' => 'app_cobranzas_test'],
        ));
        $this->assertTrue(DatabaseSafety::isDisposableTestConnection(
            'testing',
            'sqlite',
            ['database' => ':memory:'],
        ));
        $this->assertFalse(DatabaseSafety::isDisposableTestConnection(
            'local',
            'pgsql',
            ['database' => 'app_cobranzas_test'],
        ));
        $this->assertFalse(DatabaseSafety::isDisposableTestConnection(
            'testing',
            'pgsql',
            ['database' => 'app_cobranzas'],
        ));
        $this->assertFalse(DatabaseSafety::isDisposableTestConnection(
            'testing',
            'pgsql',
            ['database' => 'app_cobranzas_test', 'url' => 'pgsql://user:secret@db/app_cobranzas'],
        ));
    }

    #[DataProvider('protectedCommands')]
    public function test_it_blocks_protected_commands_on_a_non_test_database(string $command): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("El comando {$command} está protegido");

        ProtectedDatabaseCommand::ensureAllowed(
            $command,
            'local',
            'pgsql',
            ['database' => 'app_cobranzas'],
            false,
        );
    }

    public function test_it_allows_protected_commands_only_for_a_safe_test_database_or_explicit_execution(): void
    {
        ProtectedDatabaseCommand::ensureAllowed(
            'migrate:fresh',
            'testing',
            'pgsql',
            ['database' => 'app_cobranzas_test'],
            false,
        );
        ProtectedDatabaseCommand::ensureAllowed(
            'db:seed',
            'local',
            'pgsql',
            ['database' => 'app_cobranzas'],
            true,
        );

        $this->addToAssertionCount(2);
    }

    public static function protectedCommands(): array
    {
        return [
            ['db:seed'],
            ['db:wipe'],
            ['migrate:fresh'],
            ['migrate:refresh'],
            ['migrate:reset'],
            ['migrate:rollback'],
            ['model:prune'],
        ];
    }
}
