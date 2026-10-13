<?php

namespace Tests\Concerns;

use App\Models\CatalogBook;
use App\Models\User;
use Illuminate\Support\Facades\DB;

trait CreatesLibraryData
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function makeMember(array $attributes = []): User
    {
        return User::factory()->anggota()->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function makeExternal(array $attributes = []): User
    {
        return User::factory()->eksternal()->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function makeLibrarian(array $attributes = []): User
    {
        return User::factory()->pustakawan()->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function makeCatalogBook(array $attributes = []): CatalogBook
    {
        return CatalogBook::factory()->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return object{id: int, catalog_id: int, barcode: string, shelf_location: ?string, status: string}
     */
    protected function makeBookItem(CatalogBook $book, array $attributes = []): object
    {
        $id = DB::table('book_items')->insertGetId(array_merge([
            'catalog_id' => $book->id,
            'barcode' => 'BC-'.fake()->unique()->numerify('######'),
            'shelf_location' => 'A-1',
            'status' => 'available',
        ], $attributes));

        return DB::table('book_items')->where('id', $id)->first();
    }

    protected function makeDigitalBook(int $copies = 1): CatalogBook
    {
        return CatalogBook::factory()->digital($copies)->create();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function makeDigitalLoan(User $user, CatalogBook $book, array $attributes = []): int
    {
        return DB::table('digital_loans')->insertGetId(array_merge([
            'user_id' => $user->id,
            'catalog_id' => $book->id,
            'borrowed_at' => now(),
            'due_at' => now()->addDays(7),
            'status' => 'active',
            'created_at' => now(),
        ], $attributes));
    }
}
