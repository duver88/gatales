<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlanController extends Controller
{
    /**
     * List all plans
     */
    public function index(): JsonResponse
    {
        $plans = Plan::withCount(['subscriptions as active_subscriptions' => function ($query) {
            $query->where('status', 'active');
        }])
            ->orderBy('price')
            ->get()
            ->map(function ($plan) {
                return [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'slug' => $plan->slug,
                    'tokens_monthly' => $plan->tokens_monthly,
                    'price' => $plan->price,
                    'hotmart_product_id' => $plan->hotmart_product_id,
                    'features' => $plan->features,
                    'is_active' => $plan->is_active,
                    'active_subscriptions' => $plan->active_subscriptions,
                    'created_at' => $plan->created_at->toIso8601String(),
                ];
            });

        return response()->json([
            'success' => true,
            'plans' => $plans,
        ]);
    }

    /**
     * Update a plan
     */
    public function update(Request $request, Plan $plan): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'tokens_monthly' => 'sometimes|integer|min:1000',
            'price' => 'sometimes|numeric|min:0',
            'hotmart_product_id' => 'sometimes|nullable|string',
            'features' => 'sometimes|nullable|array',
            'is_active' => 'sometimes|boolean',
        ]);

        $plan->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Plan actualizado correctamente',
            'plan' => $plan->fresh(),
        ]);
    }
}
