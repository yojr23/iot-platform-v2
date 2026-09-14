<?php

namespace App\Services\Ingestion;

use App\Services\Ingestion\Concerns\UsesRawRedisCommands;
use Illuminate\Redis\Connections\Connection;
use InvalidArgumentException;

/**
 * Operational access to the shared dead-letter stream.
 *
 * Replay is deliberately explicit. Current DLQ records retain the original Redis field map in
 * `payload_json`, so they remain replayable after source-stream retention removes the entry.
 * Legacy records without it can use the original entry while it still exists. The Lua operation
 * atomically re-drives, audits, and records an idempotency marker.
 */
class DeadLetterStreamService
{
    use UsesRawRedisCommands;

    /** @param list<string> $replayableStreams */
    public function __construct(
        private Connection $connection,
        private string $deadLetterStream,
        private array $replayableStreams = [],
        private string $auditStream = 'iot.dead-letter-replays',
    ) {
    }

    /** @return list<array{id:string,fields:array<string,string>}> */
    public function inspect(int $limit = 50): array
    {
        $reply = $this->raw($this->connection, [
            'XRANGE', $this->deadLetterStream, '-', '+', 'COUNT', (string) max(1, min($limit, 1000)),
        ]);

        $entries = [];
        foreach ((array) $reply as $id => $entry) {
            if (is_string($id) && is_array($entry)) {
                $entries[] = [
                    'id' => $id,
                    'fields' => $this->fields($entry),
                ];
                continue;
            }

            if (! is_array($entry) || count($entry) < 2) {
                continue;
            }

            $entries[] = [
                'id' => (string) $entry[0],
                'fields' => $this->fields((array) $entry[1]),
            ];
        }

        return $entries;
    }

    /** @return array{status:string,replay_id:?string,source:string,original_id:string} */
    public function replay(string $deadLetterId, string $sourceStream, string $actor): array
    {
        if (! in_array($sourceStream, $this->replayableStreams, true)) {
            throw new InvalidArgumentException('Source stream is not an approved replay target.');
        }

        $marker = 'iot:dlq:replayed:'.$deadLetterId;
        $result = $this->raw($this->connection, [
            'EVAL', <<<'LUA'
local dlq = redis.call('XRANGE', KEYS[1], ARGV[1], ARGV[1])
if #dlq == 0 then return {'missing_dlq', ''} end
local original_id = ''
local stored_source = ''
local payload_json = nil
for i = 1, #dlq[1][2], 2 do
    if dlq[1][2][i] == 'orig_id' then original_id = dlq[1][2][i + 1] end
    if dlq[1][2][i] == 'orig_stream' then stored_source = dlq[1][2][i + 1] end
    if dlq[1][2][i] == 'payload_json' then payload_json = dlq[1][2][i + 1] end
end
if original_id == '' then return {'missing_orig_id', ''} end
if stored_source ~= '' and stored_source ~= KEYS[2] then return {'source_mismatch', ''} end
local existing = redis.call('GET', KEYS[3])
if existing then return {'already_replayed', existing} end
local replay_fields = nil
if payload_json ~= nil then
    local decoded, payload = pcall(cjson.decode, payload_json)
    if not decoded or type(payload) ~= 'table' or next(payload) == nil then return {'malformed_payload', ''} end
    replay_fields = {}
    for field, value in pairs(payload) do
        if type(field) ~= 'string' or type(value) ~= 'string' then return {'malformed_payload', ''} end
        table.insert(replay_fields, field)
        table.insert(replay_fields, value)
    end
else
    local source = redis.call('XRANGE', KEYS[2], original_id, original_id)
    if #source == 0 then return {'missing_source', ''} end
    replay_fields = source[1][2]
end
local replay_id = redis.call('XADD', KEYS[2], '*', unpack(replay_fields))
redis.call('XADD', KEYS[4], '*', 'dlq_id', ARGV[1], 'source_stream', KEYS[2], 'original_id', original_id, 'replay_id', replay_id, 'actor', ARGV[2])
redis.call('SET', KEYS[3], replay_id)
return {'replayed', replay_id}
LUA
            , '4', $this->deadLetterStream, $sourceStream, $marker, $this->auditStream, $deadLetterId, $actor,
        ]);

        $status = (string) ($result[0] ?? 'unknown');
        if (in_array($status, ['missing_dlq', 'missing_orig_id', 'missing_source', 'source_mismatch', 'malformed_payload'], true)) {
            throw new InvalidArgumentException(str_replace('_', ' ', $status).'.');
        }

        return [
            'status' => $status,
            'replay_id' => ($result[1] ?? '') !== '' ? (string) $result[1] : null,
            'source' => $sourceStream,
            'original_id' => $deadLetterId,
        ];
    }

    /** @param array<int|string,mixed> $raw @return array<string,string> */
    private function fields(array $raw): array
    {
        $fields = [];
        if (array_is_list($raw)) {
            for ($i = 0; $i + 1 < count($raw); $i += 2) {
                $fields[(string) $raw[$i]] = (string) $raw[$i + 1];
            }
        } else {
            foreach ($raw as $key => $value) {
                $fields[(string) $key] = (string) $value;
            }
        }

        return $fields;
    }
}
