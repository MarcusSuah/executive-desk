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
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('calendar_event_id')->nullable()->constrained()->nullOnDelete();
            $table->string('project')->nullable();
            $table->string('priority', 16)->default('normal');
            $table->string('status', 16)->default('pending');
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('due_at')->nullable();
            $table->string('timezone', 64);
            $table->unsignedInteger('estimated_minutes')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->string('recurrence_frequency', 16)->nullable();
            $table->unsignedSmallInteger('recurrence_interval')->nullable();
            $table->date('recurrence_ends_on')->nullable();
            $table->foreignId('recurrence_parent_id')->nullable()->constrained('tasks')->cascadeOnDelete();
            $table->date('occurrence_date')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['recurrence_parent_id', 'occurrence_date']);
            $table->index(['assigned_to', 'status', 'due_at']);
            $table->index(['status', 'due_at']);
            $table->index('recurrence_frequency');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
