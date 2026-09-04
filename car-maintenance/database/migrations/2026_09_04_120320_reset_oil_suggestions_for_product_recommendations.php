<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('oil_suggestions')->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {}
};
