<?php

namespace App\Services;

use App\Exceptions\DeviceLimitException;
use App\Models\Device;
use App\Models\Referral;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function maxDevices(): int
    {
        return (int) Setting::get('max_devices', 2);
    }

    /**
     * Throws DeviceLimitException if signing in on this device would exceed the limit,
     * unless the student chose a device to replace.
     *
     * @param  array{device_id:string, replace_device_id?:int|null}  $device
     */
    public function assertDeviceAllowed(?User $user, array $device): void
    {
        if (! $user) {
            return;
        }
        $active = $user->devices()->active()->get();
        if ($active->contains('device_id', $device['device_id'])) {
            return;
        }
        $replace = $device['replace_device_id'] ?? null;
        if ($active->count() >= $this->maxDevices() && ! ($replace && $active->contains('id', (int) $replace))) {
            throw new DeviceLimitException($active, $this->maxDevices());
        }
    }

    /**
     * Registers the device, enforces the limit and returns a Sanctum token.
     *
     * @param  array{device_id:string, device_name?:?string, platform?:?string, app_version?:?string, push_token?:?string, replace_device_id?:int|null}  $device
     */
    public function issueToken(User $user, array $device): string
    {
        if ($user->isSuspended()) {
            throw ValidationException::withMessages(['account' => 'This account is suspended. Contact support on Telegram.']);
        }

        $this->assertDeviceAllowed($user, $device);

        return DB::transaction(function () use ($user, $device) {
            if (! empty($device['replace_device_id'])) {
                $old = $user->devices()->active()->whereKey($device['replace_device_id'])->first();
                $old && $this->revokeDevice($old);
            }

            $model = Device::query()->updateOrCreate(
                ['user_id' => $user->id, 'device_id' => $device['device_id']],
                [
                    'name' => $device['device_name'] ?? null,
                    'platform' => in_array($device['platform'] ?? null, ['ios', 'android', 'web'], true) ? $device['platform'] : 'android',
                    'app_version' => $device['app_version'] ?? null,
                    'push_token' => $device['push_token'] ?? null,
                    'last_seen_at' => now(),
                    'revoked_at' => null,
                ],
            );

            // One token per device: signing in again on the same phone replaces the old token.
            $user->tokens()->where('name', 'device:'.$model->id)->delete();
            $user->forceFill(['last_active_at' => now()])->save();

            return $user->createToken('device:'.$model->id)->plainTextToken;
        });
    }

    public function revokeDevice(Device $device): void
    {
        $device->user->tokens()->where('name', 'device:'.$device->id)->delete();
        $device->update(['revoked_at' => now(), 'push_token' => null]);
    }

    /** Finds or creates the student for a verified phone number. */
    public function userForPhone(string $phone, ?User $guest = null, ?string $referralCode = null): User
    {
        $user = User::withTrashed()->where('phone', $phone)->first();
        if ($user?->trashed()) {
            throw ValidationException::withMessages(['phone' => 'This account was deleted. Contact support to restore it.']);
        }
        if ($user) {
            $user->phone_verified_at ??= now();
            $user->save();

            return $user;
        }

        // Upgrade the guest account so their progress is kept.
        if ($guest && $guest->is_guest) {
            $guest->forceFill(['phone' => $phone, 'phone_verified_at' => now(), 'is_guest' => false])->save();

            return $guest;
        }

        $user = User::query()->create(['phone' => $phone, 'phone_verified_at' => now()]);
        $this->attachReferral($user, $referralCode);

        return $user;
    }

    /** Finds or creates the student for a verified Google / Apple identity. */
    public function userForSocial(string $provider, array $identity, ?User $guest = null, ?string $referralCode = null): User
    {
        $column = $provider.'_id';
        $user = User::query()->where($column, $identity['sub'])->first();

        if (! $user && $identity['email']) {
            $user = User::query()->where('email', $identity['email'])->first();
            $user?->forceFill([$column => $identity['sub']])->save();
        }
        if ($user) {
            return $user;
        }

        $attrs = [
            $column => $identity['sub'],
            'email' => $identity['email'],
            'email_verified_at' => $identity['email'] ? now() : null,
            'name' => $identity['name'],
        ];

        if ($guest && $guest->is_guest) {
            $guest->forceFill($attrs + ['is_guest' => false])->save();

            return $guest;
        }

        $user = User::query()->forceCreate($attrs);
        $this->attachReferral($user, $referralCode);

        return $user;
    }

    /**
     * Finds or creates the student for a verified "Log In With Telegram" identity.
     * Links to an existing account with the same verified phone number.
     */
    public function userForTelegram(array $identity, ?User $guest = null, ?string $referralCode = null): User
    {
        $phone = $identity['phone'] ? (OtpService::normalizePhone($identity['phone']) ?? $identity['phone']) : null;

        $user = User::withTrashed()->where('telegram_id', $identity['telegram_id'])->first()
            ?? ($phone ? User::withTrashed()->where('phone', $phone)->first() : null);
        if ($user?->trashed()) {
            throw ValidationException::withMessages(['telegram' => 'This account was deleted. Contact support to restore it.']);
        }

        $attrs = ['telegram_id' => $identity['telegram_id'], 'telegram_username' => $identity['username']];

        if ($user) {
            $user->forceFill($attrs + [
                'name' => $user->name ?: $identity['name'],
                'phone' => $user->phone ?: ($phone && ! User::withTrashed()->where('phone', $phone)->exists() ? $phone : null),
                'phone_verified_at' => $user->phone_verified_at ?? ($phone ? now() : null),
            ])->save();

            return $user;
        }

        $attrs += [
            'name' => $identity['name'],
            'phone' => $phone,
            'phone_verified_at' => $phone ? now() : null,
        ];

        if ($guest && $guest->is_guest) {
            $guest->forceFill($attrs + ['is_guest' => false])->save();

            return $guest;
        }

        $user = User::query()->forceCreate($attrs);
        $this->attachReferral($user, $referralCode);

        return $user;
    }

    public function attachReferral(User $user, ?string $code): void
    {
        if (! $code) {
            return;
        }
        $referrer = User::query()->where('referral_code', strtoupper(trim($code)))->first();
        if (! $referrer || $referrer->id === $user->id) {
            return;
        }
        $user->forceFill(['referred_by' => $referrer->id])->save();
        Referral::query()->firstOrCreate(['referred_id' => $user->id], ['referrer_id' => $referrer->id]);
    }
}
