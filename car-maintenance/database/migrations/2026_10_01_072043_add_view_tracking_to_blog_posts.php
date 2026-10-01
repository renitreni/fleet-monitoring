<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->unsignedBigInteger('views_count')->default(0);
        });

        Schema::create('blog_post_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blog_post_id')->constrained()->cascadeOnDelete();
            $table->char('session_hash', 64);
            $table->date('viewed_on')->index();
            $table->unique(['blog_post_id', 'session_hash', 'viewed_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_post_views');
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->dropColumn('views_count');
        });
    }
};
