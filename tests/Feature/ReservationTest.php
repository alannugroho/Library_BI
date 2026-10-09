<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesLibraryData;
use Tests\TestCase;

class ReservationTest extends TestCase
{
    use CreatesLibraryData;
    use RefreshDatabase;

    public function test_non_member_cannot_reserve(): void
    {
        $book = $this->makeCatalogBook();

        $this->actingAs($this->makeLibrarian())
            ->post('/reserve', ['catalog_id' => $book->id])
            ->assertForbidden();
    }

    public function test_member_can_reserve_an_available_physical_book(): void
    {
        $member = $this->makeMember();
        $book = $this->makeCatalogBook();
        $item = $this->makeBookItem($book);

        $this->actingAs($member)
            ->post('/reserve', ['catalog_id' => $book->id])
            ->assertRedirect('/catalog');

        $this->assertDatabaseHas('reservations', [
            'user_id' => $member->id,
            'catalog_id' => $book->id,
            'status' => 'pending_pickup',
        ]);
        $this->assertSame('reserved', DB::table('book_items')->where('id', $item->id)->value('status'));
    }

    public function test_member_cannot_hold_more_than_three_active_reservations(): void
    {
        $member = $this->makeMember();

        foreach (range(1, 3) as $index) {
            $book = $this->makeCatalogBook();
            DB::table('reservations')->insert([
                'user_id' => $member->id,
                'catalog_id' => $book->id,
                'reservation_code' => 'RSV-'.$index,
                'status' => 'pending_pickup',
            ]);
        }

        $extraBook = $this->makeCatalogBook();
        $this->makeBookItem($extraBook);

        $response = $this->actingAs($member)->post('/reserve', ['catalog_id' => $extraBook->id]);

        $response->assertSessionHas('flash.type', 'error');
        $this->assertDatabaseMissing('reservations', [
            'user_id' => $member->id,
            'catalog_id' => $extraBook->id,
        ]);
    }

    public function test_member_cannot_reserve_the_same_book_twice(): void
    {
        $member = $this->makeMember();
        $book = $this->makeCatalogBook();
        DB::table('reservations')->insert([
            'user_id' => $member->id,
            'catalog_id' => $book->id,
            'reservation_code' => 'RSV-DUP',
            'status' => 'pending_pickup',
        ]);

        $response = $this->actingAs($member)->post('/reserve', ['catalog_id' => $book->id]);

        $response->assertSessionHas('flash.type', 'error');
        $this->assertDatabaseCount('reservations', 1);
    }

    public function test_digital_book_cannot_be_reserved(): void
    {
        $book = $this->makeCatalogBook(['type' => 'digital', 'digital_file_path' => 'uploads/x.pdf']);

        $response = $this->actingAs($this->makeMember())->post('/reserve', ['catalog_id' => $book->id]);

        $response->assertSessionHas('flash.type', 'error');
        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_member_is_waitlisted_when_no_copy_is_available(): void
    {
        $member = $this->makeMember();
        $book = $this->makeCatalogBook();

        $response = $this->actingAs($member)->post('/waitlist', ['catalog_id' => $book->id]);

        $response->assertRedirect('/catalog');
        $response->assertSessionHas('flash.type', 'success');
        $this->assertDatabaseHas('reservations', [
            'user_id' => $member->id,
            'catalog_id' => $book->id,
            'status' => 'waiting_list',
        ]);
    }

    public function test_member_cannot_waitlist_when_a_copy_is_available(): void
    {
        $book = $this->makeCatalogBook();
        $this->makeBookItem($book);

        $response = $this->actingAs($this->makeMember())->post('/waitlist', ['catalog_id' => $book->id]);

        $response->assertSessionHas('flash.type', 'error');
        $this->assertDatabaseCount('reservations', 0);
    }
}
