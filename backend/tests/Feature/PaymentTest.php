<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Plan;
use App\Models\PromoCode;
use App\Models\Question;
use App\Models\Referral;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Plans are bought on the website account page; the app only reads plan status. */
class PaymentTest extends TestCase
{
    use RefreshDatabase;

    private Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedContent();
        Storage::fake('local');
        $this->plan = Plan::query()->where('slug', 'semester')->first();
        $this->plan->update(['price' => 899, 'is_active' => true]);
    }

    private function webStudent(array $attrs = []): User
    {
        $user = User::query()->forceCreate($attrs + ['name' => 'Hana Tesfaye', 'phone' => '+2519'.random_int(10000000, 99999999)]);
        $this->actingAs($user);

        return $user;
    }

    public function test_pricing_page_shows_live_plans_with_benefits(): void
    {
        $this->plan->setTranslation('badge', 'en', 'Popular')->save();
        Plan::query()->where('slug', 'monthly')->update(['price' => 199, 'is_active' => true]);

        $this->get('/pricing')->assertOk()
            ->assertSee('Semester')->assertSee('ETB 899')->assertSee('Popular')
            ->assertSee('Every question and past paper')     // seeded benefits
            ->assertSee('about ETB 150 / month')              // 899 over 6 months
            ->assertSee(route('account.checkout', $this->plan))
            ->assertDontSee('Yearly');                         // still hidden (no price)

        // Choosing a plan while signed out goes to sign-in, then back to checkout.
        $this->get(route('account.checkout', $this->plan))->assertRedirect(route('account.login'));
    }

    public function test_pricing_page_before_prices_are_set(): void
    {
        Plan::query()->update(['is_active' => false]);
        $this->get('/pricing')->assertOk()->assertSee('Premium plans are coming soon');
    }

    public function test_app_has_no_purchase_endpoints(): void
    {
        $this->student();
        $this->postJson('/api/v1/payments', ['plan_id' => $this->plan->id, 'gateway' => 'chapa'])->assertStatus(405);
        $this->getJson('/api/v1/subscription')->assertOk()->assertJsonPath('active', false);
    }

    public function test_bank_receipt_on_website_then_approval_unlocks_premium(): void
    {
        $student = $this->webStudent();
        $this->get(route('account'))->assertOk()->assertSee('Semester');
        $this->get(route('account.checkout', $this->plan))->assertOk();

        $this->post(route('account.pay', $this->plan), [
            'method' => 'bank_transfer', 'reference' => 'FT26286XK31', 'bank_name' => 'CBE',
            'receipt' => UploadedFile::fake()->image('receipt.jpg', 800, 1200),
        ])->assertRedirect(route('account'));

        $payment = Payment::query()->where('user_id', $student->id)->firstOrFail();
        $this->assertSame('pending', $payment->status);
        Storage::disk('local')->assertExists($payment->receipt_path);
        $this->assertTrue($payment->amountMatches());

        // The same bank reference cannot be reused by someone else.
        $this->webStudent();
        $this->post(route('account.pay', $this->plan), ['method' => 'bank_transfer', 'reference' => 'FT26286XK31', 'receipt' => UploadedFile::fake()->image('r.jpg')])
            ->assertSessionHasErrors('reference');

        $sub = app(SubscriptionService::class)->approvePayment($payment, $this->superAdmin());
        $this->assertTrue($student->fresh()->isPremium());
        $this->assertEqualsWithDelta(180, now()->diffInDays($sub->ends_at), 1);
        $this->assertSame(1, $student->notifications()->count());

        // The app sees premium, and every question unlocks.
        \Illuminate\Support\Facades\Auth::forgetGuards();
        $this->app['session']->flush();
        $this->withToken($student->createToken('t')->plainTextToken);
        $this->getJson('/api/v1/subscription')->assertJsonPath('active', true);
        $this->getJson('/api/v1/quiz/options')->assertJsonPath('pools.3.count', Question::query()->published()->count());
    }

    public function test_promo_codes_on_checkout(): void
    {
        $student = $this->webStudent();
        PromoCode::query()->create(['code' => 'EXIT2026', 'type' => 'percent', 'value' => 30, 'is_active' => true]);
        $this->get(route('account.checkout', [$this->plan, 'promo' => 'exit2026']))->assertOk()->assertSee('629.30');

        PromoCode::query()->create(['code' => 'GRAND12', 'type' => 'free_days', 'value' => 7, 'is_active' => true]);
        $this->post(route('account.pay', $this->plan), ['method' => 'chapa', 'promo_code' => 'GRAND12'])->assertRedirect(route('account'));
        $this->assertTrue($student->fresh()->isPremium());

        // Each code works once per student.
        $this->get(route('account.checkout', [$this->plan, 'promo' => 'GRAND12']))->assertSee('already used');
    }

    public function test_online_payment_needs_chapa_keys(): void
    {
        $this->webStudent();
        $this->post(route('account.pay', $this->plan), ['method' => 'chapa'])->assertSessionHasErrors('gateway');
    }

    public function test_referral_rewards_both_students(): void
    {
        $referrer = User::query()->forceCreate(['name' => 'Dawit S', 'phone' => '+251955555555']);
        $student = User::query()->forceCreate(['name' => 'Hana', 'phone' => '+251966666661', 'referred_by' => $referrer->id]);
        Referral::query()->create(['referrer_id' => $referrer->id, 'referred_id' => $student->id]);

        $payment = $student->payments()->create(['plan_id' => $this->plan->id, 'gateway' => 'manual', 'amount' => 899, 'tx_ref' => 'T-1', 'status' => 'pending']);
        app(SubscriptionService::class)->approvePayment($payment);

        $this->assertTrue($referrer->fresh()->isPremium());
        $this->assertSame(2, $student->subscriptions()->count());
    }

    public function test_student_cannot_open_admin_and_staff_login_redirects(): void
    {
        $this->webStudent();
        $this->get('/admin')->assertRedirect(route('account'));
        $this->get('/account/login')->assertRedirect('/account');
    }
}
