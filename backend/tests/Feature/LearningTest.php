<?php

namespace Tests\Feature;

use App\Models\MatchResult;
use App\Models\Note;
use App\Models\Specialty;
use App\Models\University;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LearningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedContent();
    }

    public function test_flashcards_spaced_repetition(): void
    {
        $this->student();
        $deck = $this->getJson('/api/v1/flashcards/decks')->assertOk()->json('data.0');
        $this->assertSame(9, $deck['cards']);

        $cards = $this->getJson('/api/v1/flashcards/study?deck_id='.$deck['id'].'&new=5')->assertOk();
        $this->assertCount(5, $cards->json('data'));
        $this->assertSame(['1m', '8m', '1d', '4d'], collect($cards->json('data.0.buttons'))->pluck('interval')->all());

        $this->postJson('/api/v1/flashcards/'.$cards->json('data.0.id').'/review', ['rating' => 3])->assertOk()->assertJsonPath('next_in', '1d');
        $this->assertSame(1, $this->getJson('/api/v1/flashcards/decks')->json('reviewed_today'));
    }

    public function test_official_note_and_private_notes(): void
    {
        $this->student();
        $note = Note::query()->where('visibility', 'all')->first();
        $this->getJson("/api/v1/notes/{$note->id}")->assertOk()->assertJsonPath('title', $note->title);
        $premium = Note::query()->where('visibility', 'premium')->first();
        $this->getJson("/api/v1/notes/{$premium->id}")->assertStatus(402);

        $mine = $this->postJson('/api/v1/my-notes', ['body' => 'UMN = up, LMN = low', 'note_id' => $note->id])->assertCreated();
        $this->assertSame('note', $mine->json('context.type'));
        $this->getJson("/api/v1/notes/{$note->id}")->assertJsonCount(1, 'my_notes');
        $this->getJson('/api/v1/my-notes')->assertJsonPath('meta.total', 1);
    }

    public function test_match_predictor_flow(): void
    {
        $this->student(['gender' => 'female']);
        $im = Specialty::query()->where('code', 'IM')->first();
        $aau = University::query()->where('short_name', 'AAU')->first();
        $ju = University::query()->where('short_name', 'JU')->first();
        foreach ([[2023, 77, 72], [2024, 79, 73], [2025, 80, 74]] as [$year, $aauCut, $juCut]) {
            MatchResult::query()->create(['year' => $year, 'specialty_id' => $im->id, 'university_id' => $aau->id, 'seats' => 24, 'applicants' => 130, 'accepted' => 24, 'cutoff' => $aauCut]);
            MatchResult::query()->create(['year' => $year, 'specialty_id' => $im->id, 'university_id' => $ju->id, 'seats' => 18, 'applicants' => 58, 'accepted' => 18, 'cutoff' => $juCut]);
        }

        // 74 + 5% female bonus = 77.7, as in the design.
        $this->patchJson('/api/v1/match/profile', ['raw_score' => 74, 'is_female' => true, 'specialty_ids' => [$im->id]])->assertOk()->assertJsonPath('final_score', 77.7);
        $chances = $this->getJson("/api/v1/match/specialties/{$im->id}/chances")->assertOk();
        $this->assertSame($ju->id, $chances->json('universities.0.university_id'));
        $this->assertSame('likely', $chances->json('universities.0.tier'));

        $this->putJson("/api/v1/match/specialties/{$im->id}/choices", ['university_ids' => [$ju->id, $aau->id]])->assertOk();
        $list = $this->getJson('/api/v1/match/choices')->assertOk();
        $this->assertGreaterThan(80, $list->json('overall_chance'));
        $this->get('/api/v1/match/choices/pdf')->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_study_plan_generates_today(): void
    {
        $this->student();
        $this->postJson('/api/v1/study-plan', ['exam_date' => now()->addDays(60)->toDateString()])->assertCreated();
        $plan = $this->getJson('/api/v1/study-plan')->assertOk();
        $this->assertSame(1, $plan->json('plan.day'));
        $this->assertSame('questions', $plan->json('tasks.0.kind'));
        $this->assertCount(7, $plan->json('week'));
        $this->postJson('/api/v1/study-plan/tasks/'.$plan->json('tasks.0.id').'/toggle')->assertOk()->assertJsonPath('done', true);
    }

    public function test_search_tools_and_notifications(): void
    {
        $this->student();
        $res = $this->getJson('/api/v1/search?q=magnesium')->assertOk();
        $this->assertGreaterThan(0, $res->json('counts.all'));
        $this->getJson('/api/v1/tools/lab-values')->assertOk()->assertJsonStructure(['data' => [['category', 'items']]]);
        $this->getJson('/api/v1/tools/drugs')->assertOk()->assertJsonPath('data.0.name', 'Magnesium sulfate');
        $this->getJson('/api/v1/notifications')->assertOk()->assertJsonPath('unread', 0);
    }

    public function test_account_deletion_removes_personal_data(): void
    {
        $user = $this->student();
        $this->postJson('/api/v1/my-notes', ['body' => 'private'])->assertCreated();
        $this->deleteJson('/api/v1/me')->assertOk();

        $deleted = User::withTrashed()->find($user->id);
        $this->assertNotNull($deleted->deleted_at);
        $this->assertNull($deleted->phone);
        $this->assertSame(0, $deleted->userNotes()->count());
        $this->assertSame(0, $deleted->tokens()->count());
    }
}
