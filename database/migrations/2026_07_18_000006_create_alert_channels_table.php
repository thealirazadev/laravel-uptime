<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alert_channels', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['mail', 'slack', 'webhook']);
            $table->string('name');
            // Encrypted at rest via the model's encrypted:array cast; text holds
            // the ciphertext, which is longer than the plaintext JSON.
            $table->text('config');
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_channels');
    }
};
