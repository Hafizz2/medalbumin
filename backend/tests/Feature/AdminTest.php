<?php

namespace Tests\Feature;

use App\Livewire\Admin\Questions;
use App\Models\Department;
use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedContent();
    }

    public function test_every_admin_page_renders_for_super_admin(): void
    {
        $this->actingAs($this->superAdmin());
        foreach (['dashboard', 'questions', 'exams', 'videos', 'notes', 'library', 'match', 'users', 'payments', 'reports', 'push', 'community', 'analytics', 'languages', 'settings', 'security'] as $page) {
            $this->get(route('admin.'.$page))->assertOk();
        }
    }

    public function test_students_and_guests_cannot_open_the_console(): void
    {
        $this->get('/admin')->assertRedirect(route('login'));
        // A signed-in student is sent to their own account page.
        $this->actingAs(User::query()->forceCreate(['name' => 'S', 'phone' => '+251966666666']))->get('/admin')->assertRedirect(route('account'));
    }

    public function test_two_step_login_is_optional_unless_switched_on(): void
    {
        $admin = $this->superAdmin();
        $admin->forceFill(['two_factor_confirmed_at' => null])->save();
        $this->actingAs($admin)->get('/admin/questions')->assertOk();

        config(['medalbumin.require_admin_2fa' => true]);
        $this->actingAs($admin)->get('/admin/questions')->assertRedirect(route('admin.security'));
    }

    public function test_content_editor_cannot_open_payments_or_settings(): void
    {
        $editor = User::query()->forceCreate(['name' => 'Selam G', 'email' => 'selam@test.local', 'is_staff' => true, 'two_factor_confirmed_at' => now()]);
        $editor->assignRole('Content editor');
        $this->actingAs($editor);
        $this->get('/admin/questions')->assertOk();
        $this->get('/admin/payments')->assertForbidden();
        $this->get('/admin/settings')->assertForbidden();
    }

    public function test_editor_creates_question_which_goes_to_review(): void
    {
        $editor = User::query()->forceCreate(['name' => 'Selam G', 'email' => 'selam2@test.local', 'is_staff' => true, 'two_factor_confirmed_at' => now()]);
        $editor->assignRole('Content editor');
        $dept = Department::query()->first();

        Livewire::actingAs($editor)->test(Questions::class)
            ->call('newQuestion')
            ->set('department_id', $dept->id)
            ->set('stem.en', 'Which vitamin deficiency causes scurvy?')
            ->set('options.A.en', 'Vitamin A')
            ->set('options.B.en', 'Vitamin C')
            ->set('options.C.en', 'Vitamin D')
            ->set('correct', 'B')
            ->set('explanation.en', 'Vitamin C is needed for collagen synthesis.')
            ->call('save', 'publish')   // editors cannot publish → goes to review
            ->assertHasNoErrors();

        $q = Question::query()->where('stem->en', 'Which vitamin deficiency causes scurvy?')->firstOrFail();
        $this->assertSame('in_review', $q->status);
        $this->assertSame(3, $q->options()->count());
        $this->assertStringStartsWith('Q-', $q->code);
    }

    public function test_public_pages(): void
    {
        $this->get('/')->assertOk()->assertSee('Pass your medical exams');
        $this->get('/privacy')->assertOk();
        $this->get('/terms')->assertOk();
        $this->get('/delete-account')->assertOk();
        $this->get('/sitemap.xml')->assertOk();
        $this->getJson('/api/v1/config')->assertOk()->assertJsonCount(2, 'languages');
    }
}
