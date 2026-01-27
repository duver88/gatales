<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebhookLog extends Model
{
    use HasFactory;

    /**
     * Indicates if the model should be timestamped.
     * We only use created_at
     */
    public $timestamps = false;

    protected $fillable = [
        'source',
        'event_type',
        'payload',
        'processed',
        'error',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Log a webhook request
     */
    public static function logWebhook(string $source, string $eventType, array $payload): self
    {
        return static::create([
            'source' => $source,
            'event_type' => $eventType,
            'payload' => $payload,
            'processed' => false,
            'created_at' => now(),
        ]);
    }

    /**
     * Mark webhook as processed
     */
    public function markAsProcessed(): void
    {
        $this->update(['processed' => true]);
    }

    /**
     * Mark webhook as failed with error
     */
    public function markAsFailed(string $error): void
    {
        $this->update([
            'processed' => false,
            'error' => $error,
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
