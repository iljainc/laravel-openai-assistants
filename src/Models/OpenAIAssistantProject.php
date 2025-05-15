<?php

namespace Idpromogroup\LaravelOpenAIAssistants\Models; // Используй свой Vendor Name

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OpenAIAssistantProject extends Model // Имя класса остается
{
    use HasFactory;

    protected $table = 'open_ai_assistant_projects'; // Указываем имя таблицы (совпадает с соглашением)

    protected $guarded = [];

    /**
     * Получить файлы Google Docs для проекта.
     */
    public function gdocsFiles(): HasMany
    {
        // Обновлено имя модели в отношении
        return $this->hasMany(OpenAiAssistantProjectGdocsFile::class, 'openai_assistant_project_id');
    }

    // Дополнительные отношения или методы могут быть здесь
}