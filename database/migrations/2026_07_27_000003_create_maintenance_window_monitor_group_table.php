<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_window_monitor_group', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_window_id')->constrained('maintenance_windows')->cascadeOnDelete();
            $table->foreignId('monitor_group_id')->constrained('monitor_groups')->cascadeOnDelete();
            $table->unique(['maintenance_window_id', 'monitor_group_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_window_monitor_group');
    }
};
