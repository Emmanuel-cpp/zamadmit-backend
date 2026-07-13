<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Forces the user to change their password on next login.
            // Used for staff accounts provisioned with a temporary password.
            $table->boolean('must_change_password')->default(false)->after('role');

            // Audit timestamp — when the user last changed their password.
            // Useful for security policies and audit logs.
            $table->timestamp('password_changed_at')->nullable()->after('must_change_password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['must_change_password', 'password_changed_at']);
        });
    }
};