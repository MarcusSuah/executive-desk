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
        Schema::table('users', function (Blueprint $table) {
            $table->string('timezone', 64)->default('UTC')->after('email');
            $table->string('phone', 20)->nullable()->after('timezone');
            $table->timestamp('phone_verified_at')->nullable()->after('phone');
            $table->boolean('is_active')->default(true)->after('phone_verified_at');
            $table->timestamp('deactivated_at')->nullable()->after('is_active');
            $table->timestamp('last_login_at')->nullable()->after('deactivated_at');

            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['is_active']);
            $table->dropColumn(['timezone', 'phone', 'phone_verified_at', 'is_active', 'deactivated_at', 'last_login_at']);
        });
    }
};
