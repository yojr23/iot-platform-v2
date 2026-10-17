<?php

namespace Tests\Feature;

use Tests\TestCase;

class ArchiveLogsTest extends TestCase
{
    public function test_compresses_old_logs_keeps_today_and_purges_only_expired_archives(): void
    {
        $dir = sys_get_temp_dir().'/logs-'.uniqid();
        mkdir($dir);
        $day = 86400;
        $put = function (string $name, int $age) use ($dir) {
            file_put_contents("$dir/$name", "line\n");
            touch("$dir/$name", time() - $age);
        };
        $put('laravel-'.date('Y-m-d').'.log', 0);          // today: untouched
        $put('laravel-2020-01-01.log', 3 * $day);          // old: gzip
        $put('application-2020-01-01.json', 3 * $day);     // old json: gzip
        $put('audit-2020-01-01.json', 3 * $day);           // audit: Monolog owns it
        $put('old.log.gz', 31 * $day);                     // expired archive: purge
        $put('recent.log.gz', 10 * $day);                  // young archive: keep

        $this->artisan('iot:logs:archive', ['--path' => $dir])->assertSuccessful();

        $this->assertFileExists($dir.'/laravel-'.date('Y-m-d').'.log');
        $this->assertFileDoesNotExist("$dir/laravel-2020-01-01.log");
        $this->assertSame("line\n", gzdecode(file_get_contents("$dir/laravel-2020-01-01.log.gz")));
        $this->assertFileExists("$dir/application-2020-01-01.json.gz");
        $this->assertFileExists("$dir/audit-2020-01-01.json");
        $this->assertFileDoesNotExist("$dir/old.log.gz");
        $this->assertFileExists("$dir/recent.log.gz");
        // The fresh archive keeps the source file's age, so it is not purged early or kept forever.
        $this->assertEqualsWithDelta(time() - 3 * $day, filemtime("$dir/laravel-2020-01-01.log.gz"), 5);

        array_map('unlink', glob("$dir/*"));
        rmdir($dir);
    }
}
