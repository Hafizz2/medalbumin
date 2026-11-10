<?php

namespace App\Livewire\Admin;

use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Livewire\Attributes\Title;

#[Title('Security')]
class Security extends AdminPage
{
    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';
    public string $code = '';
    public string $confirmPassword = '';
    public bool $showRecovery = false;

    private function checkPassword(): void
    {
        if (! Hash::check($this->confirmPassword, (string) auth()->user()->password)) {
            throw ValidationException::withMessages(['confirmPassword' => 'Your password is not correct.']);
        }
        $this->confirmPassword = '';
    }

    public function enable(EnableTwoFactorAuthentication $enable): void
    {
        $this->checkPassword();
        $enable(auth()->user());
    }

    public function confirm(ConfirmTwoFactorAuthentication $confirm): void
    {
        $this->validate(['code' => ['required', 'digits:6']]);
        $confirm(auth()->user(), $this->code);
        $this->code = '';
        $this->showRecovery = true;
        $this->toast('2-step login is on.');
    }

    public function disable(DisableTwoFactorAuthentication $disable): void
    {
        $this->checkPassword();
        $disable(auth()->user());
        $this->toast('2-step login turned off.');
    }

    public function regenerate(GenerateNewRecoveryCodes $generate): void
    {
        $this->checkPassword();
        $generate(auth()->user());
        $this->showRecovery = true;
    }

    public function changePassword(): void
    {
        $this->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(10)->mixedCase()->numbers()->uncompromised()],
        ]);
        auth()->user()->forceFill(['password' => $this->password])->save();
        $this->reset(['current_password', 'password', 'password_confirmation']);
        $this->toast('Password changed.');
    }

    public function render()
    {
        $user = auth()->user()->fresh();

        return view('livewire.admin.security', [
            'user' => $user,
            'pending' => $user->two_factor_secret && ! $user->two_factor_confirmed_at,
            'enabled' => (bool) $user->two_factor_confirmed_at,
        ]);
    }
}
