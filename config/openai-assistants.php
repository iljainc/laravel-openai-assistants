<?php
// config/openai-assistants.php

return [
    // выводить debug-логи из helper-функции assistant_debug()
    'debug_output'  => env('OPENAI_ASSISTANTS_DEBUG', true),

    // API-ключ по умолчанию (можно переопределить при вызове)
    'api_key'       => env('OPENAI_ASSISTANTS_API_KEY'),

    // сервис, в котором обрабатываются function calls ассистента
    // можно задать класс напрямую или через переменную окружения
    'logic_service' => env(
        'OPENAI_ASSISTANTS_LOGIC_SERVICE',
        \App\Services\AppLogicService::class
    ),
];
