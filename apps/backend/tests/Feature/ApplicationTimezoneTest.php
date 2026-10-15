<?php

namespace Tests\Feature;

use Carbon\CarbonImmutable;
use Tests\TestCase;

class ApplicationTimezoneTest extends TestCase
{
    public function test_application_uses_buenos_aires_for_business_dates(): void
    {
        $this->assertSame('America/Argentina/Buenos_Aires', config('app.timezone'));
        $this->assertSame('America/Argentina/Buenos_Aires', date_default_timezone_get());

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-15 01:30:00', 'UTC'));

        try {
            $this->assertSame('2026-10-14', today()->toDateString());
            $this->assertSame('2026-10-14', today('America/Argentina/Buenos_Aires')->toDateString());
        } finally {
            CarbonImmutable::setTestNow();
        }
    }
}
