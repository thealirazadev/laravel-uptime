<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monitor_id')->constrained('monitors')->cascadeOnDelete();
            // First failure of the confirming streak, not when it was confirmed.
            $table->timestamp('started_at');
            $table->timestamp('closed_at')->nullable();
            $table->string('summary')->nullable();
            $table->timestamps();

            $table->index(['monitor_id', 'closed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incidents');
    }
};
