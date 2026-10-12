<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
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
