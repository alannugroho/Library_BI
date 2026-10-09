<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesLibraryData;
use Tests\TestCase;

class PagesRenderTest extends TestCase
{
    use CreatesLibraryData;
    use RefreshDatabase;

    public function test_public_pages_render(): void
    {
        foreach (['/', '/catalog', '/search?q=buku', '/news', '/e-resources'] as $uri) {
            $this->get($uri)->assertOk();
        }
    }

    public function test_librarian_pages_render(): void
    {
        $librarian = $this->makeLibrarian();

        foreach ([
            '/admin/news',
            '/admin/news?edit=1',
            '/admin/catalog',
            '/admin/members',
            '/admin/proposals',
            '/admin/e-resources',
            '/admin/circulation',
            '/admin/reports',
        ] as $uri) {
            $this->actingAs($librarian)->get($uri)->assertOk();
        }
    }

    public function test_member_dashboard_renders(): void
    {
        $this->actingAs($this->makeMember())->get('/dashboard')->assertOk();
    }
}
