<?php

namespace Tests\Feature\Api;

use App\Models\Alert;
use App\Models\Device;
use App\Models\Sensor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Task 19 — one table-driven authorization matrix over the sensitive routes ×
 * {guest, unverified, standard-verified, admin}. Makes permission drift a single
 * obvious failure. Backend is authoritative: guests/unverified never reach private
 * data, standard verified users cannot mutate, admin can.
 *
 * Actors mint real Sanctum PATs (standard = ['read'], admin = ['*']) and send them via
 * the Authorization header, because Sanctum's ability middleware inspects
 * currentAccessToken(), which actingAs() does not attach.
 */
class AuthorizationMatrixFullTest extends TestCase
{
    use RefreshDatabase;

    private function actorHeaders(string $actor): array
    {
        return match ($actor) {
            'guest' => [],
            'unverified' => $this->bearer(
                User::factory()->unverified()->create(['is_admin' => false])
                    ->createToken('t', ['read'])->plainTextToken
            ),
            'standard' => $this->bearer(
                User::factory()->create(['is_admin' => false])
                    ->createToken('t', ['read'])->plainTextToken
            ),
            'admin' => $this->bearer(
                User::factory()->create(['is_admin' => true])
                    ->createToken('t', ['*'])->plainTextToken
            ),
        };
    }

    private function bearer(string $token): array
    {
        return ['Authorization' => "Bearer {$token}", 'Accept' => 'application/json'];
    }

    /**
     * @return array<string,array{0:string,1:string,2:string,3:array<string,int>}>
     *   method, url-template, needs-resource?, expected status per actor
     */
    public static function matrix(): array
    {
        // 401 = unauthenticated, 403 = authenticated-but-forbidden (incl. unverified),
        // 2xx = allowed. Unverified is blocked by the `verified` middleware (403).
        return [
            'devices index'      => ['GET',   '/api/devices',                 ['guest' => 401, 'unverified' => 403, 'standard' => 200, 'admin' => 200]],
            'device create'      => ['POST',  '/api/devices',                 ['guest' => 401, 'unverified' => 403, 'standard' => 403, 'admin' => 422]],
            'sensors index'      => ['GET',   '/api/sensors',                 ['guest' => 401, 'unverified' => 403, 'standard' => 200, 'admin' => 200]],
            'alerts index'       => ['GET',   '/api/alerts',                  ['guest' => 401, 'unverified' => 403, 'standard' => 200, 'admin' => 200]],
            'resolve all alerts' => ['POST',  '/api/alerts/resolve-all',      ['guest' => 401, 'unverified' => 403, 'standard' => 403, 'admin' => 200]],
            'users index'        => ['GET',   '/api/users',                   ['guest' => 401, 'unverified' => 403, 'standard' => 403, 'admin' => 200]],
            'email config'       => ['GET',   '/api/config/email',            ['guest' => 401, 'unverified' => 403, 'standard' => 403, 'admin' => 200]],
            'dashboard metrics'  => ['GET',   '/api/dashboard/metrics',       ['guest' => 401, 'unverified' => 403, 'standard' => 200, 'admin' => 200]],
        ];
    }

    /** @dataProvider matrix */
    public function test_route_authorization(string $method, string $url, array $expected): void
    {
        // Seed the resources the read routes list, so a 200 is a real allow.
        $device = Device::factory()->create();
        Sensor::factory()->create(['device_id' => $device->id]);
        Alert::factory()->create(['resolved' => false, 'resolved_at' => null]);

        foreach ($expected as $actor => $status) {
            // Sanctum's guard memoizes the resolved user in the shared test container;
            // without this each subsequent actor in the loop reuses the first one's auth.
            $this->app['auth']->forgetGuards();

            $response = $this->withHeaders($this->actorHeaders($actor))
                ->json($method, $url);

            $this->assertSame(
                $status,
                $response->getStatusCode(),
                "Route [{$method} {$url}] for actor [{$actor}] expected {$status}, got {$response->getStatusCode()}."
            );
        }
    }
}
