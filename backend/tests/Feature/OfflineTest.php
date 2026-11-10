<?php

namespace Tests\Feature;

use App\Models\Question;
use App\Models\QuizSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfflineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedContent();
    }

    public function test_free_student_downloads_only_free_questions_with_answers(): void
    {
        $this->student();
        $packs = $this->getJson('/api/v1/offline/packs')->assertOk()->json('data');
        $this->assertNotEmpty($packs);

        $pack = $this->getJson('/api/v1/offline/packs/'.$packs[0]['id'])->assertOk();
        $this->assertNotEmpty($pack->json('questions'));
        foreach ($pack->json('questions') as $q) {
            $this->assertTrue(Question::find($q['id'])->is_free);
            $this->assertNotEmpty($q['correct']);
        }
    }

    public function test_offline_answers_sync_once(): void
    {
        $user = $this->student();
        $q = Question::query()->where('is_free', true)->first();
        $wrong = collect(['A', 'B', 'C', 'D'])->first(fn ($k) => $k !== $q->correct_option);
        $body = ['batch_id' => 'b-1', 'attempts' => [
            ['question_id' => $q->id, 'option' => $q->correct_option, 'time_ms' => 20000, 'answered_at' => now()->subHour()->toIso8601String()],
            ['question_id' => Question::query()->where('is_free', true)->whereKeyNot($q->id)->value('id'), 'option' => $wrong, 'time_ms' => 10000],
        ]];

        $this->postJson('/api/v1/offline/sync', $body)->assertOk()->assertJsonPath('synced', 2);
        $this->postJson('/api/v1/offline/sync', $body)->assertOk();

        $this->assertSame(1, QuizSession::query()->where('user_id', $user->id)->count());
        $this->assertSame(1, $q->fresh()->attempts_count);
        $this->getJson('/api/v1/home')->assertOk()->assertJsonPath('stats.answered', 2);
    }

    public function test_guests_cannot_download(): void
    {
        $token = $this->postJson('/api/v1/auth/guest', ['device_id' => 'g1'])->json('token');
        $this->withToken($token)->getJson('/api/v1/offline/packs')->assertForbidden();
    }
}
