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
        Schema::table('notification_preferences', function (Blueprint $table) {
            $table->boolean('in_app_enabled')->default(true)->after('user_id');
            $table->json('categories')->nullable()->after('push_enabled');
            $table->boolean('quiet_hours_enabled')->default(false)->after('weekly_briefing_channels');
            $table->time('quiet_hours_start')->default('22:00:00')->after('quiet_hours_enabled');
            $table->time('quiet_hours_end')->default('07:00:00')->after('quiet_hours_start');
            $table->boolean('quiet_hours_allow_high')->default(true)->after('quiet_hours_end');
            $table->string('digest_frequency', 8)->default('off')->after('quiet_hours_allow_high');
            $table->time('digest_time')->default('08:00:00')->after('digest_frequency');
            $table->unsignedTinyInteger('digest_day')->default(1)->after('digest_time');
            $table->string('digest_include', 16)->default('non_critical')->after('digest_day');
            $table->dateTime('last_digest_at')->nullable()->after('digest_include');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notification_preferences', function (Blueprint $table) {
            $table->dropColumn([
                'in_app_enabled', 'categories', 'quiet_hours_enabled', 'quiet_hours_start', 'quiet_hours_end',
                'quiet_hours_allow_high', 'digest_frequency', 'digest_time', 'digest_day', 'digest_include', 'last_digest_at',
            ]);
        });
    }
};
