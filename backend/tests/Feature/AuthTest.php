<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private function sendAndVerify(string $deviceId, array $extra = [])
    {
        $sent = $this->postJson('/api/v1/auth/otp/send', ['phone' => '0912345678'])->assertOk();
        $this->travel(61)->seconds(); // allow the next resend

        return $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => '0912345678', 'code' => $sent->json('debug_code'), 'device_id' => $deviceId, 'platform' => 'android',
        ] + $extra);
    }

    public function test_phone_otp_creates_account_and_returns_token(): void
    {
        $res = $this->sendAndVerify('phone-a')->assertOk()->assertJsonPath('is_new', true)->assertJsonPath('user.phone', '+251912345678');
        $this->withToken($res->json('token'))->getJson('/api/v1/me')->assertOk()->assertJsonPath('phone', '+251912345678');
    }

    public function test_rejects_invalid_phone_and_wrong_code(): void
    {
        $this->postJson('/api/v1/auth/otp/send', ['phone' => '12345'])->assertUnprocessable();
        $this->postJson('/api/v1/auth/otp/send', ['phone' => '0911111111'])->assertOk();
        $this->postJson('/api/v1/auth/otp/verify', ['phone' => '0911111111', 'code' => '000000', 'device_id' => 'x'])->assertUnprocessable();
    }

    public function test_third_device_is_blocked_until_one_is_replaced(): void
    {
        $this->sendAndVerify('phone-a')->assertOk();
        $this->travel(2)->minutes();
        $this->sendAndVerify('phone-b')->assertOk();
        $this->travel(2)->minutes();
        $blocked = $this->sendAndVerify('phone-c')->assertStatus(409)->assertJsonPath('code', 'DEVICE_LIMIT');

        // The student picks a device to sign out and retries.
        $user = User::query()->where('phone', '+251912345678')->first();
        $replace = $blocked->json('devices.0.id');
        $this->travel(2)->minutes();
        $code = $this->postJson('/api/v1/auth/otp/send', ['phone' => '0912345678'])->json('debug_code');
        $this->postJson('/api/v1/auth/otp/verify', ['phone' => '0912345678', 'code' => $code, 'device_id' => 'phone-c', 'replace_device_id' => $replace])->assertOk();
        $this->assertSame(2, $user->devices()->active()->count());
    }

    public function test_guest_progress_carries_over_on_sign_up(): void
    {
        $guest = $this->postJson('/api/v1/auth/guest', ['device_id' => 'guest-phone'])->assertOk();
        $guestId = $guest->json('user.id');
        $this->assertTrue($guest->json('user.is_guest'));
        $this->assertSame(20, $guest->json('user.free_questions_left'));

        $code = $this->postJson('/api/v1/auth/otp/send', ['phone' => '0922222222'])->json('debug_code');
        $this->withToken($guest->json('token'))
            ->postJson('/api/v1/auth/otp/verify', ['phone' => '0922222222', 'code' => $code, 'device_id' => 'guest-phone'])
            ->assertOk()->assertJsonPath('user.id', $guestId)->assertJsonPath('user.is_guest', false);
    }

    public function test_suspended_student_is_signed_out(): void
    {
        $user = $this->student();
        $user->forceFill(['status' => 'suspended', 'suspended_reason' => 'Account sharing'])->save();
        $this->getJson('/api/v1/home')->assertForbidden()->assertJsonPath('code', 'ACCOUNT_SUSPENDED');
    }

    public function test_referral_code_links_new_student(): void
    {
        $friend = User::query()->forceCreate(['name' => 'Dawit S', 'phone' => '+251933333333']);
        $this->sendAndVerify('p1', ['referral_code' => $friend->referral_code])->assertOk();
        $this->assertDatabaseHas('referrals', ['referrer_id' => $friend->id]);
    }
}
