<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alert_channel_monitor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alert_channel_id')->constrained('alert_channels')->cascadeOnDelete();
            $table->foreignId('monitor_id')->constrained('monitors')->cascadeOnDelete();
            $table->unique(['alert_channel_id', 'monitor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_channel_monitor');
    }
};
