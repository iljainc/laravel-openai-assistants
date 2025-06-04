<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Обновление основной таблицы
        Schema::table('open_ai_assistant_projects', function (Blueprint $table) {
            if (Schema::hasColumn('open_ai_assistant_projects', 'project_name') &&
                !Schema::hasColumn('open_ai_assistant_projects', 'name')) {
                $table->renameColumn('project_name', 'name');
            } elseif (!Schema::hasColumn('open_ai_assistant_projects', 'name')) {
                $table->string('name')->after('id');
            }

            if (!Schema::hasColumn('open_ai_assistant_projects', 'instructions')) {
                $table->text('instructions')->nullable()->after('name');
            }

            if (!Schema::hasColumn('open_ai_assistant_projects', 'model')) {
                $table->string('model')->default('gpt-4o')->after('instructions');
            }

            if (!Schema::hasColumn('open_ai_assistant_projects', 'tools')) {
                $table->json('tools')->nullable()->after('model');
            }

            if (!Schema::hasColumn('open_ai_assistant_projects', 'metadata')) {
                $table->json('metadata')->nullable()->after('tools');
            }
        });

        // Таблица логов всех изменений (с перечислением всех ключевых полей)
        Schema::create('open_ai_assistant_project_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('user_id')->nullable()->comment('Кто сделал изменение');

            $table->string('name');
            $table->text('instructions')->nullable();
            $table->string('model')->default('gpt-4o');
            $table->json('tools')->nullable();
            $table->json('metadata')->nullable();
            $table->string('openai_assistant_id');
            $table->string('openai_api_key')->nullable();

            $table->timestamps();

            $table->foreign('project_id')
                ->references('id')
                ->on('open_ai_assistant_projects')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('open_ai_assistant_projects', function (Blueprint $table) {
            $columns = ['instructions', 'model', 'tools', 'metadata'];
            foreach ($columns as $col) {
                if (Schema::hasColumn('open_ai_assistant_projects', $col)) {
                    $table->dropColumn($col);
                }
            }

            if (Schema::hasColumn('open_ai_assistant_projects', 'name') &&
                !Schema::hasColumn('open_ai_assistant_projects', 'project_name')) {
                $table->renameColumn('name', 'project_name');
            }
        });

        Schema::dropIfExists('open_ai_assistant_project_logs');
    }
};
