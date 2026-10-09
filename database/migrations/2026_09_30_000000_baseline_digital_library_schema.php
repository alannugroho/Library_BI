<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users')) {
            return;
        }

        $driver = DB::connection()->getDriverName();
        $schemaFile = $driver === 'sqlite' ? 'schema.sqlite.sql' : 'schema.sql';

        $schema = file_get_contents(database_path($schemaFile));
        if ($schema === false) {
            throw new RuntimeException('Library schema file could not be read.');
        }

        if ($driver !== 'sqlite') {
            $schema = preg_replace('/^\s*CREATE DATABASE.*?;\s*/ims', '', $schema, 1);
            $schema = preg_replace('/^\s*USE\s+digital_library_bi\s*;\s*/im', '', $schema, 1);
        }

        DB::unprepared($schema);
    }

    public function down(): void
    {
        // The baseline schema is shared with the existing application.
    }
};
