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
        Schema::table('alert_rules', function (Blueprint $table) {
            $table->foreignId('device_id')
                ->nullable()
                ->after('sensor_type_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('sensor_id')
                ->nullable()
                ->after('device_id')
                ->constrained()
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('alert_rules', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sensor_id');
            $table->dropConstrainedForeignId('device_id');
        });
    }
};
