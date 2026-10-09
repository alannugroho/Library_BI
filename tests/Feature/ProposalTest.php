<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesLibraryData;
use Tests\TestCase;

class ProposalTest extends TestCase
{
    use CreatesLibraryData;
    use RefreshDatabase;

    public function test_member_can_submit_a_proposal(): void
    {
        $member = $this->makeMember();

        $this->actingAs($member)->post('/proposals', [
            'title' => 'Pengantar Ekonomi',
            'author' => 'Penulis',
            'publisher' => 'Penerbit',
        ])->assertRedirect('/proposals');

        $this->assertDatabaseHas('book_proposals', [
            'user_id' => $member->id,
            'title' => 'Pengantar Ekonomi',
            'status' => 'submitted',
        ]);
    }

    public function test_proposal_requires_a_title(): void
    {
        $member = $this->makeMember();

        $this->actingAs($member)
            ->from('/proposals')
            ->post('/proposals', ['title' => ''])
            ->assertSessionHasErrors('title');
    }

    public function test_librarian_is_redirected_away_from_proposals(): void
    {
        $this->actingAs($this->makeLibrarian())
            ->get('/proposals')
            ->assertRedirect('/dashboard');
    }
}
