<?php

namespace App\Http\Controllers;

use App\Models\DashboardPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardPreferenceController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        $preferences = $user->dashboardPreference;

        return response()->json([
            'layout' => $preferences?->layout ?? [
                'main' => [
                    'device_id' => null,
                    'sensor_id' => null,
                ],
                'monitors' => [],
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'layout' => ['required', 'array'],
            'layout.main' => ['nullable', 'array'],
            'layout.main.device_id' => ['nullable', 'integer', 'exists:devices,id'],
            'layout.main.sensor_id' => ['nullable', 'integer', 'exists:sensors,id'],
            'layout.monitors' => ['nullable', 'array'],
            'layout.monitors.*.id' => ['required_with:layout.monitors', 'string'],
            'layout.monitors.*.device_id' => ['nullable', 'integer', 'exists:devices,id'],
            'layout.monitors.*.sensor_id' => ['nullable', 'integer', 'exists:sensors,id'],
        ]);

        $user = $request->user();

        $layout = $data['layout'];
        $layout['monitors'] = $layout['monitors'] ?? [];
        $layout['main'] = array_merge([
            'device_id' => null,
            'sensor_id' => null,
        ], $layout['main'] ?? []);

        /** @var DashboardPreference $preferences */
        $preferences = DashboardPreference::updateOrCreate(
            ['user_id' => $user->id],
            ['layout' => $layout]
        );

        return response()->json([
            'layout' => $preferences->layout,
        ]);
    }
}
