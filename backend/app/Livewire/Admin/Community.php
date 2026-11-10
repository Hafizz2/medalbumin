<?php

namespace App\Livewire\Admin;

use App\Models\Question;
use App\Models\QuestionComment;
use App\Notifications\AppNotification;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

#[Title('Community')]
class Community extends AdminPage
{
    use WithPagination;

    protected ?string $permission = 'answer doubts';

    #[Url] public string $tab = 'doubt';
    public ?int $selected = null;
    public string $answer = '';
    public bool $pin = true;
    public bool $addToExplanation = false;
    public bool $notify = true;

    public function updatingTab(): void
    {
        $this->selected = null;
        $this->resetPage();
    }

    private function doubts()
    {
        return QuestionComment::query()->visible()->whereNull('parent_id')->where('kind', 'doubt')->whereNull('answered_at');
    }

    private function flagged()
    {
        return QuestionComment::query()->where('flags_count', '>', 0)->whereNull('flags_resolved_at');
    }

    public function postAnswer(): void
    {
        $this->validate(['answer' => ['required', 'string', 'min:5', 'max:3000']]);
        $doubt = QuestionComment::query()->with(['user', 'question'])->findOrFail($this->selected);
        $me = auth()->user();

        DB::transaction(function () use ($doubt, $me) {
            if ($this->pin) {
                QuestionComment::query()->where('question_id', $doubt->question_id)->update(['is_verified' => false]);
            }
            QuestionComment::query()->create([
                'question_id' => $doubt->question_id,
                'user_id' => $me->id,
                'parent_id' => $doubt->id,
                'kind' => 'reply',
                'body' => trim($this->answer),
                'is_faculty' => true,
                'is_verified' => $this->pin,
            ]);
            $doubt->update(['answered_at' => now(), 'replies_count' => $doubt->replies_count + 1]);
            Question::query()->whereKey($doubt->question_id)->increment('comments_count');

            if ($this->addToExplanation && $doubt->question) {
                $q = $doubt->question;
                $q->setTranslation('explanation', 'en', trim(($q->getTranslation('explanation', 'en', false) ?: '')."\n\n".trim($this->answer)));
                $q->updated_by = $me->id;
                $q->save();
            }
        });

        if ($this->notify && $doubt->user && $doubt->user_id !== $me->id) {
            $doubt->user->notify(AppNotification::facultyAnswered($doubt->question_id, $this->answer));
        }
        activity()->performedOn($doubt->question)->causedBy($me)->log('Posted verified answer on '.$doubt->question?->code);

        $this->reset(['answer']);
        $this->selected = $this->doubts()->oldest()->value('id');
        $this->toast('Answer posted.');
    }

    /** $action: keep | warn | hide */
    public function moderate(string $action): void
    {
        $c = QuestionComment::query()->with('user')->findOrFail($this->selected);
        $c->update([
            'flags_resolved_at' => now(),
            'status' => $action === 'hide' ? 'hidden' : $c->status,
        ]);
        if ($action === 'warn' && $c->user) {
            $c->user->notify(new AppNotification('warning', 'Community guidelines', 'One of your posts was reported. Please keep discussions respectful and on topic — repeated reports can lead to suspension.', 'Discussion', ['questionId' => $c->question_id]));
        }
        if ($action === 'hide') {
            Question::query()->whereKey($c->question_id)->where('comments_count', '>', 0)->decrement('comments_count');
        }
        activity()->performedOn($c)->causedBy(auth()->user())->log(ucfirst($action).' flagged post on question #'.$c->question_id);
        $this->selected = $this->flagged()->oldest()->value('id');
        $this->toast('Done.');
    }

    public function render()
    {
        $items = ($this->tab === 'doubt' ? $this->doubts() : $this->flagged())
            ->with(['user.university', 'question'])->oldest()->paginate(20);
        $this->selected ??= $items->first()?->id;
        $current = $this->selected ? QuestionComment::query()->with(['user.university', 'question', 'replies.user', 'flags'])->find($this->selected) : null;

        return view('livewire.admin.community', [
            'items' => $items,
            'current' => $current,
            'counts' => ['doubt' => $this->doubts()->count(), 'flag' => $this->flagged()->count()],
            'weekPosts' => QuestionComment::query()->where('created_at', '>=', now()->subWeek())->count(),
        ]);
    }
}
