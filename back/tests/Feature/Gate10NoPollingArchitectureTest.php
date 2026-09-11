<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Gate 10 (PLAN.md Task 6.6) — source/config regression guard proving the periodic outbox relays
 * are gone and the binlog-CDC publication path is the only one wired. Pure filesystem assertions:
 * no DB, no Redis, so it runs anywhere.
 */
class Gate10NoPollingArchitectureTest extends TestCase
{
    private function backPath(string $relative): string
    {
        return base_path($relative);
    }

    private function repoPath(string $relative): string
    {
        // Tests run from back/; the compose file lives at the repo root one level up.
        return dirname(base_path()).DIRECTORY_SEPARATOR.$relative;
    }

    public function test_retired_relay_files_are_deleted(): void
    {
        $gone = [
            'app/Console/Commands/RelayRawOutbox.php',
            'app/Console/Commands/RelayDomainOutbox.php',
            'app/Jobs/RelayRawOutboxJob.php',
            'app/Jobs/RelayDomainOutboxJob.php',
            'app/Services/Ingestion/RawOutboxRelay.php',
            'app/Services/Ingestion/DomainOutboxRelay.php',
        ];

        foreach ($gone as $relative) {
            $this->assertFileDoesNotExist($this->backPath($relative), "retired relay artifact still present: {$relative}");
        }
    }

    public function test_cdc_publication_path_contains_no_polling_constructs(): void
    {
        $forbidden = ['ingestion:relay-outbox', 'domain:relay-outbox', '--interval=', 'relayPending(', 'sleep(', 'usleep('];

        $files = [
            'app/Console/Commands/ConsumeCdcOutboxes.php',
            'app/Services/Ingestion/Cdc/CdcOutboxStreamConsumer.php',
            'app/Services/Ingestion/Cdc/DebeziumChange.php',
        ];

        foreach ($files as $relative) {
            $path = $this->backPath($relative);
            $this->assertFileExists($path, "expected CDC publication file missing: {$relative}");
            $source = (string) file_get_contents($path);

            foreach ($forbidden as $token) {
                $this->assertStringNotContainsString($token, $source, "forbidden polling construct '{$token}' found in {$relative}");
            }
        }
    }

    public function test_request_handlers_do_not_dispatch_relay_wakeups(): void
    {
        foreach ([
            'app/Http/Controllers/Api/IngestionController.php',
            'app/Services/Ingestion/DomainEventRecorder.php',
        ] as $relative) {
            $source = (string) file_get_contents($this->backPath($relative));
            $this->assertStringNotContainsString('RelayRawOutboxJob', $source);
            $this->assertStringNotContainsString('RelayDomainOutboxJob', $source);
            $this->assertStringNotContainsString('::dispatch()', $source, "unexpected relay dispatch in {$relative}");
        }
    }

    public function test_compose_declares_cdc_pipeline_and_no_relay_services(): void
    {
        $compose = $this->repoPath('docker-compose.yml');
        $this->assertFileExists($compose);
        $yaml = (string) file_get_contents($compose);

        foreach (['debezium:', 'outbox-cdc-consumer:', 'raw-consumer:', 'domain-event-consumer:'] as $service) {
            $this->assertStringContainsString($service, $yaml, "compose is missing required service {$service}");
        }

        foreach (['outbox-relay:', 'domain-outbox-relay:'] as $service) {
            $this->assertStringNotContainsString($service, $yaml, "compose still declares retired relay service {$service}");
        }

        $this->assertStringNotContainsString('relay-outbox --interval', $yaml, 'compose still runs an interval relay sweep');
    }
}
