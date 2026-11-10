<?php

namespace App\Livewire\Admin;

use App\Models\PromoCode;
use App\Models\Referral;
use App\Models\Setting;
use App\Models\University;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\WithPagination;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

#[Title('Settings')]
class Settings extends AdminPage
{
    use WithPagination;

    #[Url] public string $tab = 'roles';

    public ?array $invite = null;
    public ?string $newPassword = null;
    public ?array $promo = null;
    public array $general = [];
    public array $banks = [];

    public function boot(): void
    {
        abort_unless(auth()->user()?->hasRole('Super admin'), 403);
    }

    public function mount(): void
    {
        $this->general = [
            'guest_free_questions' => Setting::get('guest_free_questions', 20),
            'max_devices' => Setting::get('max_devices', 2),
            'pass_mark' => Setting::get('pass_mark', 50),
            'seconds_per_question' => Setting::get('seconds_per_question', 72),
            'referral_reward_days' => Setting::get('referral_reward_days', 30),
            'referral_max_rewards' => Setting::get('referral_max_rewards', 6),
        ];
        $this->banks = Setting::get('bank_accounts', []) ?: [];
    }

    public function togglePermission(string $role, string $permission): void
    {
        abort_if($role === 'Super admin', 422);
        $r = Role::findByName($role, 'web');
        $r->hasPermissionTo($permission) ? $r->revokePermissionTo($permission) : $r->givePermissionTo($permission);
        activity()->causedBy(auth()->user())->log("Changed “{$permission}” for {$role}");
    }

    public function startInvite(): void
    {
        $this->invite = ['name' => '', 'email' => '', 'role' => 'Content editor'];
        $this->newPassword = null;
    }

    public function sendInvite(): void
    {
        $this->validate([
            'invite.name' => ['required', 'string', 'max:120'],
            'invite.email' => ['required', 'email', 'max:190'],
            'invite.role' => ['required', Rule::in(array_keys(RolesSeeder::ROLES))],
        ], [], ['invite.email' => 'email']);

        $email = Str::lower($this->invite['email']);
        $user = User::query()->where('email', $email)->first();
        $this->newPassword = null;
        if (! $user) {
            $this->newPassword = Str::password(14, symbols: false);
            $user = User::query()->forceCreate(['name' => $this->invite['name'], 'email' => $email, 'email_verified_at' => now(), 'password' => $this->newPassword, 'is_staff' => true]);
        } else {
            $user->forceFill(['is_staff' => true, 'name' => $user->name ?: $this->invite['name']])->save();
        }
        $user->syncRoles([$this->invite['role']]);
        activity()->performedOn($user)->causedBy(auth()->user())->log("Gave {$user->name} the {$this->invite['role']} role");
        $this->invite = null;
        $this->toast('Admin account ready.');
    }

    public function changeRole(int $userId, string $role): void
    {
        abort_unless(array_key_exists($role, RolesSeeder::ROLES), 422);
        $u = User::query()->staff()->findOrFail($userId);
        abort_if($u->id === auth()->id() && $role !== 'Super admin', 422, 'You cannot remove your own super admin role.');
        $u->syncRoles([$role]);
        activity()->performedOn($u)->causedBy(auth()->user())->log("Changed {$u->name} to {$role}");
    }

    public function removeStaff(int $userId): void
    {
        abort_if($userId === auth()->id(), 422);
        $u = User::query()->staff()->findOrFail($userId);
        $u->syncRoles([]);
        $u->forceFill(['is_staff' => false])->save();
        $u->tokens()->delete();
        activity()->performedOn($u)->causedBy(auth()->user())->log("Removed admin access for {$u->name}");
        $this->toast('Admin access removed.');
    }

    public function editPromo(?int $id = null): void
    {
        $p = $id ? PromoCode::query()->findOrFail($id) : null;
        $this->promo = [
            'id' => $p?->id, 'code' => $p?->code ?? '', 'description' => $p?->description ?? '', 'type' => $p?->type ?? 'percent', 'value' => $p ? (float) $p->value : 20,
            'university_id' => $p?->university_id, 'plan_id' => $p?->plan_id, 'max_uses' => $p?->max_uses, 'ends_at' => $p?->ends_at?->toDateString(), 'is_active' => $p?->is_active ?? true,
        ];
    }

    public function savePromo(): void
    {
        $this->promo['code'] = Str::upper(trim($this->promo['code']));
        $this->validate([
            'promo.code' => ['required', 'alpha_dash', 'max:40', Rule::unique('promo_codes', 'code')->ignore($this->promo['id'])],
            'promo.type' => ['required', 'in:percent,fixed,free_days'],
            'promo.value' => ['required', 'numeric', 'min:0.01', $this->promo['type'] === 'percent' ? 'max:100' : 'max:100000'],
            'promo.university_id' => ['nullable', 'exists:universities,id'],
            'promo.plan_id' => ['nullable', 'exists:plans,id'],
            'promo.max_uses' => ['nullable', 'integer', 'min:1'],
            'promo.ends_at' => ['nullable', 'date'],
        ], [], ['promo.code' => 'code', 'promo.value' => 'value']);

        PromoCode::query()->updateOrCreate(['id' => $this->promo['id']], [
            'code' => $this->promo['code'], 'description' => $this->promo['description'] ?: null, 'type' => $this->promo['type'], 'value' => $this->promo['value'],
            'university_id' => $this->promo['university_id'] ?: null, 'plan_id' => $this->promo['plan_id'] ?: null, 'max_uses' => $this->promo['max_uses'] ?: null,
            'ends_at' => $this->promo['ends_at'] ? \Illuminate\Support\Carbon::parse($this->promo['ends_at'])->endOfDay() : null,
            'is_active' => (bool) $this->promo['is_active'], 'created_by' => auth()->id(),
        ]);
        activity()->causedBy(auth()->user())->log('Saved promo code '.$this->promo['code']);
        $this->promo = null;
        $this->toast('Promo code saved.');
    }

    public function addBank(): void
    {
        $this->banks[] = ['bank' => '', 'account_name' => '', 'account_number' => ''];
    }

    public function removeBank(int $i): void
    {
        unset($this->banks[$i]);
        $this->banks = array_values($this->banks);
    }

    public function saveGeneral(): void
    {
        $this->validate([
            'general.guest_free_questions' => ['required', 'integer', 'between:0,500'],
            'general.max_devices' => ['required', 'integer', 'between:1,10'],
            'general.pass_mark' => ['required', 'integer', 'between:1,100'],
            'general.seconds_per_question' => ['required', 'integer', 'between:10,600'],
            'general.referral_reward_days' => ['required', 'integer', 'between:0,365'],
            'general.referral_max_rewards' => ['required', 'integer', 'between:0,100'],
            'banks.*.bank' => ['required', 'string', 'max:80'],
            'banks.*.account_name' => ['required', 'string', 'max:120'],
            'banks.*.account_number' => ['required', 'string', 'max:40'],
        ]);
        foreach ($this->general as $k => $v) {
            Setting::put($k, (int) $v);
        }
        Setting::put('bank_accounts', array_values($this->banks));
        activity()->causedBy(auth()->user())->log('Updated general settings');
        $this->toast('Settings saved.');
    }

    public function render()
    {
        $roles = Role::query()->with('permissions')->get()->keyBy('name');

        return view('livewire.admin.settings', [
            'roleNames' => array_keys(RolesSeeder::ROLES),
            'roles' => $roles,
            'roleCounts' => collect(RolesSeeder::ROLES)->map(fn ($p, $name) => User::role($name)->count()),
            'permissions' => RolesSeeder::PERMISSIONS,
            'staff' => User::query()->staff()->with('roles')->orderBy('name')->get(),
            'codes' => PromoCode::query()->with(['university', 'plan'])->latest()->get(),
            'universities' => University::query()->orderBy('name')->get(['id', 'name']),
            'plans' => \App\Models\Plan::query()->orderBy('sort')->get(),
            'invites' => [
                'month' => Referral::query()->where('created_at', '>=', now()->startOfMonth())->count(),
                'paying' => Referral::query()->where('status', 'rewarded')->where('rewarded_at', '>=', now()->startOfMonth())->count(),
            ],
            'log' => $this->tab === 'audit' ? Activity::query()->with('causer')->latest()->paginate(30) : null,
        ]);
    }
}
