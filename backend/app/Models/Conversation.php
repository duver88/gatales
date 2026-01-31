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

    // Eager load assistant by default to prevent N+1 queries
    protected $with = ['assistant'];

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
     * Generate title from first user message
     */
    public function generateTitle(): void
    {
        $firstMessage = $this->messages()->where('role', 'user')->first();
        if ($firstMessage) {
            $title = Str::limit($firstMessage->content, 50, '...');
            $this->update(['title' => $title]);
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
     */
    public function getPreviewAttribute(): string
    {
        $firstMessage = $this->messages()->where('role', 'user')->first();
        if ($firstMessage) {
            return Str::limit($firstMessage->content, 100, '...');
        }
        return '';
    }

    /**
     * Get message count
     */
    public function getMessageCountAttribute(): int
    {
        return $this->messages()->count();
    }
}
