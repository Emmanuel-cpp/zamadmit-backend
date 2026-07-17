<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('institutions', function (Blueprint $table) {
            // Pattern for generated student numbers, e.g. "{YY}{SEQ:6}"
            // {YY} = 2-digit year, {YYYY} = 4-digit year,
            // {SEQ:n} = zero-padded sequence of n digits.
            // CBU example: {YY}{SEQ:6} → 22107671
            $table->string('student_number_format', 50)->nullable();

            // Per-institution sequence counter for student numbers.
            $table->unsignedInteger('next_student_seq')->default(1);
        });

        Schema::table('applications', function (Blueprint $table) {
            // Assigned when an application is accepted. Null until then.
            $table->string('student_number', 30)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('institutions', function (Blueprint $table) {
            $table->dropColumn(['student_number_format', 'next_student_seq']);
        });

        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn('student_number');
        });
    }
};