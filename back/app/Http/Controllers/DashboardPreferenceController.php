<?php

namespace App\Http\Controllers;

use App\Models\DashboardPreference;
use App\Models\Sensor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class DashboardPreferenceController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $startTime = microtime(true);

        $user = $request->user();

        $preferences = $user->dashboardPreference;

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('DashboardPreference show request', [
            'ip' => $request->ip(),
            'method' => $request->method(),
            'path' => $request->path(),
            'request_id' => $request->header('X-Request-Id', uniqid()),
            'user_id' => auth()->id(),
            'duration_ms' => $durationMs,
        ]);

        return response()->json([
            'layout' => $preferences?->layout ?? [
                'main' => [
                    'id' => 'main',
                    'device_id' => null,
                    'sensor_id' => null,
                    'range' => '5m',
                ],
                'monitors' => [],
                'selected_id' => null,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $startTime = microtime(true);

        $data = $request->validate([
            'layout' => ['required', 'array'],
            'layout.main' => ['nullable', 'array'],
            'layout.main.id' => ['nullable', 'string'],
            'layout.main.device_id' => ['nullable', 'integer', 'exists:devices,id'],
            'layout.main.sensor_id' => ['nullable', 'integer', 'exists:sensors,id'],
            'layout.main.range' => ['nullable', 'in:1m,5m,1h,6h,24h'],
            'layout.monitors' => ['nullable', 'array'],
            'layout.monitors.*.id' => ['required_with:layout.monitors', 'string'],
            'layout.monitors.*.device_id' => ['nullable', 'integer', 'exists:devices,id'],
            'layout.monitors.*.sensor_id' => ['nullable', 'integer', 'exists:sensors,id'],
            'layout.monitors.*.range' => ['nullable', 'in:1m,5m,1h,6h,24h'],
            'layout.selected_id' => ['nullable', 'string'],
        ]);

        $this->validateSensorOwnership($data['layout']);

        $user = $request->user();

        $layout = $data['layout'];
        $layout['monitors'] = $layout['monitors'] ?? [];
        $layout['main'] = array_merge([
            'id' => 'main',
            'device_id' => null,
            'sensor_id' => null,
            'range' => '5m',
        ], $layout['main'] ?? []);
        $layout['monitors'] = array_map(static function (array $monitor): array {
            return array_merge(['range' => '5m'], $monitor);
        }, $layout['monitors']);
        $layout['selected_id'] = $layout['selected_id'] ?? null;

        /** @var DashboardPreference $preferences */
        $preferences = DashboardPreference::updateOrCreate(
            ['user_id' => $user->id],
            ['layout' => $layout]
        );

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('DashboardPreference store success', [
            'ip' => $request->ip(),
            'method' => $request->method(),
            'path' => $request->path(),
            'request_id' => $request->header('X-Request-Id', uniqid()),
            'user_id' => auth()->id(),
            'payload_keys' => array_keys($data),
            'success' => true,
            'duration_ms' => $durationMs,
        ]);

        return response()->json([
            'layout' => $preferences->layout,
        ]);
    }

    /**
     * A widget is a device/sensor pair, not two unrelated foreign keys. Keeping this check at
     * the write boundary prevents persisted layouts that the graph catalog cannot render.
     *
     * @param array<string, mixed> $layout
     */
    private function validateSensorOwnership(array $layout): void
    {
        $widgets = array_merge(
            [['key' => 'layout.main', 'widget' => $layout['main'] ?? null]],
            array_map(
                fn (mixed $widget, int $index): array => [
                    'key' => "layout.monitors.{$index}",
                    'widget' => $widget,
                ],
                $layout['monitors'] ?? [],
                array_keys($layout['monitors'] ?? []),
            ),
        );

        foreach ($widgets as $entry) {
            $widget = $entry['widget'];
            if (! is_array($widget) || empty($widget['device_id']) || empty($widget['sensor_id'])) {
                continue;
            }

            $sensor = Sensor::query()->find($widget['sensor_id']);
            if ($sensor !== null && $sensor->device_id !== (int) $widget['device_id']) {
                throw ValidationException::withMessages([
                    "{$entry['key']}.sensor_id" => 'El sensor seleccionado no pertenece al dispositivo especificado.',
                ]);
            }
        }
    }
}
