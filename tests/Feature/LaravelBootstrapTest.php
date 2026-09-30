<?php

namespace Tests\Feature;

use Tests\TestCase;

class LaravelBootstrapTest extends TestCase
{
    public function test_laravel_application_bootstraps(): void
    {
        $this->assertTrue(app()->bound('router'));
        $this->assertSame('testing', app()->environment());
        $this->assertSame('sqlite', config('database.default'));
    }
}
