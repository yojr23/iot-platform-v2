<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\DomainEventOutbox;
use App\Models\User;
use App\Services\Alerts\AlertLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PLAN.md Stage 4.2 (audit RC2): single resolve AND bulk resolve must both go through one
 * transition owner (`AlertLifecycleService`) that sets resolved+resolved_at and writes exactly one
 * domain-outbox row (the durable `alert.resolved` fact) per alert — never zero, the bug this test
 * would have caught: `resolveAll()` used to `Alert::active()->update()`, a mass update that bypasses
 * `AlertObserver` and emitted nothing at all.
 */
class AlertResolveTransitionTest extends TestCase
{
    use RefreshDatabase;

    public function test_single_resolve_marks_alert_and_writes_exactly_one_outbox_row(): void
    {
        $alert = Alert::factory()->create(['resolved' => false, 'resolved_at' => null]);

        app(AlertLifecycleService::class)->resolve($alert);

        $resolvedAt = $alert->resolved_at?->toIso8601String();
        $alert->refresh();
        $this->assertTrue((bool) $alert->resolved);
        $this->assertNotNull($alert->resolved_at);

        $this->assertSame(1, DomainEventOutbox::query()
            ->where('event_type', 'alert.resolved')
            ->where('aggregate_type', 'alert')
            ->where('aggregate_id', (string) $alert->id)
            ->count());
    }

    public function test_single_resolve_outbox_captures_the_resolution_snapshot(): void
    {
        $alert = Alert::factory()->create(['resolved' => false, 'resolved_at' => null]);

        app(AlertLifecycleService::class)->resolve($alert);

        $resolvedAt = $alert->resolved_at?->toIso8601String();
        $alert->refresh();
        $outbox = DomainEventOutbox::query()
            ->where('event_type', 'alert.resolved')
            ->where('aggregate_id', (string) $alert->id)
            ->firstOrFail();

        $this->assertSame([
            'alert_id' => $alert->id,
            'resolved' => true,
            'resolved_at' => $resolvedAt,
        ], $outbox->payload);
    }

    public function test_resolve_all_emits_one_outbox_row_per_active_alert_not_zero(): void
    {
        $active = Alert::factory()->count(3)->create(['resolved' => false, 'resolved_at' => null]);
        $alreadyResolved = Alert::factory()->create(['resolved' => true, 'resolved_at' => now()]);

        $resolvedCount = app(AlertLifecycleService::class)->resolveAll();

        $this->assertSame(3, $resolvedCount);
        $this->assertSame(0, Alert::active()->count());

        // The bug this guards against: the old Alert::active()->update() mass update fired zero
        // AlertObserver/domain-outbox writes. Bulk resolution must emit N facts, not 0.
        $this->assertSame(3, DomainEventOutbox::query()->where('event_type', 'alert.resolved')->count());

        foreach ($active as $alert) {
            $this->assertSame(1, DomainEventOutbox::query()
                ->where('event_type', 'alert.resolved')
                ->where('aggregate_id', (string) $alert->id)
                ->count(), "alert {$alert->id} must have exactly one alert.resolved outbox row");
        }

        // Already-resolved alert must not get a spurious second fact.
        $this->assertSame(0, DomainEventOutbox::query()
            ->where('aggregate_id', (string) $alreadyResolved->id)
            ->count());
    }

    public function test_api_single_resolve_writes_one_outbox_row(): void
    {
        // SEC-ALERT-001: resolve is admin-only (see AlertPolicy).
        $user = User::factory()->create(['is_admin' => true]);
        $alert = Alert::factory()->create(['resolved' => false, 'resolved_at' => null]);

        $this->actingAs($user)->patchJson("/api/alerts/{$alert->id}/resolve")->assertOk();

        $this->assertSame(1, DomainEventOutbox::query()
            ->where('event_type', 'alert.resolved')
            ->where('aggregate_id', (string) $alert->id)
            ->count());
        $this->assertTrue((bool) $alert->fresh()->resolved);
    }

    public function test_api_resolve_all_emits_n_outbox_rows_for_n_active_alerts(): void
    {
        // SEC-ALERT-001: resolve-all is admin-only (see AlertPolicy).
        $user = User::factory()->create(['is_admin' => true]);
        Alert::factory()->count(4)->create(['resolved' => false, 'resolved_at' => null]);

        $this->actingAs($user)->postJson('/api/alerts/resolve-all')
            ->assertOk()
            ->assertJsonPath('resolved_count', 4);

        $this->assertSame(4, DomainEventOutbox::query()->where('event_type', 'alert.resolved')->count());
        $this->assertSame(0, Alert::active()->count());
    }
}
