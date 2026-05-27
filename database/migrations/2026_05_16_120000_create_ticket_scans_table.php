<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_scans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('scanned_by_user_id')->constrained('users');
            $table->string('attempted_code')->nullable();
            $table->enum('result', ['valid', 'already_used', 'wrong_event', 'cancelled', 'not_found']);
            $table->timestamp('scanned_at')->useCurrent();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 1000)->nullable();
            $table->timestamps();
            $table->index(['ticket_id', 'scanned_at']);
            $table->index('scanned_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_scans');
    }
};
