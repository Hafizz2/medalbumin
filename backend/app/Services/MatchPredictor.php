<?php

namespace App\Services;

use App\Models\MatchResult;
use App\Models\Setting;
use App\Models\Specialty;
use App\Models\University;
use Illuminate\Support\Collection;

/**
 * Residency match predictor (Ministry of Health ERMP rules):
 * matching score = entrance exam score + official bonuses (female, managerial post),
 * chance per university = logistic curve around a recency-weighted cut-off.
 * Always shown with "An estimate from past data — not an official result."
 */
class MatchPredictor
{
    public function rules(): array
    {
        return [
            'female_bonus_pct' => (float) Setting::get('match.female_bonus_pct', 5),
            'managerial_bonus_pct' => (float) Setting::get('match.managerial_bonus_pct', 5),
            'minimum_cutoff' => Setting::get('match.minimum_cutoff'),
            'max_specialties' => (int) Setting::get('match.max_specialties', 2),
            'max_universities' => (int) Setting::get('match.max_universities', 3),
            'years' => (int) Setting::get('match.years', 3),
            'audience' => Setting::get('match.audience', 'final_year'),
            'disclaimer' => 'An estimate from past data — not an official result. The Ministry of Health makes the final match.',
            'timeline' => Setting::get('match.timeline', ['Registration', 'Eligibility list', 'Entrance exam', 'Results & appeal', 'Match results']),
            'timeline_step' => (int) Setting::get('match.timeline_step', 0),
        ];
    }

    /** Bonuses are a percentage of the student's own mark (+5% each). */
    public function finalScore(float $raw, bool $female, bool $managerial): float
    {
        $r = $this->rules();
        $pct = ($female ? $r['female_bonus_pct'] : 0) + ($managerial ? $r['managerial_bonus_pct'] : 0);

        return round(min(100, $raw * (1 + $pct / 100)), 1);
    }

    /** Last N years present in the data, newest last. */
    public function years(): array
    {
        $n = $this->rules()['years'];

        return MatchResult::query()->distinct()->orderByDesc('year')->limit($n)->pluck('year')->sort()->values()->all();
    }

    public static function chance(float $score, float $cutoff): int
    {
        return (int) max(3, min(97, round(100 / (1 + exp(-($score - $cutoff) / 2.5)))));
    }

    public static function tier(int $chance): string
    {
        return $chance >= 70 ? 'likely' : ($chance >= 35 ? 'possible' : 'reach');
    }

    /** Newest year counts most: weights 1, 2, 3 … */
    private function weightedCutoff(Collection $byYear, array $years): ?float
    {
        $sum = 0;
        $wsum = 0;
        foreach ($years as $i => $y) {
            if (isset($byYear[$y])) {
                $w = $i + 1;
                $sum += $byYear[$y]->cutoff * $w;
                $wsum += $w;
            }
        }

        return $wsum ? $sum / $wsum : null;
    }

    /** Specialty cards for step 2: lowest matched score last year, seats, competition. */
    public function specialties(float $score): Collection
    {
        $years = $this->years();
        $latest = end($years) ?: null;
        $rows = $latest ? MatchResult::query()->where('year', $latest)->get()->groupBy('specialty_id') : collect();

        return Specialty::query()->active()->get()->map(function (Specialty $s) use ($rows, $score) {
            $r = $rows[$s->id] ?? collect();
            $min = $r->min('cutoff');

            return [
                'id' => $s->id,
                'code' => $s->code,
                'name' => $s->name,
                'competition' => $s->competition,
                'lowest_matched' => $min !== null ? round((float) $min, 1) : null,
                'seats' => (int) $r->sum('seats'),
                'above_last_year' => $min !== null ? $score >= $min : null,
                'gap' => $min !== null ? round($min - $score, 1) : null,
            ];
        });
    }

    /** Universities for one specialty with cut-off trend and chance, best chance first. */
    public function universities(int $specialtyId, float $score): array
    {
        $years = $this->years();
        $results = MatchResult::query()->where('specialty_id', $specialtyId)->whereIn('year', $years)->get()->groupBy('university_id');
        $unis = University::query()->whereIn('id', $results->keys())->get()->keyBy('id');

        $likely = 0;
        $likelyPlus = 0;
        $list = $results->map(function (Collection $rows, $uniId) use ($years, $score, $unis, &$likely, &$likelyPlus) {
            $byYear = $rows->keyBy('year');
            $cut = $this->weightedCutoff($byYear, $years);
            $chance = self::chance($score, $cut);
            $chance >= 70 && $likely++;
            self::chance($score + 3, $cut) >= 70 && $likelyPlus++;
            $trend = collect($years)->map(fn ($y) => isset($byYear[$y]) ? round((float) $byYear[$y]->cutoff, 1) : null)->values();
            $first = $trend->first(fn ($v) => $v !== null);
            $last = $trend->last(fn ($v) => $v !== null);

            return [
                'university_id' => (int) $uniId,
                'name' => $unis[$uniId]?->name,
                'short_name' => $unis[$uniId]?->short_name,
                'seats' => (int) ($byYear->sortKeys()->last()?->seats ?? 0),
                'cutoffs' => $trend,
                'direction' => $last > $first ? 'up' : ($last < $first ? 'down' : 'flat'),
                'chance' => $chance,
                'tier' => self::tier($chance),
            ];
        })->sortByDesc('chance')->values();

        return [
            'years' => $years,
            'universities' => $list,
            'likely' => $likely,
            'others' => $list->count() - $likely,
            'more_likely_with_3' => max(0, $likelyPlus - $likely),
        ];
    }

    /** Probability of matching at least one choice on the list, assuming independence. */
    public static function overallChance(array $chances): int
    {
        if (! $chances) {
            return 0;
        }
        $miss = array_reduce($chances, fn ($acc, $p) => $acc * (1 - $p / 100), 1.0);

        return (int) min(99, round((1 - $miss) * 100));
    }
}
