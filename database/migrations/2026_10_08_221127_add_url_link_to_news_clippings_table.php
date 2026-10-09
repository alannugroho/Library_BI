<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('news_clippings', 'url_link')) {
            Schema::table('news_clippings', function (Blueprint $table) {
                $table->text('url_link')->nullable()->after('publish_date');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('news_clippings', 'url_link')) {
            Schema::table('news_clippings', function (Blueprint $table) {
                $table->dropColumn('url_link');
            });
        }
    }
};
