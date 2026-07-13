<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institutions', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('short_name', 20);
            $table->enum('type', ['public', 'private'])->default('public');
            $table->string('city');
            $table->string('province');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('established')->nullable();
            $table->date('application_deadline')->nullable();
            $table->boolean('is_accepting_applications')->default(false);
            $table->string('image_url')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('institutions');
    }
};