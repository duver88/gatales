<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'tokens_monthly',
        'price',
        'hotmart_product_id',
        'features',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'tokens_monthly' => 'integer',
            'price' => 'decimal:2',
            'features' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get subscriptions for this plan
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Find plan by Hotmart product ID
     */
    public static function findByHotmartProductId(string $productId): ?self
    {
        return static::where('hotmart_product_id', $productId)->first();
    }

    /**
     * Get active plans
     */
    public static function active()
    {
        return static::where('is_active', true);
    }

    /**
     * Check if this is the free plan
     */
    public function isFree(): bool
    {
        return $this->slug === 'free' || $this->tokens_monthly === 0;
    }

    /**
     * Get the free plan
     */
    public static function free(): ?self
    {
        return static::where('slug', 'free')->first();
    }
}
