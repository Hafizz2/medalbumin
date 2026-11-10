<?php

use App\Http\Controllers\Api\V1;
use Illuminate\Support\Facades\Route;

/*
| MedAlbumin mobile API (v1). Bearer-token auth via Sanctum; one token per device.
| Interactive docs: /docs/api (Scramble).
*/

Route::prefix('v1')->middleware('throttle:api')->group(function () {

    // Public
    Route::middleware('content.locale')->group(function () {
        Route::get('config', [V1\CatalogController::class, 'config']);
        Route::get('universities', [V1\CatalogController::class, 'universities']);
        Route::get('i18n/{locale}', [V1\CatalogController::class, 'i18n'])->where('locale', '[a-z]{2,3}');
    });

    Route::prefix('auth')->group(function () {
        Route::post('otp/send', [V1\AuthController::class, 'sendOtp'])->middleware('throttle:otp');
        Route::post('otp/verify', [V1\AuthController::class, 'verifyOtp'])->middleware('throttle:auth');
        Route::post('telegram', [V1\AuthController::class, 'telegram'])->middleware('throttle:auth');
        Route::post('email/register', [V1\EmailAuthController::class, 'register'])->middleware('throttle:email');
        Route::post('email/verify', [V1\EmailAuthController::class, 'verify'])->middleware('throttle:auth');
        Route::post('email/resend', [V1\EmailAuthController::class, 'resend'])->middleware('throttle:email');
        Route::post('email/login', [V1\EmailAuthController::class, 'login'])->middleware('throttle:auth');
        Route::post('email/forgot', [V1\EmailAuthController::class, 'forgot'])->middleware('throttle:email');
        Route::post('email/reset', [V1\EmailAuthController::class, 'reset'])->middleware('throttle:auth');
        Route::post('google', [V1\AuthController::class, 'google'])->middleware('throttle:auth');
        Route::post('apple', [V1\AuthController::class, 'apple'])->middleware('throttle:auth');
        Route::post('guest', [V1\AuthController::class, 'guest'])->middleware('throttle:auth');
    });

    // Signed in (students and guests)
    Route::middleware(['auth:sanctum', 'student', 'content.locale'])->group(function () {
        Route::post('auth/logout', [V1\AuthController::class, 'logout']);

        Route::get('me', [V1\MeController::class, 'show']);
        Route::patch('me', [V1\MeController::class, 'update']);
        Route::post('me/avatar', [V1\MeController::class, 'avatar']);
        Route::delete('me', [V1\MeController::class, 'destroy']);
        Route::get('me/devices', [V1\MeController::class, 'devices']);
        Route::delete('me/devices/{device}', [V1\MeController::class, 'revokeDevice'])->whereNumber('device');
        Route::put('me/push-token', [V1\MeController::class, 'pushToken']);
        Route::get('me/referral', [V1\MeController::class, 'referral']);
        Route::post('me/student-verification', [V1\MeController::class, 'verifyStudent']);
        Route::get('me/student-verification', [V1\MeController::class, 'verificationStatus']);

        Route::get('home', V1\HomeController::class);

        // Offline packs and syncing answers given without internet
        Route::get('offline/packs', [V1\OfflineController::class, 'packs']);
        Route::get('offline/packs/{department}', [V1\OfflineController::class, 'pack'])->whereNumber('department');
        Route::post('offline/sync', [V1\OfflineController::class, 'sync']);

        // Study
        Route::get('departments', [V1\StudyController::class, 'departments']);
        Route::get('departments/{department}', [V1\StudyController::class, 'department']);
        Route::get('papers', [V1\StudyController::class, 'papers']);
        Route::get('quiz/options', [V1\StudyController::class, 'builderOptions']);

        // Quiz sessions
        Route::get('quiz-sessions', [V1\QuizController::class, 'index']);
        Route::post('quiz-sessions', [V1\QuizController::class, 'start']);
        Route::get('quiz-sessions/{session}', [V1\QuizController::class, 'show'])->whereNumber('session');
        Route::post('quiz-sessions/{session}/answers', [V1\QuizController::class, 'answer'])->whereNumber('session');
        Route::post('quiz-sessions/{session}/finish', [V1\QuizController::class, 'finish'])->whereNumber('session');
        Route::post('quiz-sessions/{session}/abandon', [V1\QuizController::class, 'abandon'])->whereNumber('session');
        Route::get('quiz-sessions/{session}/result', [V1\QuizController::class, 'result'])->whereNumber('session');

        // Questions & discussion
        Route::post('questions/{question}/bookmark', [V1\QuestionController::class, 'bookmark']);
        Route::post('questions/{question}/report', [V1\QuestionController::class, 'report']);
        Route::get('report-reasons', [V1\QuestionController::class, 'reportReasons']);
        Route::get('questions/{question}/comments', [V1\DiscussionController::class, 'index']);
        Route::post('questions/{question}/comments', [V1\DiscussionController::class, 'store'])->middleware('throttle:posts');
        Route::post('comments/{comment}/vote', [V1\DiscussionController::class, 'vote']);
        Route::post('comments/{comment}/flag', [V1\DiscussionController::class, 'flag']);
        Route::delete('comments/{comment}', [V1\DiscussionController::class, 'destroy']);

        // Progress
        Route::get('progress/overview', [V1\ProgressController::class, 'overview']);
        Route::get('progress/subjects', [V1\ProgressController::class, 'subjects']);
        Route::get('progress/mocks', [V1\ProgressController::class, 'mocks']);

        // Notes
        Route::get('notes', [V1\NoteController::class, 'official']);
        Route::get('notes/{note}', [V1\NoteController::class, 'show']);
        Route::get('my-notes', [V1\NoteController::class, 'mine']);
        Route::post('my-notes', [V1\NoteController::class, 'store']);
        Route::patch('my-notes/{userNote}', [V1\NoteController::class, 'update']);
        Route::delete('my-notes/{userNote}', [V1\NoteController::class, 'destroy']);

        // Flashcards
        Route::get('flashcards/decks', [V1\FlashcardController::class, 'decks']);
        Route::get('flashcards/study', [V1\FlashcardController::class, 'study']);
        Route::post('flashcards/from-mistakes', [V1\FlashcardController::class, 'fromMistakes']);
        Route::post('flashcards/{flashcard}/review', [V1\FlashcardController::class, 'review']);

        // Videos
        Route::get('videos', [V1\VideoController::class, 'index']);
        Route::get('videos/{video}', [V1\VideoController::class, 'show']);
        Route::post('videos/{video}/progress', [V1\VideoController::class, 'progress']);

        // Search & reference tools
        Route::get('search', [V1\SearchController::class, 'search']);
        Route::get('image-bank', [V1\SearchController::class, 'imageBank']);
        Route::get('tools/lab-values', [V1\SearchController::class, 'labValues']);
        Route::get('tools/drugs', [V1\SearchController::class, 'drugs']);

        // Grand mocks & exams
        Route::get('exams', [V1\ExamController::class, 'index']);
        Route::get('exams/{exam}', [V1\ExamController::class, 'show']);
        Route::post('exams/{exam}/register', [V1\ExamController::class, 'register']);
        Route::get('exams/{exam}/leaderboard', [V1\ExamController::class, 'leaderboard']);

        // Residency match predictor
        Route::get('match/profile', [V1\MatchController::class, 'profileShow']);
        Route::patch('match/profile', [V1\MatchController::class, 'profileUpdate']);
        Route::get('match/specialties', [V1\MatchController::class, 'specialties']);
        Route::get('match/specialties/{specialty}/chances', [V1\MatchController::class, 'chances']);
        Route::put('match/specialties/{specialty}/choices', [V1\MatchController::class, 'saveChoices']);
        Route::get('match/choices', [V1\MatchController::class, 'choices']);
        Route::get('match/choices/pdf', [V1\MatchController::class, 'pdf']);

        // Study plan
        Route::get('study-plan', [V1\StudyPlanController::class, 'show']);
        Route::post('study-plan', [V1\StudyPlanController::class, 'store']);
        Route::post('study-plan/rebalance', [V1\StudyPlanController::class, 'rebalance']);
        Route::post('study-plan/tasks/{task}/toggle', [V1\StudyPlanController::class, 'toggle']);

        // Notifications
        Route::get('notifications', [V1\NotificationController::class, 'index']);
        Route::post('notifications/read-all', [V1\NotificationController::class, 'readAll']);
        Route::post('notifications/{id}/read', [V1\NotificationController::class, 'read']);
        Route::delete('notifications/{id}', [V1\NotificationController::class, 'destroy']);
        Route::get('notification-settings', [V1\NotificationController::class, 'settings']);
        Route::patch('notification-settings', [V1\NotificationController::class, 'updateSettings']);
        Route::post('notifications/test', [V1\NotificationController::class, 'test']);

        // Plan status only. Buying happens on the website (App Store / Play rules for digital goods).
        Route::get('payments', [V1\PaymentController::class, 'index']);
        Route::get('subscription', [V1\PaymentController::class, 'subscription']);
    });
});
