<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Esta migración usa SQL específico de MySQL; en SQLite (testing)
        // se omite para mantener compatibilidad y evitar falsos negativos.
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        // 1) Estandarizar tabla labs (si aún existe classrooms).
        if (Schema::hasTable('classrooms') && ! Schema::hasTable('labs')) {
            Schema::rename('classrooms', 'labs');
        }

        if (Schema::hasTable('labs')) {
            if (Schema::hasColumn('labs', 'building') && ! Schema::hasColumn('labs', 'area')) {
                DB::statement('ALTER TABLE `labs` CHANGE `building` `area` VARCHAR(255) NOT NULL');
            }

            if (Schema::hasColumn('labs', 'floor') && ! Schema::hasColumn('labs', 'process_line')) {
                DB::statement('ALTER TABLE `labs` CHANGE `floor` `process_line` VARCHAR(255) NOT NULL');
            }

            if (! Schema::hasColumn('labs', 'description')) {
                DB::statement('ALTER TABLE `labs` ADD COLUMN `description` TEXT NULL AFTER `process_line`');
            }
        }

        // 2) Estandarizar FK devices.lab_id -> labs.id.
        if (Schema::hasTable('devices')) {
            if (Schema::hasColumn('devices', 'classroom_id') && ! Schema::hasColumn('devices', 'lab_id')) {
                $constraints = DB::select("
                    SELECT CONSTRAINT_NAME
                    FROM information_schema.KEY_COLUMN_USAGE
                    WHERE TABLE_SCHEMA = DATABASE()
                      AND TABLE_NAME = 'devices'
                      AND COLUMN_NAME = 'classroom_id'
                      AND REFERENCED_TABLE_NAME IS NOT NULL
                ");

                foreach ($constraints as $constraint) {
                    DB::statement("ALTER TABLE `devices` DROP FOREIGN KEY `{$constraint->CONSTRAINT_NAME}`");
                }

                DB::statement('ALTER TABLE `devices` CHANGE `classroom_id` `lab_id` BIGINT UNSIGNED NOT NULL');
            }

            if (Schema::hasColumn('devices', 'lab_id')) {
                $labFk = DB::selectOne("
                    SELECT COUNT(*) AS total
                    FROM information_schema.KEY_COLUMN_USAGE
                    WHERE TABLE_SCHEMA = DATABASE()
                      AND TABLE_NAME = 'devices'
                      AND COLUMN_NAME = 'lab_id'
                      AND REFERENCED_TABLE_NAME = 'labs'
                ");

                if ((int) ($labFk->total ?? 0) === 0 && Schema::hasTable('labs')) {
                    DB::statement('ALTER TABLE `devices` ADD CONSTRAINT `devices_lab_id_foreign` FOREIGN KEY (`lab_id`) REFERENCES `labs`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT');
                }
            }
        }

        // 3) Marcar como ejecutada la migración de labs si no está en historial
        //    (evita que "php artisan migrate" intente recrearla).
        $legacyLabsMigration = '2025_04_29_134327_create_labs_table';
        $exists = DB::table('migrations')->where('migration', $legacyLabsMigration)->exists();

        if (! $exists) {
            $batch = (int) (DB::table('migrations')->max('batch') ?? 0);
            DB::table('migrations')->insert([
                'migration' => $legacyLabsMigration,
                'batch' => $batch > 0 ? $batch : 1,
            ]);
        }
    }

    public function down(): void
    {
        // No-op: migración de alineación de esquema productivo.
    }
};
