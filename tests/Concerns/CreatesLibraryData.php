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
}
