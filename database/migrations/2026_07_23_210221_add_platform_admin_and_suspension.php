<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Defensive: a previous run added some of these columns before failing,
     * so each is added only when absent.
     */
    public function up(): void
    {
        Schema::table('institutions', function (Blueprint $table) {
            if (!Schema::hasColumn('institutions', 'is_suspended')) {
                $table->boolean('is_suspended')->default(false);
            }
            if (!Schema::hasColumn('institutions', 'suspended_at')) {
                $table->timestamp('suspended_at')->nullable();
            }
            if (!Schema::hasColumn('institutions', 'suspension_reason')) {
                $table->string('suspension_reason', 500)->nullable();
            }
            if (!Schema::hasColumn('institutions', 'onboarded_by')) {
                $table->foreignId('onboarded_by')->nullable()->constrained('users')->nullOnDelete();
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'is_suspended')) {
                $table->boolean('is_suspended')->default(false);
            }
            if (!Schema::hasColumn('users', 'suspended_at')) {
                $table->timestamp('suspended_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('institutions', function (Blueprint $table) {
            if (Schema::hasColumn('institutions', 'onboarded_by')) {
                $table->dropConstrainedForeignId('onboarded_by');
            }
            $table->dropColumn(['is_suspended', 'suspended_at', 'suspension_reason']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_suspended', 'suspended_at']);
        });
    }
};