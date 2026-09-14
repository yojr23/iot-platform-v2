<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->string('api_key_hash', 64)->nullable()->after('api_key');
            $table->string('api_key_prefix', 8)->nullable()->after('api_key_hash');
            $table->timestamp('api_key_last_rotated_at')->nullable()->after('api_key_prefix');
        });

        // Hash existing plaintext API keys
        $devices = DB::table('devices')->select('id', 'api_key')->get();
        foreach ($devices as $device) {
            if ($device->api_key) {
                DB::table('devices')
                    ->where('id', $device->id)
                    ->update([
                        'api_key_hash' => hash('sha256', $device->api_key),
                        'api_key_prefix' => substr($device->api_key, 0, 8),
                        'api_key_last_rotated_at' => now(),
                    ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn(['api_key_hash', 'api_key_prefix', 'api_key_last_rotated_at']);
        });
    }
};
