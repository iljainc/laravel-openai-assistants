<?php

namespace Idpromogroup\LaravelOpenAIAssistants\Models; // Используй свой Vendor Name

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpenAiAssistantFunctionCall extends Model // Новое имя класса
{
    use HasFactory;

    protected $table = 'open_ai_assistant_function_calls'; // Указываем имя таблицы

    protected $guarded = [];

    // Поля, которые должны быть преобразованы из JSON
    protected $casts = [
        'arguments' => 'array',
        'output' => 'array',
    ];

    /**
     * Получить лог Assistant Log, которому принадлежит этот вызов функции.
     */
    public function assistantLog(): BelongsTo
    {
        // Обновлено имя модели в отношении
        return $this->belongsTo(OpenAiAssistantLog::class, 'log_id');
    }
}