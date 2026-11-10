<?php

namespace App\Livewire\Admin;

use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\SubscriptionService;
use App\Support\Translations;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\WithPagination;
use Spatie\SimpleExcel\SimpleExcelWriter;

#[Title('Payments')]
class Payments extends AdminPage
{
    use WithPagination;

    protected ?string $permission = 'approve payments';

    #[Url] public string $tab = 'pending';
    #[Url] public ?int $selected = null;
    public string $rejectReason = '';
    public bool $rejecting = false;

    // Plan editor
    public ?int $planId = null;
    public array $plan = [];

    public function updatingTab(): void
    {
        $this->resetPage();
        $this->selected = null;
    }

    public function approve(SubscriptionService $subs): void
    {
        $p = Payment::query()->findOrFail($this->selected);
        abort_unless($p->status === 'pending', 422);
        $sub = $subs->approvePayment($p, auth()->user());
        $this->toast('Approved — '.$p->user->shortName().' is premium until '.$sub->ends_at->format('j M Y').'.');
        $this->next();
    }

    public function reject(SubscriptionService $subs): void
    {
        $this->validate(['rejectReason' => ['required', 'string', 'min:5', 'max:190']], [], ['rejectReason' => 'reason']);
        $p = Payment::query()->findOrFail($this->selected);
        abort_unless($p->status === 'pending', 422);
        $subs->rejectPayment($p, auth()->user(), $this->rejectReason);
        $this->toast('Rejected. The student was notified.');
        $this->rejecting = false;
        $this->rejectReason = '';
        $this->next();
    }

    /** Jump to the next waiting receipt, like the design's queue. */
    private function next(): void
    {
        $this->selected = Payment::query()->where('status', 'pending')->where('gateway', 'bank_transfer')->oldest()->value('id') ?? $this->selected;
    }

    public function editPlan(?int $id = null): void
    {
        abort_unless(auth()->user()->can('manage plans'), 403);
        $p = $id ? Plan::query()->findOrFail($id) : null;
        $this->planId = $p?->id ?? 0;
        $this->plan = [
            'name' => $p?->getTranslations('name') ?: ['en' => ''],
            'note' => $p?->getTranslations('note') ?: [],
            'badge' => $p?->getTranslations('badge') ?: [],
            'features' => $p?->getTranslations('features') ?: [],
            'price' => $p ? (float) $p->price : '',
            'duration_days' => $p?->duration_days,
            'ends_on' => $p?->ends_on?->toDateString(),
            'is_active' => $p?->is_active ?? true,
            'sort' => $p?->sort ?? Plan::query()->max('sort') + 1,
        ];
    }

    public function savePlan(): void
    {
        abort_unless(auth()->user()->can('manage plans'), 403);
        $this->validate([
            ...Translations::rules('plan.name', 60),
            ...Translations::rules('plan.note', 60, false),
            ...Translations::rules('plan.badge', 30, false),
            ...Translations::rules('plan.features', 1500, false),
            'plan.price' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'plan.duration_days' => ['nullable', 'required_without:plan.ends_on', 'integer', 'min:1', 'max:1825'],
            'plan.ends_on' => ['nullable', 'date'],
            'plan.sort' => ['nullable', 'integer'],
        ], [], ['plan.name.en' => 'name', 'plan.price' => 'price', 'plan.duration_days' => 'duration']);

        $model = $this->planId ? Plan::query()->findOrFail($this->planId) : new Plan(['slug' => \Illuminate\Support\Str::slug($this->plan['name']['en']).'-'.uniqid()]);
        $model->fill([
            'price' => $this->plan['price'],
            'duration_days' => $this->plan['duration_days'] ?: null,
            'ends_on' => $this->plan['ends_on'] ?: null,
            'is_active' => (bool) $this->plan['is_active'],
            'sort' => (int) ($this->plan['sort'] ?? 0),
        ]);
        $model->replaceTranslations('name', Translations::clean($this->plan['name']));
        $model->replaceTranslations('note', Translations::clean($this->plan['note']));
        $model->replaceTranslations('badge', Translations::clean($this->plan['badge']));
        $model->replaceTranslations('features', Translations::clean($this->plan['features']));
        $model->save();

        $this->planId = null;
        $this->toast('Plan saved.');
    }

    public function export()
    {
        abort_unless(auth()->user()->can('view analytics') || auth()->user()->can('approve payments'), 403);
        $path = storage_path('app/private/exports/payments-'.now()->format('Ymd-His').'.csv');
        @mkdir(dirname($path), 0775, true);
        $writer = SimpleExcelWriter::create($path);
        Payment::query()->with(['user', 'plan', 'reviewer'])->latest()->chunk(500, function ($rows) use ($writer) {
            foreach ($rows as $p) {
                $writer->addRow([
                    'Date' => $p->created_at->toDateTimeString(),
                    'Reference' => $p->tx_ref,
                    'Bank reference' => $p->reference,
                    'Student' => $p->user?->name,
                    'Phone' => $p->user?->phone,
                    'Plan' => $p->plan?->getTranslation('name', 'en'),
                    'Gateway' => $p->gatewayLabel(),
                    'Amount (ETB)' => (float) $p->amount,
                    'Discount (ETB)' => (float) $p->discount,
                    'Status' => $p->status,
                    'Reviewed by' => $p->reviewer?->name,
                    'Paid at' => $p->paid_at?->toDateTimeString(),
                ]);
            }
        });
        $writer->close();

        return response()->download($path)->deleteFileAfterSend();
    }

    public function render()
    {
        $month = now()->startOfMonth();
        $gateways = Payment::query()->where('status', 'approved')->where('paid_at', '>=', $month)
            ->groupBy('gateway')->selectRaw('gateway, SUM(amount) total, COUNT(*) n')->get()->keyBy('gateway');

        $queue = Payment::query()->with(['user.university', 'plan'])
            ->when($this->tab === 'pending', fn ($q) => $q->where('status', 'pending')->where('gateway', 'bank_transfer')->oldest())
            ->when($this->tab === 'online', fn ($q) => $q->whereIn('gateway', ['telebirr', 'cbe_birr', 'chapa'])->latest())
            ->when(in_array($this->tab, ['approved', 'rejected'], true), fn ($q) => $q->where('status', $this->tab)->latest('reviewed_at'))
            ->paginate(15);

        $this->selected ??= $queue->first()?->id;
        $current = $this->selected ? Payment::query()->with(['user.university', 'plan', 'reviewer', 'promoCode'])->find($this->selected) : null;

        $subscribers = Subscription::query()->current()->groupBy('plan_id')->selectRaw('plan_id, COUNT(DISTINCT user_id) n')->pluck('n', 'plan_id');

        return view('livewire.admin.payments', [
            'gateways' => $gateways,
            'queue' => $queue,
            'current' => $current,
            'pending' => Payment::query()->where('status', 'pending')->where('gateway', 'bank_transfer')->count(),
            'plans' => Plan::query()->orderBy('sort')->get(),
            'subscribers' => $subscribers,
        ]);
    }
}
