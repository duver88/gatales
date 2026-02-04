<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'to_email',
        'to_name',
        'subject',
        'type',
        'status',
        'provider',
        'message_id',
        'error_message',
        'bounce_type',
        'bounce_subtype',
        'sent_at',
        'delivered_at',
        'bounced_at',
        'opened_at',
        'metadata',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'bounced_at' => 'datetime',
        'opened_at' => 'datetime',
        'metadata' => 'array',
    ];

    // Status constants
    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT = 'sent';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_BOUNCED = 'bounced';
    public const STATUS_FAILED = 'failed';
    public const STATUS_COMPLAINED = 'complained';

    // Type constants
    public const TYPE_WELCOME = 'welcome';
    public const TYPE_PASSWORD_RESET = 'password_reset';
    public const TYPE_VERIFICATION = 'verification';
    public const TYPE_NOTIFICATION = 'notification';
    public const TYPE_GENERAL = 'general';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Log a new email
     */
    public static function log(
        string $toEmail,
        string $subject,
        string $type = self::TYPE_GENERAL,
        ?int $userId = null,
        ?string $toName = null,
        ?string $provider = null
    ): self {
        return self::create([
            'user_id' => $userId,
            'to_email' => $toEmail,
            'to_name' => $toName,
            'subject' => $subject,
            'type' => $type,
            'status' => self::STATUS_PENDING,
            'provider' => $provider ?? config('mail.default'),
        ]);
    }

    /**
     * Mark as sent
     */
    public function markAsSent(?string $messageId = null): self
    {
        $this->update([
            'status' => self::STATUS_SENT,
            'message_id' => $messageId,
            'sent_at' => now(),
        ]);
        return $this;
    }

    /**
     * Mark as delivered
     */
    public function markAsDelivered(): self
    {
        $this->update([
            'status' => self::STATUS_DELIVERED,
            'delivered_at' => now(),
        ]);
        return $this;
    }

    /**
     * Mark as bounced
     */
    public function markAsBounced(?string $bounceType = null, ?string $bounceSubtype = null, ?string $errorMessage = null): self
    {
        $this->update([
            'status' => self::STATUS_BOUNCED,
            'bounce_type' => $bounceType,
            'bounce_subtype' => $bounceSubtype,
            'error_message' => $errorMessage,
            'bounced_at' => now(),
        ]);
        return $this;
    }

    /**
     * Mark as failed
     */
    public function markAsFailed(string $errorMessage): self
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $errorMessage,
        ]);
        return $this;
    }

    /**
     * Scope for bounced emails
     */
    public function scopeBounced($query)
    {
        return $query->where('status', self::STATUS_BOUNCED);
    }

    /**
     * Scope for failed emails
     */
    public function scopeFailed($query)
    {
        return $query->whereIn('status', [self::STATUS_BOUNCED, self::STATUS_FAILED]);
    }

    /**
     * Get statistics
     */
    public static function getStats(int $days = 30): array
    {
        $since = now()->subDays($days);

        $stats = self::where('created_at', '>=', $since)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $byType = self::where('created_at', '>=', $since)
            ->selectRaw('type, COUNT(*) as count')
            ->groupBy('type')
            ->pluck('count', 'type')
            ->toArray();

        $total = array_sum($stats);
        $bounced = ($stats[self::STATUS_BOUNCED] ?? 0) + ($stats[self::STATUS_FAILED] ?? 0);

        return [
            'total' => $total,
            'by_status' => [
                'pending' => $stats[self::STATUS_PENDING] ?? 0,
                'sent' => $stats[self::STATUS_SENT] ?? 0,
                'delivered' => $stats[self::STATUS_DELIVERED] ?? 0,
                'bounced' => $stats[self::STATUS_BOUNCED] ?? 0,
                'failed' => $stats[self::STATUS_FAILED] ?? 0,
                'complained' => $stats[self::STATUS_COMPLAINED] ?? 0,
            ],
            'by_type' => $byType,
            'bounce_rate' => $total > 0 ? round(($bounced / $total) * 100, 2) : 0,
            'delivery_rate' => $total > 0 ? round((($stats[self::STATUS_DELIVERED] ?? 0) / $total) * 100, 2) : 0,
        ];
    }
}
