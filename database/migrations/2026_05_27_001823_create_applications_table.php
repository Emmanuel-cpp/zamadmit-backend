<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('programme_id')->constrained()->cascadeOnDelete();

            $table->enum('status', [
                'draft',
                'submitted',
                'under_review',
                'accepted',
                'rejected',
                'waitlisted',
            ])->default('draft');

            $table->text('personal_statement')->nullable();

            // Admin-only internal note — never shown to the applicant
            $table->text('internal_note')->nullable();

            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('decision_at')->nullable();
            $table->timestamps();

            // A student can only apply once per programme
            $table->unique(['user_id', 'programme_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};