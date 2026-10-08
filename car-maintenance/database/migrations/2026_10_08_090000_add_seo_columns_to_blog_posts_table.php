<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->string('byline', 120)->nullable()->after('author_id');
            $table->string('cover_image', 2048)->nullable()->after('meta_description');
            $table->unsignedSmallInteger('reading_time_minutes')->default(1)->after('cover_image');
            $table->json('toc')->nullable()->after('reading_time_minutes');
        });

        $this->backfillReadingTime();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->dropColumn(['byline', 'cover_image', 'reading_time_minutes', 'toc']);
        });
    }

    /**
     * Populate reading_time_minutes for existing rows from their rendered HTML.
     */
    private function backfillReadingTime(): void
    {
        DB::table('blog_posts')
            ->select(['id', 'body_html'])
            ->orderBy('id')
            ->chunk(200, function ($posts): void {
                foreach ($posts as $post) {
                    $minutes = max(1, (int) ceil(str_word_count(strip_tags((string) $post->body_html)) / 220));

                    DB::table('blog_posts')->where('id', $post->id)->update(['reading_time_minutes' => $minutes]);
                }
            });
    }
};
