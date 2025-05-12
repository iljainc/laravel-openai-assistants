<?php

namespace Idpromogroup\LaravelOpenAIAssistants\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssistantProjectGdocsFile extends Model
{
    use HasFactory;

    protected $table = 'open_ai_assistant_project_gdocs_file';

    protected $guarded = [];

    /**
     * Получить проект, которому принадлежит файл.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(OpenAIAssistantProject::class, 'openai_assistant_project_id');
    }
}