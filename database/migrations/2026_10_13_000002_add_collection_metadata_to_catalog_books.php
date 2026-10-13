<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $columns = [];

        if (! Schema::hasColumn('catalog_books', 'collection_type')) {
            $columns[] = 'collection_type';
        }

        if (! Schema::hasColumn('catalog_books', 'genre')) {
            $columns[] = 'genre';
        }

        if (! Schema::hasColumn('catalog_books', 'access_mode')) {
            $columns[] = 'access_mode';
        }

        if ($columns === []) {
            return;
        }

        Schema::table('catalog_books', function (Blueprint $table) use ($columns): void {
            if (in_array('collection_type', $columns, true)) {
                $table->enum('collection_type', ['book', 'journal', 'magazine'])->default('book');
            }

            if (in_array('genre', $columns, true)) {
                $table->string('genre', 100)->nullable();
            }

            if (in_array('access_mode', $columns, true)) {
                $table->enum('access_mode', ['borrowable', 'reading_room'])->default('borrowable');
            }
        });
    }

    public function down(): void
    {
        $columns = array_filter([
            Schema::hasColumn('catalog_books', 'collection_type') ? 'collection_type' : null,
            Schema::hasColumn('catalog_books', 'genre') ? 'genre' : null,
            Schema::hasColumn('catalog_books', 'access_mode') ? 'access_mode' : null,
        ]);

        if ($columns !== []) {
            Schema::table('catalog_books', fn (Blueprint $table) => $table->dropColumn($columns));
        }
    }
};
