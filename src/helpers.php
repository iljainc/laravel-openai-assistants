<?php

if (! function_exists('assistant_debug')) {
    function assistant_debug(...$vars)
    {
        if (config('openai-assistants.debug_output')) {
            dump(...$vars);
        }
    }
}