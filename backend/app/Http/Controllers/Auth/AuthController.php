<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\PasswordResetMail;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
     * Request password reset - sends email with reset link
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::where('email', $validated['email'])->first();

        // Always return success to prevent email enumeration attacks
        if (!$user) {
            return response()->json([
                'success' => true,
                'message' => 'Si el correo existe en nuestro sistema, recibirás un enlace para restablecer tu contraseña.',
            ]);
        }

        // Generate password reset token
        $token = Str::random(64);

        $user->update([
            'password_token' => $token,
            'password_token_expires_at' => Carbon::now()->addHour(), // 1 hour expiry for security
        ]);

        // Send password reset email
        try {
            Mail::to($user->email)->send(new PasswordResetMail($user, $token));
            \Log::info('Password reset email sent successfully', ['email' => $user->email]);
        } catch (\Exception $e) {
            \Log::error('Failed to send password reset email', [
                'email' => $user->email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            // Still return success to prevent email enumeration
        }

        return response()->json([
            'success' => true,
            'message' => 'Si el correo existe en nuestro sistema, recibirás un enlace para restablecer tu contraseña.',
        ]);
    }

    /**
     * Reset password using token from email
     */
    public function resetPassword(Request $request): JsonResponse
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

        // Update user password
        $user->update([
            'password' => Hash::make($validated['password']),
            'password_token' => null,
            'password_token_expires_at' => null,
        ]);

        // Create token for immediate login
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Contraseña restablecida correctamente',
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

        // If refresh param is passed, clear plan cache to get fresh data
        if ($request->boolean('refresh')) {
            $user->clearPlanCache();
        }

        $user->load('activeSubscription.plan');

        return response()->json([
            'success' => true,
            'user' => $this->formatUserResponse($user),
        ]);
    }

    /**
     * Change user password
     */
    public function changePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();

        if (!Hash::check($validated['current_password'], $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'La contraseña actual es incorrecta.',
            ], 400);
        }

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Contraseña actualizada correctamente',
        ]);
    }

    /**
     * Update user profile
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
        ]);

        $user = $request->user();
        $user->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Perfil actualizado correctamente',
            'user' => $this->formatUserResponse($user),
        ]);
    }

    /**
     * Upload user avatar
     */
    public function uploadAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpeg,png,gif|max:2048',
        ]);

        $user = $request->user();

        // Delete old avatar if exists
        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        // Store new avatar
        $path = $request->file('avatar')->store('avatars', 'public');

        $user->update([
            'avatar_path' => $path,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Foto de perfil actualizada',
            'avatar_url' => Storage::disk('public')->url($path),
        ]);
    }

    /**
     * Delete user avatar
     */
    public function deleteAvatar(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
            $user->update(['avatar_path' => null]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Foto de perfil eliminada',
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
            'avatar_url' => $user->avatar_path ? Storage::disk('public')->url($user->avatar_path) : null,
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
