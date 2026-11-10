<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\MatchChoice;
use App\Models\MatchPrediction;
use App\Models\MatchProfile;
use App\Models\MatchResult;
use App\Models\QuizSession;
use App\Models\Specialty;
use App\Services\MatchPredictor;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class MatchController extends Controller
{
    public function __construct(private readonly MatchPredictor $predictor) {}

    private function profile(Request $request): MatchProfile
    {
        return MatchProfile::query()->firstOrNew(['user_id' => $request->user()->id], [
            'is_female' => $request->user()->gender === 'female',
            'has_managerial' => false,
            'remind' => false,
        ]);
    }

    private function finalScore(MatchProfile $p): ?float
    {
        return $p->raw_score !== null ? $this->predictor->finalScore($p->raw_score, $p->is_female, $p->has_managerial) : null;
    }

    /** Step 1 data: saved score, bonuses, rules and a predicted score from the student's mocks. */
    public function profileShow(Request $request): JsonResponse
    {
        $p = $this->profile($request);
        $mocks = QuizSession::query()->where('user_id', $request->user()->id)->where('status', 'completed')->where('mode', 'timed')->latest()->limit(10)->pluck('score_pct');

        return response()->json([
            'raw_score' => $p->raw_score,
            'is_female' => $p->is_female,
            'has_managerial' => $p->has_managerial,
            'final_score' => $this->finalScore($p),
            'specialty_ids' => $p->specialty_ids ?? [],
            'remind' => $p->remind,
            'predicted' => $mocks->count() >= 3 ? ['score' => (int) round($mocks->avg()), 'from_mocks' => $mocks->count()] : null,
            'rules' => $this->predictor->rules(),
            'has_data' => MatchResult::query()->exists(),
        ]);
    }

    public function profileUpdate(Request $request): JsonResponse
    {
        $rules = $this->predictor->rules();
        $data = $request->validate([
            'raw_score' => ['sometimes', 'numeric', 'between:0,100'],
            'is_female' => ['sometimes', 'boolean'],
            'has_managerial' => ['sometimes', 'boolean'],
            'specialty_ids' => ['sometimes', 'array', 'max:'.$rules['max_specialties']],
            'specialty_ids.*' => ['integer', 'exists:specialties,id'],
            'remind' => ['sometimes', 'boolean'],
        ]);

        $p = $this->profile($request);
        $p->fill($data)->save();

        if (array_key_exists('specialty_ids', $data)) {
            MatchChoice::query()->where('user_id', $request->user()->id)->whereNotIn('specialty_id', $data['specialty_ids'] ?: [0])->delete();
        }

        return response()->json(['final_score' => $this->finalScore($p), 'specialty_ids' => $p->specialty_ids ?? []]);
    }

    /** Step 2: specialty cards. */
    public function specialties(Request $request): JsonResponse
    {
        $score = $this->finalScore($this->profile($request)) ?? 0;

        return response()->json([
            'score' => $score,
            'max' => $this->predictor->rules()['max_specialties'],
            'data' => $this->predictor->specialties($score),
        ]);
    }

    /** Step 3: chances per university for one specialty. */
    public function chances(Request $request, Specialty $specialty): JsonResponse
    {
        $p = $this->profile($request);
        $score = $this->finalScore($p);
        abort_if($score === null, 422, 'Enter your score first.');

        MatchPrediction::query()->create(['user_id' => $request->user()->id, 'score' => $score]);
        $out = $this->predictor->universities($specialty->id, $score);
        $picked = MatchChoice::query()->where('user_id', $request->user()->id)->where('specialty_id', $specialty->id)->orderBy('rank')->pluck('university_id');

        return response()->json($out + [
            'specialty' => ['id' => $specialty->id, 'name' => $specialty->name],
            'score' => $score,
            'picked' => $picked,
            'max' => $this->predictor->rules()['max_universities'],
            'disclaimer' => $this->predictor->rules()['disclaimer'],
        ]);
    }

    /** Saves the ordered university list for one specialty. */
    public function saveChoices(Request $request, Specialty $specialty): JsonResponse
    {
        $max = $this->predictor->rules()['max_universities'];
        $data = $request->validate([
            'university_ids' => ['present', 'array', 'max:'.$max],
            'university_ids.*' => ['integer', 'distinct', 'exists:universities,id'],
        ]);
        $userId = $request->user()->id;

        DB::transaction(function () use ($data, $specialty, $userId) {
            MatchChoice::query()->where('user_id', $userId)->where('specialty_id', $specialty->id)->delete();
            foreach (array_values($data['university_ids']) as $i => $uni) {
                MatchChoice::query()->create(['user_id' => $userId, 'specialty_id' => $specialty->id, 'university_id' => $uni, 'rank' => $i + 1]);
            }
        });

        return $this->choices($request);
    }

    /** "My choice list" with the overall chance of matching somewhere on it. */
    public function choices(Request $request): JsonResponse
    {
        return response()->json($this->choiceList($request));
    }

    private function choiceList(Request $request): array
    {
        $p = $this->profile($request);
        $score = $this->finalScore($p) ?? 0;
        $order = $p->specialty_ids ?? [];
        $choices = MatchChoice::query()->where('user_id', $request->user()->id)->with(['specialty', 'university'])->orderBy('rank')->get()
            ->groupBy('specialty_id')
            ->sortBy(fn ($g, $sid) => ($i = array_search($sid, $order)) === false ? 99 : $i);

        $all = [];
        $groups = $choices->values()->map(function ($rows, $i) use ($score, &$all) {
            $chances = collect($this->predictor->universities($rows->first()->specialty_id, $score)['universities'])->keyBy('university_id');
            $items = $rows->map(function ($c) use ($chances, &$all) {
                $chance = $chances[$c->university_id]['chance'] ?? 0;
                $all[] = $chance;

                return ['university_id' => $c->university_id, 'name' => $c->university->name, 'rank' => $c->rank, 'chance' => $chance, 'tier' => MatchPredictor::tier($chance)];
            })->values();

            return [
                'order' => $i + 1,
                'specialty' => ['id' => $rows->first()->specialty_id, 'name' => $rows->first()->specialty->name],
                'has_safe_option' => $items->contains(fn ($x) => $x['chance'] >= 70),
                'items' => $items,
            ];
        })->values();

        $rules = $this->predictor->rules();

        return [
            'score' => $score,
            'overall_chance' => MatchPredictor::overallChance($all),
            'groups' => $groups,
            'timeline' => collect($rules['timeline'])->map(fn ($t, $i) => ['title' => $t, 'done' => $i <= $rules['timeline_step']])->values(),
            'remind' => $p->remind,
            'disclaimer' => $rules['disclaimer'],
        ];
    }

    /** Share as PDF. */
    public function pdf(Request $request): Response
    {
        $data = $this->choiceList($request) + ['user' => $request->user()];

        return Pdf::loadView('pdf.match-choices', $data)->download('medalbumin-choice-list.pdf');
    }
}
