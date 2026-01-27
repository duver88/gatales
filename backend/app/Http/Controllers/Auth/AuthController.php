<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Set password for a new user using the token from email
     */
    public function setPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::where('password_token', $validated['token'])->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'El enlace no es válido o ha expirado.',
            ], 400);
        }

        if ($user->password_token_expires_at && Carbon::parse($user->password_token_expires_at)->isPast()) {
            return response()->json([
                'success' => false,
                'message' => 'El enlace ha expirado. Por favor solicita uno nuevo.',
            ], 400);
        }

        // Update user
        $user->update([
            'password' => Hash::make($validated['password']),
            'password_token' => null,
            'password_token_expires_at' => null,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        // Create token for immediate login
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Contraseña establecida correctamente',
            'token' => $token,
            'user' => $this->formatUserResponse($user),
        ]);
    }

    /**
     * Login user
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (!$user || !$user->password || !Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales proporcionadas son incorrectas.'],
            ]);
        }

        // Check user status
        if ($user->status === 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Tu cuenta está pendiente de activación. Por favor establece tu contraseña desde el email que recibiste.',
                'status' => 'pending',
            ], 403);
        }

        if ($user->status === 'inactive') {
            return response()->json([
                'success' => false,
                'message' => 'Tu cuenta está inactiva. Por favor renueva tu suscripción.',
                'status' => 'inactive',
                'renew_url' => config('services.hotmart.renew_url'),
            ], 403);
        }

        if ($user->status === 'suspended') {
            return response()->json([
                'success' => false,
                'message' => 'Tu cuenta ha sido suspendida. Por favor contacta a soporte.',
                'status' => 'suspended',
            ], 403);
        }

        // Revoke all previous tokens
        $user->tokens()->delete();

        // Create new token
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Inicio de sesión exitoso',
            'token' => $token,
            'user' => $this->formatUserResponse($user),
        ]);
    }

    /**
     * Logout user
     */
    public function logout(Request $request): JsonResponse
    {
        // Revoke current token
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Sesión cerrada correctamente',
        ]);
    }

    /**
     * Get current authenticated user
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load('activeSubscription.plan');

        return response()->json([
            'success' => true,
            'user' => $this->formatUserResponse($user),
        ]);
    }

    /**
     * Format user response
     */
    private function formatUserResponse(User $user): array
    {
        $subscription = $user->activeSubscription;
        $hasFreePlan = $user->hasFreePlan();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'status' => $user->status,
            'tokens_balance' => $user->tokens_balance,
            'tokens_used_month' => $user->tokens_used_month,
            'has_free_plan' => $hasFreePlan,
            'current_plan' => $user->getCurrentPlanName(),
            'subscription' => $subscription ? [
                'plan' => $subscription->plan->name,
                'plan_slug' => $subscription->plan->slug,
                'tokens_monthly' => $subscription->plan->tokens_monthly,
                'ends_at' => $subscription->ends_at->toIso8601String(),
            ] : null,
            'hotmart_upgrade_url' => config('services.hotmart.upgrade_url'),
            'hotmart_renew_url' => config('services.hotmart.renew_url'),
        ];
    }
}
