<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

class Assistant extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_active',
        'is_default',
        'model',
        'system_prompt',
        'temperature',
        'max_tokens',
        'top_p',
        'frequency_penalty',
        'presence_penalty',
        'response_format',
        'stop_sequences',
        'seed',
        'n_completions',
        'logprobs',
        'stream',
        'assistant_display_name',
        'welcome_message',
        'context_messages',
        'filter_unsafe_content',
        'include_user_id',
        'avatar_url',
        'openai_assistant_id',
        'openai_vector_store_id',
        'use_knowledge_base',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'temperature' => 'decimal:2',
        'max_tokens' => 'integer',
        'top_p' => 'decimal:2',
        'frequency_penalty' => 'decimal:2',
        'presence_penalty' => 'decimal:2',
        'seed' => 'integer',
        'n_completions' => 'integer',
        'logprobs' => 'boolean',
        'stream' => 'boolean',
        'context_messages' => 'integer',
        'filter_unsafe_content' => 'boolean',
        'include_user_id' => 'boolean',
        'use_knowledge_base' => 'boolean',
    ];

    /**
     * Get users assigned to this assistant
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get files attached to this assistant (knowledge base)
     */
    public function files(): HasMany
    {
        return $this->hasMany(AssistantFile::class);
    }

    /**
     * Get only ready files
     */
    public function readyFiles(): HasMany
    {
        return $this->files()->where('status', 'ready');
    }

    /**
     * Check if assistant has knowledge base enabled and files
     */
    public function hasKnowledgeBase(): bool
    {
        return $this->use_knowledge_base && $this->openai_assistant_id && $this->files()->ready()->exists();
    }

    /**
     * Check if assistant needs OpenAI Assistants API (has knowledge base)
     */
    public function usesAssistantsApi(): bool
    {
        return $this->use_knowledge_base && $this->openai_assistant_id !== null;
    }

    /**
     * Scope for active assistants
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get the default assistant (cached)
     */
    public static function getDefault(): ?self
    {
        return Cache::remember('default_assistant', 3600, function () {
            return static::where('is_default', true)->first();
        });
    }

    /**
     * Set an assistant as the default
     */
    public static function setDefault(int $id): void
    {
        // Remove default from all
        static::where('is_default', true)->update(['is_default' => false]);

        // Set new default
        static::where('id', $id)->update(['is_default' => true]);

        // Clear cache
        Cache::forget('default_assistant');
    }

    /**
     * Convert assistant settings to array format compatible with OpenAIService
     */
    public function toSettingsArray(): array
    {
        return [
            'model' => $this->model,
            'system_prompt' => $this->system_prompt,
            'temperature' => (string) $this->temperature,
            'max_tokens' => (string) $this->max_tokens,
            'top_p' => (string) $this->top_p,
            'frequency_penalty' => (string) $this->frequency_penalty,
            'presence_penalty' => (string) $this->presence_penalty,
            'response_format' => $this->response_format,
            'stop_sequences' => $this->stop_sequences ?? '',
            'seed' => $this->seed ? (string) $this->seed : '',
            'n_completions' => (string) $this->n_completions,
            'logprobs' => $this->logprobs ? 'true' : 'false',
            'stream' => $this->stream ? 'true' : 'false',
            'assistant_name' => $this->assistant_display_name,
            'welcome_message' => $this->welcome_message,
            'context_messages' => (string) $this->context_messages,
            'filter_unsafe_content' => $this->filter_unsafe_content ? 'true' : 'false',
            'include_user_id' => $this->include_user_id ? 'true' : 'false',
        ];
    }

    /**
     * Get list of available models
     */
    public static function getAvailableModels(): array
    {
        return [
            // Modelos principales
            'gpt-4o-mini' => 'GPT-4o Mini (Recomendado - Economico)',
            'gpt-4o' => 'GPT-4o (Mejor calidad)',
            'gpt-4-turbo' => 'GPT-4 Turbo',
            'gpt-3.5-turbo' => 'GPT-3.5 Turbo (Muy economico)',
            // GPT-5
            'gpt-5' => 'GPT-5 (Ultimo modelo)',
            // Modelos de razonamiento (o1)
            'o1' => 'o1 (Razonamiento avanzado)',
            'o1-mini' => 'o1-mini (Razonamiento rapido)',
            'o1-preview' => 'o1-preview',
        ];
    }

    /**
     * Clear cache when assistant is updated
     */
    protected static function booted()
    {
        static::updated(function ($assistant) {
            if ($assistant->is_default) {
                Cache::forget('default_assistant');
            }
        });

        static::deleted(function ($assistant) {
            if ($assistant->is_default) {
                Cache::forget('default_assistant');
            }
        });
    }
}
