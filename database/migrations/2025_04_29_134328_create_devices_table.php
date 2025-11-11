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
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('serial_number')->unique();
            $table->foreignId('device_type_id')->constrained();
            $table->foreignId('classroom_id')->constrained();
            $table->boolean('status')->default(true); // Encendido/Apagado
            $table->string('ip_address')->nullable();
            $table->string('mac_address')->nullable();
            $table->timestamp('last_communication')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
