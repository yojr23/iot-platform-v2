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
        Schema::create('raw_sensor_events', function (Blueprint $table): void {
            $table->id();
            $table->string('topic')->nullable();
            $table->string('source')->nullable();
            $table->string('node_id')->nullable()->index();
            $table->json('payload');
            $table->timestamp('received_at')->nullable();
            $table->string('status')->default('received');
            $table->text('error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('raw_sensor_events');
    }
};
