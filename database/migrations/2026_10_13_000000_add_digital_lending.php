<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('catalog_books', 'digital_copies')) {
            Schema::table('catalog_books', function (Blueprint $table): void {
                $table->unsignedInteger('digital_copies')->default(1)->after('digital_file_path');
            });
        }

        if (! Schema::hasTable('digital_loans')) {
            Schema::create('digital_loans', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->unsignedInteger('user_id');
                $table->unsignedInteger('catalog_id');
                $table->dateTime('borrowed_at');
                $table->dateTime('due_at');
                $table->dateTime('returned_at')->nullable();
                $table->enum('status', ['active', 'returned', 'expired'])->default('active');
                $table->timestamp('created_at')->useCurrent();

                $table->foreign('user_id')->references('id')->on('users')->cascadeOnUpdate()->restrictOnDelete();
                $table->foreign('catalog_id')->references('id')->on('catalog_books')->cascadeOnUpdate()->restrictOnDelete();
                $table->index(['catalog_id', 'status'], 'idx_digital_loans_catalog');
                $table->index(['user_id', 'status'], 'idx_digital_loans_user');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('digital_loans');

        if (Schema::hasColumn('catalog_books', 'digital_copies')) {
            Schema::table('catalog_books', function (Blueprint $table): void {
                $table->dropColumn('digital_copies');
            });
        }
    }
};
