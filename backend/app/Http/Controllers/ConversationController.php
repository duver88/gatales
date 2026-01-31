<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    /**
     * List user's conversations grouped by date
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $conversations = Conversation::forUser($user->id)
            ->notArchived()
            ->with('assistant:id,assistant_display_name')
            ->withCount('messages')
            ->orderByDesc('last_message_at')
            ->get()
            ->map(function ($conversation) {
                return [
                    'id' => $conversation->id,
                    'title' => $conversation->title ?: 'Nueva conversacion',
                    'assistant_name' => $conversation->assistant?->assistant_display_name,
                    'total_tokens' => $conversation->total_tokens,
                    'message_count' => $conversation->messages_count,
                    'last_message_at' => $conversation->last_message_at?->toIso8601String(),
                    'created_at' => $conversation->created_at->toIso8601String(),
                ];
            });

        // Group by date
        $grouped = $this->groupByDate($conversations);

        return response()->json([
            'success' => true,
            'conversations' => $conversations,
            'grouped' => $grouped,
        ]);
    }

    /**
     * Create a new conversation
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $assistant = $user->getAssistant();

        $conversation = Conversation::create([
            'user_id' => $user->id,
            'assistant_id' => $assistant?->id,
            'type' => 'user_chat',
        ]);

        return response()->json([
            'success' => true,
            'conversation' => [
                'id' => $conversation->id,
                'title' => null,
                'assistant_name' => $assistant?->assistant_display_name,
                'total_tokens' => 0,
                'message_count' => 0,
                'last_message_at' => null,
                'created_at' => $conversation->created_at->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * Get a single conversation with its messages
     */
    public function show(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();

        // Verify ownership
        if ($conversation->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'No autorizado',
            ], 403);
        }

        // Eager load assistant to avoid N+1
        $conversation->load('assistant:id,assistant_display_name');

        // Only select necessary columns from messages
        $messages = $conversation->messages()
            ->select(['id', 'role', 'content', 'created_at'])
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($message) {
                return [
                    'id' => $message->id,
                    'role' => $message->role,
                    'content' => $message->content,
                    'created_at' => $message->created_at->toIso8601String(),
                ];
            });

        return response()->json([
            'success' => true,
            'conversation' => [
                'id' => $conversation->id,
                'title' => $conversation->title ?: 'Nueva conversacion',
                'assistant_id' => $conversation->assistant_id,
                'assistant_name' => $conversation->assistant?->assistant_display_name,
                'total_tokens' => $conversation->total_tokens,
                'last_message_at' => $conversation->last_message_at?->toIso8601String(),
                'created_at' => $conversation->created_at->toIso8601String(),
            ],
            'messages' => $messages,
        ]);
    }

    /**
     * Update conversation (title)
     */
    public function update(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();

        // Verify ownership
        if ($conversation->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'No autorizado',
            ], 403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
        ]);

        $conversation->update(['title' => $validated['title']]);

        return response()->json([
            'success' => true,
            'message' => 'Titulo actualizado',
            'conversation' => [
                'id' => $conversation->id,
                'title' => $conversation->title,
            ],
        ]);
    }

    /**
     * Soft delete a conversation
     */
    public function destroy(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();

        // Verify ownership
        if ($conversation->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'No autorizado',
            ], 403);
        }

        $conversation->delete();

        return response()->json([
            'success' => true,
            'message' => 'Conversacion eliminada',
        ]);
    }

    /**
     * Archive a conversation
     */
    public function archive(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();

        // Verify ownership
        if ($conversation->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'No autorizado',
            ], 403);
        }

        $conversation->archive();

        return response()->json([
            'success' => true,
            'message' => 'Conversacion archivada',
        ]);
    }

    /**
     * Unarchive a conversation
     */
    public function unarchive(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();

        // Verify ownership
        if ($conversation->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'No autorizado',
            ], 403);
        }

        $conversation->unarchive();

        return response()->json([
            'success' => true,
            'message' => 'Conversacion restaurada',
        ]);
    }

    /**
     * Search conversations by title or content
     */
    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => 'required|string|min:2|max:100',
        ]);

        $user = $request->user();
        $query = $validated['q'];

        $conversations = Conversation::forUser($user->id)
            ->notArchived()
            ->where(function ($q) use ($query) {
                $q->where('title', 'ilike', "%{$query}%")
                    ->orWhereHas('messages', function ($mq) use ($query) {
                        $mq->where('content', 'ilike', "%{$query}%");
                    });
            })
            ->orderByDesc('last_message_at')
            ->limit(20)
            ->get()
            ->map(function ($conversation) {
                return [
                    'id' => $conversation->id,
                    'title' => $conversation->title ?: 'Nueva conversacion',
                    'last_message_at' => $conversation->last_message_at?->toIso8601String(),
                ];
            });

        return response()->json([
            'success' => true,
            'conversations' => $conversations,
        ]);
    }

    /**
     * Get archived conversations
     */
    public function archived(Request $request): JsonResponse
    {
        $user = $request->user();

        $conversations = Conversation::forUser($user->id)
            ->whereNotNull('archived_at')
            ->orderByDesc('archived_at')
            ->limit(50)
            ->get()
            ->map(function ($conversation) {
                return [
                    'id' => $conversation->id,
                    'title' => $conversation->title ?: 'Nueva conversacion',
                    'archived_at' => $conversation->archived_at->toIso8601String(),
                ];
            });

        return response()->json([
            'success' => true,
            'conversations' => $conversations,
        ]);
    }

    /**
     * Group conversations by date
     */
    private function groupByDate($conversations): array
    {
        $now = Carbon::now();
        $today = $now->copy()->startOfDay();
        $yesterday = $now->copy()->subDay()->startOfDay();
        $last7Days = $now->copy()->subDays(7)->startOfDay();
        $last30Days = $now->copy()->subDays(30)->startOfDay();

        $groups = [
            'today' => ['title' => 'Hoy', 'conversations' => []],
            'yesterday' => ['title' => 'Ayer', 'conversations' => []],
            'last_7_days' => ['title' => 'Ultimos 7 dias', 'conversations' => []],
            'last_30_days' => ['title' => 'Ultimos 30 dias', 'conversations' => []],
            'older' => ['title' => 'Anteriores', 'conversations' => []],
        ];

        foreach ($conversations as $conversation) {
            $date = Carbon::parse($conversation['last_message_at'] ?? $conversation['created_at']);

            if ($date->gte($today)) {
                $groups['today']['conversations'][] = $conversation;
            } elseif ($date->gte($yesterday)) {
                $groups['yesterday']['conversations'][] = $conversation;
            } elseif ($date->gte($last7Days)) {
                $groups['last_7_days']['conversations'][] = $conversation;
            } elseif ($date->gte($last30Days)) {
                $groups['last_30_days']['conversations'][] = $conversation;
            } else {
                $groups['older']['conversations'][] = $conversation;
            }
        }

        // Filter out empty groups
        return array_filter($groups, fn($group) => !empty($group['conversations']));
    }
}
