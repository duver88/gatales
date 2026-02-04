<?php

use Illuminate\Support\Facades\Route;

// Controllers will be added as we create them
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\WebhookLogController;
use App\Http\Controllers\Admin\AiSettingsController;
use App\Http\Controllers\Admin\AssistantController;
use App\Http\Controllers\Admin\AssistantFileController;
use App\Http\Controllers\Admin\AdminConversationController;
use App\Http\Controllers\Admin\EmailLogController;
use App\Http\Controllers\ConversationController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// ========================================
// PUBLIC ROUTES (No authentication required)
// ========================================

// User Authentication (with rate limiting to prevent brute force)
Route::prefix('auth')->group(function () {
    Route::post('/set-password', [AuthController::class, 'setPassword'])->middleware('throttle:5,1'); // 5 attempts per minute
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1'); // 5 attempts per minute
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:3,1'); // 3 attempts per minute
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:5,1'); // 5 attempts per minute
});

// Webhooks (Protected by API Key middleware)
Route::prefix('webhooks')->middleware('webhook.secret')->group(function () {
    Route::post('/purchase', [WebhookController::class, 'purchase']);
    Route::post('/cancel', [WebhookController::class, 'cancel']);
    Route::post('/renewal', [WebhookController::class, 'renewal']);
    Route::post('/refund', [WebhookController::class, 'refund']);
});

// ========================================
// USER AUTHENTICATED ROUTES
// ========================================

Route::middleware(['auth:sanctum', 'user.active'])->group(function () {
    // Auth
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/change-password', [AuthController::class, 'changePassword']);
    Route::patch('/auth/profile', [AuthController::class, 'updateProfile']);
    Route::post('/auth/avatar', [AuthController::class, 'uploadAvatar']);
    Route::delete('/auth/avatar', [AuthController::class, 'deleteAvatar']);

    // Chat (legacy endpoints - for backward compatibility)
    Route::prefix('chat')->group(function () {
        Route::get('/messages', [ChatController::class, 'messages']);
        Route::post('/send', [ChatController::class, 'send']);
        Route::delete('/clear', [ChatController::class, 'clear']);
    });

    // Conversations (new - ChatGPT-style history)
    Route::prefix('conversations')->group(function () {
        Route::get('/', [ConversationController::class, 'index']);
        Route::post('/', [ConversationController::class, 'store']);
        Route::get('/search', [ConversationController::class, 'search']);
        Route::get('/archived', [ConversationController::class, 'archived']);
        Route::get('/{conversation}', [ConversationController::class, 'show']);
        Route::patch('/{conversation}', [ConversationController::class, 'update']);
        Route::delete('/{conversation}', [ConversationController::class, 'destroy']);
        Route::post('/{conversation}/archive', [ConversationController::class, 'archive']);
        Route::post('/{conversation}/unarchive', [ConversationController::class, 'unarchive']);
        Route::get('/{conversation}/messages', [ChatController::class, 'conversationMessages']);
        Route::post('/{conversation}/messages', [ChatController::class, 'conversationSend']);
        Route::post('/{conversation}/messages/stream', [ChatController::class, 'conversationSendStream']);
        Route::delete('/{conversation}/messages', [ChatController::class, 'conversationClear']);
    });

    // Assistants (user can view and change)
    Route::get('/assistants', [ChatController::class, 'assistants']);
    Route::patch('/user/assistant', [ChatController::class, 'changeAssistant']);
});

// ========================================
// ADMIN ROUTES
// ========================================

Route::prefix('admin')->group(function () {
    // Admin Auth (Public - with strict rate limiting)
    Route::post('/auth/login', [AdminAuthController::class, 'login'])->middleware('throttle:3,1'); // 3 attempts per minute

    // Admin Protected Routes
    Route::middleware('auth:admin')->group(function () {
        Route::post('/auth/logout', [AdminAuthController::class, 'logout']);
        Route::get('/auth/me', [AdminAuthController::class, 'me']);

        // Dashboard
        Route::get('/dashboard', [DashboardController::class, 'index']);

        // Users Management
        Route::get('/users', [AdminUserController::class, 'index']);
        Route::post('/users', [AdminUserController::class, 'store']);
        Route::get('/users/{user}', [AdminUserController::class, 'show']);
        Route::patch('/users/{user}', [AdminUserController::class, 'update']);
        Route::post('/users/{user}/activate', [AdminUserController::class, 'activate']);
        Route::post('/users/{user}/deactivate', [AdminUserController::class, 'deactivate']);
        Route::post('/users/{user}/add-tokens', [AdminUserController::class, 'addTokens']);
        Route::patch('/users/{user}/plan', [AdminUserController::class, 'changePlan']);

        // Plans Management
        Route::get('/plans', [PlanController::class, 'index']);
        Route::post('/plans', [PlanController::class, 'store']);
        Route::patch('/plans/{plan}', [PlanController::class, 'update']);
        Route::delete('/plans/{plan}', [PlanController::class, 'destroy']);

        // Token Statistics
        Route::get('/stats/tokens', [DashboardController::class, 'tokenStats']);
        Route::get('/stats/openai', [DashboardController::class, 'openaiStats']);
        Route::get('/stats/providers', [DashboardController::class, 'providerStats']);

        // Webhook Logs
        Route::get('/webhook-logs', [WebhookLogController::class, 'index']);
        Route::get('/webhook-logs/{webhookLog}', [WebhookLogController::class, 'show']);

        // AI Settings
        Route::get('/ai-settings', [AiSettingsController::class, 'index']);
        Route::post('/ai-settings', [AiSettingsController::class, 'update']);
        Route::post('/ai-settings/test', [AiSettingsController::class, 'test']);

        // Assistants Management
        Route::get('/assistants', [AssistantController::class, 'index']);
        Route::post('/assistants', [AssistantController::class, 'store']);
        Route::get('/assistants/{assistant}', [AssistantController::class, 'show']);
        Route::patch('/assistants/{assistant}', [AssistantController::class, 'update']);
        Route::delete('/assistants/{assistant}', [AssistantController::class, 'destroy']);
        Route::post('/assistants/{assistant}/set-default', [AssistantController::class, 'setDefault']);
        Route::post('/assistants/{assistant}/duplicate', [AssistantController::class, 'duplicate']);
        Route::post('/assistants/{assistant}/test', [AssistantController::class, 'test']);

        // Assistant Files (Knowledge Base)
        Route::get('/assistants/{assistant}/files', [AssistantFileController::class, 'index']);
        Route::post('/assistants/{assistant}/files', [AssistantFileController::class, 'store']);
        Route::delete('/assistants/{assistant}/files/{file}', [AssistantFileController::class, 'destroy']);
        Route::post('/assistants/{assistant}/knowledge-base/enable', [AssistantFileController::class, 'enableKnowledgeBase']);
        Route::post('/assistants/{assistant}/knowledge-base/disable', [AssistantFileController::class, 'disableKnowledgeBase']);
        Route::post('/assistants/{assistant}/sync', [AssistantFileController::class, 'sync']);

        // Assign assistant to user (admin)
        Route::patch('/users/{user}/assistant', [AdminUserController::class, 'assignAssistant']);

        // Test Conversations (admin testing chat history)
        Route::prefix('test-conversations')->group(function () {
            Route::get('/', [AdminConversationController::class, 'index']);
            Route::post('/', [AdminConversationController::class, 'store']);
            Route::get('/{conversation}', [AdminConversationController::class, 'show']);
            Route::post('/{conversation}/messages', [AdminConversationController::class, 'sendMessage']);
            Route::post('/{conversation}/messages/stream', [AdminConversationController::class, 'sendMessageStream']);
            Route::delete('/{conversation}', [AdminConversationController::class, 'destroy']);
            Route::delete('/clear-all', [AdminConversationController::class, 'clearAll']);
        });

        // Email Logs (monitoring bounces and delivery issues)
        Route::prefix('emails')->group(function () {
            Route::get('/stats', [EmailLogController::class, 'stats']);
            Route::get('/bounced', [EmailLogController::class, 'bouncedEmails']);
            Route::get('/', [EmailLogController::class, 'index']);
            Route::post('/', [EmailLogController::class, 'store']);
            Route::get('/{emailLog}', [EmailLogController::class, 'show']);
            Route::post('/{emailLog}/resend', [EmailLogController::class, 'resend']);
            Route::post('/webhook', [EmailLogController::class, 'updateStatus']);
        });
    });
});
