<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('specialties', function (Blueprint $table) {
            $table->id();
            $table->json('name');
            $table->string('code', 10)->unique();
            $table->enum('competition', ['low', 'medium', 'high', 'very_high'])->default('medium');
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('match_results', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->foreignId('specialty_id')->constrained()->cascadeOnDelete();
            $table->foreignId('university_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('seats');
            $table->unsignedSmallInteger('applicants')->default(0);
            $table->unsignedSmallInteger('accepted')->default(0);
            $table->decimal('cutoff', 5, 2);
            $table->timestamps();
            $table->unique(['year', 'specialty_id', 'university_id']);
        });

        Schema::create('match_profiles', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();
            $table->decimal('raw_score', 5, 2)->nullable();
            $table->boolean('is_female')->default(false);
            $table->boolean('has_managerial')->default(false);
            $table->json('specialty_ids')->nullable();
            $table->boolean('remind')->default(false);
            $table->timestamps();
        });

        Schema::create('match_choices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('specialty_id')->constrained()->cascadeOnDelete();
            $table->foreignId('university_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('rank');
            $table->timestamps();
            $table->unique(['user_id', 'specialty_id', 'university_id']);
        });

        Schema::create('match_predictions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('score', 5, 2);
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        foreach (['match_predictions', 'match_choices', 'match_profiles', 'match_results', 'specialties'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
