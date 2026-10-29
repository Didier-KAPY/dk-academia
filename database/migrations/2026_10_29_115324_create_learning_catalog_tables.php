<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $createTable = function (string $tableName, Closure $definition): void {
            if (! Schema::hasTable($tableName)) {
                Schema::create($tableName, $definition);
            }
        };

        $createTable('categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $createTable('delivery_modes', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $createTable('trainings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('summary', 500)->nullable();
            $table->longText('description')->nullable();
            $table->json('objectives')->nullable();
            $table->text('target_audience')->nullable();
            $table->string('level')->default('debutant');
            $table->unsignedSmallInteger('duration_hours')->nullable();
            $table->json('syllabus')->nullable();
            $table->text('prerequisites')->nullable();
            $table->decimal('price', 12, 2)->nullable();
            $table->char('currency', 3)->default('USD');
            $table->string('image_path')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->index(['is_published', 'category_id']);
        });

        $createTable('delivery_mode_training', function (Blueprint $table): void {
            $table->foreignId('delivery_mode_id')->constrained()->cascadeOnDelete();
            $table->foreignId('training_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['delivery_mode_id', 'training_id']);
        });

        $createTable('training_modules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('training_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['training_id', 'sort_order']);
        });

        $createTable('lessons', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('training_module_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->longText('content')->nullable();
            $table->string('resource_path')->nullable();
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_published')->default(false);
            $table->timestamps();
            $table->unique(['training_module_id', 'slug']);
            $table->index(['training_module_id', 'sort_order']);
        });

        $createTable('training_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('training_id')->constrained()->cascadeOnDelete();
            $table->foreignId('delivery_mode_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('instructor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name')->nullable();
            $table->string('status')->default('scheduled');
            $table->unsignedInteger('capacity')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->string('venue')->nullable();
            $table->text('meeting_url')->nullable();
            $table->timestamps();
            $table->index(['training_id', 'status', 'starts_at']);
            $table->index(['instructor_id', 'starts_at']);
        });

        $createTable('enrollments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('training_id')->constrained()->restrictOnDelete();
            $table->foreignId('training_session_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('delivery_mode_id')->nullable()->constrained()->nullOnDelete();
            $table->string('registration_key', 64)->unique();
            $table->string('status')->default('pending');
            $table->decimal('agreed_amount', 12, 2)->nullable();
            $table->char('currency', 3)->default('USD');
            $table->timestamp('registered_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('administrative_notes')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'training_id', 'training_session_id']);
            $table->index(['user_id', 'status']);
        });

        $createTable('progressions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->decimal('completion_percent', 5, 2)->default(0);
            $table->unsignedInteger('seconds_watched')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'lesson_id']);
        });

        $createTable('attendance_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('training_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('attendance_date');
            $table->string('status')->default('present');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'attendance_date']);
        });

        Schema::whenTableDoesntHaveIndex(
            'attendance_records',
            ['training_session_id', 'user_id', 'attendance_date'],
            function (Blueprint $table): void {
                $table->unique(
                    ['training_session_id', 'user_id', 'attendance_date'],
                    'attendance_session_user_date_unique',
                );
            },
            'unique',
        );

        $createTable('evaluations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('training_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('training_session_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('type')->default('quiz');
            $table->decimal('total_points', 8, 2)->default(0);
            $table->decimal('passing_points', 8, 2)->nullable();
            $table->timestamp('opens_at')->nullable();
            $table->timestamp('closes_at')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamps();
            $table->index(['training_session_id', 'is_published']);
        });

        $createTable('evaluation_questions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('evaluation_id')->constrained()->cascadeOnDelete();
            $table->text('prompt');
            $table->string('question_type')->default('short_answer');
            $table->json('options')->nullable();
            $table->json('answer_key')->nullable();
            $table->decimal('points', 8, 2)->default(1);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['evaluation_id', 'sort_order']);
        });

        $createTable('evaluation_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('evaluation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('attempt_number')->default(1);
            $table->string('status')->default('in_progress');
            $table->decimal('score', 8, 2)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->unique(['evaluation_id', 'user_id', 'attempt_number']);
            $table->index(['user_id', 'status']);
        });

        $createTable('evaluation_responses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('evaluation_attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('evaluation_question_id')->constrained()->cascadeOnDelete();
            $table->text('answer')->nullable();
            $table->decimal('awarded_points', 8, 2)->nullable();
            $table->text('instructor_feedback')->nullable();
            $table->timestamps();
            $table->unique(['evaluation_attempt_id', 'evaluation_question_id'], 'eval_response_attempt_question_unique');
        });

        $createTable('certificates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('enrollment_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('certificate_number')->unique();
            $table->string('document_path')->nullable();
            $table->timestamp('issued_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'issued_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificates');
        Schema::dropIfExists('evaluation_responses');
        Schema::dropIfExists('evaluation_attempts');
        Schema::dropIfExists('evaluation_questions');
        Schema::dropIfExists('evaluations');
        Schema::dropIfExists('attendance_records');
        Schema::dropIfExists('progressions');
        Schema::dropIfExists('enrollments');
        Schema::dropIfExists('training_sessions');
        Schema::dropIfExists('lessons');
        Schema::dropIfExists('training_modules');
        Schema::dropIfExists('delivery_mode_training');
        Schema::dropIfExists('trainings');
        Schema::dropIfExists('delivery_modes');
        Schema::dropIfExists('categories');
    }
};
