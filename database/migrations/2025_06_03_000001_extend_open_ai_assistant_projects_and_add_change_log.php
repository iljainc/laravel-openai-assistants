<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('open_ai_assistant_projects', function (Blueprint $table) {
            if (Schema::hasColumn('open_ai_assistant_projects', 'project_name') && !Schema::hasColumn('open_ai_assistant_projects', 'name')) {
                $table->renameColumn('project_name', 'name');
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


        // Создаём таблицу логов
        if (!Schema::hasTable('open_ai_assistant_project_logs')) {
            Schema::create('open_ai_assistant_project_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('project_id');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('name')->nullable();
                $table->text('instructions')->nullable();
                $table->string('model')->nullable();
                $table->json('tools')->nullable();
                $table->json('metadata')->nullable();
                $table->string('openai_assistant_id')->nullable();
                $table->string('openai_api_key')->nullable();
                $table->timestamps();

                $table->foreign('project_id')
                    ->references('id')
                    ->on('open_ai_assistant_projects')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('open_ai_assistant_projects', function (Blueprint $table) {
            $table->dropColumn([
                'name', 'instructions', 'model', 'tools', 'metadata',
            ]);
        });

        Schema::dropIfExists('open_ai_assistant_project_logs');
    }
};
