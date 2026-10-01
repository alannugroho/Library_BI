<?php

namespace Tests\Feature;

use Illuminate\Routing\Route as LaravelRoute;
use Tests\TestCase;

class NativeRoutesTest extends TestCase
{
    public function test_public_auth_pages_are_available(): void
    {
        $this->get('/login')->assertOk();
        $this->get('/register')->assertOk();
        $this->get('/verify-email?token=invalid')->assertOk();
    }

    public function test_authenticated_pages_require_login(): void
    {
        foreach ([
            '/dashboard',
            '/proposals',
            '/reservations',
            '/admin/members',
            '/admin/proposals',
            '/admin/news',
            '/admin/e-resources',
            '/admin/reports',
            '/admin/catalog',
        ] as $uri) {
            $this->get($uri)->assertRedirect('/login');
        }
    }

    public function test_login_requires_a_laravel_csrf_token(): void
    {
        $this->post('/login', [
            'email' => 'member@example.test',
            'password' => 'password',
        ])->assertStatus(419);
    }

    public function test_registration_validates_before_writing_to_database(): void
    {
        $this->from('/register')
            ->post('/register', [
                'email' => 'not-an-email',
                'password' => 'short',
                'password_confirmation' => 'different',
            ])
            ->assertSessionHasErrors(['email', 'password'])
            ->assertRedirect('/register');
    }

    public function test_all_application_routes_are_native_laravel_routes(): void
    {
        $legacyRoutes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(function (LaravelRoute $route): bool {
                $action = $route->getActionName();

                return str_contains($action, 'LegacyController')
                    || $route->uri() === '{any}';
            });

        $this->assertCount(0, $legacyRoutes);
    }
}
