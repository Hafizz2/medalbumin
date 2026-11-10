<?php

namespace App\Livewire\Admin;

use App\Jobs\SendPushCampaign;
use App\Models\PushCampaign;
use App\Models\University;
use App\Services\PushCampaignSender;
use App\Support\Translations;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Title;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Title('Push notifications')]
class Push extends AdminPage
{
    use WithFileUploads, WithPagination;

    protected ?string $permission = 'send notifications';

    public array $audience = ['plan' => 'all', 'target_exam' => '', 'university_id' => '', 'inactive_days' => ''];
    public array $title = ['en' => ''];
    public array $body = ['en' => ''];
    public string $link = '';
    /** announcement = news everyone gets; promo = offers students can switch off. */
    public string $kind = 'announcement';
    public array $actionLabel = ['en' => ''];
    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null */
    public $image = null;
    public string $when = 'now';
    public ?string $scheduledAt = null;

    /** Screens the app can open from a notification. */
    public const LINKS = [
        '' => 'Open the app (Home)',
        'PastPapers' => 'Past papers',
        'QuizBuilder' => 'Practice / quiz builder',
        'LiveMock' => 'Grand mock',
        'Flashcards' => 'Flashcards',
        'Notes' => 'Notes',
        'StudyPlan' => 'Study plan',
        'Plans' => 'Plans & payment',
        'MatchScore' => 'Match predictor',
        'Downloads' => 'Offline downloads',
        'Invite' => 'Invite friends',
    ];

    public function send(PushCampaignSender $sender): void
    {
        $this->validate([
            ...Translations::rules('title', 80),
            ...Translations::rules('body', 300),
            ...Translations::rules('actionLabel', 24, required: false),
            'link' => ['nullable', 'in:'.implode(',', array_keys(self::LINKS))],
            'kind' => ['required', 'in:announcement,promo'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'audience.plan' => ['required', 'in:all,free,premium'],
            'audience.target_exam' => ['nullable', 'in:exit,qualification,licensing,residency'],
            'audience.university_id' => ['nullable', 'exists:universities,id'],
            'audience.inactive_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'scheduledAt' => ['nullable', 'required_if:when,later', 'date', 'after:now'],
        ]);

        $audience = array_filter($this->audience, fn ($v) => $v !== '' && $v !== null);
        $count = $sender->audienceQuery($audience)->count();
        if ($count === 0) {
            $this->addError('audience.plan', 'No students match this audience.');

            return;
        }

        $campaign = PushCampaign::query()->create([
            'kind' => $this->kind,
            'title' => Translations::clean($this->title),
            'body' => Translations::clean($this->body),
            'image_path' => $this->image ? $this->image->store('push', 'public') : null,
            'action_label' => Translations::clean($this->actionLabel) ?: null,
            'audience' => $audience,
            'link' => $this->link ?: null,
            'scheduled_at' => $this->when === 'later' ? Carbon::parse($this->scheduledAt) : now(),
            'recipients_count' => $count,
            'created_by' => auth()->id(),
        ]);
        activity()->performedOn($campaign)->causedBy(auth()->user())->log('Scheduled '.$this->kind.' “'.$this->title['en'].'” to '.number_format($count).' students');

        $now = $this->when === 'now';
        if ($now) {
            SendPushCampaign::dispatch($campaign->id);
        }

        $this->reset(['title', 'body', 'link', 'when', 'scheduledAt', 'kind', 'actionLabel', 'image']);
        $this->toast($now ? 'Sending to '.number_format($count).' students.' : 'Scheduled.');
    }

    public function cancel(int $id): void
    {
        PushCampaign::query()->where('status', 'scheduled')->whereKey($id)->delete();
        $this->toast('Cancelled.');
    }

    public function render(PushCampaignSender $sender)
    {
        $audience = array_filter($this->audience, fn ($v) => $v !== '' && $v !== null);

        return view('livewire.admin.push', [
            'reach' => $sender->audienceQuery($audience)->count(),
            'audienceLabel' => $sender->audienceLabel($audience),
            'campaigns' => PushCampaign::query()->with('creator')->latest()->paginate(15),
            'universities' => University::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'links' => self::LINKS,
            'sender' => $sender,
        ]);
    }
}
