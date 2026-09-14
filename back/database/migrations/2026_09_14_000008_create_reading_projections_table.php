<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reading_projections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('raw_sensor_event_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sensor_reading_id')->constrained()->cascadeOnDelete();
            $table->string('source_key', 255);
            $table->string('normalizer_version', 50)->default('v1');
            $table->timestamps();

            $table->unique('sensor_reading_id');
            $table->unique(['raw_sensor_event_id', 'source_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reading_projections');
    }
};
