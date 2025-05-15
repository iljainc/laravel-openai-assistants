Laravel OpenAI Assistants Package

Installation:
composer require idpromogroup/laravel-openai-assistants
php artisan vendor:publish --provider="Idpromogroup\LaravelOpenAIAssistants\OpenAIAssistantsServiceProvider"

Configuration (config/openai-assistants.php):
'openai_api_key' => env('OPENAI_API_KEY'), // Optional
'debug_mode' => env('OPENAI_DEBUG_MODE', false),

.env:
OPENAI_API_KEY=your-key (Optional)
OPENAI_DEBUG_MODE=true/false

Usage:
use Idpromogroup\LaravelOpenAIAssistants\Facades\OpenAIAssistants;

assistantGet():
$response = OpenAIAssistants::assistantGet($assistantId, $text, $channel, $userId, $msgId, $useThread);
Parameters: $assistantId (string), $text (string), $channel (string), $userId (int), $msgId (?string), $useThread (bool, default: true)

assistant():
$answer = OpenAIAssistants::assistant($assistantId, $text, $userId, 'web');

assistantNoThread():
$response = OpenAIAssistants::assistantNoThread($assistantId, $text, $userId, 'web');

assistantJSON():
$jsonResponse = OpenAIAssistants::assistantJSON($assistantId, $text, $userId, 'web');

Process Service: автоматическое управление параллельными запросами через assistantGet().

Vector Store Management:
OpenAIAssistants::manageAssistantVectorStore($project); // $project - ваша модель OpenAIAssistantProject

