<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('check_rollups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monitor_id')->constrained('monitors')->cascadeOnDelete();
            $table->enum('period', ['hour', 'day']);
            $table->timestamp('period_start');
            $table->unsignedInteger('checks_total');
            $table->unsignedInteger('checks_failed');
            $table->unsignedInteger('avg_response_time_ms')->nullable();
            $table->unsignedInteger('min_response_time_ms')->nullable();
            $table->unsignedInteger('max_response_time_ms')->nullable();
            $table->timestamps();

            // The upsert key: one row per monitor per bucket.
            $table->unique(['monitor_id', 'period', 'period_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('check_rollups');
    }
};
