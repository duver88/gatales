<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmailLogController extends Controller
{
    /**
     * Get email statistics
     */
    public function stats(): JsonResponse
    {
        $stats = EmailLog::getStats(30);

        // Get recent bounces count
        $recentBounces = EmailLog::failed()
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        // Daily breakdown for last 7 days
        $dailyStats = EmailLog::where('created_at', '>=', now()->subDays(7))
            ->selectRaw('DATE(created_at) as date, status, COUNT(*) as count')
            ->groupBy('date', 'status')
            ->orderBy('date')
            ->get()
            ->groupBy('date')
            ->map(function ($items, $date) {
                $data = ['date' => $date, 'total' => 0];
                foreach ($items as $item) {
                    $data[$item->status] = $item->count;
                    $data['total'] += $item->count;
                }
                return $data;
            })
            ->values();

        return response()->json([
            'success' => true,
            'stats' => $stats,
            'recent_bounces' => $recentBounces,
            'daily' => $dailyStats,
        ]);
    }

    /**
     * List email logs with filters
     */
    public function index(Request $request): JsonResponse
    {
        $query = EmailLog::with('user:id,name,email')
            ->orderBy('created_at', 'desc');

        // Filter by status
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Filter by type
        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        // Filter by email
        if ($email = $request->input('email')) {
            $query->where('to_email', 'ilike', "%{$email}%");
        }

        // Filter by date range
        if ($from = $request->input('from')) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to = $request->input('to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        // Only bounced/failed
        if ($request->boolean('only_issues')) {
            $query->failed();
        }

        $perPage = min($request->input('per_page', 20), 100);
        $logs = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'logs' => $logs->items(),
            'pagination' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ]);
    }

    /**
     * Get bounced emails grouped by email address
     */
    public function bouncedEmails(): JsonResponse
    {
        $bounced = EmailLog::failed()
            ->selectRaw('to_email, MAX(to_name) as to_name, COUNT(*) as bounce_count, MAX(created_at) as last_bounce, MAX(bounce_type) as bounce_type, MAX(error_message) as error_message')
            ->groupBy('to_email')
            ->orderByDesc('bounce_count')
            ->limit(100)
            ->get();

        return response()->json([
            'success' => true,
            'bounced_emails' => $bounced,
        ]);
    }

    /**
     * Get email log details
     */
    public function show(EmailLog $emailLog): JsonResponse
    {
        $emailLog->load('user:id,name,email');

        return response()->json([
            'success' => true,
            'log' => $emailLog,
        ]);
    }

    /**
     * Manually log an email (for testing or manual sends)
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'to_email' => 'required|email',
            'to_name' => 'nullable|string|max:255',
            'subject' => 'required|string|max:255',
            'type' => 'nullable|string|max:50',
            'status' => 'nullable|string|in:pending,sent,delivered,bounced,failed',
            'error_message' => 'nullable|string',
        ]);

        $log = EmailLog::create([
            'to_email' => $validated['to_email'],
            'to_name' => $validated['to_name'] ?? null,
            'subject' => $validated['subject'],
            'type' => $validated['type'] ?? EmailLog::TYPE_GENERAL,
            'status' => $validated['status'] ?? EmailLog::STATUS_PENDING,
            'error_message' => $validated['error_message'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'log' => $log,
        ], 201);
    }

    /**
     * Update email log status (webhook endpoint)
     */
    public function updateStatus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message_id' => 'required|string',
            'status' => 'required|string|in:sent,delivered,bounced,failed,complained',
            'bounce_type' => 'nullable|string',
            'bounce_subtype' => 'nullable|string',
            'error_message' => 'nullable|string',
        ]);

        $log = EmailLog::where('message_id', $validated['message_id'])->first();

        if (!$log) {
            return response()->json([
                'success' => false,
                'message' => 'Email log not found',
            ], 404);
        }

        $updateData = ['status' => $validated['status']];

        switch ($validated['status']) {
            case 'sent':
                $updateData['sent_at'] = now();
                break;
            case 'delivered':
                $updateData['delivered_at'] = now();
                break;
            case 'bounced':
                $updateData['bounced_at'] = now();
                $updateData['bounce_type'] = $validated['bounce_type'] ?? null;
                $updateData['bounce_subtype'] = $validated['bounce_subtype'] ?? null;
                $updateData['error_message'] = $validated['error_message'] ?? null;
                break;
            case 'failed':
            case 'complained':
                $updateData['error_message'] = $validated['error_message'] ?? null;
                break;
        }

        $log->update($updateData);

        return response()->json([
            'success' => true,
            'log' => $log->fresh(),
        ]);
    }

    /**
     * Resend an email (mark for retry)
     */
    public function resend(EmailLog $emailLog): JsonResponse
    {
        // Create a new log entry as retry
        $newLog = EmailLog::create([
            'user_id' => $emailLog->user_id,
            'to_email' => $emailLog->to_email,
            'to_name' => $emailLog->to_name,
            'subject' => $emailLog->subject . ' (Reenvío)',
            'type' => $emailLog->type,
            'status' => EmailLog::STATUS_PENDING,
            'provider' => $emailLog->provider,
            'metadata' => ['retry_of' => $emailLog->id],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Email marcado para reenvío',
            'log' => $newLog,
        ]);
    }
}
