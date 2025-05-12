<?php

namespace Idpromogroup\LaravelOpenAIAssistants\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OpenAIAssistantThread extends Model
{
    use HasFactory;

    protected $table = 'open_ai_assistant_threads';

    protected $guarded = [];

    // Если нужно проверить, истек ли срок жизни треда (например, 24 часа)
    public function isExpired(): bool
    {
        // В OpenAI треды сейчас живут 24 часа после последнего сообщения.
        // Можно добавить логику проверки, основанную на updated_at или созданном поле expires_at
        return $this->updated_at->diffInHours() >= 24;
    }
}