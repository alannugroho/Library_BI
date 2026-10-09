<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesLibraryData;
use Tests\TestCase;

class CirculationTest extends TestCase
{
    use CreatesLibraryData;
    use RefreshDatabase;

    public function test_non_librarian_cannot_open_circulation(): void
    {
        $this->actingAs($this->makeMember())
            ->get('/admin/circulation')
            ->assertForbidden();
    }

    public function test_librarian_can_borrow_an_available_copy(): void
    {
        $librarian = $this->makeLibrarian();
        $member = $this->makeMember(['nip' => 'NIP-100']);
        $book = $this->makeCatalogBook();
        $item = $this->makeBookItem($book);

        $this->actingAs($librarian)->post('/circulation', [
            'action' => 'borrow',
            'member_identifier' => 'NIP-100',
            'barcode' => $item->barcode,
        ])->assertRedirect('/admin/circulation');

        $this->assertDatabaseHas('circulations', [
            'user_id' => $member->id,
            'book_item_id' => $item->id,
            'status' => 'active',
        ]);
        $this->assertSame('borrowed', DB::table('book_items')->where('id', $item->id)->value('status'));
    }

    public function test_borrowing_a_reserved_copy_completes_the_reservation(): void
    {
        $librarian = $this->makeLibrarian();
        $member = $this->makeMember(['nip' => 'NIP-101']);
        $book = $this->makeCatalogBook();
        $item = $this->makeBookItem($book, ['status' => 'reserved']);
        DB::table('reservations')->insert([
            'user_id' => $member->id,
            'catalog_id' => $book->id,
            'reservation_code' => 'RSV-101',
            'status' => 'pending_pickup',
        ]);

        $this->actingAs($librarian)->post('/circulation', [
            'action' => 'borrow',
            'member_identifier' => 'NIP-101',
            'barcode' => $item->barcode,
        ])->assertRedirect('/admin/circulation');

        $this->assertSame('completed', DB::table('reservations')->where('reservation_code', 'RSV-101')->value('status'));
        $this->assertSame('borrowed', DB::table('book_items')->where('id', $item->id)->value('status'));
    }

    public function test_borrowing_a_reserved_copy_without_reservation_is_rejected(): void
    {
        $book = $this->makeCatalogBook();
        $item = $this->makeBookItem($book, ['status' => 'reserved']);

        $response = $this->actingAs($this->makeLibrarian())->post('/circulation', [
            'action' => 'borrow',
            'member_identifier' => $this->makeMember()->email,
            'barcode' => $item->barcode,
        ]);

        $response->assertSessionHas('flash.type', 'error');
        $this->assertDatabaseCount('circulations', 0);
    }

    public function test_returning_a_late_loan_records_a_fine(): void
    {
        $librarian = $this->makeLibrarian();
        $member = $this->makeMember();
        $book = $this->makeCatalogBook();
        $item = $this->makeBookItem($book, ['status' => 'borrowed']);
        $loanId = DB::table('circulations')->insertGetId([
            'user_id' => $member->id,
            'book_item_id' => $item->id,
            'borrow_date' => today()->subDays(19)->toDateString(),
            'due_date' => today()->subDays(5)->toDateString(),
            'status' => 'active',
        ]);

        $this->actingAs($librarian)->post('/circulation', [
            'action' => 'return',
            'member_identifier' => $member->email,
            'barcode' => $item->barcode,
        ])->assertRedirect('/admin/circulation');

        $this->assertSame('returned', DB::table('circulations')->where('id', $loanId)->value('status'));
        $this->assertEquals(5000, (float) DB::table('circulations')->where('id', $loanId)->value('fine_amount'));
        $this->assertSame('available', DB::table('book_items')->where('id', $item->id)->value('status'));
    }

    public function test_extending_an_active_loan_adds_fourteen_days(): void
    {
        $librarian = $this->makeLibrarian();
        $member = $this->makeMember();
        $book = $this->makeCatalogBook();
        $item = $this->makeBookItem($book, ['status' => 'borrowed']);
        $loanId = DB::table('circulations')->insertGetId([
            'user_id' => $member->id,
            'book_item_id' => $item->id,
            'borrow_date' => today()->toDateString(),
            'due_date' => today()->toDateString(),
            'status' => 'active',
        ]);

        $this->actingAs($librarian)->post('/circulation', [
            'action' => 'extend',
            'member_identifier' => $member->email,
            'barcode' => $item->barcode,
        ])->assertRedirect('/admin/circulation');

        $dueDate = Carbon::parse(DB::table('circulations')->where('id', $loanId)->value('due_date'));

        $this->assertSame(today()->addDays(14)->toDateString(), $dueDate->toDateString());
    }

    public function test_overdue_loan_cannot_be_extended(): void
    {
        $member = $this->makeMember();
        $book = $this->makeCatalogBook();
        $item = $this->makeBookItem($book, ['status' => 'borrowed']);
        DB::table('circulations')->insert([
            'user_id' => $member->id,
            'book_item_id' => $item->id,
            'borrow_date' => today()->subDays(20)->toDateString(),
            'due_date' => today()->subDays(6)->toDateString(),
            'status' => 'overdue',
        ]);

        $response = $this->actingAs($this->makeLibrarian())->post('/circulation', [
            'action' => 'extend',
            'member_identifier' => $member->email,
            'barcode' => $item->barcode,
        ]);

        $response->assertSessionHas('flash.type', 'error');
    }

    public function test_returning_a_copy_promotes_the_next_waiting_member(): void
    {
        $librarian = $this->makeLibrarian();
        $borrower = $this->makeMember();
        $waiter = $this->makeMember();
        $book = $this->makeCatalogBook();
        $item = $this->makeBookItem($book, ['status' => 'borrowed']);
        DB::table('circulations')->insert([
            'user_id' => $borrower->id,
            'book_item_id' => $item->id,
            'borrow_date' => today()->subDays(2)->toDateString(),
            'due_date' => today()->addDays(12)->toDateString(),
            'status' => 'active',
        ]);
        DB::table('reservations')->insert([
            'user_id' => $waiter->id,
            'catalog_id' => $book->id,
            'status' => 'waiting_list',
        ]);

        $this->actingAs($librarian)->post('/circulation', [
            'action' => 'return',
            'member_identifier' => $borrower->email,
            'barcode' => $item->barcode,
        ])->assertRedirect('/admin/circulation');

        $this->assertSame('pending_pickup', DB::table('reservations')->where('user_id', $waiter->id)->value('status'));
        $this->assertNotNull(DB::table('reservations')->where('user_id', $waiter->id)->value('reservation_code'));
        $this->assertSame('reserved', DB::table('book_items')->where('id', $item->id)->value('status'));
    }

    public function test_overdue_maintenance_flags_past_due_active_loans(): void
    {
        $member = $this->makeMember();
        $book = $this->makeCatalogBook();
        $item = $this->makeBookItem($book, ['status' => 'borrowed']);
        DB::table('circulations')->insert([
            'user_id' => $member->id,
            'book_item_id' => $item->id,
            'borrow_date' => today()->subDays(20)->toDateString(),
            'due_date' => today()->subDay()->toDateString(),
            'status' => 'active',
        ]);

        $this->actingAs($this->makeLibrarian())
            ->post('/admin/maintenance/overdue')
            ->assertRedirect('/admin/circulation');

        $this->assertSame('overdue', DB::table('circulations')->where('book_item_id', $item->id)->value('status'));
    }
}
