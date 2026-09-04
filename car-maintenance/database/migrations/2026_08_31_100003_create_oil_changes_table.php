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
        Schema::create('oil_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('car_id')->constrained('cars')->cascadeOnDelete();
            $table->date('last_changed_at');
            $table->unsignedInteger('last_changed_mileage');
            $table->unsignedTinyInteger('interval_months')->default(6);
            $table->unsignedInteger('interval_mileage')->default(5000);
            $table->date('next_due_date');
            $table->unsignedInteger('next_due_mileage');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('oil_changes');
    }
};
