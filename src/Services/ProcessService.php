<?php

namespace Idpromogroup\LaravelOpenAIAssistants\Services; // Используй свой Vendor Name

use Idpromogroup\LaravelOpenAIAssistants\Models\OpenAiRequestLog; // >>> Изменено на новое имя модели <<<
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/*
 * В задачи сервиса входит отслеживание процессов, которые выполняют задания. Задание состоит из канала + пользователя + текста запроса.
 * Одновременно только один процесс(запись в таблице) может быть активен для уникальной комбинации channel + user + message,
 * но сервис позволяет возобновить обработку после небольшого таймаута, даже если предыдущая попытка завершилась.
 * Повторного запроса мы ожидаем из-за логики работы внешних систем (например, Telegram), который в случае ошибки или колдауна повторяет запросы.
 * Задача этого класса — фиксировать все попытки и управлять тем, какая из них является "активной" в данный момент.
 */
class ProcessService
{
    // Статусы из миграции request_logs
    const STATUS_PENDING = 'pending';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';

    private $requestLog;
    private $pid;

    public function __construct()
    {
        $this->pid = mt_rand(10000000, 21474836);
        assistant_debug("ProcessService::__construct() - PID: {$this->pid} created.");
    }

    /**
     * Инициализирует или находит процесс для данного запроса, на основе логики из предоставленного кода.
     *
     * @param string $channel Канал запроса (например, 'telegram', 'web').
     * @param unsignedBigInteger $userId ID пользователя (unsignedBigInteger согласно миграции).
     * @param unsignedBigInteger|null $msgId ID сообщения или запроса из внешнего канала.
     * @param string $requestText Текст запроса.
     * @return bool Возвращает true, если процесс взят в работу этим PID (новый или существующий устаревший), false, если уже активно обрабатывается другим (обновлен менее 2 секунд назад).
     */
    public function init($channel, $userId, $msgId, $requestText): bool
    {
        assistant_debug("ProcessService::init() - Channel: {$channel}, User ID: {$userId}, Message ID: {$msgId}, Request: {$requestText}");

        if (empty($userId) && empty($msgId)) {
            $this->requestLog = OpenAiRequestLog::create([
                'channel' => $channel,
                'user_id' => $userId, // unsignedBigInteger
                'msg_id' => $msgId, // unsignedBigInteger
                'request' => $requestText,
                'pid' => $this->pid,
                // Статус in_progress при создании, как в твоем исходном коде
                'status' => self::STATUS_IN_PROGRESS,
            ]);

            assistant_debug("ProcessService::init() - empty $userId && $msgId - return true");

            return true;
        }

        // Получаем запись по ключу, независимо от статуса
        // >>> Изменено на новое имя модели RequestLog -> OpenAiRequestLog <<<
        $request = OpenAiRequestLog::where('channel', $channel)
            ->where('user_id', $userId)
            ->where('msg_id', $msgId)
            ->first();

        $this->requestLog = $request;

        $currentTime = Carbon::now();

        if ($request) {
            $lastUpdated = Carbon::parse($request->updated_at);
            assistant_debug("ProcessService::init() - Found existing request. Last updated: {$lastUpdated->toDateTimeString()}");

            // Проверяем, если обновляли менее 2 секунд назад.
            // Если да, это очень быстрый повтор, не берем в работу СЕЙЧАС.
            if ($currentTime->diffInSeconds($lastUpdated) < 2) {
                // Добавляем комментарий о получении быстрого повторного запроса к существующей записи
                $this->comment("ProcessService::init - Запрос найден. Обновлен менее 2 сек назад. Считаю активным, отключаюсь.");
                assistant_debug("ProcessService::init() - Request updated less than 2 seconds ago, considering active. Exiting.");
                return false; // Отключаемся
            }

            // Если запрос найден, но устарел (прошло >= 2 сек), берем его в работу.
            // Обновляем PID и статус на in_progress (логика из твоего исходного кода при взятии в работу)
            $request->update([
                'pid' => $this->pid,
                'status' => self::STATUS_IN_PROGRESS, // Снова устанавливаем in_progress при взятии в работу
            ]);
            $this->comment('ProcessService::init - Обнаружен существующий устаревший запрос. Взят в работу этим PID.');
            assistant_debug("ProcessService::init() - Found outdated request. Taking over with PID: {$this->pid}.");
            return true; // Берем в работу

        } else {
            // Если запись не найдена, создаем новую.
            // >>> Изменено на новое имя модели RequestLog -> OpenAiRequestLog <<<
            $this->requestLog = OpenAiRequestLog::create([
                'channel' => $channel,
                'user_id' => $userId, // unsignedBigInteger
                'msg_id' => $msgId, // unsignedBigInteger
                'request' => $requestText,
                'pid' => $this->pid,
                // Статус in_progress при создании, как в твоем исходном коде
                'status' => self::STATUS_IN_PROGRESS,
            ]);

            $this->comment('ProcessService::init - Новый запрос взят в работу');
            assistant_debug("ProcessService::init() - New request taken over with PID: {$this->pid}.");

            return true; // Берем в работу
        }
    }

    /**
     * Добавляет комментарий к логу текущего запроса.
     *
     * @param string $text Текст комментария.
     * @return void
     */
    public function comment($text): void
    {
        if ($this->requestLog) {
            try {
                DB::transaction(function () use ($text) {
                    // Загружаем актуальную запись внутри транзакции
                    // >>> Изменено на новое имя модели RequestLog -> OpenAiRequestLog <<<
                    $currentLog = OpenAiRequestLog::find($this->requestLog->id);

                    if ($currentLog) {
                        $timestamp = Carbon::now()->format('Y-m-d H:i:s.v');
                        $newComment = "[{$timestamp}] [PID:{$this->pid}] - {$text}\n";

                        $updatedComments = ($currentLog->comments ?? '') . $newComment;

                        if (mb_strlen($updatedComments, 'UTF-8') > 30000) {
                            $updatedComments = mb_substr($updatedComments, -30000, null, 'UTF-8');
                        }

                        // Обновляем запись. updated_at обновится автоматически.
                        $currentLog->update([
                            'comments' => $updatedComments,
                        ]);

                        // Обновляем локальный объект
                        $this->requestLog = $currentLog;
                        assistant_debug("ProcessService::comment() - Added comment: {$text} to log ID: {$this->requestLog->id}");
                    } else {
                        assistant_debug("ProcessService::comment() - Could not find RequestLog with ID: {$this->requestLog->id} to add comment: {$text}");
                    }
                });
            } catch (\Exception $e) {
                // Логируем ошибку записи комментария
                Log::error("LaravelOpenAIAssistants: Failed to add comment to RequestLog #" . ($this->requestLog->id ?? 'N/A') . ": " . $e->getMessage(), [
                    'request_log_id' => $this->requestLog->id ?? 'N/A',
                    'comment_text' => $text,
                    'exception' => $e,
                    'pid' => $this->pid
                ]);
                assistant_debug("ProcessService::comment() - Failed to add comment: {$text}. Error: {$e->getMessage()}");
            }
        } else {
            // Если requestLog не установлен
            Log::warning("LaravelOpenAIAssistants: ProcessService::comment called but no active requestLog found.", ['pid' => $this->pid]);
            assistant_debug("ProcessService::comment() - No active requestLog found to add comment: {$text}");
        }
    }

    /**
     * Устанавливает статус процесса как 'failed' и добавляет комментарий об ошибке.
     *
     * @param string $errorMessage Сообщение об ошибке.
     * @return void
     */
    public function logError($errorMessage): void
    {
        $this->comment('Error: ' . $errorMessage);
        if ($this->requestLog) {
            // Устанавливаем статус failed
            $this->requestLog->update(['status' => self::STATUS_FAILED]);
            assistant_debug("ProcessService::logError() - Set status to FAILED for log ID: {$this->requestLog->id}. Error: {$errorMessage}");
        } else {
            Log::warning("LaravelOpenAIAssistants: ProcessService::logError called but no active requestLog found.", ['pid' => $this->pid]);
            assistant_debug("ProcessService::logError() - No active requestLog found to log error: {$errorMessage}");
        }
    }

    /**
     * Завершает текущий процесс, устанавливая статус 'completed'.
     *
     * @return void
     */
    public function close(): void
    {
        if ($this->requestLog) {
            $this->requestLog->update([
                'status' => self::STATUS_COMPLETED,
            ]);
            $this->comment('ProcessService::close - Процесс завершен');
            assistant_debug("ProcessService::close() - Set status to COMPLETED for log ID: {$this->requestLog->id}.");
            $this->requestLog = null;
        } else {
            Log::warning("LaravelOpenAIAssistants: ProcessService::close called but no active requestLog found.", ['pid' => $this->pid]);
            assistant_debug("ProcessService::close() - No active requestLog found to close.");
        }
    }

    /**
     * Обновляет метку времени updated_at для текущего лога запроса.
     * Может использоваться, чтобы показать, что процесс еще жив, если комментарии не добавляются.
     * @return void
     */
    public function checkAndUpdateTimestamp(): void
    {
        if ($this->requestLog) {
            $lastUpdated = Carbon::parse($this->requestLog->updated_at);
            $currentTime = Carbon::now();

            // Проверяем, прошло ли больше 1 секунды с последнего обновления
            if ($currentTime->diffInSeconds($lastUpdated) >= 1) {
                try {
                    // Используем find($this->requestLog->id) для актуальности
                    // >>> Изменено на новое имя модели RequestLog -> OpenAiRequestLog <<<
                    $currentLog = OpenAiRequestLog::find($this->requestLog->id);
                    if ($currentLog) {
                        // Метод touch() обновляет только updated_at
                        $currentLog->touch();
                        $this->requestLog = $currentLog; // Обновляем локальный объект
                        assistant_debug("ProcessService::checkAndUpdateTimestamp() - Updated timestamp for log ID: {$this->requestLog->id}.");
                    } else {
                        assistant_debug("ProcessService::checkAndUpdateTimestamp() - Could not find RequestLog with ID: {$this->requestLog->id} to update timestamp.");
                    }
                } catch (\Exception $e) {
                    Log::error("LaravelOpenAIAssistants: Failed to update timestamp for RequestLog #" . ($this->requestLog->id ?? 'N/A') . ": " . $e->getMessage());
                    assistant_debug("ProcessService::checkAndUpdateTimestamp() - Failed to update timestamp. Error: {$e->getMessage()}");
                }
            }
        }
    }
}