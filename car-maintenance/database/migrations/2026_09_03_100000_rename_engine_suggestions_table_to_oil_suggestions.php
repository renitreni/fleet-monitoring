<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::rename('engine_suggestions', 'oil_suggestions');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::rename('oil_suggestions', 'engine_suggestions');
    }
};
