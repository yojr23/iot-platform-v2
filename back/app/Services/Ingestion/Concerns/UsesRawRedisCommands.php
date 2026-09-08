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
}
