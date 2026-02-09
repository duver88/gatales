<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateWebhookSecret
{
    /**
     * Handle an incoming request.
     * Validates that the webhook request contains the correct secret key.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $secret = $request->header('X-Webhook-Secret');
        $expected = config('services.webhook.secret');

        if (!$secret || !$expected || !hash_equals($expected, $secret)) {
            return response()->json([
                'success' => false,
                'message' => 'Acceso no autorizado',
            ], 401);
        }

        return $next($request);
    }
}
