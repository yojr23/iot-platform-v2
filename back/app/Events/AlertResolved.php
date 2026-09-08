<?php

namespace App\Events;

use App\Events\Concerns\HasEventEnvelope;
use App\Events\Contracts\VersionedDomainEvent;
use App\Models\Alert;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * PLAN.md Stage 4.2/4.4 — the `alert.resolved` fact (audit RC2: previously never emitted, because
 * `AlertController::resolveAll()` used a mass `update()` that bypasses `AlertObserver`, and even
 * single `resolve()` never dispatched anything).
 *
 * Existing code reused: shape mirrors `App\Events\NewAlertTriggered` exactly (same envelope trait,
 * same public channel name `alerts`, same `ShouldBroadcastNow` contract) so the front-end Echo
 * wiring pattern (`useAlertsRealtime.js`) can add a listener for this class the same way it already
 * listens for `NewAlertTriggered` (Stage 7, front-owned, not part of this change).
 * Existing owner retired/delegated: n/a — no `alert.resolved` broadcast existed before this stage.
 * Compatibility window: none.
 *
 * Dispatched only from `App\Services\Ingestion\DomainEventBroadcastConsumer` (the durable
 * `browser-delivery-v1` stream consumer), never from a controller or the `AlertLifecycleService`
 * transition directly — that is what makes this "not `ShouldBroadcastNow` on the request path"
 * (PLAN.md 4.3): by the time this event is constructed, the request that triggered the resolve has
 * already returned; `ShouldBroadcastNow` here only means "broadcast without a second queue hop",
 * which is correct because the durable stream + consumer already provided the async boundary.
 */
class AlertResolved implements ShouldBroadcastNow, VersionedDomainEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels, HasEventEnvelope;

    public $alert;

    public function __construct(Alert $alert, ?string $correlationId = null, ?string $causationId = null)
    {
        $this->alert = $alert;
        $this->correlationId = $correlationId;
        $this->causationId = $causationId;
    }

    public function broadcastOn()
    {
        return new Channel('alerts');
    }

    public function broadcastWith()
    {
        $legacy = [
            'id' => $this->alert->id,
            'resolved' => true,
            'resolved_at' => $this->alert->resolved_at,
        ];

        return array_merge($legacy, $this->envelopeMetadata());
    }

    public function eventType(): string
    {
        return 'alert.resolved';
    }

    public function aggregateType(): string
    {
        return 'alert';
    }

    public function aggregateId(): int|string
    {
        return $this->alert->id;
    }
}
