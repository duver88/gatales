<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WebhookLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookLogController extends Controller
{
    /**
     * List webhook logs with pagination
     */
    public function index(Request $request): JsonResponse
    {
        $query = WebhookLog::query();

        // Filter by source
        if ($source = $request->input('source')) {
            $query->where('source', $source);
        }

        // Filter by event type
        if ($eventType = $request->input('event_type')) {
            $query->where('event_type', $eventType);
        }

        // Filter by processed status
        if ($request->has('processed')) {
            $query->where('processed', $request->boolean('processed'));
        }

        // Order by created_at desc
        $query->orderBy('created_at', 'desc');

        // Paginate
        $perPage = min($request->input('per_page', 20), 100);
        $logs = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'logs' => collect($logs->items())->map(function ($log) {
                return [
                    'id' => $log->id,
                    'source' => $log->source,
                    'event_type' => $log->event_type,
                    'processed' => $log->processed,
                    'error' => $log->error,
                    'created_at' => $log->created_at->toIso8601String(),
                    // Don't include full payload in list view
                ];
            }),
            'pagination' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ]);
    }

    /**
     * Get webhook log details
     */
    public function show(WebhookLog $webhookLog): JsonResponse
    {
        return response()->json([
            'success' => true,
            'log' => [
                'id' => $webhookLog->id,
                'source' => $webhookLog->source,
                'event_type' => $webhookLog->event_type,
                'payload' => $webhookLog->payload,
                'processed' => $webhookLog->processed,
                'error' => $webhookLog->error,
                'created_at' => $webhookLog->created_at->toIso8601String(),
            ],
        ]);
    }
}
