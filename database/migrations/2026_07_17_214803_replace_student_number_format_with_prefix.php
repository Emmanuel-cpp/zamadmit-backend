<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('institutions', function (Blueprint $table) {
            $table->dropColumn('student_number_format');

            // e.g. "2610" — the fixed leading digits of student numbers
            $table->string('student_number_prefix', 20)->nullable();

            // Total length of the full student number, e.g. 8 → 2610 + 4 seq digits
            $table->unsignedTinyInteger('student_number_length')->default(8);
        });
    }

    public function down(): void
    {
        Schema::table('institutions', function (Blueprint $table) {
            $table->dropColumn(['student_number_prefix', 'student_number_length']);
            $table->string('student_number_format', 50)->nullable();
        });
    }
};