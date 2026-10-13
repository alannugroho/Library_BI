<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    public function test_external_user_receives_access_message_for_e_resources(): void
    {
        $external = $this->makeExternal(['status' => 'active']);
        $resourceId = DB::table('e_resources')->insertGetId([
            'title' => 'Jurnal uji',
            'url_link' => 'https://example.com',
            'description' => 'Resource uji',
        ]);

        $response = $this->actingAs($external)->get('/e-resources/go?id='.$resourceId);

        $response->assertRedirect('/e-resources')
            ->assertSessionHas('flash', [
                'type' => 'error',
                'message' => 'Maaf Anda tidak memiliki akses untuk ini.',
            ]);
        $this->get('/e-resources')
            ->assertOk()
            ->assertSee('Maaf Anda tidak memiliki akses untuk ini.');
    }

    public function test_guest_receives_access_message_for_e_resources(): void
    {
        $resourceId = DB::table('e_resources')->insertGetId([
            'title' => 'Jurnal uji',
            'url_link' => 'https://example.com',
            'description' => 'Resource uji',
        ]);

        $response = $this->get('/e-resources/go?id='.$resourceId);

        $response->assertRedirect('/e-resources')
            ->assertSessionHas('flash', [
                'type' => 'error',
                'message' => 'Maaf Anda tidak memiliki akses untuk ini.',
            ]);
        $this->get('/e-resources')
            ->assertOk()
            ->assertSee('Maaf Anda tidak memiliki akses untuk ini.');
    }
}
