<?php

namespace Idpromogroup\LaravelOpenAIAssistants\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssistantFunctionCall extends Model
{
    use HasFactory;

    protected $table = 'open_ai_assistant_function_calls';

    protected $guarded = [];

    // Поля, которые должны быть преобразованы из JSON
    protected $casts = [
        'arguments' => 'array', // Аргументы вызова
        'output' => 'array', // Результат выполнения
    ];

    /**
     * Получить лог Assistant Log, которому принадлежит этот вызов функции.
     */
    public function assistantLog(): BelongsTo
    {
        return $this->belongsTo(AssistantLog::class, 'log_id');
    }
}