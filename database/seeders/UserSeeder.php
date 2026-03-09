<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Gravoni Admin
        User::updateOrCreate(
            ['email' => 'admin@gravoni.com'],
            [
                'name' => 'Gravoni Owner',
                'password' => Hash::make('password123'),
                'tenant_id' => 1 // Links to Gravoni in TenantSeeder
            ]
        );

        // TechWave Admin
        User::updateOrCreate(
            ['email' => 'admin@techwave.com'],
            [
                'name' => 'TechWave Owner',
                'password' => Hash::make('password123'),
                'tenant_id' => 2 // Links to TechWave in TenantSeeder
            ]
        );
    }
}
