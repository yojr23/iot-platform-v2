<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cold tier for raw_sensor_events / audit_logs (see iot:storage:tier). MySQL ENGINE=ARCHIVE is
 * append-only and compressed (~30 B/row vs ~944 B InnoDB); it allows no secondary indexes, so
 * `id` is a plain BIGINT copied from the source table. Also compresses the two InnoDB measurement
 * tables (sensor_readings, reading_projections). MySQL-only DDL is guarded for the SQLite tests.
 */
return new class extends Migration
{
    public function up(): void
    {
        $mysql = DB::getDriverName() === 'mysql';

        Schema::create('raw_sensor_events_archive', function (Blueprint $table) use ($mysql): void {
            if ($mysql) {
                $table->engine = 'ARCHIVE';
            }
            $table->unsignedBigInteger('id');
            $table->string('topic')->nullable();
            $table->string('source')->nullable();
            $table->string('source_event_id')->nullable();
            $table->string('node_id')->nullable();
            $table->json('payload');
            $table->timestamp('received_at')->nullable();
            $table->string('status')->default('received');
            $table->text('error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->timestamp('archived_at')->nullable();
        });

        Schema::create('audit_logs_archive', function (Blueprint $table) use ($mysql): void {
            if ($mysql) {
                $table->engine = 'ARCHIVE';
            }
            $table->unsignedBigInteger('id');
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->string('action', 100);
            $table->string('resource_type', 100);
            $table->unsignedBigInteger('resource_id')->nullable();
            $table->string('request_id', 100)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->timestamp('archived_at')->nullable();
        });

        if ($mysql) {
            DB::statement('ALTER TABLE sensor_readings ROW_FORMAT=COMPRESSED KEY_BLOCK_SIZE=8');
            DB::statement('ALTER TABLE reading_projections ROW_FORMAT=COMPRESSED KEY_BLOCK_SIZE=8');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE sensor_readings ROW_FORMAT=DYNAMIC');
            DB::statement('ALTER TABLE reading_projections ROW_FORMAT=DYNAMIC');
        }
        Schema::dropIfExists('audit_logs_archive');
        Schema::dropIfExists('raw_sensor_events_archive');
    }
};
