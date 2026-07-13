<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // What kind of event triggered this (used for icon/colour)
            // e.g. 'application_accepted', 'application_rejected',
            // 'application_under_review', 'application_waitlisted'
            $table->string('type', 60);

            $table->string('title');
            $table->text('body');

            // Where clicking the notification should take the user
            // e.g. '/applications/12'
            $table->string('link')->nullable();

            // NULL means unread; timestamp means read at that time.
            // Using a nullable timestamp rather than a bool is the standard
            // approach — gives us a free audit trail.
            $table->timestamp('read_at')->nullable();

            $table->timestamps();

            // Speeds up "show me unread notifications for this user" queries,
            // which is the dominant access pattern.
            $table->index(['user_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};