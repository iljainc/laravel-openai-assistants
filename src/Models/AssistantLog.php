<?php

namespace Idpromogroup\LaravelOpenAIAssistants\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssistantLog extends Model
{
    use HasFactory;

    protected $table = 'open_ai_assistant_logs';

    protected $guarded = [];

    // Поля, которые должны быть преобразованы из JSON
    protected $casts = [
        'input' => 'array', // Если input хранится как JSON
        'output' => 'array', // Если output хранится как JSON
        // 'error' => 'array', // Если error хранится как JSON
    ];

    /**
     * Получить вызовы функций, связанные с этим логом.
     */
    public function functionCalls(): HasMany
    {
        return $this->hasMany(AssistantFunctionCall::class, 'log_id');
    }

    /**
     * Получить тред OpenAI Assistant, связанный с этим логом (по openai_assistant_thread_id).
     */
    public function assistantThread(): BelongsTo
    {
        // Убедись, что поле 'openai_assistant_thread_id' существует и соответствует типу в миграции
        return $this->belongsTo(OpenAIAssistantThread::class, 'openai_assistant_thread_id');
    }
}