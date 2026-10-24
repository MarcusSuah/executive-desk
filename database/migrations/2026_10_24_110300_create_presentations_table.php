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
        Schema::create('presentations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('calendar_event_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('presenter_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('presenter_name')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->dateTime('presentation_at')->nullable();
            $table->string('timezone', 64);
            $table->string('venue')->nullable();
            $table->string('meeting_url', 2048)->nullable();
            $table->dateTime('preparation_deadline_at')->nullable();
            $table->dateTime('review_deadline_at')->nullable();
            $table->string('status', 24)->default('draft');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'preparation_deadline_at']);
            $table->index(['owner_id', 'presentation_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('presentations');
    }
};
