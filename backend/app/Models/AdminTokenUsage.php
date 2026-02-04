<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminTokenUsage extends Model
{
    protected $table = 'admin_token_usage';

    public $timestamps = false;

    protected $fillable = [
        'admin_id',
        'assistant_id',
        'provider',
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

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    public function assistant(): BelongsTo
    {
        return $this->belongsTo(Assistant::class);
    }

    public function getTotalTokensAttribute(): int
    {
        return $this->tokens_input + $this->tokens_output;
    }

    /**
     * Record admin token usage
     */
    public static function record(int $adminId, int $tokensInput, int $tokensOutput, string $provider = 'openai', ?int $assistantId = null): self
    {
        return static::create([
            'admin_id' => $adminId,
            'assistant_id' => $assistantId,
            'provider' => $provider,
            'tokens_input' => $tokensInput,
            'tokens_output' => $tokensOutput,
            'date' => now()->toDateString(),
            'created_at' => now(),
        ]);
    }

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
