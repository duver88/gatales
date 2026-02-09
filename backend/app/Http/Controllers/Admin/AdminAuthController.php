<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AdminAuthController extends Controller
{
    /**
     * Login admin
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $admin = Admin::where('email', $validated['email'])->first();

        if (!$admin || !Hash::check($validated['password'], $admin->password)) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales proporcionadas son incorrectas.'],
            ]);
        }

        // Revoke all previous tokens
        $admin->tokens()->delete();

        // Create new token with admin abilities
        $token = $admin->createToken('admin-token', ['admin'])->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Inicio de sesión exitoso',
            'token' => $token,
            'admin' => [
                'id' => $admin->id,
                'name' => $admin->name,
                'email' => $admin->email,
            ],
        ]);
    }

    /**
     * Logout admin
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Sesión cerrada correctamente',
        ]);
    }

    /**
     * Get current admin
     */
    public function me(Request $request): JsonResponse
    {
        $admin = $request->user();

        return response()->json([
            'success' => true,
            'admin' => [
                'id' => $admin->id,
                'name' => $admin->name,
                'email' => $admin->email,
            ],
        ]);
    }

    /**
     * Update admin profile
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $admin = $request->user();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:admins,email,' . $admin->id,
        ]);

        $admin->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Perfil actualizado correctamente',
            'admin' => [
                'id' => $admin->id,
                'name' => $admin->name,
                'email' => $admin->email,
            ],
        ]);
    }

    /**
     * Change admin password
     */
    public function changePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $admin = $request->user();

        if (!Hash::check($validated['current_password'], $admin->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['La contraseña actual es incorrecta.'],
            ]);
        }

        $admin->update(['password' => $validated['password']]);

        return response()->json([
            'success' => true,
            'message' => 'Contraseña actualizada correctamente',
        ]);
    }
}
