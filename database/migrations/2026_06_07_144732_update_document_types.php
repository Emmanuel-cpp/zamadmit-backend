<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Change `type` from enum to varchar so we can add new types freely
        DB::statement("ALTER TABLE documents MODIFY COLUMN type VARCHAR(50) NOT NULL");
    }

    public function down(): void
    {
        // No down — we don't need to revert this
    }
};