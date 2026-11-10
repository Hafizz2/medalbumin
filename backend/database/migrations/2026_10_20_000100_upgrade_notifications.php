<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Per-category switches, e.g. {"promos": false}. Missing keys mean "on".
            $table->json('notification_prefs')->nullable()->after('reminder_time');
            $table->unsignedSmallInteger('daily_goal')->default(20)->after('notification_prefs');
        });

        Schema::table('push_campaigns', function (Blueprint $table) {
            $table->enum('kind', ['announcement', 'promo'])->default('announcement')->after('id');
            $table->string('image_path')->nullable()->after('body');
            $table->json('action_label')->nullable()->after('image_path');
        });

        // So each new exam / paper is announced once, and results once.
        Schema::table('exams', function (Blueprint $table) {
            $table->timestamp('announced_at')->nullable();
            $table->timestamp('results_notified_at')->nullable();
        });
        Schema::table('papers', function (Blueprint $table) {
            $table->timestamp('announced_at')->nullable();
        });
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->timestamp('expiry_reminded_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['notification_prefs', 'daily_goal']));
        Schema::table('push_campaigns', fn (Blueprint $t) => $t->dropColumn(['kind', 'image_path', 'action_label']));
        Schema::table('exams', fn (Blueprint $t) => $t->dropColumn(['announced_at', 'results_notified_at']));
        Schema::table('papers', fn (Blueprint $t) => $t->dropColumn('announced_at'));
        Schema::table('subscriptions', fn (Blueprint $t) => $t->dropColumn('expiry_reminded_at'));
    }
};
