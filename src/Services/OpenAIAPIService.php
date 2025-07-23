<?php

namespace Idpromogroup\LaravelOpenAIAssistants\Services;

use function config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAIAPIService
{
    private string $apiKey;

    /**
     * Ключ не обязателен: если не передали – берём из конфига.
     */
    public function __construct(?string $apiKey = null)
    {
        $this->apiKey = $apiKey ?? config('openai-assistants.api_key');
    }

    /* ------------------------------------------------------------------ */
    /*  Запрос-обёртка                                                   */
    /* ------------------------------------------------------------------ */

    public function sendRequest(string $method, string $uri, array $options = []): ?array
    {
        $baseUri = 'https://api.openai.com/v1/';
        $headers = [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'OpenAI-Beta'   => 'assistants=v2',
        ];
        if (isset($options['headers'])) {
            $headers = array_merge($headers, $options['headers']);
        }
        $url = $baseUri . ltrim($uri, '/');
        try {
            $response = match (strtoupper($method)) {
                'GET'    => Http::withHeaders($headers)->get($url, $options['query'] ?? []),
                'POST'   => Http::withHeaders($headers)->post($url, $options['json'] ?? ($options['form_params'] ?? [])),
                'PUT'    => Http::withHeaders($headers)->put($url, $options['json'] ?? ($options['form_params'] ?? [])),
                'PATCH'  => Http::withHeaders($headers)->patch($url, $options['json'] ?? ($options['form_params'] ?? [])),
                'DELETE' => Http::withHeaders($headers)->delete($url, $options['json'] ?? ($options['form_params'] ?? [])),
                default  => throw new \InvalidArgumentException('Unsupported HTTP method'),
            };
            $decoded = $response->json();
            if (isset($decoded['error'])) {
                Log::error('OpenAI API Response Contains Error', [
                    'method'  => $method,
                    'uri'     => $uri,
                    'opts'    => $options,
                    'error'   => $decoded['error'],
                ]);
            }
            return $decoded;
        } catch (\Throwable $e) {
            Log::error('OpenAI API Request Error', [
                'method' => $method,
                'uri'    => $uri,
                'opts'   => $options,
                'msg'    => $e->getMessage(),
            ]);
            return ['error' => ['message' => $e->getMessage()]];
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Шорткаты API                                                     */
    /* ------------------------------------------------------------------ */

    public function createThread(): ?array
    {
        return $this->sendRequest('POST', 'threads');
    }

    public function addMessageToThread(string $threadId, string $role, string $content): ?array
    {
        return $this->sendRequest('POST', "threads/{$threadId}/messages", [
            'json' => compact('role', 'content'),
        ]);
    }

    public function runThread(string $threadId, string $assistantId): ?array
    {
        return $this->sendRequest('POST', "threads/{$threadId}/runs", [
            'json' => ['assistant_id' => $assistantId],
        ]);
    }

    public function getRun(string $threadId, string $runId): ?array
    {
        return $this->sendRequest('GET', "threads/{$threadId}/runs/{$runId}");
    }
    
    public function getThreadMessages(string $threadId): ?array
    {
        return $this->sendRequest('GET', "threads/{$threadId}/messages");
    }
}
