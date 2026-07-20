<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Permission tier within an institution. NULL for students.
            //   owner              — full control (settings, programmes, team, decisions)
            //   admissions_officer — review + decide applications only
            //   viewer             — read-only access
            $table->enum('admin_role', ['owner', 'admissions_officer', 'viewer'])
                ->nullable()
                ->after('institution_id');
        });

        // Every existing institution admin becomes an owner — they were
        // the sole account with full control, so this preserves behaviour.
        DB::table('users')
            ->where('role', 'institution_admin')
            ->update(['admin_role' => 'owner']);

        Schema::create('admin_invites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->onDelete('cascade');
            $table->string('email');
            $table->enum('admin_role', ['owner', 'admissions_officer', 'viewer']);

            // SHA-256 hash of the invite token. The raw token appears only
            // once, in the response to the inviting owner — never stored.
            $table->string('token_hash', 64)->unique();

            $table->foreignId('invited_by')->constrained('users');
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            // One pending invite per email per institution
            $table->unique(['institution_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_invites');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('admin_role');
        });
    }
};