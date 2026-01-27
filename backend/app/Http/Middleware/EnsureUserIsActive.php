<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    /**
     * Handle an incoming request.
     * Ensures the authenticated user has an active status.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'No autenticado',
            ], 401);
        }

        if (!$user->isActive()) {
            $message = match ($user->status) {
                'pending' => 'Tu cuenta está pendiente de activación. Por favor establece tu contraseña.',
                'inactive' => 'Tu cuenta está inactiva. Por favor renueva tu suscripción.',
                'suspended' => 'Tu cuenta ha sido suspendida. Contacta a soporte.',
                default => 'Tu cuenta no está activa.',
            };

            return response()->json([
                'success' => false,
                'message' => $message,
                'status' => $user->status,
            ], 403);
        }

        return $next($request);
    }
}
