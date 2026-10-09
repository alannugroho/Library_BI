<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesLibraryData;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use CreatesLibraryData;
    use RefreshDatabase;

    public function test_member_is_redirected_from_librarian_pages(): void
    {
        $member = $this->makeMember();

        $this->actingAs($member)->get('/admin/members')->assertRedirect('/dashboard');
        $this->actingAs($member)->get('/admin/proposals')->assertRedirect('/dashboard');
    }

    public function test_librarian_can_approve_an_external_member(): void
    {
        $librarian = $this->makeLibrarian();
        $external = User::factory()->eksternal()->create();

        $this->actingAs($librarian)->post('/admin/members', [
            'member_id' => $external->id,
            'status' => 'active',
        ])->assertRedirect('/admin/members');

        $this->assertSame('active', DB::table('users')->where('id', $external->id)->value('status'));
    }

    public function test_librarian_cannot_change_an_internal_member_status(): void
    {
        $librarian = $this->makeLibrarian();
        $internal = $this->makeMember(['status' => 'active']);

        $this->actingAs($librarian)->post('/admin/members', [
            'member_id' => $internal->id,
            'status' => 'inactive',
        ])->assertRedirect('/admin/members');

        $this->assertSame('active', DB::table('users')->where('id', $internal->id)->value('status'));
    }

    public function test_librarian_can_update_a_proposal_status(): void
    {
        $librarian = $this->makeLibrarian();
        $member = $this->makeMember();
        $proposalId = DB::table('book_proposals')->insertGetId([
            'user_id' => $member->id,
            'title' => 'Usulan',
            'status' => 'submitted',
        ]);

        $this->actingAs($librarian)->post('/admin/proposals', [
            'proposal_id' => $proposalId,
            'status' => 'approved',
        ])->assertRedirect('/admin/proposals');

        $this->assertSame('approved', DB::table('book_proposals')->where('id', $proposalId)->value('status'));
    }

    public function test_rejected_proposal_cannot_be_updated(): void
    {
        $librarian = $this->makeLibrarian();
        $member = $this->makeMember();
        $proposalId = DB::table('book_proposals')->insertGetId([
            'user_id' => $member->id,
            'title' => 'Usulan ditolak',
            'status' => 'rejected',
        ]);

        $this->actingAs($librarian)->post('/admin/proposals', [
            'proposal_id' => $proposalId,
            'status' => 'approved',
        ])->assertRedirect('/admin/proposals');

        $this->assertSame('rejected', DB::table('book_proposals')->where('id', $proposalId)->value('status'));
    }
}
