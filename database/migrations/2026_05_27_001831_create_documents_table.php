<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('name');          // Original filename shown to user
            $table->enum('type', [
                'nrc',
                'certificate',
                'transcript',
                'photo',
                'birth_certificate',
                'other',
            ]);
            $table->string('path');          // Storage path on the server
            $table->unsignedBigInteger('size_bytes');
            $table->boolean('verified')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};