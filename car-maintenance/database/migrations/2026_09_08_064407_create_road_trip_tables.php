<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_trip_admin')->default(false);
        });
        Schema::create('trips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->json('route_points');
            $table->json('checkpoints');
            $table->double('distance_m');
            $table->string('invite_token', 64)->unique();
            $table->boolean('is_public')->default(false);
            $table->timestamp('ends_at');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('trip_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('public_consent')->default(false);
            $table->boolean('tracking')->default(false);
            $table->uuid('tracking_token')->nullable();
            $table->double('progress_m')->default(0);
            $table->unsignedInteger('checkpoints_completed')->default(0);
            $table->json('last_fix')->nullable();
            $table->json('verified_fix')->nullable();
            $table->boolean('continuous')->default(false);
            $table->timestamp('last_received_at')->nullable();
            $table->timestamp('last_recorded_at', 3)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->timestamps();
            $table->unique(['trip_id', 'user_id']);
        });
        Schema::create('trip_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_participant_id')->constrained()->cascadeOnDelete();
            $table->double('latitude');
            $table->double('longitude');
            $table->double('accuracy');
            $table->double('speed')->nullable();
            $table->timestamp('recorded_at', 3);
            $table->timestamp('received_at')->index();
            $table->boolean('verified');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_locations');
        Schema::dropIfExists('trip_participants');
        Schema::dropIfExists('trips');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('is_trip_admin'));
    }
};
