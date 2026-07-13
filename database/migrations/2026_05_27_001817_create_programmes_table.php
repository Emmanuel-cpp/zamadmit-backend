<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programmes', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->enum('qualification', [
                'Certificate', 'Diploma', 'Bachelor', 'Master', 'PhD'
            ]);
            $table->string('school');   // e.g. School of Engineering, School of ICT
            $table->unsignedTinyInteger('duration_years');
            $table->enum('study_mode', ['Full-time', 'Part-time', 'Distance'])
                  ->default('Full-time');
            $table->string('intake')->nullable();   // e.g. "January 2026"
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Programme entry requirements — stored as separate rows
        // so we can have multiple subjects per programme
        Schema::create('programme_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('programme_id')->constrained()->cascadeOnDelete();
            $table->string('subject');      // e.g. Mathematics
            $table->unsignedTinyInteger('min_grade'); // ECZ scale 1-9
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programme_requirements');
        Schema::dropIfExists('programmes');
    }
};