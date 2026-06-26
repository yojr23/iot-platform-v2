<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreRawIngestionEventRequest;
use App\Models\RawSensorEvent;
use App\Services\Ingestion\RawSensorEventPublisher;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class IngestionController extends Controller
{
    public function __construct(private RawSensorEventPublisher $publisher)
    {
    }

    public function store(StoreRawIngestionEventRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $nodeId = data_get($validated, 'payload.device.node_id');

        $event = RawSensorEvent::create([
            'topic' => $validated['topic'] ?? null,
            'source' => $validated['source'] ?? 'ingestion_service',
            'node_id' => is_string($nodeId) && $nodeId !== '' ? $nodeId : null,
            'payload' => $validated['payload'],
            'received_at' => $validated['received_at'] ?? null,
            'status' => 'received',
        ]);

        $published = $this->publisher->publish($event);

        if (! $published) {
            Log::warning('Raw sensor event stored but publish step failed', [
                'event_id' => $event->id,
            ]);
        }

        return response()->json([
            'message' => 'Raw sensor event stored successfully',
            'event_id' => $event->id,
            'status' => $event->status,
        ], 201);
    }
}
