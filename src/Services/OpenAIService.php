<?php

namespace Idpromogroup\LaravelOpenAIAssistants\Services;

use Idpromogroup\LaravelOpenAIAssistants\Models\AssistantFunctionCall;
use Idpromogroup\LaravelOpenAIAssistants\Models\OpenAiAssistantLog;
use Idpromogroup\LaravelOpenAIAssistantThread;
use Illuminate\Support\Facades\Log;

class OpenAIService
{
    private OpenAIAPIService $apiService;
    private $appLogicService;

    public function __construct(OpenAIAPIService $apiService, $appLogicService)
    {
        $this->apiService      = $apiService;
        $this->appLogicService = $appLogicService;
    }

    /* --------------------------------------------------------------------
     |  PUBLIC API
     |------------------------------------------------------------------- */

    public function assistant(
        string $assistantId,
        string $text,
        string $channel = 'api',
        int    $userId  = 0,
        int    $msgId   = 0
    ): ?string {
        $res = $this->assistantGet($assistantId, $text, $channel, $userId, $msgId);
        return $res['data'][0]['content'][0]['text']['value']
            ?? ($res['status'] ?? 'System error');
    }

    public function assistantNoThread(
        string $assistantId,
        string $text,
        string $channel = 'api',
        int    $userId  = 0,
        int    $msgId   = 0
    ): ?array {
        return $this->assistantGet($assistantId, $text, $channel, $userId, $msgId, false);
    }

    public function assistantJSON(
        string $assistantId,
        string $text,
        string $channel = 'api',
        int    $userId  = 0,
        int    $msgId   = 0
    ): ?array {
        $output = $this->assistantGet($assistantId, $text, $channel, $userId, $msgId);
        if ($output['status'] ?? null === 'Already in work') {
            return $output;
        }
        $plain  = $output['data'][0]['content'][0]['text']['value'] ?? null;
        return $this->responseToJSON($plain);
    }

    /* --------------------------------------------------------------------
     |  CORE
     |------------------------------------------------------------------- */

    private function assistantGet(
        string  $assistantId,
        string  $text,
        string  $channel,
        int     $userId,
        int     $msgId,
        bool    $useThread = true
    ): ?array {
        $apiKey              = config('openai-assistants.api_key');
        $this->apiService    = new OpenAIAPIService($apiKey);

        $processService      = app(ProcessService::class);
        if (! $processService->init($channel, $userId, $msgId, $text)) {
            return ['status' => 'Already in work'];
        }

        $startTime = round(microtime(true) * 1000);

        /* ---------- thread init ------------------------------------------------- */
        $thread = false;
        if ($useThread) {
            $thread = OpenAIAssistantThread::where('assistant_id', $assistantId)
                ->where('user_id', $userId)
                ->first();

            if ($thread && $thread->isExpired()) {
                $thread->delete();
                $thread = null;
            }
        }

        if (! $thread) {
            $threadData = $this->apiService->createThread();
            $thread     = OpenAIAssistantThread::create([
                'assistant_id' => $assistantId,
                'user_id'      => $userId,
                'thread_id'    => $threadData['id'],
            ]);
        }

        /* ---------- log --------------------------------------------------------- */
        $log = OpenAiAssistantLog::create([
            'assistant_id'               => $assistantId,
            'user_id'                    => $userId,
            'input'                      => $text,
            'start_time_ms'              => $startTime,
            'output'                     => '',
            'openai_assistant_thread_id' => $thread->id,
        ]);

        /* ---------- run control -------------------------------------------------- */
        if ($thread->run_id) {
            $run = $this->apiService->getRun($thread->thread_id, $thread->run_id);
            if (in_array($run['status'], ['completed', 'cancelled', 'expired', 'failed'])) {
                $thread->update(['run_id' => null]);
            }
        }

        if (! $thread->run_id) {
            $this->apiService->addMessageToThread($thread->thread_id, 'user', $text);
            $runResponse      = $this->apiService->runThread($thread->thread_id, $assistantId);
            $thread->run_id   = $runResponse['id'] ?? null;
            $thread->save();
        }

        $res = '';
        if ($thread->run_id) {
            for ($i = 0; $i < 600; $i++) {
                $processService->comment("OpenAIService:: checkRunStatus {$thread->run_id}");

                $run = $this->apiService->getRun($thread->thread_id, $thread->run_id);

                if ($run['status'] === 'completed') {
                    $res = $this->apiService->getThreadMessages($thread->thread_id);
                    if (($res['data'][0]['role'] ?? null) === 'assistant') {
                        $thread->update(['run_id' => null]);
                        break;
                    }
                }

                if ($run['status'] === 'requires_action') {
                    $this->handleFunctionCall($thread, $run, $log);
                }

                if (in_array($run['status'], ['cancelled', 'expired', 'failed'])) {
                    $this->finalizeLog($log, $startTime, 'Error status: '.$run['status']);
                    $processService->close();
                    return null;
                }

                usleep(300_000); // 0.3 s
            }
        }

        $this->finalizeLog($log, $startTime, json_encode($res));
        $processService->close();
        return $res;
    }

    /* --------------------------------------------------------------------
     |  HELPERS
     |------------------------------------------------------------------- */

    private function responseToJSON(?string $response): ?array
    {
        if (! $response) {
            return null;
        }
        $response = trim($response, "```json\n");
        $response = trim($response, "``` \n");
        return json_decode($response, true);
    }

    private function finalizeLog(OpenAiAssistantLog $log, int $start, string $output): void
    {
        $end = round(microtime(true) * 1000);
        $log->update([
            'output'          => $output,
            'execution_time'  => round(($end - $start) / 1000, 2),
            'end_time_ms'     => $end,
        ]);
    }

    private function handleFunctionCall(
        OpenAIAssistantThread $thread,
        array                 $run,
        OpenAiAssistantLog    $log
    ): ?array {
        if (empty($run['required_action']['submit_tool_outputs']['tool_calls'])) {
            return null;
        }

        $toolOutputs = [];
        foreach ($run['required_action']['submit_tool_outputs']['tool_calls'] as $call) {
            $functionName   = $call['function']['name'];
            $argumentsJson  = $call['function']['arguments'];

            $functionLog = AssistantFunctionCall::create([
                'log_id'        => $log->id,
                'run_id'        => $thread->run_id,
                'function_name' => $functionName,
                'arguments'     => $argumentsJson,
                'status'        => 'pending',
            ]);

            $arguments = json_decode($argumentsJson, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('JSON Decode Error', ['error' => json_last_error_msg()]);
                return null;
            }

            $result = $this->appLogicService->execute($functionName, $arguments);

            $toolOutputs[] = [
                'tool_call_id' => $call['id'],
                'output'       => json_encode($result) ?: 'Error',
            ];

            $functionLog->update([
                'output' => json_encode($result),
                'status' => 'success',
            ]);
        }

        if (empty($toolOutputs)) {
            return null;
        }

        $data = ['json' => ['tool_outputs' => $toolOutputs]];

        // Отправляем результаты всех вызовов
        return $this->apiService->sendRequest(
            'POST',
            "threads/{$thread->thread_id}/runs/{$thread->run_id}/submit_tool_outputs",
            $data
        );
    }
}
