<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\Question;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExamBuilder
{
    /**
     * Picks questions for an exam from its blueprint ({department_id: count}).
     * Prefers questions that have not appeared in earlier grand mocks.
     */
    public function generate(Exam $exam): int
    {
        if ($exam->type === 'past_paper') {
            $ids = $exam->paper?->questions()->published()->pluck('id') ?? collect();
        } elseif ($exam->type === 'daily') {
            // Daily minis are personalised per student when they start (weak-topic mix).
            $exam->update(['question_count' => $exam->question_count ?: 20]);

            return $exam->question_count;
        } else {
            $ids = $this->fromBlueprint($exam);
        }

        if ($ids->isEmpty()) {
            throw ValidationException::withMessages(['blueprint' => 'No published questions match this exam yet.']);
        }

        DB::transaction(function () use ($exam, $ids) {
            DB::table('exam_questions')->where('exam_id', $exam->id)->delete();
            DB::table('exam_questions')->insert($ids->values()->map(fn ($id, $i) => ['exam_id' => $exam->id, 'question_id' => $id, 'position' => $i + 1])->all());
            $exam->update(['question_count' => $ids->count()]);
        });

        return $ids->count();
    }

    private function fromBlueprint(Exam $exam): Collection
    {
        $used = DB::table('exam_questions')->join('exams', 'exams.id', '=', 'exam_questions.exam_id')
            ->where('exams.type', 'grand')->where('exams.id', '!=', $exam->id)->pluck('question_id');

        $ids = collect();
        foreach ($exam->blueprint ?? [] as $deptId => $count) {
            $count = (int) $count;
            if ($count <= 0) {
                continue;
            }
            $fresh = Question::query()->published()->where('department_id', $deptId)->whereNotIn('id', $used)->inRandomOrder()->limit($count)->pluck('id');
            $more = $fresh->count() < $count
                ? Question::query()->published()->where('department_id', $deptId)->whereNotIn('id', $fresh)->inRandomOrder()->limit($count - $fresh->count())->pluck('id')
                : collect();
            $ids = $ids->merge($fresh)->merge($more);
        }

        return $ids->shuffle();
    }

    public function available(): Collection
    {
        return Question::query()->published()->groupBy('department_id')->selectRaw('department_id, COUNT(*) n')->pluck('n', 'department_id');
    }
}
