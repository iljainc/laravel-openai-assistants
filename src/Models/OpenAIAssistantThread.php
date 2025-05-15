<?php

namespace Idpromogroup\LaravelOpenAIAssistants\Models; // Используй свой Vendor Name

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany; // Добавил HasMany

class OpenAIAssistantThread extends Model // Имя класса остается
{
    use HasFactory;

    protected $table = 'open_ai_assistant_threads'; // Указываем имя таблицы (совпадает с соглашением)

    protected $guarded = [];

    /**
     * Получить логи Assistant Log, связанные с этим тредом.
     */
    public function assistantLogs(): HasMany
    {
        // Обновлено имя модели в отношении
        // Связь по openai_assistant_thread_id в AssistantLog
        return $this->hasMany(OpenAiAssistantLog::class, 'openai_assistant_thread_id');
    }

    // Если нужно проверить, истек ли срок жизни треда (например, 24 часа)
    public function isExpired(): bool
    {
        // В OpenAI треды сейчас живут 24 часа после последнего сообщения.
        // Можно добавить логику проверки, основанную на updated_at или созданном поле expires_at
        return $this->updated_at->diffInHours() >= 24;
    }
}