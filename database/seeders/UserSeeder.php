<?php

namespace Database\Seeders;

use App\Core\Authentication\Domain\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Seed the deterministic admin account for local/dev.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@admin.com'],
            [
                'name' => 'admin',
                'password' => Hash::make('P@ssw0'),
                'status' => UserStatus::Active,
                'email_verified_at' => now(),
            ]
        );

        User::updateOrCreate(
            ['email' => 'admin1@admin1.com'],
            [
                'name' => 'testAdmin',
                'password' => Hash::make('P@ssw0'),
                'status' => UserStatus::Active,
                'email_verified_at' => now(),
            ]
        );
    }
}
