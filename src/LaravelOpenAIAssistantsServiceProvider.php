<?php

namespace Idpromogroup\LaravelOpenAIAssistants; // Убедись, что это твой Vendor Name

use Illuminate\Support\ServiceProvider;

class LaravelOpenAIAssistantsServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register(): void
    {
        // Здесь регистрируются сервисы в контейнере Laravel
        // Например: $this->app->singleton(OpenAIService::class, function ($app) { ... });
        // Или загрузка файла конфигурации пакета
        // $this->mergeConfigFrom(__DIR__.'/../config/openai-assistants.php', 'openai-assistants');
    }

    /**
     * Bootstrap any package services.
     *
     * @return void
     */
    public function boot(): void
    {
        // Загрузка миграций пакета
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Здесь можно публиковать ресурсы пакета:
        // Конфигурация:
        // $this->publishes([
        //     __DIR__.'/../config/openai-assistants.php' => config_path('openai-assistants.php'),
        // ], 'openai-assistants-config');

        // Миграции (хотя loadMigrationsFrom часто достаточно, publish позволяет копировать файлы)
        // $this->publishes([
        //     __DIR__.'/../database/migrations/' => database_path('migrations'),
        // ], 'openai-assistants-migrations');

        // Загрузка роутов пакета:
        // $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        // $this->loadRoutesFrom(__DIR__.'/../routes/api.php');

        // Загрузка представлений (views):
        // $this->loadViewsFrom(__DIR__.'/../resources/views', 'openai-assistants');

        // Публикация представлений:
        // $this->publishes([
        //     __DIR__.'/../resources/views' => resource_path('views/vendor/openai-assistants'),
        // ], 'openai-assistants-views');
    }
}