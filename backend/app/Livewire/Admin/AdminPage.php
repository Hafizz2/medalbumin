<?php

namespace App\Livewire\Admin;

use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.admin')]
abstract class AdminPage extends Component
{
    /** Permission needed to open the page (null = any staff). */
    protected ?string $permission = null;

    public function boot(): void
    {
        if ($this->permission) {
            abort_unless(auth()->user()?->can($this->permission), 403);
        }
    }

    protected function toast(string $message, string $type = 'ok'): void
    {
        $this->dispatch('toast', message: $message, type: $type);
    }
}
