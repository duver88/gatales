<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'status',
        'openai_thread_id',
        'assistant_id',
        'tokens_balance',
        'tokens_used_month',
        'password_token',
        'password_token_expires_at',
        'email_verified_at',
        'avatar_path',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'password',
        'password_token',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password_token_expires_at' => 'datetime',
            'password' => 'hashed',
            'tokens_balance' => 'integer',
            'tokens_used_month' => 'integer',
        ];
    }

    /**
     * Check if user is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if user has available tokens
     */
    public function hasTokens(): bool
    {
        return $this->tokens_balance > 0;
    }

    /**
     * Deduct tokens from user balance
     */
    public function deductTokens(int $amount): void
    {
        $this->tokens_balance = max(0, $this->tokens_balance - $amount);
        $this->tokens_used_month += $amount;
        $this->save();
    }

    /**
     * Reset monthly token usage
     */
    public function resetMonthlyTokens(int $newBalance): void
    {
        $this->tokens_balance = $newBalance;
        $this->tokens_used_month = 0;
        $this->save();
    }

    /**
     * Get the active subscription for the user
     */
    public function activeSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)
            ->where('status', 'active')
            ->latest();
    }

    /**
     * Get all subscriptions for the user
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Get all messages for the user
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('created_at', 'asc');
    }

    /**
     * Get all conversations for the user
     */
    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class)->orderByDesc('last_message_at');
    }

    /**
     * Get active (non-archived) conversations
     */
    public function activeConversations(): HasMany
    {
        return $this->conversations()->whereNull('archived_at');
    }

    /**
     * Get token usage records for the user
     */
    public function tokenUsage(): HasMany
    {
        return $this->hasMany(TokenUsage::class);
    }

    /**
     * Get the assigned assistant
     */
    public function assistant(): BelongsTo
    {
        return $this->belongsTo(Assistant::class);
    }

    /**
     * Get the user's assistant or the default one
     */
    public function getAssistant(): ?Assistant
    {
        return $this->assistant ?? Assistant::getDefault();
    }

    /**
     * Check if user has the free plan
     */
    public function hasFreePlan(): bool
    {
        $subscription = $this->activeSubscription;

        if (!$subscription) {
            return true; // No subscription = free plan
        }

        $plan = $subscription->plan;

        return $plan && $plan->isFree();
    }

    /**
     * Get the current plan name
     */
    public function getCurrentPlanName(): string
    {
        $subscription = $this->activeSubscription;

        if (!$subscription || !$subscription->plan) {
            return 'Plan Gratuito';
        }

        return $subscription->plan->name;
    }
}
