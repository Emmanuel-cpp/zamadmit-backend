<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            // verified column already exists as boolean — we drop and recreate
            // with three states: null (pending), true (verified), false (rejected)
            $table->string('verification_status')->default('pending')->after('verified');
            $table->text('verification_reason')->nullable()->after('verification_status');
            $table->text('ocr_text')->nullable()->after('verification_reason');
            $table->float('confidence_score')->nullable()->after('ocr_text');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn([
                'verification_status',
                'verification_reason',
                'ocr_text',
                'confidence_score',
            ]);
        });
    }
};