<?php

if (! function_exists('assistant_debug')) {
    function assistant_debug(...$vars)
    {
        if (config('openai-assistants.debug_output')) {
            if (\Illuminate\Support\Facades\App::runningInConsole()) {
                foreach ($vars as $var) {
                    echo print_r($var, true) . PHP_EOL;
                }
            } else {
                foreach ($vars as $var) {
                    var_dump($var);
                }
            }
        }
    }
}

if (!function_exists('assistant_debug_error')) {
    function assistant_debug_error(...$vars)
    {
        if (config('openai-assistants.debug_output')) {
            if (\Illuminate\Support\Facades\App::runningInConsole()) {
                foreach ($vars as $var) {
                    // Красный цвет в консоли: \033[31m ... \033[0m
                    echo "\033[31m" . print_r($var, true) . "\033[0m" . PHP_EOL;
                }
            } else {
                echo '<span style="color:red">';
                foreach ($vars as $var) {
                    var_dump($var);
                }
                echo '</span>';
            }
        }
    }
}