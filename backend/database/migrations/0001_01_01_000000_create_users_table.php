<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('universities', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('short_name', 30)->nullable();
            $table->string('city', 80)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('phone', 20)->nullable()->unique();
            $table->timestamp('phone_verified_at')->nullable();
            $table->string('email')->nullable()->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable();
            $table->string('google_id')->nullable()->unique();
            $table->string('apple_id')->nullable()->unique();
            $table->string('telegram_id')->nullable()->unique();
            $table->string('avatar_path')->nullable();
            $table->enum('gender', ['female', 'male'])->nullable();
            $table->foreignId('university_id')->nullable()->constrained()->nullOnDelete();
            $table->string('program', 60)->nullable();          // Medicine, Nursing, Pharmacy…
            $table->string('study_year', 30)->nullable();       // Year 5, Year 6, Intern…
            $table->string('target_exam', 30)->nullable();      // exit, qualification, licensing, residency
            $table->date('exam_date')->nullable();
            $table->unsignedTinyInteger('target_score')->default(85);
            $table->string('app_locale', 5)->default('en');
            $table->string('content_locale', 5)->default('en');
            $table->boolean('dark_mode')->default(false);
            $table->boolean('daily_reminder')->default(true);
            $table->time('reminder_time')->default('20:00:00');
            $table->boolean('is_guest')->default(false);
            $table->boolean('is_staff')->default(false);
            $table->enum('status', ['active', 'suspended'])->default('active');
            $table->string('suspended_reason')->nullable();
            $table->string('referral_code', 12)->nullable()->unique();
            $table->foreignId('referred_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_active_at')->nullable()->index();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        Schema::create('otp_codes', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 20)->index();
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('ip', 45)->nullable();
            $table->dateTime('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('device_id', 100);
            $table->string('name', 120)->nullable();
            $table->enum('platform', ['ios', 'android', 'web'])->default('android');
            $table->string('app_version', 20)->nullable();
            $table->string('push_token')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'device_id']);
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->json('value')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::dropIfExists('devices');
        Schema::dropIfExists('otp_codes');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
        Schema::dropIfExists('universities');
    }
};
