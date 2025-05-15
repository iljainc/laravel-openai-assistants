<?php

namespace Idpromogroup\LaravelOpenAIAssistants\Models; // Используй свой Vendor Name

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OpenAiRequestLog extends Model // Новое имя класса
{
    use HasFactory;

    protected $table = 'open_ai_request_logs'; // Указываем имя таблицы

    protected $guarded = [];

    // Константы для статусов, если нужно
    const STATUS_PENDING = 'pending';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
}