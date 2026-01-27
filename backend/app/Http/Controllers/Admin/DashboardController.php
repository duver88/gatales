<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TokenService;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __construct(
        private TokenService $tokenService
    ) {}

    /**
     * Get dashboard statistics
     */
    public function index(): JsonResponse
    {
        // Total users
        $totalUsers = User::count();

        // Active users today (users who sent at least one message today)
        $activeUsersToday = User::whereHas('messages', function ($query) {
            $query->whereDate('created_at', today());
        })->count();

        // Tokens consumed today
        $tokensToday = $this->tokenService->getTodayTotalConsumption();

        // Tokens consumed this month
        $tokensMonth = $this->tokenService->getMonthTotalConsumption();

        // Token usage chart (last 7 days)
        $tokenUsageChart = $this->tokenService->getGlobalStats(7);

        // Recent users (last 10)
        $recentUsers = User::with('activeSubscription.plan')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'status' => $user->status,
                    'plan' => $user->activeSubscription?->plan?->name ?? 'Sin plan',
                    'tokens_balance' => $user->tokens_balance,
                    'created_at' => $user->created_at->toIso8601String(),
                ];
            });

        // Users by status
        $usersByStatus = [
            'active' => User::where('status', 'active')->count(),
            'pending' => User::where('status', 'pending')->count(),
            'inactive' => User::where('status', 'inactive')->count(),
            'suspended' => User::where('status', 'suspended')->count(),
        ];

        return response()->json([
            'success' => true,
            'stats' => [
                'total_users' => $totalUsers,
                'active_users_today' => $activeUsersToday,
                'tokens_consumed_today' => $tokensToday,
                'tokens_consumed_month' => $tokensMonth,
                'users_by_status' => $usersByStatus,
            ],
            'token_usage_chart' => $tokenUsageChart,
            'recent_users' => $recentUsers,
        ]);
    }

    /**
     * Get detailed token statistics
     */
    public function tokenStats(): JsonResponse
    {
        // Last 30 days of token usage
        $dailyStats = $this->tokenService->getGlobalStats(30);

        // Top users by token consumption this month
        $topUsers = User::withSum(['tokenUsage as total_tokens' => function ($query) {
            $query->whereMonth('date', now()->month)
                ->whereYear('date', now()->year)
                ->selectRaw('tokens_input + tokens_output');
        }], 'tokens_input')
            ->orderByDesc('total_tokens')
            ->limit(10)
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'tokens_used' => (int) $user->total_tokens ?? 0,
                ];
            });

        return response()->json([
            'success' => true,
            'daily_stats' => $dailyStats,
            'top_users' => $topUsers,
            'totals' => [
                'today' => $this->tokenService->getTodayTotalConsumption(),
                'month' => $this->tokenService->getMonthTotalConsumption(),
            ],
        ]);
    }

    /**
     * Get OpenAI usage statistics with cost estimation
     */
    public function openaiStats(): JsonResponse
    {
        $stats = $this->tokenService->getOpenAIUsageStats();

        return response()->json([
            'success' => true,
            'openai_usage' => $stats,
        ]);
    }
}
