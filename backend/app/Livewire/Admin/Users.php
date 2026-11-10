<?php

namespace App\Livewire\Admin;

use App\Models\Device;
use App\Models\Payment;
use App\Models\User;
use App\Services\AuthService;
use App\Services\ProgressService;
use App\Services\SubscriptionService;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\WithPagination;
use Spatie\SimpleExcel\SimpleExcelWriter;

#[Title('Users')]
class Users extends AdminPage
{
    use WithPagination;

    protected ?string $permission = 'manage users';

    #[Url] public string $tab = 'all';
    #[Url] public string $search = '';
    public ?int $selected = null;
    public string $suspendReason = '';
    public int $grantDays = 30;

    public function updating($name): void
    {
        if (in_array($name, ['tab', 'search'], true)) {
            $this->resetPage();
        }
    }

    private function filtered(string $tab): Builder
    {
        return User::query()->students()->where('is_guest', false)
            ->when($tab === 'subscribers', fn ($q) => $q->whereHas('subscriptions', fn ($s) => $s->current()))
            ->when($tab === 'pending', fn ($q) => $q->whereHas('payments', fn ($p) => $p->where('status', 'pending')->where('gateway', 'bank_transfer')))
            ->when($tab === 'suspended', fn ($q) => $q->where('status', 'suspended'));
    }

    public function suspend(): void
    {
        $this->validate(['suspendReason' => ['required', 'string', 'max:190']], [], ['suspendReason' => 'reason']);
        $user = User::query()->findOrFail($this->selected);
        $user->forceFill(['status' => 'suspended', 'suspended_reason' => $this->suspendReason])->save();
        $user->tokens()->delete();
        activity()->performedOn($user)->causedBy(auth()->user())->log('Suspended '.$user->name.' — '.$this->suspendReason);
        $this->suspendReason = '';
        $this->toast('Account suspended and signed out everywhere.');
    }

    public function unsuspend(): void
    {
        $user = User::query()->findOrFail($this->selected);
        $user->forceFill(['status' => 'active', 'suspended_reason' => null])->save();
        activity()->performedOn($user)->causedBy(auth()->user())->log('Re-activated '.$user->name);
        $this->toast('Account active again.');
    }

    public function revokeDevice(int $id, AuthService $auth): void
    {
        $device = Device::query()->where('user_id', $this->selected)->findOrFail($id);
        $auth->revokeDevice($device);
        $this->toast('Device signed out.');
    }

    public function grant(SubscriptionService $subs): void
    {
        abort_unless(auth()->user()->can('approve payments'), 403);
        $this->validate(['grantDays' => ['required', 'integer', 'between:1,730']]);
        $user = User::query()->findOrFail($this->selected);
        $sub = $subs->grant($user, null, 'admin', days: $this->grantDays);
        activity()->performedOn($user)->causedBy(auth()->user())->log("Granted {$this->grantDays} premium days to {$user->name}");
        $this->toast('Premium until '.$sub->ends_at->format('j M Y').'.');
    }

    public function export()
    {
        abort_unless(auth()->user()->can('view analytics'), 403);
        $path = storage_path('app/private/exports/users-'.now()->format('Ymd-His').'.csv');
        @mkdir(dirname($path), 0775, true);
        $writer = SimpleExcelWriter::create($path);
        $this->filtered($this->tab)->with('university')->chunk(500, function ($users) use ($writer) {
            foreach ($users as $u) {
                $writer->addRow([
                    'ID' => $u->id, 'Name' => $u->name, 'Phone' => $u->phone, 'Email' => $u->email,
                    'University' => $u->university?->name, 'Program' => $u->program, 'Year' => $u->study_year,
                    'Target exam' => $u->target_exam, 'Premium' => $u->isPremium() ? 'yes' : 'no', 'Status' => $u->status,
                    'Joined' => $u->created_at->toDateString(), 'Last active' => $u->last_active_at?->toDateTimeString(),
                ]);
            }
        });
        $writer->close();

        return response()->download($path)->deleteFileAfterSend();
    }

    public function render(AuthService $auth, ProgressService $progress)
    {
        $users = $this->filtered($this->tab)->with(['university', 'activeSubscription.plan'])
            ->withCount(['devices as active_devices_count' => fn ($q) => $q->whereNull('revoked_at')])
            ->withExists(['payments as has_pending_payment' => fn ($q) => $q->where('status', 'pending')->where('gateway', 'bank_transfer')])
            ->when($this->search, fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")->orWhere('phone', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%")))
            ->latest('last_active_at')->paginate(25);

        $current = $this->selected ? User::query()->with(['university', 'devices' => fn ($q) => $q->whereNull('revoked_at'), 'activeSubscription.plan'])->find($this->selected) : null;

        return view('livewire.admin.users', [
            'users' => $users,
            'counts' => collect(['all', 'subscribers', 'pending', 'suspended'])->mapWithKeys(fn ($t) => [$t => $this->filtered($t)->count()]),
            'current' => $current,
            'stats' => $current ? $progress->totals($current) : null,
            'payments' => $current ? Payment::query()->with('plan')->where('user_id', $current->id)->latest()->limit(5)->get() : collect(),
            'premiumUntil' => $current ? app(SubscriptionService::class)->premiumUntil($current) : null,
            'maxDevices' => $auth->maxDevices(),
        ]);
    }
}
