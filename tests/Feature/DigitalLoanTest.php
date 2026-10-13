<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\DigitalLending;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesLibraryData;
use Tests\TestCase;

class DigitalLoanTest extends TestCase
{
    use CreatesLibraryData;
    use RefreshDatabase;

    public function test_anggota_can_borrow_a_digital_book_when_a_copy_is_available(): void
    {
        $member = $this->makeMember();
        $book = $this->makeDigitalBook();

        $this->actingAs($member)
            ->post('/digital/borrow', ['catalog_id' => $book->id])
            ->assertRedirect('/catalog');

        $this->assertDatabaseHas('digital_loans', [
            'user_id' => $member->id,
            'catalog_id' => $book->id,
            'status' => 'active',
        ]);
    }

    public function test_external_member_can_borrow_a_digital_book(): void
    {
        $external = User::factory()->eksternal()->create();
        $book = $this->makeDigitalBook();

        $this->actingAs($external)
            ->post('/digital/borrow', ['catalog_id' => $book->id])
            ->assertRedirect('/catalog');

        $this->assertDatabaseHas('digital_loans', [
            'user_id' => $external->id,
            'catalog_id' => $book->id,
            'status' => 'active',
        ]);
    }

    public function test_librarian_cannot_borrow_a_digital_book(): void
    {
        $book = $this->makeDigitalBook();

        $this->actingAs($this->makeLibrarian())
            ->post('/digital/borrow', ['catalog_id' => $book->id])
            ->assertForbidden();
    }

    public function test_member_cannot_borrow_when_no_copy_is_left(): void
    {
        $book = $this->makeDigitalBook(1);
        $this->makeDigitalLoan($this->makeMember(), $book);

        $response = $this->actingAs($this->makeMember())
            ->post('/digital/borrow', ['catalog_id' => $book->id]);

        $response->assertSessionHas('flash.type', 'error');
        $this->assertSame(1, DB::table('digital_loans')->count());
    }

    public function test_member_can_join_the_waitlist_when_no_copy_is_left(): void
    {
        $book = $this->makeDigitalBook(1);
        $this->makeDigitalLoan($this->makeMember(), $book);
        $waiter = $this->makeMember();

        $this->actingAs($waiter)
            ->post('/digital/waitlist', ['catalog_id' => $book->id])
            ->assertRedirect('/catalog');

        $this->assertDatabaseHas('reservations', [
            'user_id' => $waiter->id,
            'catalog_id' => $book->id,
            'status' => 'waiting_list',
        ]);
    }

    public function test_member_cannot_join_the_waitlist_when_a_copy_is_available(): void
    {
        $book = $this->makeDigitalBook(1);

        $response = $this->actingAs($this->makeMember())
            ->post('/digital/waitlist', ['catalog_id' => $book->id]);

        $response->assertSessionHas('flash.type', 'error');
        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_physical_book_cannot_be_borrowed_through_the_digital_route(): void
    {
        $book = $this->makeCatalogBook();

        $response = $this->actingAs($this->makeMember())
            ->post('/digital/borrow', ['catalog_id' => $book->id]);

        $response->assertSessionHas('flash.type', 'error');
        $this->assertDatabaseCount('digital_loans', 0);
    }

    public function test_stream_is_forbidden_without_an_active_loan(): void
    {
        Storage::fake('digital');
        $book = $this->makeDigitalBook();
        Storage::disk('digital')->put('books/test.pdf', '%PDF-1.4 fake');

        $this->actingAs($this->makeMember())
            ->get('/digital/stream?id='.$book->id)
            ->assertForbidden();
    }

    public function test_member_with_active_loan_can_stream_the_pdf(): void
    {
        Storage::fake('digital');
        $member = $this->makeMember();
        $book = $this->makeDigitalBook();
        Storage::disk('digital')->put('books/'.$book->digital_file_path, '%PDF-1.4 fake content');
        $this->makeDigitalLoan($member, $book);

        $this->actingAs($member)
            ->get('/digital/stream?id='.$book->id)
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_catalog_offers_a_new_tab_read_link_for_an_active_loan(): void
    {
        $member = $this->makeMember();
        $book = $this->makeDigitalBook();
        $this->makeDigitalLoan($member, $book);

        $this->actingAs($member)
            ->get('/catalog?type=digital')
            ->assertOk()
            ->assertSee('/digital/stream?id='.$book->id.'#toolbar=0', false);
    }

    public function test_returning_a_loan_frees_the_seat_and_promotes_the_waiting_member(): void
    {
        $borrower = $this->makeMember();
        $book = $this->makeDigitalBook(1);
        $this->makeDigitalLoan($borrower, $book);
        $waiter = $this->makeMember();
        DB::table('reservations')->insert([
            'user_id' => $waiter->id,
            'catalog_id' => $book->id,
            'status' => 'waiting_list',
        ]);

        $this->actingAs($borrower)
            ->post('/digital/return', ['catalog_id' => $book->id])
            ->assertRedirect('/catalog');

        $this->assertSame('returned', DB::table('digital_loans')->where('user_id', $borrower->id)->value('status'));
        $this->assertDatabaseHas('digital_loans', [
            'user_id' => $waiter->id,
            'catalog_id' => $book->id,
            'status' => 'active',
        ]);
        $this->assertSame('completed', DB::table('reservations')->where('user_id', $waiter->id)->value('status'));
    }

    public function test_expired_loan_is_swept_and_a_new_member_can_borrow(): void
    {
        $book = $this->makeDigitalBook(1);
        $this->makeDigitalLoan($this->makeMember(), $book, [
            'borrowed_at' => now()->subDays(10),
            'due_at' => now()->subDays(3),
        ]);

        DigitalLending::sweepExpired();
        $this->assertSame('expired', DB::table('digital_loans')->value('status'));

        $newMember = $this->makeMember();
        $this->actingAs($newMember)
            ->post('/digital/borrow', ['catalog_id' => $book->id])
            ->assertRedirect('/catalog');

        $this->assertDatabaseHas('digital_loans', [
            'user_id' => $newMember->id,
            'catalog_id' => $book->id,
            'status' => 'active',
        ]);
    }

    public function test_librarian_can_force_return_a_digital_loan(): void
    {
        $member = $this->makeMember();
        $book = $this->makeDigitalBook(1);
        $loanId = $this->makeDigitalLoan($member, $book);

        $this->actingAs($this->makeLibrarian())
            ->post('/digital/return', ['loan_id' => $loanId])
            ->assertRedirect('/admin/circulation');

        $this->assertSame('returned', DB::table('digital_loans')->where('id', $loanId)->value('status'));
    }
}
