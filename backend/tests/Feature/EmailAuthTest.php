<?php

namespace Tests\Feature;

use App\Mail\VerificationCodeMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmailAuthTest extends TestCase
{
    use RefreshDatabase;

    private function register(string $email = 'Abebe@Example.com')
    {
        return $this->postJson('/api/v1/auth/email/register', ['name' => 'Abebe Kebede', 'email' => $email, 'password' => 'secret123']);
    }

    public function test_signup_sends_code_and_creates_account_only_after_verifying(): void
    {
        Mail::fake();
        $this->register()->assertOk()->assertJsonPath('masked', 'ab•••@example.com');
        Mail::assertSent(VerificationCodeMail::class);
        $this->assertDatabaseMissing('users', ['email' => 'abebe@example.com']);

        $code = null;
        Mail::assertSent(VerificationCodeMail::class, function ($m) use (&$code) {
            $code = $m->code;

            return true;
        });

        $this->postJson('/api/v1/auth/email/verify', ['email' => 'abebe@example.com', 'code' => '000000', 'device_id' => 'p1'])->assertUnprocessable();
        $res = $this->postJson('/api/v1/auth/email/verify', ['email' => 'abebe@example.com', 'code' => $code, 'device_id' => 'p1', 'platform' => 'ios'])
            ->assertOk()->assertJsonPath('is_new', true)->assertJsonPath('user.name', 'Abebe Kebede');

        $this->withToken($res->json('token'))->getJson('/api/v1/me')->assertOk();
        $this->assertNotNull(User::query()->where('email', 'abebe@example.com')->value('email_verified_at'));

        // The code is single-use and the email is now taken.
        $this->postJson('/api/v1/auth/email/verify', ['email' => 'abebe@example.com', 'code' => $code, 'device_id' => 'p2'])->assertUnprocessable();
        $this->travel(2)->minutes();
        $this->register()->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_weak_password_is_rejected(): void
    {
        $this->postJson('/api/v1/auth/email/register', ['name' => 'A B', 'email' => 'x@example.com', 'password' => 'short'])->assertJsonValidationErrors('password');
    }

    public function test_login_and_password_reset(): void
    {
        Mail::fake();
        User::query()->forceCreate(['name' => 'Sara', 'email' => 'sara@example.com', 'email_verified_at' => now(), 'password' => 'oldpass123']);

        $this->postJson('/api/v1/auth/email/login', ['email' => 'sara@example.com', 'password' => 'wrong999', 'device_id' => 'p1'])->assertJsonValidationErrors('email');
        $this->postJson('/api/v1/auth/email/login', ['email' => 'SARA@example.com', 'password' => 'oldpass123', 'device_id' => 'p1'])->assertOk()->assertJsonPath('is_new', false);

        // Unknown emails get the same answer and no email.
        $this->postJson('/api/v1/auth/email/forgot', ['email' => 'nobody@example.com'])->assertOk();
        Mail::assertNothingSent();

        $this->postJson('/api/v1/auth/email/forgot', ['email' => 'sara@example.com'])->assertOk();
        $code = null;
        Mail::assertSent(VerificationCodeMail::class, function ($m) use (&$code) {
            $code = $m->code;

            return $m->purpose === 'reset';
        });

        $this->postJson('/api/v1/auth/email/reset', ['email' => 'sara@example.com', 'code' => $code, 'password' => 'newpass456', 'device_id' => 'p1'])->assertOk();
        $this->postJson('/api/v1/auth/email/login', ['email' => 'sara@example.com', 'password' => 'newpass456', 'device_id' => 'p1'])->assertOk();
    }
}
