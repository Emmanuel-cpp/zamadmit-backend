<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('subject');
            $table->unsignedTinyInteger('grade'); // ECZ scale 1-9
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_grades');
    }
};