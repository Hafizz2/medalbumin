<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->json('name');
            $table->string('slug')->unique();
            $table->string('short_name', 30)->nullable();
            $table->string('icon', 30)->default('steth');
            $table->string('color_bg', 9)->default('#DDF3F5');
            $table->string('color_ink', 9)->default('#017F8E');
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('subcategories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->json('name');
            $table->string('slug');
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['department_id', 'slug']);
        });

        Schema::create('topics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subcategory_id')->constrained()->cascadeOnDelete();
            $table->json('name');
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('papers', function (Blueprint $table) {
            $table->id();
            $table->enum('category', ['exit', 'qualification', 'block']);
            $table->string('name');                       // National Exit Exam, Licensing Exam…
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('ec_label', 20)->nullable();   // 2017 EC, C-I, PC-II
            $table->string('code', 10)->nullable();       // CVS, RES — groups block papers
            $table->string('description')->nullable();    // All systems, Clinical, Basic sciences
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('time_limit_minutes')->nullable();
            $table->boolean('is_premium')->default(true);
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
            $table->index(['category', 'status']);
        });

        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 12)->nullable()->unique();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->foreignId('subcategory_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('topic_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('paper_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('paper_position')->nullable();
            $table->unsignedSmallInteger('exam_year')->nullable();
            $table->enum('difficulty', ['easy', 'moderate', 'hard'])->default('moderate');
            $table->json('stem');
            $table->json('explanation')->nullable();
            $table->string('image_path')->nullable();
            $table->string('image_caption')->nullable();
            $table->string('image_type', 20)->nullable();   // ecg, xray, histology, ct, photo, other
            $table->char('correct_option', 1);
            $table->enum('status', ['draft', 'in_review', 'published'])->default('draft');
            $table->boolean('is_free')->default(false);
            $table->json('translation_status')->nullable(); // {"am":"published","om":"draft"}
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->unsignedInteger('attempts_count')->default(0);
            $table->unsignedInteger('correct_count')->default(0);
            $table->unsignedBigInteger('total_time_ms')->default(0);
            $table->decimal('top_correct_pct', 5, 2)->nullable(); // discrimination: % of top students correct
            $table->string('quality_flag', 20)->nullable();       // good, too_hard, too_easy, ambiguous
            $table->unsignedInteger('comments_count')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'department_id']);
            $table->index(['status', 'subcategory_id']);
        });

        Schema::create('question_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->char('key', 1);
            $table->json('text');
            $table->unsignedInteger('pick_count')->default(0);
            $table->unique(['question_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_options');
        Schema::dropIfExists('questions');
        Schema::dropIfExists('papers');
        Schema::dropIfExists('topics');
        Schema::dropIfExists('subcategories');
        Schema::dropIfExists('departments');
    }
};
