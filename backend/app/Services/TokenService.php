<?php

namespace App\Services;

use App\Models\TokenUsage;
use App\Models\User;

class TokenService
{
    /**
     * Check if user has enough tokens
     */
    public function hasEnoughTokens(User $user, int $minimumTokens = 100): bool
    {
        return $user->tokens_balance >= $minimumTokens;
    }

    /**
     * Deduct tokens from user and record usage
     */
    public function deductTokens(User $user, int $tokensInput, int $tokensOutput): void
    {
        $totalTokens = $tokensInput + $tokensOutput;

        // Deduct from user balance
        $user->deductTokens($totalTokens);

        // Record usage for statistics
        TokenUsage::record($user->id, $tokensInput, $tokensOutput);
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
     * Get token usage statistics for a user
     */
    public function getUserStats(User $user, int $days = 30): array
    {
        $usage = TokenUsage::where('user_id', $user->id)
            ->where('date', '>=', now()->subDays($days))
            ->selectRaw('date, SUM(tokens_input) as input, SUM(tokens_output) as output')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return $usage->map(function ($item) {
            return [
                'date' => $item->date->format('Y-m-d'),
                'tokens_input' => (int) $item->input,
                'tokens_output' => (int) $item->output,
                'total' => (int) $item->input + (int) $item->output,
            ];
        })->toArray();
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
     * OpenAI model pricing per 1M tokens (as of 2024)
     */
    private function getModelPricing(): array
    {
        return [
            'gpt-4o-mini' => ['input' => 0.15, 'output' => 0.60],
            'gpt-4o' => ['input' => 2.50, 'output' => 10.00],
            'gpt-4-turbo' => ['input' => 10.00, 'output' => 30.00],
            'gpt-4' => ['input' => 30.00, 'output' => 60.00],
            'gpt-3.5-turbo' => ['input' => 0.50, 'output' => 1.50],
        ];
    }

    /**
     * Calculate estimated cost for tokens
     */
    public function calculateCost(int $tokensInput, int $tokensOutput, string $model = 'gpt-4o-mini'): float
    {
        $pricing = $this->getModelPricing();
        $modelPricing = $pricing[$model] ?? $pricing['gpt-4o-mini'];

        $inputCost = ($tokensInput / 1000000) * $modelPricing['input'];
        $outputCost = ($tokensOutput / 1000000) * $modelPricing['output'];

        return round($inputCost + $outputCost, 4);
    }

    /**
     * Get OpenAI usage statistics with cost estimation (for admin)
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
