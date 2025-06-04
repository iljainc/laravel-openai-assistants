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

    // Приводим поля tools и metadata к/из JSON автоматически
    protected $casts = [
        'tools'    => 'array',
        'metadata' => 'array',
    ];

    /**
     * Получить файлы Google Docs для проекта.
     */
    public function gdocsFiles(): HasMany
    {
        // Обновлено имя модели в отношении
        return $this->hasMany(OpenAiAssistantProjectGdocsFile::class, 'openai_assistant_project_id');
    }

    public function logState(): void
    {
        \Idpromogroup\LaravelOpenAIAssistants\Models\OpenAIAssistantProjectLog::create([
            'project_id'          => $this->id,
            'user_id'             => $this->user_id,
            'name'                => $this->name,
            'instructions'        => $this->instructions,
            'model'               => $this->model,
            'tools'               => $this->tools,
            'metadata'            => $this->metadata,
            'openai_assistant_id' => $this->openai_assistant_id,
            'openai_api_key'      => $this->openai_api_key,
        ]);
    }
    // Дополнительные отношения или методы могут быть здесь
}