<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Transactional outbox row for durable domain facts (PLAN.md Stage 4.1, ADR-1), mirroring
 * `App\Models\RawEventOutbox` (Stage 3). One row per emitted fact (`alert.resolved`,
 * `device.status.changed`, ...), written in the same DB transaction as the aggregate mutation by
 * its transition owner (`App\Services\Alerts\AlertLifecycleService`, `App\Services\DeviceService`).
 * Gate 10: the committed row is captured from the MySQL binlog by Debezium and published by
 * `App\Services\Ingestion\Cdc\CdcOutboxStreamConsumer` via the existing `DomainEventPublisher`
 * transport; there is no periodic DB relay any more.
 */
class DomainEventOutbox extends Model
{
    use HasFactory;

    protected $table = 'domain_event_outboxes';

    protected $fillable = [
        'event_type',
        'aggregate_type',
        'aggregate_id',
        'payload',
        'status',
        'attempts',
        'locked_until',
        'last_error',
        'published_at',
        'delivered_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'locked_until' => 'datetime',
        'published_at' => 'datetime',
        'delivered_at' => 'datetime',
        'attempts' => 'integer',
    ];
}
