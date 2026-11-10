<?php

namespace App\Services\Importers;

use App\Models\Department;
use App\Models\Language;
use App\Models\Paper;
use App\Models\Question;
use App\Models\Subcategory;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\SimpleExcel\SimpleExcelReader;
use Throwable;

/**
 * Imports questions from Excel / CSV. One row per question.
 *
 * Required columns: department, stem, option_a, option_b, correct
 * Optional: subcategory, topic, paper_id, year, difficulty, option_c…option_e,
 *           explanation, is_free, image_caption, and translations with a
 *           locale suffix: stem_am, option_a_am, explanation_am (am, om, so, ar).
 */
class QuestionImporter
{
    public const COLUMNS = [
        'department', 'subcategory', 'topic', 'paper_id', 'year', 'difficulty',
        'stem', 'option_a', 'option_b', 'option_c', 'option_d', 'option_e', 'correct', 'explanation', 'is_free',
        'stem_am', 'option_a_am', 'option_b_am', 'option_c_am', 'option_d_am', 'option_e_am', 'explanation_am',
    ];

    private array $departments = [];

    /**
     * @return array{created:int, errors:array<int, string>}
     */
    public function import(string $path, User $author, string $status = 'draft', ?string $extension = null): array
    {
        $this->departments = Department::query()->get()->flatMap(fn ($d) => [
            Str::lower($d->getTranslation('name', 'en')) => $d->id,
            Str::lower((string) $d->short_name) => $d->id,
            Str::lower($d->slug) => $d->id,
        ])->filter()->all();

        $reader = SimpleExcelReader::create($path, $extension)->trimHeaderRow()->headersToSnakeCase();
        $created = 0;
        $errors = [];

        foreach ($reader->getRows() as $i => $row) {
            $line = $i + 2; // header is line 1
            try {
                DB::transaction(function () use ($row, $author, $status, &$created) {
                    $this->importRow(array_map(fn ($v) => is_string($v) ? trim($v) : $v, $row), $author, $status);
                    $created++;
                });
            } catch (Throwable $e) {
                $errors[$line] = $e->getMessage();
            }
            if (count($errors) > 200) {
                $errors[0] = 'Stopped after 200 errors.';
                break;
            }
        }

        return ['created' => $created, 'errors' => $errors];
    }

    private function importRow(array $row, User $author, string $status): void
    {
        $deptId = $this->departments[Str::lower((string) ($row['department'] ?? ''))] ?? null;
        if (! $deptId) {
            throw new \RuntimeException('Unknown department “'.($row['department'] ?? '').'”.');
        }
        if (blank($row['stem'] ?? null) || blank($row['option_a'] ?? null) || blank($row['option_b'] ?? null)) {
            throw new \RuntimeException('Stem, option A and option B are required.');
        }

        $keys = collect(['a', 'b', 'c', 'd', 'e'])->filter(fn ($k) => filled($row['option_'.$k] ?? null));
        $correct = Str::upper((string) ($row['correct'] ?? ''));
        if (! $keys->contains(Str::lower($correct))) {
            throw new \RuntimeException("Correct answer “{$correct}” does not match a filled option.");
        }

        $sub = filled($row['subcategory'] ?? null)
            ? Subcategory::query()->where('department_id', $deptId)->where(fn ($q) => $q->where('name->en', $row['subcategory'])->orWhere('slug', Str::slug($row['subcategory'])))->first()
            : null;
        if (filled($row['subcategory'] ?? null) && ! $sub) {
            throw new \RuntimeException('Unknown subcategory “'.$row['subcategory'].'” in this department.');
        }
        $topic = $sub && filled($row['topic'] ?? null) ? Topic::query()->where('subcategory_id', $sub->id)->where('name->en', $row['topic'])->first() : null;
        $paper = filled($row['paper_id'] ?? null) ? Paper::query()->find((int) $row['paper_id']) : null;

        // English in "stem", other languages in "stem_am", "stem_om", … for every configured language.
        $codes = Language::contentCodes();
        $tr = fn (string $base) => array_filter(
            collect($codes)->mapWithKeys(fn ($l) => [$l => $row[$l === Language::DEFAULT ? $base : $base.'_'.$l] ?? null])->all(),
            fn ($v) => filled($v),
        );

        $stem = $tr('stem');
        $translationStatus = collect($codes)->reject(fn ($l) => $l === Language::DEFAULT)->filter(fn ($l) => isset($stem[$l]))->mapWithKeys(fn ($l) => [$l => 'draft'])->all();

        $difficulty = Str::lower((string) ($row['difficulty'] ?? 'moderate'));

        $q = Question::query()->create([
            'department_id' => $deptId,
            'subcategory_id' => $sub?->id,
            'topic_id' => $topic?->id,
            'paper_id' => $paper?->id,
            'paper_position' => $paper ? ($paper->questions()->max('paper_position') + 1) : null,
            'exam_year' => filled($row['year'] ?? null) ? (int) $row['year'] : $paper?->year,
            'difficulty' => in_array($difficulty, ['easy', 'moderate', 'hard'], true) ? $difficulty : 'moderate',
            'stem' => $stem,
            'explanation' => $tr('explanation') ?: null,
            'correct_option' => $correct,
            'status' => $status,
            'is_free' => in_array(Str::lower((string) ($row['is_free'] ?? '')), ['1', 'yes', 'true', 'y'], true),
            'translation_status' => $translationStatus ?: null,
            'author_id' => $author->id,
            'updated_by' => $author->id,
            'published_at' => $status === 'published' ? now() : null,
        ]);

        foreach ($keys as $k) {
            $q->options()->create(['key' => Str::upper($k), 'text' => $tr('option_'.$k)]);
        }
    }
}
