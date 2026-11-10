<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 6-digit codes sent by email: confirm a new account, or reset a forgotten password.
        Schema::create('email_codes', function (Blueprint $table) {
            $table->id();
            $table->string('email')->index();
            $table->enum('purpose', ['signup', 'reset']);
            $table->string('code_hash');
            $table->text('payload')->nullable();               // encrypted pending sign-up (name, password hash)
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('ip', 45)->nullable();
            $table->dateTime('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_codes');
    }
};
