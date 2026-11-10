<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Reference data + the first super admin. Sample content is separate:
     *   php artisan db:seed --class=DemoContentSeeder
     */
    public function run(): void
    {
        $this->call([RolesSeeder::class, ReferenceDataSeeder::class]);

        $email = env('ADMIN_EMAIL', 'admin@medalbumin.local');
        if (! User::query()->where('email', $email)->exists()) {
            $password = env('ADMIN_PASSWORD') ?: Str::password(14, symbols: false);
            $admin = User::query()->forceCreate([
                'name' => env('ADMIN_NAME', 'MedAlbumin Admin'),
                'email' => $email,
                'email_verified_at' => now(),
                'password' => $password,
                'is_staff' => true,
            ]);
            $admin->assignRole('Super admin');

            $this->command?->warn("Super admin created: {$email} / {$password}  (change it after first login)");
        }
    }
}
