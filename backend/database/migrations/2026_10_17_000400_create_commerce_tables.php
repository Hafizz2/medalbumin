<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->json('name');
            $table->json('note')->nullable();           // "6 months", "Until exam day"
            $table->json('badge')->nullable();          // "Popular", "Best value"
            $table->decimal('price', 10, 2);
            $table->string('currency', 3)->default('ETB');
            $table->unsignedSmallInteger('duration_days')->nullable();
            $table->date('ends_on')->nullable();        // fixed end date (exit pack)
            $table->json('features')->nullable();
            $table->string('apple_product_id')->nullable();
            $table->string('google_product_id')->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('promo_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('description')->nullable();
            $table->enum('type', ['percent', 'fixed', 'free_days']);
            $table->decimal('value', 10, 2);
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('university_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->enum('gateway', ['telebirr', 'cbe_birr', 'chapa', 'bank_transfer', 'promo', 'manual']);
            $table->decimal('amount', 10, 2);
            $table->decimal('discount', 10, 2)->default(0);
            $table->string('currency', 3)->default('ETB');
            $table->enum('status', ['pending', 'approved', 'rejected', 'failed', 'cancelled'])->default('pending');
            $table->string('tx_ref', 64)->unique();       // our reference sent to the gateway
            $table->string('reference', 100)->nullable(); // bank / gateway reference
            $table->string('bank_name', 80)->nullable();
            $table->string('receipt_path')->nullable();
            $table->string('checkout_url', 500)->nullable();
            $table->json('gateway_payload')->nullable();
            $table->foreignId('promo_code_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('reject_reason')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'gateway']);
            $table->index('reference');
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('source', ['payment', 'promo', 'referral', 'admin'])->default('payment');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->enum('status', ['active', 'cancelled'])->default('active');
            $table->timestamps();
            $table->index(['user_id', 'ends_at']);
        });

        Schema::create('promo_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promo_code_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->unique(['promo_code_id', 'user_id']);
        });

        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referrer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('referred_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->enum('status', ['joined', 'rewarded'])->default('joined');
            $table->timestamp('rewarded_at')->nullable();
            $table->timestamps();
        });

        Schema::create('student_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('student_id_number', 60)->nullable();
            $table->string('image_path');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();
        });

        Schema::create('push_campaigns', function (Blueprint $table) {
            $table->id();
            $table->json('title');
            $table->json('body');
            $table->json('audience');                    // {"plan":"free","target_exam":"exit",…}
            $table->string('link')->nullable();          // in-app route, e.g. papers
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->enum('status', ['scheduled', 'sending', 'sent', 'failed'])->default('scheduled');
            $table->unsignedInteger('recipients_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['push_campaigns', 'student_verifications', 'referrals', 'promo_redemptions', 'subscriptions', 'payments', 'promo_codes', 'plans'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
