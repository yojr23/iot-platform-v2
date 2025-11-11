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
        Schema::create('alert_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sensor_type_id')->constrained();
            $table->float('min_value')->nullable();
            $table->float('max_value')->nullable();
            $table->string('severity'); // info, warning, danger
            $table->string('message');
            $table->string('name')->nullable()->default('Default Alert Rule');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alert_rules');
    }
};
