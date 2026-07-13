<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Stored as a JSON array of interest tags like
            // ["Engineering & Technology", "Information & Communication Technology"].
            // JSON gives us atomic storage + simple read/write while leaving
            // room to later filter or report on individual interests.
            $table->json('interests')->nullable()->after('province');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('interests');
        });
    }
};