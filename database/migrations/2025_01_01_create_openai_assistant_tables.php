<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Таблица open_ai_assistant_projects
        if (!Schema::hasTable('open_ai_assistant_projects')) {
            Schema::create('open_ai_assistant_projects', function (Blueprint $table) {
                $table->id();
                $table->string('project_name');
                $table->string('telegram_api_key')->nullable();
                $table->string('telegram_secret_token')->nullable();
                $table->string('openai_api_key')->nullable();
                $table->string('openai_assistant_id');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->timestamps();
            });
        } else {
            // Если таблица существует, добавляем только недостающие колонки
            Schema::table('open_ai_assistant_projects', function (Blueprint $table) {
                if (!Schema::hasColumn('open_ai_assistant_projects', 'project_name')) {
                    $table->string('project_name')->after('id'); // Пример: добавить после id
                }
                if (!Schema::hasColumn('open_ai_assistant_projects', 'telegram_api_key')) {
                    $table->string('telegram_api_key')->nullable()->after('project_name');
                }
                if (!Schema::hasColumn('open_ai_assistant_projects', 'telegram_secret_token')) {
                    $table->string('telegram_secret_token')->nullable()->after('telegram_api_key');
                }
                if (!Schema::hasColumn('open_ai_assistant_projects', 'openai_api_key')) {
                    $table->string('openai_api_key')->nullable()->after('telegram_secret_token');
                }
                if (!Schema::hasColumn('open_ai_assistant_projects', 'openai_assistant_id')) {
                    $table->string('openai_assistant_id')->after('openai_api_key');
                }
                if (!Schema::hasColumn('open_ai_assistant_projects', 'user_id')) {
                    $table->unsignedBigInteger('user_id')->nullable()->after('openai_assistant_id');
                }
                // timestamps() обычно добавляются автоматически при создании,
                // но если их нет, нужно добавить вручную, но это сложнее
                // if (!Schema::hasColumn('open_ai_assistant_projects', 'created_at')) {
                //      $table->timestamp('created_at')->nullable();
                //      $table->timestamp('updated_at')->nullable();
                // }
            });
        }

        // Таблица open_ai_assistant_project_gdocs_file
        if (!Schema::hasTable('open_ai_assistant_project_gdocs_file')) {
            Schema::create('open_ai_assistant_project_gdocs_file', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('openai_assistant_project_id');
                $table->string('file_url');
                $table->string('file_type');
                $table->string('gdocs_file_hash')->nullable();
                $table->string('vector_store_file_id')->nullable();
                $table->timestamps();
            });
        } else {
            Schema::table('open_ai_assistant_project_gdocs_file', function (Blueprint $table) {
                if (!Schema::hasColumn('open_ai_assistant_project_gdocs_file', 'openai_assistant_project_id')) {
                    $table->unsignedBigInteger('openai_assistant_project_id')->after('id');
                }
                if (!Schema::hasColumn('open_ai_assistant_project_gdocs_file', 'file_url')) {
                    $table->string('file_url')->after('openai_assistant_project_id');
                }
                if (!Schema::hasColumn('open_ai_assistant_project_gdocs_file', 'file_type')) {
                    $table->string('file_type')->after('file_url');
                }
                if (!Schema::hasColumn('open_ai_assistant_project_gdocs_file', 'gdocs_file_hash')) {
                    $table->string('gdocs_file_hash')->nullable()->after('file_type');
                }
                if (!Schema::hasColumn('open_ai_assistant_project_gdocs_file', 'vector_store_file_id')) {
                    $table->string('vector_store_file_id')->nullable()->after('gdocs_file_hash');
                }
                // timestamps() аналогично open_ai_assistant_projects
            });
        }


        // Таблица open_ai_assistant_threads
        if (!Schema::hasTable('open_ai_assistant_threads')) {
            Schema::create('open_ai_assistant_threads', function (Blueprint $table) {
                $table->id();
                $table->string('assistant_id');
                $table->unsignedBigInteger('user_id')->nullable(); // unsignedBigInteger
                $table->string('thread_id');
                $table->string('run_id')->nullable()->comment('ID of the active run');
                $table->unsignedInteger('counter')->default(0);
                $table->timestamps();
            });
        } else {
            Schema::table('open_ai_assistant_threads', function (Blueprint $table) {
                if (!Schema::hasColumn('open_ai_assistant_threads', 'assistant_id')) {
                    $table->string('assistant_id')->after('id');
                }
                // Проверяем тип user_id, но изменить его автоматически сложно и рискованно
                // if (!Schema::hasColumn('open_ai_assistant_threads', 'user_id') || Schema::getColumnType('open_ai_assistant_threads', 'user_id') !== 'bigint unsigned') {
                //     $table->unsignedBigInteger('user_id')->nullable()->change(); // ->change() может быть рискованным
                // }
                if (!Schema::hasColumn('open_ai_assistant_threads', 'user_id')) { // Добавляем только если нет
                    $table->unsignedBigInteger('user_id')->nullable()->after('assistant_id');
                }

                if (!Schema::hasColumn('open_ai_assistant_threads', 'thread_id')) {
                    $table->string('thread_id')->after('user_id');
                }
                if (!Schema::hasColumn('open_ai_assistant_threads', 'run_id')) {
                    $table->string('run_id')->nullable()->after('thread_id')->comment('ID of the active run');
                }
                if (!Schema::hasColumn('open_ai_assistant_threads', 'counter')) {
                    $table->unsignedInteger('counter')->default(0)->after('run_id');
                }
                // timestamps() аналогично
            });
        }

        // Таблица open_ai_request_logs (ВСЕГДА СОЗДАЕТСЯ)
        Schema::create('open_ai_request_logs', function (Blueprint $table) {
            $table->id();
            $table->string('channel');
            $table->unsignedBigInteger('user_id'); // unsignedBigInteger
            $table->unsignedBigInteger('msg_id')->nullable();
            $table->text('request');
            $table->integer('pid')->nullable();
            $table->enum('status', ['pending', 'in_progress', 'completed', 'failed'])->default('pending');
            $table->text('comments')->nullable();
            $table->timestamps();
        });

        // Таблица open_ai_assistant_logs (ВСЕГДА СОЗДАЕТСЯ)
        Schema::create('open_ai_assistant_logs', function (Blueprint $table) {
            $table->id();
            $table->string('assistant_id');
            $table->unsignedBigInteger('user_id')->nullable(); // unsignedBigInteger
            $table->unsignedBigInteger('openai_assistant_thread_id')->nullable(); // Ссылка на open_ai_assistant_threads.id
            $table->text('input');
            $table->text('output');
            $table->string('error')->nullable();
            $table->decimal('execution_time', 8, 2)->nullable()->comment('Execution time in seconds');
            $table->bigInteger('start_time_ms')->nullable()->comment('Start time in milliseconds');
            $table->bigInteger('end_time_ms')->nullable()->comment('End time in milliseconds');
            $table->timestamps();
        });

        // Таблица open_ai_assistant_function_calls (ВСЕГДА СОЗДАЕТСЯ)
        Schema::create('open_ai_assistant_function_calls', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('log_id')->nullable()->comment('Связь с open_ai_assistant_logs');
            $table->string('run_id');
            $table->string('function_name');
            $table->json('arguments');
            $table->json('output')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('open_ai_assistant_function_calls');
        Schema::dropIfExists('open_ai_assistant_logs');
        Schema::dropIfExists('open_ai_request_logs');
        Schema::dropIfExists('open_ai_assistant_threads');
        Schema::dropIfExists('open_ai_assistant_project_gdocs_file');
        Schema::dropIfExists('open_ai_assistant_projects');
    }
};