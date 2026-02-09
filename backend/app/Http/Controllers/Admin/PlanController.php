<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class PlanController extends Controller
{
    /**
     * List all plans
     */
    public function index(): JsonResponse
    {
        $plans = Plan::with('assistants')
            ->withCount(['subscriptions as active_subscriptions' => function ($query) {
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
                    'hotmart_offer_code' => $plan->hotmart_offer_code,
                    'features' => $plan->features,
                    'is_active' => $plan->is_active,
                    'active_subscriptions' => $plan->active_subscriptions,
                    'assistant_ids' => $plan->assistants->pluck('id'),
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
            'hotmart_offer_code' => 'nullable|string|max:50',
            'features' => 'nullable|array',
            'is_active' => 'boolean',
            'assistant_ids' => 'nullable|array',
            'assistant_ids.*' => 'exists:assistants,id',
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
            'hotmart_offer_code' => $validated['hotmart_offer_code'] ?? null,
            'features' => $validated['features'] ?? [],
            'is_active' => $validated['is_active'] ?? true,
        ]);

        if (!empty($validated['assistant_ids'])) {
            $plan->assistants()->sync($validated['assistant_ids']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Plan creado correctamente',
            'plan' => $plan->load('assistants'),
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
            'hotmart_offer_code' => 'sometimes|nullable|string|max:50',
            'features' => 'sometimes|nullable|array',
            'is_active' => 'sometimes|boolean',
            'assistant_ids' => 'sometimes|nullable|array',
            'assistant_ids.*' => 'exists:assistants,id',
        ]);

        $assistantIds = $validated['assistant_ids'] ?? null;
        unset($validated['assistant_ids']);

        $plan->update($validated);

        if ($assistantIds !== null) {
            $plan->assistants()->sync($assistantIds);
            Cache::forget("plan_{$plan->id}_assistants");
        }

        return response()->json([
            'success' => true,
            'message' => 'Plan actualizado correctamente',
            'plan' => $plan->fresh()->load('assistants'),
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
