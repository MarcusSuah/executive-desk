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
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('email_enabled')->default(true);
            $table->boolean('sms_enabled')->default(false);
            $table->boolean('push_enabled')->default(false);
            $table->json('event_reminder_minutes');
            $table->json('task_reminder_minutes');
            $table->json('presentation_reminder_minutes');
            $table->boolean('schedule_change_notifications')->default(true);
            $table->boolean('daily_briefing_enabled')->default(false);
            $table->time('daily_briefing_time')->default('07:00:00');
            $table->json('daily_briefing_channels');
            $table->boolean('weekly_briefing_enabled')->default(false);
            $table->unsignedTinyInteger('weekly_briefing_day')->default(1);
            $table->time('weekly_briefing_time')->default('07:00:00');
            $table->json('weekly_briefing_channels');
            $table->timestamps();
        });

        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('endpoint');
            $table->char('endpoint_hash', 64)->unique();
            $table->text('public_key');
            $table->text('auth_token');
            $table->string('content_encoding', 16)->default('aes128gcm');
            $table->string('device_name')->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->dateTime('last_used_at')->nullable();
            $table->timestamps();
        });

        Schema::create('reminders', function (Blueprint $table) {
            $table->id();
            $table->morphs('remindable');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 32);
            $table->string('channel', 16);
            $table->unsignedInteger('minutes_before');
            $table->dateTime('scheduled_for');
            $table->string('status', 16)->default('pending');
            $table->string('idempotency_key', 64)->unique();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->dateTime('reserved_at')->nullable();
            $table->dateTime('last_attempt_at')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->string('provider_message_id')->nullable();
            $table->string('last_error', 1000)->nullable();
            $table->string('status_reason')->nullable();
            $table->timestamps();

            $table->index(['status', 'scheduled_for']);
            $table->index(['user_id', 'scheduled_for']);
        });

        Schema::create('executive_briefings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 16);
            $table->date('period_start');
            $table->date('period_end');
            $table->string('timezone', 64);
            $table->json('content');
            $table->dateTime('generated_at');
            $table->string('status', 24)->default('generated');
            $table->dateTime('delivered_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'type', 'period_start']);
            $table->index(['type', 'generated_at']);
        });

        Schema::create('notification_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->nullableMorphs('deliverable');
            $table->string('notification_type', 48);
            $table->string('channel', 16);
            $table->string('status', 16);
            $table->unsignedTinyInteger('attempt_number')->default(1);
            $table->dateTime('scheduled_for')->nullable();
            $table->dateTime('attempted_at');
            $table->string('provider_message_id')->nullable();
            $table->string('error_message', 1000)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'attempted_at']);
            $table->index(['status', 'attempted_at']);
            $table->index(['channel', 'attempted_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_deliveries');
        Schema::dropIfExists('executive_briefings');
        Schema::dropIfExists('reminders');
        Schema::dropIfExists('push_subscriptions');
        Schema::dropIfExists('notification_preferences');
    }
};
