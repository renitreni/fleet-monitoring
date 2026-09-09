<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->boolean('is_route')->default(false);
            $table->timestamp('ends_at')->nullable()->change();
            $table->index(['is_route', 'is_public', 'closed_at']);
        });
        Schema::create('route_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_participant_id')->constrained()->cascadeOnDelete();
            $table->timestamp('started_at', 3)->nullable();
            $table->timestamp('finished_at', 3)->nullable();
            $table->unsignedBigInteger('elapsed_ms')->nullable();
            $table->string('status')->default('ready');
            $table->timestamps();
            $table->index(['trip_participant_id', 'status', 'elapsed_ms']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('route_attempts');
        Schema::table('trips', function (Blueprint $table) {
            $table->dropIndex(['is_route', 'is_public', 'closed_at']);
            $table->dropColumn('is_route');
        });
        // Keep ends_at nullable: permanent routes have no historical expiry to restore.
    }
};
