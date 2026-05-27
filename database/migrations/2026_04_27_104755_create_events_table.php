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
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description');
            $table->string('category');
            $table->string('custom_category')->nullable();
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('venue_name');
            $table->string('venue_address');
            $table->string('banner_path')->nullable();
            $table->enum('visibility', ['public', 'private'])->default('public');
            $table->enum('status', ['draft', 'published', 'ended'])->default('draft');
            $table->softDeletes();
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
