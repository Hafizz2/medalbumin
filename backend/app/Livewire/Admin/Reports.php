<?php

namespace App\Livewire\Admin;

use App\Models\Question;
use App\Models\QuestionReport;
use App\Models\StudentVerification;
use App\Notifications\AppNotification;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

#[Title('Reported')]
class Reports extends AdminPage
{
    use WithPagination;

    protected ?string $permission = 'edit content';

    #[Url] public string $tab = 'questions';
    public array $notes = [];

    /** Resolve or dismiss every open report on a question. */
    public function close(int $questionId, string $status): void
    {
        abort_unless(in_array($status, ['resolved', 'dismissed'], true), 422);
        $reports = QuestionReport::query()->with('user')->where('question_id', $questionId)->where('status', 'open')->get();
        QuestionReport::query()->whereIn('id', $reports->pluck('id'))->update(['status' => $status, 'resolved_by' => auth()->id(), 'resolved_at' => now()]);

        if ($status === 'resolved') {
            $code = Question::withTrashed()->find($questionId)?->code;
            foreach ($reports->pluck('user')->filter()->unique('id') as $u) {
                $u->notify(new AppNotification('report_fixed', 'Thanks for your report', "We reviewed and fixed {$code}.", null, [], false));
            }
        }
        $this->toast($status === 'resolved' ? 'Marked as fixed. Reporters were thanked.' : 'Reports dismissed.');
    }

    public function decideId(int $id, string $decision): void
    {
        abort_unless(auth()->user()->can('approve payments') || auth()->user()->can('manage users'), 403);
        $v = StudentVerification::query()->with('user')->findOrFail($id);
        $v->update(['status' => $decision, 'reviewed_by' => auth()->id(), 'reviewed_at' => now(), 'note' => $this->notes[$id] ?? null]);
        $v->user?->notify(new AppNotification(
            'verification',
            $decision === 'approved' ? 'Student ID verified' : 'Student ID not accepted',
            $decision === 'approved' ? 'You can now use student discounts.' : ($this->notes[$id] ?? 'Please upload a clearer photo of your student ID.'),
            'Profile',
        ));
        $this->toast('Saved.');
    }

    public function render()
    {
        $grouped = QuestionReport::query()->where('status', 'open')
            ->selectRaw('question_id, COUNT(*) as total, MAX(created_at) as latest')
            ->groupBy('question_id')->orderByDesc('total')->orderByDesc('latest')
            ->paginate(15, pageName: 'rp');

        $questions = Question::withTrashed()->with(['department', 'subcategory'])->whereIn('id', collect($grouped->items())->pluck('question_id'))->get()->keyBy('id');
        $details = QuestionReport::query()->with('user')->where('status', 'open')->whereIn('question_id', $questions->keys())->latest()->get()->groupBy('question_id');

        return view('livewire.admin.reports', [
            'grouped' => $grouped,
            'questions' => $questions,
            'details' => $details,
            'openCount' => QuestionReport::query()->where('status', 'open')->count(),
            'ids' => StudentVerification::query()->with('user.university')->where('status', 'pending')->oldest()->paginate(12, pageName: 'ip'),
            'idCount' => StudentVerification::query()->where('status', 'pending')->count(),
            'reasons' => QuestionReport::REASONS,
        ]);
    }
}
