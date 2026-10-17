<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Gzips rotated logs (laravel-YYYY-MM-DD.log, application-*.json, health-*.json) older than 1 day and
 * purges *.gz older than LOG_ARCHIVE_DAYS. Monolog's maxFiles only globs its own *.log/*.json pattern,
 * so the .gz files are never counted nor deleted by it. audit-*.json is left alone: Monolog keeps it 90 days.
 * ponytail: age = file mtime (rotated files stop changing at midnight), so no filename date parsing.
 */
class ArchiveLogs extends Command
{
    protected $signature = 'iot:logs:archive {--compress : Only compress} {--purge : Only purge old .gz} {--path= : Log directory (default storage/logs)}';

    protected $description = 'Gzip rotated log files older than 1 day and purge archives older than LOG_ARCHIVE_DAYS.';

    public function handle(): int
    {
        $dir = rtrim($this->option('path') ?: storage_path('logs'), '/');
        $all = ! $this->option('compress') && ! $this->option('purge');
        $compressed = $purged = 0;

        if ($all || $this->option('compress')) {
            foreach (array_merge(glob("$dir/*.log") ?: [], glob("$dir/*.json") ?: []) as $file) {
                // Never today's active file; daily rotation names it ...-YYYY-MM-DD.
                if (str_starts_with(basename($file), 'audit') || filemtime($file) > time() - 86400
                    || str_contains(basename($file), date('Y-m-d').'.')) {
                    continue;
                }
                $mtime = filemtime($file);
                $in = fopen($file, 'rb');
                $out = gzopen("$file.gz", 'wb6');
                while (! feof($in)) {
                    gzwrite($out, fread($in, 1 << 20));
                }
                fclose($in);
                gzclose($out);
                touch("$file.gz", $mtime); // keep original age for the purge
                unlink($file);
                $compressed++;
            }
        }

        if ($all || $this->option('purge')) {
            $cutoff = time() - 86400 * (int) config('logging.archive_days');
            foreach (glob("$dir/*.gz") ?: [] as $file) {
                if (filemtime($file) < $cutoff && unlink($file)) {
                    $purged++;
                }
            }
        }

        $this->info("compressed=$compressed purged=$purged");

        return self::SUCCESS;
    }
}
