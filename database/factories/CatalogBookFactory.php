<?php

namespace Database\Factories;

use App\Models\CatalogBook;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CatalogBook>
 */
class CatalogBookFactory extends Factory
{
    protected $model = CatalogBook::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->unique()->sentence(3),
            'author' => fake()->name(),
            'publisher' => fake()->company(),
            'publication_year' => fake()->numberBetween(1990, (int) date('Y')),
            'isbn' => fake()->isbn13(),
            'udc_classification' => null,
            'collection_type' => 'book',
            'genre' => 'Umum',
            'access_mode' => 'borrowable',
            'type' => 'physical',
            'digital_file_path' => null,
            'digital_copies' => 1,
        ];
    }

    /**
     * Indicate that the book is a digital collection.
     */
    public function digital(int $copies = 1): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => 'digital',
            'digital_file_path' => Str::uuid().'.pdf',
            'digital_copies' => $copies,
        ]);
    }
}
