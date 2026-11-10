<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exams', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('description')->nullable();
            $table->enum('type', ['grand', 'past_paper', 'department', 'daily']);
            $table->unsignedSmallInteger('sequence_no')->nullable();   // Grand Mock #12
            $table->foreignId('paper_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedSmallInteger('duration_minutes')->default(60);
            $table->enum('access', ['all', 'premium', 'university'])->default('all');
            $table->foreignId('university_id')->nullable()->constrained()->nullOnDelete();
            $table->json('blueprint')->nullable();   // {department_id: count}
            $table->json('rules')->nullable();       // shuffle, shuffle_options, allow_back, show_answers, leaderboard
            $table->unsignedSmallInteger('question_count')->default(0);
            $table->enum('status', ['draft', 'scheduled', 'live', 'results_out', 'published', 'automatic'])->default('draft');
            $table->unsignedInteger('registrations_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['type', 'status']);
        });

        Schema::create('exam_questions', function (Blueprint $table) {
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->primary(['exam_id', 'question_id']);
        });

        Schema::create('exam_registrations', function (Blueprint $table) {
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('reminded_day_at')->nullable();
            $table->timestamp('reminded_hour_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->primary(['exam_id', 'user_id']);
        });

        Schema::create('quiz_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['practice', 'past_paper', 'exam', 'review', 'daily']);
            $table->enum('mode', ['practice', 'timed'])->default('practice');
            $table->string('title');
            $table->foreignId('exam_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('paper_id')->nullable()->constrained()->nullOnDelete();
            $table->json('config')->nullable();
            $table->unsignedSmallInteger('question_count');
            $table->unsignedSmallInteger('answered_count')->default(0);
            $table->unsignedSmallInteger('correct_count')->default(0);
            $table->decimal('score_pct', 5, 2)->nullable();
            $table->unsignedInteger('time_limit_seconds')->nullable();
            $table->unsignedInteger('time_spent_seconds')->default(0);
            $table->enum('status', ['in_progress', 'completed', 'abandoned'])->default('in_progress');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
            $table->index(['exam_id', 'status', 'score_pct']);
        });

        Schema::create('quiz_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->json('option_order')->nullable();   // shuffled option keys, when enabled
            $table->char('selected_option', 1)->nullable();
            $table->boolean('is_correct')->nullable();
            $table->unsignedInteger('time_spent_ms')->default(0);
            $table->boolean('is_flagged')->default(false);
            $table->timestamp('answered_at')->nullable();
            $table->unique(['quiz_session_id', 'question_id']);
            $table->index(['quiz_session_id', 'position']);
        });

        Schema::create('user_question_stats', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedSmallInteger('correct_count')->default(0);
            $table->boolean('last_correct')->default(false);
            $table->timestamp('last_answered_at')->nullable();
            $table->primary(['user_id', 'question_id']);
            $table->index(['user_id', 'last_correct']);
        });

        Schema::create('bookmarks', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->primary(['user_id', 'question_id']);
        });

        Schema::create('question_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('reason', ['wrong_key', 'image_unclear', 'translation', 'typo', 'outdated', 'other']);
            $table->string('locale', 5)->default('en');
            $table->text('note')->nullable();
            $table->enum('status', ['open', 'resolved', 'dismissed'])->default('open');
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'question_id']);
        });

        Schema::create('daily_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedSmallInteger('questions_answered')->default(0);
            $table->unsignedSmallInteger('correct_answers')->default(0);
            $table->unsignedInteger('study_seconds')->default(0);
            $table->unsignedSmallInteger('cards_reviewed')->default(0);
            $table->unsignedInteger('video_seconds')->default(0);
            $table->unique(['user_id', 'date']);
            $table->index('date');
        });

        Schema::create('question_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('question_comments')->cascadeOnDelete();
            $table->enum('kind', ['doubt', 'tip', 'reply'])->default('tip');
            $table->text('body');
            $table->boolean('is_faculty')->default(false);
            $table->boolean('is_verified')->default(false);
            $table->enum('status', ['visible', 'hidden'])->default('visible');
            $table->unsignedInteger('votes_count')->default(0);
            $table->unsignedInteger('replies_count')->default(0);
            $table->unsignedSmallInteger('flags_count')->default(0);
            $table->timestamp('answered_at')->nullable();
            $table->timestamp('flags_resolved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['question_id', 'status']);
        });

        Schema::create('comment_votes', function (Blueprint $table) {
            $table->foreignId('comment_id')->constrained('question_comments')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->primary(['comment_id', 'user_id']);
        });

        Schema::create('comment_flags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comment_id')->constrained('question_comments')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('reason', 40);
            $table->timestamp('created_at')->nullable();
            $table->unique(['comment_id', 'user_id']);
        });
    }

    public function down(): void
    {
        foreach (['comment_flags', 'comment_votes', 'question_comments', 'daily_activities', 'question_reports', 'bookmarks',
            'user_question_stats', 'quiz_answers', 'quiz_sessions', 'exam_registrations', 'exam_questions', 'exams'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
