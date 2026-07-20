<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            // Who. Nullable: failed logins have no authenticated user.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // Which institution's scope the event belongs to (for admin
            // activity pages). Nullable: student events have none.
            $table->foreignId('institution_id')->nullable()->constrained()->nullOnDelete();

            // What happened, dot-namespaced:
            // application.decided, payment.completed, payment.failed,
            // programme.created, programme.updated, institution.updated,
            // team.invited, team.role_changed, team.removed,
            // auth.login, auth.login_failed, document.verified, ...
            $table->string('action', 60);

            // Which record (polymorphic, no FK — logs outlive their subjects).
            $table->string('auditable_type', 120)->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();

            // What changed. JSON snapshots of relevant before/after values.
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();

            $table->string('ip_address', 45)->nullable();

            $table->timestamp('created_at')->useCurrent();
            // No updated_at: audit rows are immutable by design.

            $table->index(['institution_id', 'created_at']);
            $table->index(['auditable_type', 'auditable_id']);
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};