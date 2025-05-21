<?php

namespace Idpromogroup\LaravelOpenAIAssistants;

use Idpromogroup\LaravelOpenAIAssistants\Services\OpenAIAPIService;
use Idpromogroup\LaravelOpenAIAssistants\Services\OpenAIService;
use Idpromogroup\LaravelOpenAIAssistants\Services\VectorStoreManagementService;
use Illuminate\Foundation\AliasLoader;
use Illuminate\Support\ServiceProvider;

class LaravelOpenAIAssistantsServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if (!config()->has('openai-assistants')) {
            throw new \RuntimeException(
                'Missing config file: please publish the package config using `php artisan vendor:publish --tag=openai-assistants-config`'
            );
        }

        if (empty(config('openai-assistants.api_key'))) {
            throw new \RuntimeException(
                'Missing OpenAI API key: please set OPENAI_API_KEY in your .env file or openai-assistants.php config.'
            );
        }

        // Биндинг «дефолтного» клиента OpenAI
        $this->app->bind(OpenAIAPIService::class, fn () =>
            new OpenAIAPIService(config('openai-assistants.api_key'))
        );

        // Управление векторным хранилищем
        $this->app->bind(VectorStoreManagementService::class, fn ($app) =>
            new VectorStoreManagementService($app->make(OpenAIAPIService::class))
        );

        // Главный сервис ассистента
        $this->app->bind(OpenAIService::class, function ($app) {
            $logicClass = config('openai-assistants.logic_service');
            return new OpenAIService(
                $app->make(OpenAIAPIService::class),   // уже с ключом из конфига
                $app->make($logicClass)
            );
        });

        $this->mergeConfigFrom(
            __DIR__ . '/../config/openai-assistants.php',
            'openai-assistants'
        );
    }

    /**
     * Bootstrap any package services.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->publishes([
            __DIR__ . '/../config/openai-assistants.php' => config_path('openai-assistants.php'),
        ], 'openai-assistants-config');

        require __DIR__ . '/helpers.php';

        if ($this->app->runningInConsole()) {
            $this->commands([
                \Idpromogroup\LaravelOpenAIAssistants\Console\Commands\UploadGdocsToOpenAI::class,
            ]);
        }

        // Регистрация фасада
        $this->app->booting(function () {
            AliasLoader::getInstance()->alias(
                'OpenAIAssistants',
                \Idpromogroup\LaravelOpenAIAssistants\Facades\OpenAIAssistants::class
            );
        });
    }
}
