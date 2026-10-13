<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $columns = [];

        if (! Schema::hasColumn('book_proposals', 'publication_year')) {
            $columns[] = 'publication_year';
        }

        if (! Schema::hasColumn('book_proposals', 'isbn_issn')) {
            $columns[] = 'isbn_issn';
        }

        if (! Schema::hasColumn('book_proposals', 'purpose')) {
            $columns[] = 'purpose';
        }

        if ($columns === []) {
            return;
        }

        Schema::table('book_proposals', function (Blueprint $table) use ($columns): void {
            if (in_array('publication_year', $columns, true)) {
                $table->unsignedSmallInteger('publication_year')->nullable();
            }

            if (in_array('isbn_issn', $columns, true)) {
                $table->string('isbn_issn', 50)->nullable();
            }

            if (in_array('purpose', $columns, true)) {
                $table->text('purpose')->nullable();
            }
        });
    }

    public function down(): void
    {
        $columns = array_filter([
            Schema::hasColumn('book_proposals', 'publication_year') ? 'publication_year' : null,
            Schema::hasColumn('book_proposals', 'isbn_issn') ? 'isbn_issn' : null,
            Schema::hasColumn('book_proposals', 'purpose') ? 'purpose' : null,
        ]);

        if ($columns !== []) {
            Schema::table('book_proposals', fn (Blueprint $table) => $table->dropColumn($columns));
        }
    }
};
