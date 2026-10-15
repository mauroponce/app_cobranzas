<?php

namespace Tests;

use App\Support\DatabaseSafety;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Str;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $this->ensureSafeTestEnvironment(
            (string) getenv('APP_ENV'),
            (string) getenv('DB_CONNECTION'),
            ['database' => getenv('DB_DATABASE'), 'url' => getenv('DB_URL')],
        );

        $app = parent::createApplication();
        $driver = (string) $app['config']->get('database.default');
        $connection = $app['config']->get("database.connections.{$driver}", []);
        $this->ensureSafeTestEnvironment(
            (string) $app->environment(),
            $driver,
            is_array($connection) ? $connection : [],
        );

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function ensureSafeTestEnvironment(string $environment, string $driver, array $connection): void
    {
        if (DatabaseSafety::isDisposableTestConnection($environment, $driver, $connection)) {
            return;
        }

        $database = DatabaseSafety::databaseName($connection) ?: '(sin nombre)';

        throw new RuntimeException(
            "Pruebas bloqueadas: la conexión {$driver}:{$database} no es una base descartable terminada en _test.",
        );
    }

    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        if (in_array(strtoupper($method), ['POST', 'PUT', 'PATCH', 'DELETE'], true) && ! str_starts_with(ltrim($uri, '/'), 'api/') && ! str_starts_with(ltrim($uri, '/'), 'livewire/') && ! array_key_exists('_token', $parameters)) {
            $token = Str::random(40);
            $parameters['_token'] = $token;
            $this->withSession(['_token' => $token]);
        }

        return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
    }
}
