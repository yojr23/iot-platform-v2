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
        Schema::create('sensor_types', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Temperatura, pH, Oxigeno Disuelto, Conductividad
            $table->string('unit'); // °C, pH, mg/L, uS/cm, etc.
            $table->float('min_range');
            $table->float('max_range');
            $table->timestamps();   
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sensor_types');
    }
};
