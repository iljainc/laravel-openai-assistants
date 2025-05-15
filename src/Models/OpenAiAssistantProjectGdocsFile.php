<?php

namespace Idpromogroup\LaravelOpenAIAssistants\Models; // Используй свой Vendor Name

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpenAiAssistantProjectGdocsFile extends Model // Новое имя класса
{
    use HasFactory;

    protected $table = 'open_ai_assistant_project_gdocs_file'; // Указываем имя таблицы

    protected $guarded = [];

    /**
     * Получить проект, которому принадлежит файл.
     */
    public function project(): BelongsTo
    {
        // Имя модели в отношении остается, так как она не менялась
        return $this->belongsTo(OpenAIAssistantProject::class, 'openai_assistant_project_id');
    }
}