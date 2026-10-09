<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesLibraryData;
use Tests\TestCase;

class AdminNewsTest extends TestCase
{
    use CreatesLibraryData;
    use RefreshDatabase;

    public function test_librarian_can_update_a_clipping(): void
    {
        $librarian = $this->makeLibrarian();
        $id = $this->makeClipping($librarian->id);

        $this->actingAs($librarian)->post('/admin/news', [
            'news_id' => $id,
            'title' => 'Judul baru',
            'source_media' => 'Media baru',
            'publish_date' => '2026-02-02',
            'url_link' => 'https://example.test/baru',
        ])->assertRedirect('/admin/news');

        $clipping = DB::table('news_clippings')->where('id', $id)->first();

        $this->assertSame('Judul baru', $clipping->title);
        $this->assertSame('https://example.test/baru', $clipping->url_link);
        $this->assertDatabaseCount('news_clippings', 1);
    }

    public function test_librarian_can_delete_a_clipping(): void
    {
        $librarian = $this->makeLibrarian();
        $id = $this->makeClipping($librarian->id);

        $this->actingAs($librarian)
            ->post('/admin/news/delete', ['news_id' => $id])
            ->assertRedirect('/admin/news');

        $this->assertDatabaseMissing('news_clippings', ['id' => $id]);
    }

    public function test_member_cannot_update_or_delete_clippings(): void
    {
        $librarian = $this->makeLibrarian();
        $member = $this->makeMember();
        $id = $this->makeClipping($librarian->id);

        $this->actingAs($member)->post('/admin/news', [
            'news_id' => $id,
            'title' => 'Dicoba ubah',
            'source_media' => 'Media',
            'publish_date' => '2026-02-02',
            'url_link' => 'https://example.test/x',
        ])->assertRedirect('/dashboard');

        $this->actingAs($member)
            ->post('/admin/news/delete', ['news_id' => $id])
            ->assertRedirect('/dashboard');

        $this->assertDatabaseHas('news_clippings', ['id' => $id, 'title' => 'Klipping awal']);
    }

    private function makeClipping(int $uploadedBy): int
    {
        return DB::table('news_clippings')->insertGetId([
            'title' => 'Klipping awal',
            'source_media' => 'Media awal',
            'publish_date' => '2026-01-01',
            'url_link' => 'https://example.test/awal',
            'uploaded_by' => $uploadedBy,
        ]);
    }
}
