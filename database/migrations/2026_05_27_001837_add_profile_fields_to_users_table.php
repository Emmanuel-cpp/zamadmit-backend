<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Split the default 'name' into first and last
            $table->string('first_name')->after('id');
            $table->string('last_name')->after('first_name');

            // Zambian identity
            $table->string('nrc')->nullable()->unique()->after('email');
            $table->string('phone')->nullable()->after('nrc');
            $table->string('province')->nullable()->after('phone');
            $table->date('date_of_birth')->nullable()->after('province');

            // Role determines which portal the user sees
            // 'student' or 'institution_admin'
            $table->enum('role', ['student', 'institution_admin'])
                  ->default('student')
                  ->after('date_of_birth');

            // For institution admins — which institution they manage
            $table->foreignId('institution_id')
                  ->nullable()
                  ->constrained()
                  ->nullOnDelete()
                  ->after('role');

            // Whether the student has completed their profile
            // (personal info + grades + documents uploaded)
            $table->boolean('profile_complete')->default(false)->after('institution_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['institution_id']);
            $table->dropColumn([
                'first_name', 'last_name', 'nrc', 'phone', 'province',
                'date_of_birth', 'role', 'institution_id', 'profile_complete',
            ]);
        });
    }
};