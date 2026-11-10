<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->foreignId('subcategory_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('topic_id')->nullable()->constrained()->nullOnDelete();
            $table->json('title');
            $table->json('body');                       // sanitised HTML per locale
            $table->unsignedTinyInteger('read_minutes')->default(5);
            $table->enum('visibility', ['all', 'premium'])->default('all');
            $table->enum('status', ['draft', 'in_review', 'published'])->default('draft');
            $table->string('attachment_path')->nullable();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->unsignedInteger('reads_count')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'subcategory_id']);
        });

        Schema::create('note_reads', function (Blueprint $table) {
            $table->foreignId('note_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('read_at')->nullable();
            $table->primary(['note_id', 'user_id']);
        });

        Schema::create('user_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->text('body');
            $table->nullableMorphs('notable');            // official note, question or video
            $table->foreignId('subcategory_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('video_seconds')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'updated_at']);
        });

        Schema::create('flashcard_decks', function (Blueprint $table) {
            $table->id();
            $table->json('title');
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subcategory_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete(); // personal deck
            $table->boolean('is_official')->default(true);
            $table->enum('status', ['draft', 'published'])->default('published');
            $table->unsignedInteger('cards_count')->default(0);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('flashcards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flashcard_deck_id')->constrained()->cascadeOnDelete();
            $table->json('front');           // e.g. "Paracetamol overdose"
            $table->json('prompt')->nullable();  // e.g. "What is the antidote…?"
            $table->json('back');            // e.g. "N-acetylcysteine (NAC)"
            $table->json('detail')->nullable();
            $table->foreignId('question_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('flashcard_reviews', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('flashcard_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('repetitions')->default(0);
            $table->unsignedInteger('interval_minutes')->default(0);
            $table->decimal('ease', 4, 2)->default(2.50);
            $table->unsignedSmallInteger('lapses')->default(0);
            $table->unsignedTinyInteger('last_rating')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('last_reviewed_at')->nullable();
            $table->primary(['user_id', 'flashcard_id']);
            $table->index(['user_id', 'due_at']);
        });

        Schema::create('videos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->foreignId('subcategory_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('topic_id')->nullable()->constrained()->nullOnDelete();
            $table->json('title');
            $table->json('description')->nullable();
            $table->string('lecturer')->nullable();
            $table->enum('source', ['upload', 'youtube', 'url'])->default('upload');
            $table->string('source_path')->nullable();     // storage path or external URL / YouTube id
            $table->json('variants')->nullable();          // {"720":"path","360":"path"}
            $table->json('captions')->nullable();          // {"en":"path.vtt","am":"path.vtt"}
            $table->string('thumbnail_path')->nullable();
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->enum('access', ['free', 'premium'])->default('premium');
            $table->enum('status', ['draft', 'processing', 'published', 'failed'])->default('draft');
            $table->unsignedInteger('views_count')->default(0);
            $table->unsignedInteger('completions_count')->default(0);
            $table->unsignedBigInteger('watch_seconds_total')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('video_chapters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('video_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('start_seconds');
            $table->json('title');
        });

        Schema::create('question_video', function (Blueprint $table) {
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->foreignId('video_id')->constrained()->cascadeOnDelete();
            $table->primary(['question_id', 'video_id']);
        });

        Schema::create('video_progress', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('video_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position_seconds')->default(0);
            $table->unsignedInteger('max_position_seconds')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->primary(['user_id', 'video_id']);
        });

        Schema::create('study_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('exam_label')->nullable();
            $table->date('exam_date');
            $table->date('start_date');
            $table->unsignedSmallInteger('daily_minutes')->default(180);
            $table->enum('status', ['active', 'archived'])->default('active');
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });

        Schema::create('study_plan_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('study_plan_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->enum('kind', ['questions', 'read', 'video', 'cards', 'mock']);
            $table->string('title');
            $table->string('meta')->nullable();
            $table->unsignedSmallInteger('minutes')->default(30);
            $table->json('target')->nullable();      // ids the app needs to open the task
            $table->unsignedTinyInteger('position')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['study_plan_id', 'date']);
        });

        Schema::create('lab_values', function (Blueprint $table) {
            $table->id();
            $table->string('category', 60)->default('General');
            $table->json('name');
            $table->string('range', 60);
            $table->string('unit', 30)->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('drugs', function (Blueprint $table) {
            $table->id();
            $table->json('name');
            $table->json('category');
            $table->json('dose');
            $table->json('watch')->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['drugs', 'lab_values', 'study_plan_tasks', 'study_plans', 'video_progress', 'question_video', 'video_chapters',
            'videos', 'flashcard_reviews', 'flashcards', 'flashcard_decks', 'user_notes', 'note_reads', 'notes'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
