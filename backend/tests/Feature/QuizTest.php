<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Paper;
use App\Models\Question;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuizTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedContent();
    }

    public function test_free_student_practice_session_reveals_answer_after_answering(): void
    {
        $this->student();
        $start = $this->postJson('/api/v1/quiz-sessions', ['source' => 'builder', 'size' => 10, 'mode' => 'practice', 'pools' => ['all']])->assertCreated();

        $questions = $start->json('questions');
        $this->assertNotEmpty($questions);
        // Free students only get free questions, and no answer key before answering.
        foreach ($questions as $q) {
            $this->assertTrue(Question::find($q['question']['id'])->is_free);
            $this->assertArrayNotHasKey('correct_option', $q['question']);
        }

        $first = Question::find($questions[0]['question']['id']);
        $res = $this->postJson("/api/v1/quiz-sessions/{$start->json('session.id')}/answers", [
            'question_id' => $first->id, 'option' => $first->correct_option, 'time_ms' => 15000,
        ])->assertOk()->assertJsonPath('is_correct', true)->assertJsonPath('question.correct_option', $first->correct_option);
        $this->assertNotEmpty($res->json('question.explanation'));

        $this->assertSame(1, $first->fresh()->attempts_count);
        $this->getJson('/api/v1/home')->assertOk()->assertJsonPath('stats.answered', 1)->assertJsonPath('stats.streak', 1);
    }

    public function test_timed_session_hides_results_until_finish(): void
    {
        $user = $this->student();
        $sid = $this->postJson('/api/v1/quiz-sessions', ['source' => 'builder', 'size' => 5, 'mode' => 'timed'])->assertCreated()->json('session.id');
        $qs = $this->getJson("/api/v1/quiz-sessions/{$sid}")->assertOk()->json('questions');

        foreach ($qs as $i => $q) {
            $question = Question::find($q['question']['id']);
            $option = $i === 0 ? collect(['A', 'B', 'C', 'D'])->first(fn ($k) => $k !== $question->correct_option) : $question->correct_option;
            $r = $this->postJson("/api/v1/quiz-sessions/{$sid}/answers", ['question_id' => $question->id, 'option' => $option, 'time_ms' => 30000])->assertOk();
            $this->assertArrayNotHasKey('is_correct', $r->json());
        }

        $result = $this->postJson("/api/v1/quiz-sessions/{$sid}/finish")->assertOk();
        $this->assertSame(count($qs) - 1, $result->json('result.correct'));
        $this->assertNotEmpty($result->json('result.by_system'));

        // The wrong one is now in the "mistakes" pool.
        $this->getJson('/api/v1/quiz/options')->assertOk()->assertJsonPath('pools.1.count', 1);
        $this->postJson('/api/v1/quiz-sessions', ['source' => 'retry', 'session_id' => $sid])->assertCreated()->assertJsonPath('session.question_count', 1);
        $this->assertSame(1, $user->fresh()->dailyActivities()->count());
    }

    public function test_premium_paper_is_locked_for_free_students(): void
    {
        $this->student();
        $paper = Paper::query()->create(['category' => 'exit', 'name' => 'National Exit Exam', 'year' => 2024, 'is_premium' => true, 'status' => 'published']);
        Question::query()->first()->update(['paper_id' => $paper->id]);
        $this->postJson('/api/v1/quiz-sessions', ['source' => 'paper', 'paper_id' => $paper->id])->assertStatus(402);
    }

    public function test_guest_quota_is_enforced(): void
    {
        $guest = $this->student(['is_guest' => true, 'phone' => null]);
        $guest->dailyActivities()->create(['date' => today(), 'questions_answered' => 20]);
        $this->postJson('/api/v1/quiz-sessions', ['source' => 'builder', 'size' => 10])->assertStatus(402);
    }

    public function test_department_and_progress_endpoints(): void
    {
        $this->student();
        $dept = Department::query()->where('short_name', 'Internal Med')->first();
        $this->getJson('/api/v1/departments')->assertOk()->assertJsonStructure(['total_questions', 'data' => [['id', 'name', 'done_pct', 'topics_total']]]);
        $this->getJson("/api/v1/departments/{$dept->id}")->assertOk()->assertJsonStructure(['subcategories' => [['id', 'name', 'questions', 'topics']]]);
        $this->getJson('/api/v1/papers?category=exit')->assertOk()->assertJsonPath('data.0.name', 'Starter practice paper');
        $this->getJson('/api/v1/progress/overview')->assertOk()->assertJsonCount(12, 'trend');
        $this->getJson('/api/v1/progress/mocks')->assertOk();
    }

    public function test_bookmark_report_and_discussion(): void
    {
        $user = $this->student();
        $q = Question::query()->first();
        $this->postJson("/api/v1/questions/{$q->id}/bookmark")->assertOk()->assertJsonPath('bookmarked', true);
        $this->postJson("/api/v1/questions/{$q->id}/report", ['reason' => 'wrong_key', 'note' => 'Key looks wrong'])->assertCreated();

        $post = $this->postJson("/api/v1/questions/{$q->id}/comments", ['body' => 'Would right-sided leads help here?'])->assertCreated();
        $this->assertSame('doubt', $post->json('kind'));
        $this->postJson("/api/v1/comments/{$post->json('id')}/vote")->assertOk()->assertJsonPath('votes', 1);
        $this->getJson("/api/v1/questions/{$q->id}/comments?sort=top")->assertOk()->assertJsonPath('count', 1);
        $this->assertSame(1, $user->bookmarks()->count());
    }
}
