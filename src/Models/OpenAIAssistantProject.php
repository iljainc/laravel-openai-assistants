<?php

namespace Idpromogroup\LaravelOpenAIAssistants\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OpenAIAssistantProject extends Model
{
    use HasFactory; // Можно добавить для фабрик, если планируется тестирование

    protected $table = 'open_ai_assistant_projects'; // Указываем имя таблицы

    // Разрешаем массовое присваивание для всех полей, кроме защищенных ($guarded)
    protected $guarded = [];

    /**
     * Получить файлы Google Docs для проекта.
     */
    public function gdocsFiles(): HasMany
    {
        return $this->hasMany(AssistantProjectGdocsFile::class, 'openai_assistant_project_id');
    }

    // Дополнительные отношения или методы модели могут быть добавлены здесь
}