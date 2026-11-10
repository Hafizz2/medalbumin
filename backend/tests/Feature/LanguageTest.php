<?php

namespace Tests\Feature;

use App\Livewire\Admin\LanguageStrings;
use App\Livewire\Admin\Languages;
use App\Models\Language;
use App\Models\Question;
use App\Models\User;
use App\Services\UiTranslationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LanguageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedContent();
        app(UiTranslationService::class)->import('en', ['home' => ['greeting' => 'Good evening, {name}', 'cta' => 'Continue']]);
    }

    public function test_launch_languages_are_english_and_amharic(): void
    {
        $codes = collect($this->getJson('/api/v1/config')->assertOk()->json('languages'))->pluck('code')->all();
        $this->assertSame(['en', 'am'], $codes);
    }

    public function test_super_admin_adds_draft_then_makes_it_live(): void
    {
        $admin = $this->superAdmin();
        Livewire::actingAs($admin)->test(Languages::class)
            ->call('startAdd')->set('adding.code', 'om')->assertSet('adding.native_name', 'Afaan Oromoo')
            ->call('add')->assertHasNoErrors();

        $this->assertFalse(Language::query()->find('om')->is_active);
        // Drafts are invisible to students and cannot be chosen.
        $this->assertNotContains('om', collect($this->getJson('/api/v1/config')->json('languages'))->pluck('code'));
        $this->student();
        $this->patchJson('/api/v1/me', ['app_locale' => 'om'])->assertUnprocessable();
        $this->getJson('/api/v1/i18n/om')->assertNotFound();

        Livewire::actingAs($admin)->test(Languages::class)->call('ask', 'om', 'activate')->call('confirm');
        $this->assertTrue(Language::query()->find('om')->is_active);
        $this->patchJson('/api/v1/me', ['app_locale' => 'om'])->assertOk();
        // Untranslated app text falls back to English.
        $this->getJson('/api/v1/i18n/om')->assertOk()->assertJsonPath('strings.home.cta', 'Continue')->assertJsonPath('missing', 2);
    }

    public function test_only_known_language_codes_can_be_added(): void
    {
        Livewire::actingAs($this->superAdmin())->test(Languages::class)
            ->call('startAdd')->set('adding.code', 'xx')->set('adding.native_name', 'Fake')->call('add')->assertHasErrors('adding.code');
    }

    public function test_editors_cannot_manage_languages(): void
    {
        $editor = User::query()->forceCreate(['name' => 'Ed', 'email' => 'ed@test.local', 'is_staff' => true, 'two_factor_confirmed_at' => now()]);
        $editor->assignRole('Content editor');
        Livewire::actingAs($editor)->test(Languages::class)->call('startAdd')->assertForbidden();
    }

    public function test_translations_keep_placeholders_and_reject_html(): void
    {
        $editor = User::query()->forceCreate(['name' => 'Ed', 'email' => 'ed2@test.local', 'is_staff' => true, 'two_factor_confirmed_at' => now()]);
        $editor->assignRole('Content editor');

        Livewire::actingAs($editor)->test(LanguageStrings::class, ['code' => 'am'])
            ->set('values.home.greeting', 'እንደምን አመሹ')->call('saveRow', 'home.greeting')->assertHasErrors('values.home.greeting')
            ->set('values.home.greeting', 'እንደምን አመሹ <b>{name}</b>')->call('saveRow', 'home.greeting')->assertHasErrors('values.home.greeting')
            ->set('values.home.greeting', 'እንደምን አመሹ፣ {name}')->call('saveRow', 'home.greeting')->assertHasNoErrors();

        $this->getJson('/api/v1/i18n/am')->assertOk()->assertJsonPath('strings.home.greeting', 'እንደምን አመሹ፣ {name}');
        $etag = $this->getJson('/api/v1/i18n/am')->headers->get('ETag');
        $this->getJson('/api/v1/i18n/am', ['If-None-Match' => $etag])->assertStatus(304);
    }

    public function test_draft_language_content_is_never_shown_to_students(): void
    {
        Language::query()->create(['code' => 'so', 'name' => 'Somali', 'native_name' => 'Soomaali', 'is_active' => false]);
        $q = Question::query()->where('is_free', true)->first();
        $q->setTranslation('stem', 'so', 'Su’aal Soomaali')->forceFill(['translation_status' => ['so' => 'published']])->save();

        $this->student(['content_locale' => 'so']);
        $this->postJson('/api/v1/quiz-sessions', ['source' => 'questions', 'question_ids' => [$q->id]])->assertCreated()
            ->assertJsonPath('questions.0.question.locale', 'en');
    }
}
