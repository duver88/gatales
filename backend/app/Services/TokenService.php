<?php

namespace App\Services;

use App\Models\AdminTokenUsage;
use App\Models\TokenUsage;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class TokenService
{
    // Cache duration for dashboard stats (5 minutes)
    private const DASHBOARD_CACHE_TTL = 300;
    /**
     * Check if user has enough tokens
     */
    public function hasEnoughTokens(User $user, int $minimumTokens = 100): bool
    {
        return $user->tokens_balance >= $minimumTokens;
    }

    /**
     * Deduct tokens from user and record usage (atomic transaction)
     */
    public function deductTokens(User $user, int $tokensInput, int $tokensOutput, string $provider = 'openai'): void
    {
        $totalTokens = $tokensInput + $tokensOutput;

        // Wrap in transaction to ensure both operations succeed or fail together
        DB::transaction(function () use ($user, $totalTokens, $tokensInput, $tokensOutput, $provider) {
            // Deduct from user balance
            $user->deductTokens($totalTokens);

            // Record usage for statistics with provider
            TokenUsage::record($user->id, $tokensInput, $tokensOutput, $provider);
        });
    }

    /**
     * Record admin token usage (for testing)
     */
    public function recordAdminUsage(int $adminId, int $tokensInput, int $tokensOutput, string $provider = 'openai', ?int $assistantId = null): void
    {
        AdminTokenUsage::record($adminId, $tokensInput, $tokensOutput, $provider, $assistantId);
    }

    /**
     * Get user's remaining tokens
     */
    public function getRemainingTokens(User $user): int
    {
        return $user->tokens_balance;
    }

    /**
     * Get user's token usage for the current month
     */
    public function getMonthlyUsage(User $user): int
    {
        return $user->tokens_used_month;
    }

    /**
     * Add tokens to user (admin function)
     */
    public function addTokens(User $user, int $amount): void
    {
        $user->tokens_balance += $amount;
        $user->save();
    }

    /**
     * Get token usage statistics for a user - separated by provider
     */
    public function getUserStats(User $user, int $days = 30): array
    {
        // Get daily usage grouped by date and provider
        $usage = TokenUsage::where('user_id', $user->id)
            ->where('date', '>=', now()->subDays($days))
            ->selectRaw('date, provider, SUM(tokens_input) as input, SUM(tokens_output) as output')
            ->groupBy('date', 'provider')
            ->orderBy('date')
            ->get();

        // Initialize provider totals for period
        $providerTotals = [
            'openai' => ['input' => 0, 'output' => 0, 'total' => 0, 'cost' => 0],
            'deepseek' => ['input' => 0, 'output' => 0, 'total' => 0, 'cost' => 0],
        ];

        // Group daily data by date with provider breakdown
        $dailyByDate = [];
        foreach ($usage as $item) {
            $date = $item->date->format('Y-m-d');
            $provider = $item->provider ?? 'openai';
            $input = (int) $item->input;
            $output = (int) $item->output;
            $total = $input + $output;
            $cost = $this->calculateCost($input, $output, null, $provider);

            if (!isset($dailyByDate[$date])) {
                $dailyByDate[$date] = [
                    'date' => $date,
                    'openai' => ['input' => 0, 'output' => 0, 'total' => 0, 'cost' => 0],
                    'deepseek' => ['input' => 0, 'output' => 0, 'total' => 0, 'cost' => 0],
                    'total' => 0,
                    'total_cost' => 0,
                ];
            }

            $dailyByDate[$date][$provider] = [
                'input' => $input,
                'output' => $output,
                'total' => $total,
                'cost' => $cost,
            ];
            $dailyByDate[$date]['total'] += $total;
            $dailyByDate[$date]['total_cost'] += $cost;

            // Accumulate provider totals
            $providerTotals[$provider]['input'] += $input;
            $providerTotals[$provider]['output'] += $output;
            $providerTotals[$provider]['total'] += $total;
            $providerTotals[$provider]['cost'] += $cost;
        }

        // Round costs
        foreach ($providerTotals as $provider => $data) {
            $providerTotals[$provider]['cost'] = round($data['cost'], 4);
        }
        foreach ($dailyByDate as $date => $data) {
            $dailyByDate[$date]['total_cost'] = round($data['total_cost'], 4);
        }

        // All-time totals by provider for this user
        $allTimeByProvider = TokenUsage::where('user_id', $user->id)
            ->selectRaw('provider, COALESCE(SUM(tokens_input), 0) as input, COALESCE(SUM(tokens_output), 0) as output')
            ->groupBy('provider')
            ->get()
            ->keyBy('provider');

        $allTimeTotals = [
            'openai' => ['input' => 0, 'output' => 0, 'total' => 0, 'cost' => 0],
            'deepseek' => ['input' => 0, 'output' => 0, 'total' => 0, 'cost' => 0],
        ];
        $allTimeTotal = 0;
        $allTimeTotalCost = 0;

        foreach (['openai', 'deepseek'] as $provider) {
            $data = $allTimeByProvider->get($provider);
            $input = (int) ($data->input ?? 0);
            $output = (int) ($data->output ?? 0);
            $total = $input + $output;
            $cost = $this->calculateCost($input, $output, null, $provider);

            $allTimeTotals[$provider] = [
                'input' => $input,
                'output' => $output,
                'total' => $total,
                'cost' => round($cost, 4),
            ];
            $allTimeTotal += $total;
            $allTimeTotalCost += $cost;
        }

        return [
            'daily' => array_values($dailyByDate),
            'period_totals' => [
                'openai' => $providerTotals['openai'],
                'deepseek' => $providerTotals['deepseek'],
                'total' => $providerTotals['openai']['total'] + $providerTotals['deepseek']['total'],
                'total_cost' => round($providerTotals['openai']['cost'] + $providerTotals['deepseek']['cost'], 4),
            ],
            'all_time_totals' => [
                'openai' => $allTimeTotals['openai'],
                'deepseek' => $allTimeTotals['deepseek'],
                'total' => $allTimeTotal,
                'total_cost' => round($allTimeTotalCost, 4),
            ],
        ];
    }

    /**
     * Get global token usage statistics (for admin)
     */
    public function getGlobalStats(int $days = 7): array
    {
        $usage = TokenUsage::where('date', '>=', now()->subDays($days))
            ->selectRaw('date, SUM(tokens_input) as input, SUM(tokens_output) as output, COUNT(DISTINCT user_id) as users')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return $usage->map(function ($item) {
            return [
                'date' => $item->date->format('Y-m-d'),
                'tokens_input' => (int) $item->input,
                'tokens_output' => (int) $item->output,
                'total' => (int) $item->input + (int) $item->output,
                'active_users' => (int) $item->users,
            ];
        })->toArray();
    }

    /**
     * Get today's total token consumption (for admin)
     */
    public function getTodayTotalConsumption(): int
    {
        return TokenUsage::where('date', now()->toDateString())
            ->selectRaw('COALESCE(SUM(tokens_input + tokens_output), 0) as total')
            ->value('total') ?? 0;
    }

    /**
     * Get this month's total token consumption (for admin)
     */
    public function getMonthTotalConsumption(): int
    {
        return TokenUsage::whereMonth('date', now()->month)
            ->whereYear('date', now()->year)
            ->selectRaw('COALESCE(SUM(tokens_input + tokens_output), 0) as total')
            ->value('total') ?? 0;
    }

    /**
     * OpenAI model pricing per 1M tokens
     */
    private function getOpenAIPricing(): array
    {
        return [
            'gpt-5.2' => ['input' => 5.00, 'output' => 15.00],
            'gpt-5.2-mini' => ['input' => 0.30, 'output' => 1.20],
            'gpt-5.2-codex' => ['input' => 3.00, 'output' => 12.00],
            'gpt-5.1' => ['input' => 4.00, 'output' => 12.00],
            'gpt-5.1-mini' => ['input' => 0.25, 'output' => 1.00],
            'gpt-5.1-codex' => ['input' => 2.50, 'output' => 10.00],
            'gpt-5' => ['input' => 3.00, 'output' => 10.00],
            'gpt-5-mini' => ['input' => 0.20, 'output' => 0.80],
            'gpt-4o-mini' => ['input' => 0.15, 'output' => 0.60],
            'gpt-4o' => ['input' => 2.50, 'output' => 10.00],
            'gpt-4-turbo' => ['input' => 10.00, 'output' => 30.00],
            'gpt-4' => ['input' => 30.00, 'output' => 60.00],
            'gpt-3.5-turbo' => ['input' => 0.50, 'output' => 1.50],
            'o1' => ['input' => 15.00, 'output' => 60.00],
            'o1-mini' => ['input' => 3.00, 'output' => 12.00],
        ];
    }

    /**
     * DeepSeek model pricing per 1M tokens
     */
    private function getDeepSeekPricing(): array
    {
        return [
            'deepseek-chat' => ['input' => 0.14, 'output' => 0.28],
            'deepseek-reasoner' => ['input' => 0.55, 'output' => 2.19],
        ];
    }

    /**
     * Get default pricing for a provider
     */
    private function getDefaultPricing(string $provider): array
    {
        if ($provider === 'deepseek') {
            return ['input' => 0.14, 'output' => 0.28]; // deepseek-chat default
        }
        return ['input' => 0.15, 'output' => 0.60]; // gpt-4o-mini default
    }

    /**
     * Calculate estimated cost for tokens
     */
    public function calculateCost(int $tokensInput, int $tokensOutput, ?string $model = null, string $provider = 'openai'): float
    {
        if ($provider === 'deepseek') {
            $pricing = $this->getDeepSeekPricing();
            $modelPricing = $pricing[$model ?? 'deepseek-chat'] ?? $this->getDefaultPricing('deepseek');
        } else {
            $pricing = $this->getOpenAIPricing();
            $modelPricing = $pricing[$model ?? 'gpt-4o-mini'] ?? $this->getDefaultPricing('openai');
        }

        $inputCost = ($tokensInput / 1000000) * $modelPricing['input'];
        $outputCost = ($tokensOutput / 1000000) * $modelPricing['output'];

        return round($inputCost + $outputCost, 4);
    }

    /**
     * Get usage statistics by provider (for admin dashboard)
     * Cached for 5 minutes to reduce DB load with 2000+ users
     */
    public function getUsageByProvider(): array
    {
        return Cache::remember('dashboard_usage_by_provider', self::DASHBOARD_CACHE_TTL, function () {
            return $this->fetchUsageByProvider();
        });
    }

    /**
     * Fetch usage by provider from database (internal)
     */
    private function fetchUsageByProvider(): array
    {
        // Today's usage by provider
        $todayByProvider = TokenUsage::where('date', now()->toDateString())
            ->selectRaw('provider, SUM(tokens_input) as input, SUM(tokens_output) as output')
            ->groupBy('provider')
            ->get()
            ->keyBy('provider');

        // This month's usage by provider
        $monthByProvider = TokenUsage::whereMonth('date', now()->month)
            ->whereYear('date', now()->year)
            ->selectRaw('provider, SUM(tokens_input) as input, SUM(tokens_output) as output')
            ->groupBy('provider')
            ->get()
            ->keyBy('provider');

        // All time by provider
        $allTimeByProvider = TokenUsage::selectRaw('provider, SUM(tokens_input) as input, SUM(tokens_output) as output')
            ->groupBy('provider')
            ->get()
            ->keyBy('provider');

        $providers = ['openai', 'deepseek'];
        $result = [];

        foreach ($providers as $provider) {
            $todayData = $todayByProvider->get($provider);
            $monthData = $monthByProvider->get($provider);
            $allTimeData = $allTimeByProvider->get($provider);

            $todayInput = (int) ($todayData->input ?? 0);
            $todayOutput = (int) ($todayData->output ?? 0);
            $monthInput = (int) ($monthData->input ?? 0);
            $monthOutput = (int) ($monthData->output ?? 0);
            $allTimeInput = (int) ($allTimeData->input ?? 0);
            $allTimeOutput = (int) ($allTimeData->output ?? 0);

            $result[$provider] = [
                'today' => [
                    'tokens_input' => $todayInput,
                    'tokens_output' => $todayOutput,
                    'total' => $todayInput + $todayOutput,
                    'estimated_cost' => $this->calculateCost($todayInput, $todayOutput, null, $provider),
                ],
                'month' => [
                    'tokens_input' => $monthInput,
                    'tokens_output' => $monthOutput,
                    'total' => $monthInput + $monthOutput,
                    'estimated_cost' => $this->calculateCost($monthInput, $monthOutput, null, $provider),
                ],
                'all_time' => [
                    'tokens_input' => $allTimeInput,
                    'tokens_output' => $allTimeOutput,
                    'total' => $allTimeInput + $allTimeOutput,
                    'estimated_cost' => $this->calculateCost($allTimeInput, $allTimeOutput, null, $provider),
                ],
            ];
        }

        return $result;
    }

    /**
     * Get admin usage statistics (for admin dashboard)
     * Cached for 5 minutes to reduce DB load
     */
    public function getAdminUsageStats(): array
    {
        return Cache::remember('dashboard_admin_usage_stats', self::DASHBOARD_CACHE_TTL, function () {
            return $this->fetchAdminUsageStats();
        });
    }

    /**
     * Fetch admin usage stats from database (internal)
     */
    private function fetchAdminUsageStats(): array
    {
        // Today's admin usage
        $todayUsage = AdminTokenUsage::where('date', now()->toDateString())
            ->selectRaw('provider, SUM(tokens_input) as input, SUM(tokens_output) as output')
            ->groupBy('provider')
            ->get()
            ->keyBy('provider');

        // This month's admin usage
        $monthUsage = AdminTokenUsage::whereMonth('date', now()->month)
            ->whereYear('date', now()->year)
            ->selectRaw('provider, SUM(tokens_input) as input, SUM(tokens_output) as output')
            ->groupBy('provider')
            ->get()
            ->keyBy('provider');

        // All time admin usage
        $allTimeUsage = AdminTokenUsage::selectRaw('provider, SUM(tokens_input) as input, SUM(tokens_output) as output')
            ->groupBy('provider')
            ->get()
            ->keyBy('provider');

        $providers = ['openai', 'deepseek'];
        $result = [];

        foreach ($providers as $provider) {
            $todayData = $todayUsage->get($provider);
            $monthData = $monthUsage->get($provider);
            $allTimeData = $allTimeUsage->get($provider);

            $todayInput = (int) ($todayData->input ?? 0);
            $todayOutput = (int) ($todayData->output ?? 0);
            $monthInput = (int) ($monthData->input ?? 0);
            $monthOutput = (int) ($monthData->output ?? 0);
            $allTimeInput = (int) ($allTimeData->input ?? 0);
            $allTimeOutput = (int) ($allTimeData->output ?? 0);

            $result[$provider] = [
                'today' => [
                    'tokens_input' => $todayInput,
                    'tokens_output' => $todayOutput,
                    'total' => $todayInput + $todayOutput,
                    'estimated_cost' => $this->calculateCost($todayInput, $todayOutput, null, $provider),
                ],
                'month' => [
                    'tokens_input' => $monthInput,
                    'tokens_output' => $monthOutput,
                    'total' => $monthInput + $monthOutput,
                    'estimated_cost' => $this->calculateCost($monthInput, $monthOutput, null, $provider),
                ],
                'all_time' => [
                    'tokens_input' => $allTimeInput,
                    'tokens_output' => $allTimeOutput,
                    'total' => $allTimeInput + $allTimeOutput,
                    'estimated_cost' => $this->calculateCost($allTimeInput, $allTimeOutput, null, $provider),
                ],
            ];
        }

        // Calculate totals
        $totalToday = 0;
        $totalMonth = 0;
        $totalAllTime = 0;
        $totalCostToday = 0;
        $totalCostMonth = 0;
        $totalCostAllTime = 0;

        foreach ($result as $providerData) {
            $totalToday += $providerData['today']['total'];
            $totalMonth += $providerData['month']['total'];
            $totalAllTime += $providerData['all_time']['total'];
            $totalCostToday += $providerData['today']['estimated_cost'];
            $totalCostMonth += $providerData['month']['estimated_cost'];
            $totalCostAllTime += $providerData['all_time']['estimated_cost'];
        }

        $result['totals'] = [
            'today' => ['total' => $totalToday, 'estimated_cost' => round($totalCostToday, 4)],
            'month' => ['total' => $totalMonth, 'estimated_cost' => round($totalCostMonth, 4)],
            'all_time' => ['total' => $totalAllTime, 'estimated_cost' => round($totalCostAllTime, 4)],
        ];

        return $result;
    }

    /**
     * Get top users by token consumption
     */
    public function getTopUsersByUsage(int $limit = 10, ?string $provider = null): array
    {
        $query = TokenUsage::whereMonth('date', now()->month)
            ->whereYear('date', now()->year)
            ->selectRaw('user_id, SUM(tokens_input) as input, SUM(tokens_output) as output, SUM(tokens_input + tokens_output) as total');

        if ($provider) {
            $query->where('provider', $provider);
        }

        return $query->groupBy('user_id')
            ->orderByDesc('total')
            ->limit($limit)
            ->with('user:id,name,email')
            ->get()
            ->map(function ($item) {
                return [
                    'user_id' => $item->user_id,
                    'name' => $item->user->name ?? 'Usuario eliminado',
                    'email' => $item->user->email ?? '-',
                    'tokens_input' => (int) $item->input,
                    'tokens_output' => (int) $item->output,
                    'total' => (int) $item->total,
                ];
            })
            ->toArray();
    }

    /**
     * Get usage breakdown by user (for admin dashboard)
     * Optimized for large user bases (2000+ users)
     * Cached for 5 minutes
     */
    public function getUsersUsageBreakdown(): array
    {
        return Cache::remember('dashboard_users_breakdown', self::DASHBOARD_CACHE_TTL, function () {
            return $this->fetchUsersUsageBreakdown();
        });
    }

    /**
     * Fetch users usage breakdown from database (internal)
     */
    private function fetchUsersUsageBreakdown(): array
    {
        // Step 1: Get top 20 user IDs by total consumption (efficient - single aggregation)
        $topUserIds = TokenUsage::whereMonth('date', now()->month)
            ->whereYear('date', now()->year)
            ->selectRaw('user_id, SUM(tokens_input + tokens_output) as total')
            ->groupBy('user_id')
            ->orderByDesc('total')
            ->limit(20)
            ->pluck('user_id')
            ->toArray();

        if (empty($topUserIds)) {
            return [];
        }

        // Step 2: Get detailed breakdown ONLY for top 20 users (much less data)
        $usage = TokenUsage::whereMonth('date', now()->month)
            ->whereYear('date', now()->year)
            ->whereIn('user_id', $topUserIds)
            ->selectRaw('user_id, provider, SUM(tokens_input) as input, SUM(tokens_output) as output')
            ->groupBy('user_id', 'provider')
            ->get();

        // Step 3: Get user info for top 20 only
        $users = User::whereIn('id', $topUserIds)->get()->keyBy('id');

        // Build stats array
        $userStats = [];
        foreach ($topUserIds as $userId) {
            $userStats[$userId] = [
                'openai' => ['input' => 0, 'output' => 0, 'total' => 0, 'cost' => 0],
                'deepseek' => ['input' => 0, 'output' => 0, 'total' => 0, 'cost' => 0],
                'total' => 0,
                'total_cost' => 0,
            ];
        }

        foreach ($usage as $item) {
            $userId = $item->user_id;
            $input = (int) $item->input;
            $output = (int) $item->output;
            $total = $input + $output;
            $cost = $this->calculateCost($input, $output, null, $item->provider);

            $userStats[$userId][$item->provider] = [
                'input' => $input,
                'output' => $output,
                'total' => $total,
                'cost' => $cost,
            ];
            $userStats[$userId]['total'] += $total;
            $userStats[$userId]['total_cost'] += $cost;
        }

        // Build result maintaining order from SQL
        $result = [];
        foreach ($topUserIds as $userId) {
            $user = $users->get($userId);
            $stats = $userStats[$userId];
            $result[] = [
                'user_id' => $userId,
                'name' => $user->name ?? 'Usuario eliminado',
                'email' => $user->email ?? '-',
                'openai' => $stats['openai'],
                'deepseek' => $stats['deepseek'],
                'total' => $stats['total'],
                'total_cost' => round($stats['total_cost'], 4),
            ];
        }

        return $result;
    }

    /**
     * Get OpenAI usage statistics with cost estimation (for admin) - LEGACY
     */
    public function getOpenAIUsageStats(): array
    {
        // Get today's usage
        $todayUsage = TokenUsage::where('date', now()->toDateString())
            ->selectRaw('COALESCE(SUM(tokens_input), 0) as input, COALESCE(SUM(tokens_output), 0) as output')
            ->first();

        // Get this month's usage
        $monthUsage = TokenUsage::whereMonth('date', now()->month)
            ->whereYear('date', now()->year)
            ->selectRaw('COALESCE(SUM(tokens_input), 0) as input, COALESCE(SUM(tokens_output), 0) as output')
            ->first();

        // Get all time usage
        $allTimeUsage = TokenUsage::selectRaw('COALESCE(SUM(tokens_input), 0) as input, COALESCE(SUM(tokens_output), 0) as output')
            ->first();

        // Get last 30 days with costs
        $dailyStats = TokenUsage::where('date', '>=', now()->subDays(30))
            ->selectRaw('date, SUM(tokens_input) as input, SUM(tokens_output) as output')
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->get()
            ->map(function ($item) {
                $input = (int) $item->input;
                $output = (int) $item->output;
                return [
                    'date' => $item->date->format('Y-m-d'),
                    'tokens_input' => $input,
                    'tokens_output' => $output,
                    'total' => $input + $output,
                    'estimated_cost' => $this->calculateCost($input, $output),
                ];
            });

        $todayInput = (int) ($todayUsage->input ?? 0);
        $todayOutput = (int) ($todayUsage->output ?? 0);
        $monthInput = (int) ($monthUsage->input ?? 0);
        $monthOutput = (int) ($monthUsage->output ?? 0);
        $allTimeInput = (int) ($allTimeUsage->input ?? 0);
        $allTimeOutput = (int) ($allTimeUsage->output ?? 0);

        return [
            'today' => [
                'tokens_input' => $todayInput,
                'tokens_output' => $todayOutput,
                'total' => $todayInput + $todayOutput,
                'estimated_cost' => $this->calculateCost($todayInput, $todayOutput),
            ],
            'month' => [
                'tokens_input' => $monthInput,
                'tokens_output' => $monthOutput,
                'total' => $monthInput + $monthOutput,
                'estimated_cost' => $this->calculateCost($monthInput, $monthOutput),
            ],
            'all_time' => [
                'tokens_input' => $allTimeInput,
                'tokens_output' => $allTimeOutput,
                'total' => $allTimeInput + $allTimeOutput,
                'estimated_cost' => $this->calculateCost($allTimeInput, $allTimeOutput),
            ],
            'daily_breakdown' => $dailyStats,
            'pricing_info' => [
                'model' => 'gpt-4o-mini',
                'input_per_1m' => 0.15,
                'output_per_1m' => 0.60,
            ],
        ];
    }
}
