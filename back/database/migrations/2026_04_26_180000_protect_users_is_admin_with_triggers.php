<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS users_before_insert_block_is_admin');
        DB::unprepared('DROP TRIGGER IF EXISTS users_before_update_block_is_admin');

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER users_before_insert_block_is_admin
            BEFORE INSERT ON users
            FOR EACH ROW
            BEGIN
                IF NEW.is_admin = 1 AND COALESCE(@allow_admin_role_change, 0) <> 1 THEN
                    SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'Direct admin role creation is restricted';
                END IF;
            END
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER users_before_update_block_is_admin
            BEFORE UPDATE ON users
            FOR EACH ROW
            BEGIN
                IF NEW.is_admin <> OLD.is_admin AND COALESCE(@allow_admin_role_change, 0) <> 1 THEN
                    SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'Direct admin role update is restricted';
                END IF;
            END
        SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS users_before_insert_block_is_admin');
        DB::unprepared('DROP TRIGGER IF EXISTS users_before_update_block_is_admin');
    }
};
