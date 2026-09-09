<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('normalized_name')->nullable();
        });

        $usedNames = [];

        DB::table('users')->select(['id', 'name'])->orderBy('id')->chunkById(100, function ($users) use (&$usedNames): void {
            foreach ($users as $user) {
                $baseName = Str::squish($user->name);
                $name = $baseName;
                $suffix = 2;

                while (isset($usedNames[Str::lower($name)])) {
                    $ending = " {$suffix}";
                    $name = Str::limit($baseName, 255 - Str::length($ending), '').$ending;
                    $suffix++;
                }

                $normalizedName = Str::lower($name);
                $usedNames[$normalizedName] = true;

                DB::table('users')->where('id', $user->id)->update([
                    'name' => $name,
                    'normalized_name' => $normalizedName,
                ]);
            }
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unique('normalized_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['normalized_name']);
            $table->dropColumn('normalized_name');
        });
    }
};
