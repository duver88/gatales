<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to release resources before streaming responses
 * This prevents blocking other requests while the AI generates a response
 */
class ReleaseResourcesForStreaming
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    /**
     * Handle tasks after the response has been sent to the browser
     * Note: This doesn't work well with StreamedResponse, so we release manually
     */
    public function terminate(Request $request, Response $response): void
    {
        // Cleanup is handled in the controller
    }

    /**
     * Release session and database connections
     * Call this from controllers BEFORE starting streaming
     */
    public static function releaseAll(): void
    {
        // 1. Save and close session to release lock
        if (session()->isStarted()) {
            session()->save();
            // Force session handler to close
            if (function_exists('session_write_close')) {
                @session_write_close();
            }
        }

        // 2. Disconnect all database connections
        // This allows other requests to use the connection pool
        foreach (array_keys(config('database.connections')) as $name) {
            try {
                DB::connection($name)->disconnect();
            } catch (\Throwable $e) {
                // Ignore errors during disconnect
            }
        }
    }

    /**
     * Reconnect to database (call when you need to do DB operations in streaming)
     */
    public static function reconnect(): void
    {
        DB::reconnect();
    }
}
