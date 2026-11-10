<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Languages the company manages from Admin → Languages.
        Schema::create('languages', function (Blueprint $table) {
            $table->string('code', 12)->primary();          // ISO 639-1/-3, e.g. am, om, so
            $table->string('name', 60);                      // English name
            $table->string('native_name', 60);
            $table->enum('direction', ['ltr', 'rtl'])->default('ltr');
            $table->boolean('is_active')->default(false);    // visible to students
            $table->boolean('is_default')->default(false);   // English: the fallback, cannot be removed
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();
        });

        // App interface text. English rows are the source keys; other locales are translations.
        Schema::create('ui_translations', function (Blueprint $table) {
            $table->id();
            $table->string('key', 150);
            $table->string('locale', 12);
            $table->text('value');
            $table->string('context', 190)->nullable();      // hint for translators (English rows only)
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['key', 'locale']);
            $table->index('locale');
        });

        // OTP codes are now delivered through Telegram Gateway.
        Schema::table('otp_codes', function (Blueprint $table) {
            $table->string('channel', 20)->default('telegram')->after('phone');
            $table->string('provider_request_id', 100)->nullable()->after('channel')->index();
            $table->string('delivery_status', 20)->nullable()->after('provider_request_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('telegram_username', 64)->nullable()->after('telegram_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('telegram_username'));
        Schema::table('otp_codes', fn (Blueprint $t) => $t->dropColumn(['channel', 'provider_request_id', 'delivery_status']));
        Schema::dropIfExists('ui_translations');
        Schema::dropIfExists('languages');
    }
};
