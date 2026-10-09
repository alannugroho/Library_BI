<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->pustakawan()->create([
            'email' => 'pustakawan@example.test',
        ]);

        User::factory()->anggota()->create([
            'email' => 'anggota@example.test',
        ]);
    }
}
