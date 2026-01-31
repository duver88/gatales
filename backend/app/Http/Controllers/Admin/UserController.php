<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Assistant;
use App\Models\Message;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\TokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function __construct(
        private TokenService $tokenService
    ) {}

    /**
     * Create a new user manually
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'plan_id' => 'nullable|exists:plans,id',
            'tokens_balance' => 'nullable|integer|min:0',
            'status' => 'nullable|in:active,inactive,pending',
        ]);

        // Create user
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'status' => $validated['status'] ?? 'active',
            'tokens_balance' => $validated['tokens_balance'] ?? 0,
            'email_verified_at' => now(),
        ]);

        // Assign plan if provided
        if (!empty($validated['plan_id'])) {
            $plan = Plan::find($validated['plan_id']);

            Subscription::create([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'status' => 'active',
                'starts_at' => now(),
                'ends_at' => now()->addMonth(),
            ]);

            // Set tokens from plan if not explicitly provided
            if (!isset($validated['tokens_balance'])) {
                $user->update(['tokens_balance' => $plan->tokens_monthly]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Usuario creado correctamente',
            'user' => $user->fresh()->load('activeSubscription.plan'),
        ], 201);
    }

    /**
     * List all users with pagination and filters
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::with('activeSubscription.plan');

        // Search by email or name
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('email', 'ilike', "%{$search}%")
                    ->orWhere('name', 'ilike', "%{$search}%");
            });
        }

        // Filter by status
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Filter by plan
        if ($planId = $request->input('plan_id')) {
            $query->whereHas('activeSubscription', function ($q) use ($planId) {
                $q->where('plan_id', $planId);
            });
        }

        // Order by
        $orderBy = $request->input('order_by', 'created_at');
        $orderDir = $request->input('order_dir', 'desc');
        $query->orderBy($orderBy, $orderDir);

        // Paginate
        $perPage = min($request->input('per_page', 15), 100);
        $users = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'users' => $users->items(),
            'pagination' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ]);
    }

    /**
     * Get user details
     */
    public function show(User $user): JsonResponse
    {
        $user->load(['activeSubscription.plan', 'subscriptions.plan', 'assistant']);

        // Get recent messages (last 50)
        $recentMessages = Message::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get()
            ->reverse()
            ->values()
            ->map(function ($message) {
                return [
                    'id' => $message->id,
                    'role' => $message->role,
                    'content' => $message->content,
                    'tokens_used' => $message->tokens_input + $message->tokens_output,
                    'created_at' => $message->created_at->toIso8601String(),
                ];
            });

        // Get token usage stats
        $tokenStats = $this->tokenService->getUserStats($user, 30);

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'status' => $user->status,
                'tokens_balance' => $user->tokens_balance,
                'tokens_used_month' => $user->tokens_used_month,
                'created_at' => $user->created_at->toIso8601String(),
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                'assistant' => $user->assistant ? [
                    'id' => $user->assistant->id,
                    'name' => $user->assistant->name,
                ] : null,
                'subscription' => $user->activeSubscription ? [
                    'id' => $user->activeSubscription->id,
                    'plan' => $user->activeSubscription->plan->name,
                    'plan_id' => $user->activeSubscription->plan_id,
                    'status' => $user->activeSubscription->status,
                    'starts_at' => $user->activeSubscription->starts_at->toIso8601String(),
                    'ends_at' => $user->activeSubscription->ends_at->toIso8601String(),
                ] : null,
                'subscription_history' => $user->subscriptions->map(function ($sub) {
                    return [
                        'id' => $sub->id,
                        'plan' => $sub->plan->name,
                        'status' => $sub->status,
                        'starts_at' => $sub->starts_at->toIso8601String(),
                        'ends_at' => $sub->ends_at->toIso8601String(),
                        'cancelled_at' => $sub->cancelled_at?->toIso8601String(),
                    ];
                }),
            ],
            'recent_messages' => $recentMessages,
            'token_stats' => $tokenStats,
        ]);
    }

    /**
     * Update user details
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
        ]);

        $user->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Usuario actualizado correctamente',
            'user' => $user->fresh(),
        ]);
    }

    /**
     * Activate user
     */
    public function activate(User $user): JsonResponse
    {
        $user->update(['status' => 'active']);

        return response()->json([
            'success' => true,
            'message' => 'Usuario activado correctamente',
        ]);
    }

    /**
     * Deactivate user
     */
    public function deactivate(User $user): JsonResponse
    {
        $user->update(['status' => 'inactive']);

        return response()->json([
            'success' => true,
            'message' => 'Usuario desactivado correctamente',
        ]);
    }

    /**
     * Add tokens to user
     */
    public function addTokens(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'required|integer|min:1|max:10000000',
        ]);

        $this->tokenService->addTokens($user, $validated['amount']);

        return response()->json([
            'success' => true,
            'message' => "Se agregaron {$validated['amount']} tokens al usuario",
            'tokens_balance' => $user->fresh()->tokens_balance,
        ]);
    }

    /**
     * Change user's plan
     */
    public function changePlan(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'reset_tokens' => 'boolean',
        ]);

        $plan = Plan::findOrFail($validated['plan_id']);

        // Cancel current subscription if exists
        if ($user->activeSubscription) {
            $user->activeSubscription->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ]);
        }

        // Create new subscription
        Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);

        // Reset tokens if requested
        if ($request->boolean('reset_tokens', true)) {
            $user->resetMonthlyTokens($plan->tokens_monthly);
        }

        // Ensure user is active
        $user->update(['status' => 'active']);

        return response()->json([
            'success' => true,
            'message' => "Plan cambiado a {$plan->name}",
            'user' => $user->fresh()->load('activeSubscription.plan'),
        ]);
    }

    /**
     * Assign an assistant to a user
     */
    public function assignAssistant(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'assistant_id' => 'required|exists:assistants,id',
        ]);

        $assistant = Assistant::findOrFail($validated['assistant_id']);

        $user->update(['assistant_id' => $assistant->id]);

        return response()->json([
            'success' => true,
            'message' => "Asistente asignado: {$assistant->name}",
            'user' => $user->fresh()->load('assistant'),
        ]);
    }
}
