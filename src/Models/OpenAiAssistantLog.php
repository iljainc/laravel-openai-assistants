<?php

namespace Idpromogroup\LaravelOpenAIAssistants\Models; // Используй свой Vendor Name

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpenAiAssistantLog extends Model // Новое имя класса
{
    use HasFactory;

    protected $table = 'open_ai_assistant_logs'; // Указываем имя таблицы

    protected $guarded = [];

    // Поля, которые должны быть преобразованы из JSON
    protected $casts = [
        // Убедись, что input и output действительно хранятся как JSON в таблице
        // В дампе ff они были text
        // 'input' => 'array',
        // 'output' => 'array',
    ];

    /**
     * Получить вызовы функций, связанные с этим логом.
     */
    public function functionCalls(): HasMany
    {
        // Обновлено имя модели в отношении
        return $this->hasMany(OpenAiAssistantFunctionCall::class, 'log_id');
    }

    /**
     * Получить тред OpenAI Assistant, связанный с этим логом (по openai_assistant_thread_id).
     */
    public function assistantThread(): BelongsTo
    {
        // Обновлено имя модели в отношении
        return $this->belongsTo(OpenAIAssistantThread::class, 'openai_assistant_thread_id');
    }
}