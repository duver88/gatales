<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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
                    'duration_months' => $plan->duration_months ?? 1,
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
     * Create a new plan
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'tokens_monthly' => 'required|integer|min:0',
            'price' => 'required|numeric|min:0',
            'duration_months' => 'integer|in:1,3,12',
            'hotmart_product_id' => 'nullable|string',
            'features' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        // Generate slug from name
        $slug = Str::slug($validated['name']);
        $originalSlug = $slug;
        $counter = 1;

        // Ensure unique slug
        while (Plan::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        $plan = Plan::create([
            'name' => $validated['name'],
            'slug' => $slug,
            'tokens_monthly' => $validated['tokens_monthly'],
            'price' => $validated['price'],
            'duration_months' => $validated['duration_months'] ?? 1,
            'hotmart_product_id' => $validated['hotmart_product_id'] ?? null,
            'features' => $validated['features'] ?? [],
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Plan creado correctamente',
            'plan' => $plan,
        ], 201);
    }

    /**
     * Update a plan
     */
    public function update(Request $request, Plan $plan): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'tokens_monthly' => 'sometimes|integer|min:0',
            'price' => 'sometimes|numeric|min:0',
            'duration_months' => 'sometimes|integer|in:1,3,12',
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

    /**
     * Delete a plan
     */
    public function destroy(Plan $plan): JsonResponse
    {
        // Check if plan has active subscriptions
        $activeSubscriptions = $plan->subscriptions()->where('status', 'active')->count();

        if ($activeSubscriptions > 0) {
            return response()->json([
                'success' => false,
                'message' => "No se puede eliminar el plan porque tiene {$activeSubscriptions} suscripciones activas",
            ], 422);
        }

        // Don't allow deleting the free plan
        if ($plan->slug === 'free') {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar el plan gratuito',
            ], 422);
        }

        $plan->delete();

        return response()->json([
            'success' => true,
            'message' => 'Plan eliminado correctamente',
        ]);
    }
}
