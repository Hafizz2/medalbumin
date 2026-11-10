<?php

namespace Tests;

use App\Models\Device;
use App\Models\User;
use Database\Seeders\DemoContentSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;

abstract class TestCase extends BaseTestCase
{
    protected function seedContent(bool $demo = true): void
    {
        Cache::flush();
        $this->seed(RolesSeeder::class);
        $this->seed(ReferenceDataSeeder::class);
        if ($demo) {
            $this->seed(DemoContentSeeder::class);
        }
    }

    /** A signed-in student with a device token. */
    protected function student(array $attrs = []): User
    {
        // Act as the app (bearer token), not as whoever is signed in on the website.
        \Illuminate\Support\Facades\Auth::forgetGuards();
        $user = User::query()->forceCreate($attrs + ['name' => 'Hana Tesfaye', 'phone' => '+2519'.random_int(10000000, 99999999), 'phone_verified_at' => now()]);
        $device = Device::query()->create(['user_id' => $user->id, 'device_id' => 'test-'.$user->id, 'platform' => 'android']);
        $token = $user->createToken('device:'.$device->id)->plainTextToken;
        $this->withHeader('Authorization', 'Bearer '.$token)->withHeader('Accept', 'application/json');

        return $user;
    }

    protected function superAdmin(): User
    {
        $admin = User::query()->forceCreate([
            'name' => 'Dawit Admin', 'email' => 'admin'.random_int(1, 99999).'@test.local', 'password' => 'secret-password',
            'is_staff' => true, 'two_factor_confirmed_at' => now(),
        ]);
        $admin->assignRole('Super admin');

        return $admin;
    }
}
