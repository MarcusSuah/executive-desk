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
        Schema::create('calendar_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('type', 32);
            $table->string('category', 64)->nullable();
            $table->string('priority', 16)->default('normal');
            $table->string('status', 16)->default('scheduled');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('timezone', 64);
            $table->boolean('all_day')->default(false);
            $table->string('location')->nullable();
            $table->string('meeting_url', 2048)->nullable();
            $table->text('agenda')->nullable();
            $table->text('notes')->nullable();
            $table->text('preparation_notes')->nullable();
            $table->json('reminder_minutes')->nullable();
            $table->boolean('conflict_acknowledged')->default(false);
            $table->dateTime('cancelled_at')->nullable();
            $table->string('cancellation_reason', 500)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['owner_id', 'starts_at']);
            $table->index(['status', 'starts_at']);
            $table->index(['starts_at', 'ends_at']);
        });

        Schema::create('event_attendees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calendar_event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('role', 16)->default('required');
            $table->string('response_status', 16)->default('pending');
            $table->timestamps();

            $table->unique(['calendar_event_id', 'user_id']);
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_attendees');
        Schema::dropIfExists('calendar_events');
    }
};
