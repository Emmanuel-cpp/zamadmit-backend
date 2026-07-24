<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Widen users.role to admit the platform_admin tier.
     * Raw SQL because Doctrine cannot modify MySQL enums in place.
     */
    public function up(): void
    {
        DB::statement(
            "ALTER TABLE users MODIFY COLUMN role
             ENUM('student','institution_admin','platform_admin')
             NOT NULL DEFAULT 'student'"
        );
    }

    public function down(): void
    {
        // Demote any platform admins before narrowing the column again.
        DB::statement("UPDATE users SET role = 'institution_admin' WHERE role = 'platform_admin'");

        DB::statement(
            "ALTER TABLE users MODIFY COLUMN role
             ENUM('student','institution_admin')
             NOT NULL DEFAULT 'student'"
        );
    }
};