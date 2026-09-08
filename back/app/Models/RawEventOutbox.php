<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Transactional outbox row for `raw_sensor_events` (PLAN.md Stage 3.1, ADR-1).
 *
 * One row per raw receipt, written in the same DB transaction as its `RawSensorEvent`. The relay
 * (`App\Services\Ingestion\RawOutboxRelay`) claims `pending`/lease-expired rows and republishes
 * them via the existing `RawSensorEventPublisher` transport; it never invents a second publish
 * path.
 */
class RawEventOutbox extends Model
{
    use HasFactory;

    protected $table = 'raw_event_outboxes';

    protected $fillable = [
        'raw_sensor_event_id',
        'status',
        'attempts',
        'locked_until',
        'last_error',
        'published_at',
    ];

    protected $casts = [
        'locked_until' => 'datetime',
        'published_at' => 'datetime',
        'attempts' => 'integer',
    ];

    public function rawSensorEvent()
    {
        return $this->belongsTo(RawSensorEvent::class);
    }
}
