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
        Schema::table('analytics_page_views', function (Blueprint $table) {
            $table->string('country_code', 2)->nullable();
            $table->index(['occurred_at', 'country_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('analytics_page_views', function (Blueprint $table) {
            $table->dropIndex(['occurred_at', 'country_code']);
            $table->dropColumn('country_code');
        });
    }
};
