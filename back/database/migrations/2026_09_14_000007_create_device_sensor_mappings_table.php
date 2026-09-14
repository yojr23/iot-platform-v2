<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_sensor_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sensor_id')->constrained()->cascadeOnDelete();
            $table->string('source', 100);
            $table->string('external_key', 255);
            $table->boolean('is_active')->default(true);
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['device_id', 'source', 'external_key']);
            $table->index(['source', 'external_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_sensor_mappings');
    }
};
