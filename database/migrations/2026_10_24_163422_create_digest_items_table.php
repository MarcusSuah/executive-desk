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
        Schema::create('digest_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('category', 32);
            $table->string('title', 200);
            $table->string('body', 500);
            $table->string('url', 500)->nullable();
            $table->dateTime('digested_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'digested_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('digest_items');
    }
};
