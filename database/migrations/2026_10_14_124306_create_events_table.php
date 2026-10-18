<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description');
            $table->foreignId('category_id')->constrained('categories');
            $table->foreignId('organizer_id')->constrained('users');
            $table->string('venue');
            $table->date('event_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedInteger('max_capacity');
            $table->string('banner_path')->nullable();
            $table->dateTime('registration_deadline');
            $table->enum('status', ['upcoming', 'ongoing', 'completed', 'cancelled']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};