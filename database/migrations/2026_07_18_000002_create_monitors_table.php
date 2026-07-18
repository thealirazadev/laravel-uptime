<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monitor_group_id')
                ->nullable()
                ->constrained('monitor_groups')
                ->nullOnDelete();
            $table->string('name');
            $table->string('url', 2048);
            $table->unsignedInteger('interval_seconds');
            $table->unsignedTinyInteger('timeout_seconds')->default(10);
            $table->unsignedSmallInteger('expected_status')->default(200);
            $table->string('expected_keyword')->nullable();
            $table->unsignedTinyInteger('confirmation_threshold')->default(2);
            $table->boolean('is_active')->default(true);
            $table->enum('status', ['up', 'down', 'unknown'])->default('unknown');
            $table->unsignedSmallInteger('consecutive_failures')->default(0);
            $table->unsignedSmallInteger('consecutive_successes')->default(0);
            $table->timestamp('first_failed_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            // Claim column: the dispatcher's conditional UPDATE races on this.
            $table->timestamp('next_check_at');
            $table->string('last_error')->nullable();
            $table->timestamp('ssl_expires_at')->nullable();
            $table->timestamp('ssl_checked_at')->nullable();
            $table->unsignedSmallInteger('ssl_notified_days')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'next_check_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitors');
    }
};
