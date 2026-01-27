<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TokenUsage extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'token_usage';

    /**
     * Indicates if the model should be timestamped.
     * We only use created_at
     */
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'tokens_input',
        'tokens_output',
        'date',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'tokens_input' => 'integer',
            'tokens_output' => 'integer',
            'date' => 'date',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Get the user that owns the token usage record
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get total tokens
     */
    public function getTotalTokensAttribute(): int
    {
        return $this->tokens_input + $this->tokens_output;
    }

    /**
     * Record token usage for a user
     */
    public static function record(int $userId, int $tokensInput, int $tokensOutput): self
    {
        return static::create([
            'user_id' => $userId,
            'tokens_input' => $tokensInput,
            'tokens_output' => $tokensOutput,
            'date' => now()->toDateString(),
            'created_at' => now(),
        ]);
    }

    /**
     * Boot method to set created_at automatically
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (!$model->created_at) {
                $model->created_at = now();
            }
        });
    }
}
