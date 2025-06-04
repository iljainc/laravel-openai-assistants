<?php

namespace Idpromogroup\LaravelOpenAIAssistants\Services;

use Idpromogroup\LaravelOpenAIAssistants\Models\AssistantFunctionCall;
use Idpromogroup\LaravelOpenAIAssistants\Models\OpenAiAssistantLog;
use Idpromogroup\LaravelOpenAIAssistants\Models\OpenAIAssistantThread;

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
    ) {
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
    ) {
        $res = $this->assistantGet($assistantId, $text, $channel, $userId, $msgId, false);

        return $res['data'][0]["content"][0]["text"]["value"] ?? 'System error';
    }

    public function assistantJSON(
        string $assistantId,
        string $text,
        string $channel = 'api',
        int    $userId  = 0,
        int    $msgId   = 0,
        bool   $useThread = true
    ) {
        $output = $this->assistantGet($assistantId, $text, $channel, $userId, $msgId, $useThread);
        if ($output['status'] ?? null === 'Already in work') {
            return $output;
        }
        $plain  = $output['data'][0]['content'][0]['text']['value'] ?? null;
        return $this->responseToJSON($plain);
    }

    /* --------------------------------------------------------------------
     |  CORE
     |------------------------------------------------------------------- */

    public function createAssistant(array $data): OpenAIAssistantProject
    {
        $response = $this->apiService->sendRequest('POST', 'assistants', [
            'json' => [
                'name'         => $data['name'] ?? null,
                'instructions' => $data['instructions'] ?? '',
                'model'        => $data['model'] ?? 'gpt-4o',
                'tools'        => $data['tools'] ?? [],
                'metadata'     => $data['metadata'] ?? [],
            ],
        ]);

        $project = OpenAIAssistantProject::create([
            'name'                => $response['name'] ?? $data['name'] ?? null,
            'instructions'        => $response['instructions'] ?? $data['instructions'] ?? '',
            'model'               => $response['model'] ?? $data['model'] ?? 'gpt-4o',
            'tools'               => $response['tools'] ?? $data['tools'] ?? [],
            'metadata'            => $response['metadata'] ?? $data['metadata'] ?? [],
            'openai_assistant_id' => $response['id'],
            'openai_api_key'      => $data['openai_api_key'] ?? null,
            'user_id'             => $data['user_id'] ?? null,
        ]);

        $project->logState();

        return $project;
    }

    public function updateAssistant(OpenAIAssistantProject $project, array $data): OpenAIAssistantProject
    {
        $payload = [
            'name'         => $data['name']         ?? $project->name,
            'instructions' => $data['instructions'] ?? $project->instructions,
            'model'        => $data['model']        ?? $project->model,
            'tools'        => $data['tools']        ?? $project->tools,
            'metadata'     => $data['metadata']     ?? $project->metadata,
        ];

        $response = $this->apiService->sendRequest('POST', "assistants/{$project->openai_assistant_id}", [
            'json' => $payload,
        ]);

        $project->update([
            'name'         => $response['name']         ?? $payload['name'],
            'instructions' => $response['instructions'] ?? $payload['instructions'],
            'model'        => $response['model']        ?? $payload['model'],
            'tools'        => $response['tools']        ?? $payload['tools'],
            'metadata'     => $response['metadata']     ?? $payload['metadata'],
        ]);

        $project->logState();

        return $project;
    }

    private function assistantGet(
        string  $assistantId,
        string  $text,
        string  $channel,
        int     $userId,
        int     $msgId,
        bool    $useThread = true
    ) {
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

        assistant_debug("OpenAIService::assistantGet() - init run ".($thread->run_id ?? 'false'));

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
