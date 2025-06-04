<?php

namespace Idpromogroup\LaravelOpenAIAssistants\Models;

use Illuminate\Database\Eloquent\Model;

class OpenAIAssistantProjectLog extends Model
{
    protected $table = 'open_ai_assistant_project_logs';

    protected $fillable = [
        'project_id',
        'user_id',
        'name',
        'instructions',
        'model',
        'tools',
        'metadata',
        'openai_assistant_id',
        'openai_api_key',
    ];

    protected $casts = [
        'tools'    => 'array',
        'metadata' => 'array',
    ];

    public function project()
    {
        return $this->belongsTo(OpenAIAssistantProject::class, 'project_id');
    }
}
