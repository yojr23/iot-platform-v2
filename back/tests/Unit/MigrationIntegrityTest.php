<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * L1: Schema migration integrity test.
 *
 * Verifies that migration files exist, are parseable PHP, and follow the
 * project's naming convention (YYYY_MM_DD_HHMMSS_description). This catches
 * accidental renames, missing timestamps, and broken syntax without needing
 * a live database connection.
 *
 * ponytail: structural check only — does NOT run migrate:fresh. That belongs
 * in CI with a real DB. This test runs in unit-test mode (no DB required).
 */
final class MigrationIntegrityTest extends TestCase
{
    private const MIGRATIONS_DIR = __DIR__ . '/../../database/migrations';

    private const EXPECTED_TABLES = [
        'users',
        'devices',
        'sensors',
        'sensor_types',
        'sensor_readings',
        'alerts',
        'alert_rules',
        'device_status_logs',
        'dashboard_preferences',
        'system_settings',
        'raw_sensor_events',
        'domain_event_outboxes',
        'raw_event_outbox',
    ];

    public function test_migrations_directory_exists(): void
    {
        $this->assertDirectoryExists(self::MIGRATIONS_DIR);
    }

    public function test_migration_files_follow_naming_convention(): void
    {
        $files = $this->globMigrations();

        $this->assertNotEmpty($files, 'No migration files found');

        $pattern = '/^\d{4}_\d{2}_\d{2}_\d{6}_.+\.php$/';

        foreach ($files as $file) {
            $basename = basename($file);
            $this->assertMatchesRegularExpression(
                $pattern,
                $basename,
                "Migration '$basename' does not follow YYYY_MM_DD_HHMMSS_description.php convention"
            );
        }
    }

    public function test_migration_files_are_valid_php(): void
    {
        $phpBinary = PHP_BINARY ?: trim((string) shell_exec('which php'));
        if (!$phpBinary || !is_executable($phpBinary)) {
            $this->markTestSkipped('PHP binary not available for syntax check');
        }

        $files = $this->globMigrations();

        foreach ($files as $file) {
            $output = [];
            $exitCode = 0;
            exec(escapeshellarg($phpBinary) . " -l " . escapeshellarg($file) . " 2>&1", $output, $exitCode);

            $this->assertSame(
                0,
                $exitCode,
                "Syntax error in " . basename($file) . ": " . implode("\n", $output)
            );
        }
    }

    public function test_core_tables_have_corresponding_migrations(): void
    {
        $files = $this->globMigrations();
        $allContent = '';

        foreach ($files as $file) {
            $allContent .= file_get_contents($file) . "\n";
        }

        foreach (self::EXPECTED_TABLES as $table) {
            $this->assertStringContainsString(
                "'$table'",
                $allContent,
                "No migration references table '$table'"
            );
        }
    }

    public function test_no_duplicate_migration_names(): void
    {
        $files = $this->globMigrations();
        $names = array_map(
            static fn (string $file): string => basename($file, '.php'),
            $files
        );

        $this->assertSame(count($names), count(array_unique($names)), 'Duplicate migration names found.');
    }

    private function globMigrations(): array
    {
        return glob(self::MIGRATIONS_DIR . '/*.php') ?: [];
    }
}
