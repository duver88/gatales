<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Conversation extends Model
{
    use HasFactory, SoftDeletes;

    // Note: Removed automatic eager loading of assistant to improve performance
    // Controllers should explicitly use ->with('assistant') when needed

    protected $fillable = [
        'user_id',
        'admin_id',
        'assistant_id',
        'title',
        'openai_thread_id',
        'type',
        'total_tokens_input',
        'total_tokens_output',
        'last_message_at',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'total_tokens_input' => 'integer',
            'total_tokens_output' => 'integer',
            'last_message_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    /**
     * Get the user that owns the conversation
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the admin that owns the conversation (for test conversations)
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    /**
     * Get the assistant used in this conversation
     */
    public function assistant(): BelongsTo
    {
        return $this->belongsTo(Assistant::class);
    }

    /**
     * Get all messages in this conversation
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('created_at', 'asc');
    }

    /**
     * Scope: Filter by user
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId)->where('type', 'user_chat');
    }

    /**
     * Scope: Filter by admin
     */
    public function scopeForAdmin($query, $adminId)
    {
        return $query->where('admin_id', $adminId)->where('type', 'admin_test');
    }

    /**
     * Scope: Not archived
     */
    public function scopeNotArchived($query)
    {
        return $query->whereNull('archived_at');
    }

    /**
     * Scope: User chats only
     */
    public function scopeUserChats($query)
    {
        return $query->where('type', 'user_chat');
    }

    /**
     * Scope: Admin tests only
     */
    public function scopeAdminTests($query)
    {
        return $query->where('type', 'admin_test');
    }

    /**
     * Generate title from first user message (race-condition safe)
     * Only updates if title is currently null to prevent concurrent overwrites
     */
    public function generateTitle(): void
    {
        // Skip if title already exists (prevents race condition)
        if ($this->title !== null) {
            return;
        }

        $firstMessage = $this->messages()->where('role', 'user')->first();
        if ($firstMessage) {
            $title = Str::limit($firstMessage->content, 50, '...');

            // Atomic update: only set title if still null (race-condition safe)
            static::where('id', $this->id)
                ->whereNull('title')
                ->update(['title' => $title]);

            // Update local model
            $this->title = $title;
        }
    }

    /**
     * Update token statistics
     */
    public function updateTokenStats(int $tokensInput, int $tokensOutput): void
    {
        $this->increment('total_tokens_input', $tokensInput);
        $this->increment('total_tokens_output', $tokensOutput);
        $this->update(['last_message_at' => now()]);
    }

    /**
     * Archive the conversation
     */
    public function archive(): void
    {
        $this->update(['archived_at' => now()]);
    }

    /**
     * Unarchive the conversation
     */
    public function unarchive(): void
    {
        $this->update(['archived_at' => null]);
    }

    /**
     * Check if conversation is archived
     */
    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /**
     * Get total tokens used
     */
    public function getTotalTokensAttribute(): int
    {
        return $this->total_tokens_input + $this->total_tokens_output;
    }

    /**
     * Get preview text (first 100 chars of first message)
     * Uses title if available to avoid N+1 query
     */
    public function getPreviewAttribute(): string
    {
        // Use title if available (already stored)
        if ($this->title) {
            return $this->title;
        }

        // Check if messages are already loaded to avoid N+1
        if ($this->relationLoaded('messages')) {
            $firstMessage = $this->messages->where('role', 'user')->first();
            if ($firstMessage) {
                return Str::limit($firstMessage->content, 100, '...');
            }
        }

        return '';
    }

    /**
     * Get message count
     * Uses messages_count if available (from withCount) to avoid N+1
     */
    public function getMessageCountAttribute(): int
    {
        // Use eager-loaded count if available
        if (isset($this->attributes['messages_count'])) {
            return (int) $this->attributes['messages_count'];
        }

        // Check if messages are already loaded
        if ($this->relationLoaded('messages')) {
            return $this->messages->count();
        }

        // Fallback to query (should be avoided in loops)
        return $this->messages()->count();
    }
}
