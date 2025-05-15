<?php

namespace Idpromogroup\LaravelOpenAIAssistants\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

class OpenAIAPIService
{
    private Client $client;
    private string $apiKey;

    /**
     * Ключ не обязателен: если не передали – берём из конфига.
     */
    public function __construct(?string $apiKey = null)
    {
        $this->apiKey = $apiKey ?? config('openai-assistants.api_key');

        $this->client = new Client([
            'base_uri' => 'https://api.openai.com/v1/',
            'headers'  => [
                'Authorization' => 'Bearer '.$this->apiKey,
                'OpenAI-Beta'  => 'assistants=v2',
            ],
        ]);
    }

    /* ------------------------------------------------------------------ */
    /*  Запрос-обёртка                                                   */
    /* ------------------------------------------------------------------ */

    public function sendRequest(string $method, string $uri, array $options = []): ?array
    {
        try {
            $response = $this->client->request($method, $uri, $options);
            return json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            $body = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : null;
            Log::error('OpenAI API Request Error', [
                'method' => $method,
                'uri'    => $uri,
                'opts'   => $options,
                'msg'    => $e->getMessage(),
                'body'   => $body,
            ]);
            return ['error' => ['message' => $e->getMessage(), 'response' => $body]];
        } catch (\Throwable $e) {
            Log::error('Unexpected OpenAI API Error', [
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
