<?php

namespace App\Services\Ingestion\Concerns;

use Illuminate\Redis\Connections\Connection;
use RuntimeException;

/**
 * PLAN.md Stage 4.1 — extracted from `App\Services\Ingestion\RawStreamConsumer` (Stage 3) so the
 * new `DomainEventBroadcastConsumer` (Stage 4) reuses the exact same client-agnostic raw-command
 * helper instead of duplicating it. No behavior change: same phpredis `rawCommand()` /
 * predis `executeRaw()` dispatch, same reply shape either way.
 *
 * Existing code reused: `RawStreamConsumer::raw()` (Stage 3), moved here verbatim.
 * Existing owner retired/delegated: `RawStreamConsumer` now uses this trait instead of its own
 * private copy.
 * Compatibility window: none — behavior is identical, only the location moved.
 */
trait UsesRawRedisCommands
{
    private function raw(Connection $connection, array $args): mixed
    {
        $client = $connection->client();

        if ($client instanceof \Redis || (class_exists(\RedisCluster::class) && $client instanceof \RedisCluster)) {
            return $client->rawCommand(...$args);
        }

        if (method_exists($client, 'executeRaw')) {
            return $client->executeRaw($args);
        }

        throw new RuntimeException('Unsupported Redis client: '.get_debug_type($client));
    }

    /**
     * @param  array<string,scalar>  $fields
     */
    private function rawXadd(Connection $connection, string $stream, int $maxLength, array $fields, ?int $minIdMs = null): mixed
    {
        if ($minIdMs !== null) {
            $cutoffMs = (int) (microtime(true) * 1000) - $minIdMs;
            // Redis XADD accepts one trimming strategy per command. MINID takes precedence
            // when age retention is enabled; combining it with MAXLEN is invalid grammar.
            $args = ['XADD', $stream, 'MINID', '~', (string) max(0, $cutoffMs).'-0'];
        } else {
            $args = ['XADD', $stream, 'MAXLEN', '~', (string) $maxLength];
        }

        $args[] = '*';

        foreach ($fields as $key => $value) {
            $args[] = $key;
            $args[] = (string) $value;
        }

        return $this->raw($connection, $args);
    }

    /**
     * Normalize Redis XREAD/XREADGROUP flat entry arrays into [id, [field => value]] pairs.
     */
    protected static function normalizeStreamEntries(array $entries): array
    {
        $out = [];

        foreach ($entries as $entry) {
            [$id, $flat] = $entry;

            if ($id === null) {
                continue;
            }

            $fields = [];
            $flat = is_array($flat) ? $flat : [];

            for ($i = 0, $len = count($flat); $i < $len; $i += 2) {
                $fields[$flat[$i]] = $flat[$i + 1] ?? null;
            }

            $out[] = [$id, $fields];
        }

        return $out;
    }
}
