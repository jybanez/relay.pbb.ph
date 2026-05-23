<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'admin@relay.local'],
            [
                'name' => 'Relay Admin',
                'password' => Hash::make('relay-admin-123'),
                'role' => User::ROLE_ADMIN,
                'is_active' => true,
            ]
        );

        User::query()->updateOrCreate(
            ['email' => 'operator@relay.local'],
            [
                'name' => 'Relay Operator',
                'password' => Hash::make('relay-operator-123'),
                'role' => User::ROLE_OPERATOR,
                'is_active' => true,
            ]
        );
    }
}
