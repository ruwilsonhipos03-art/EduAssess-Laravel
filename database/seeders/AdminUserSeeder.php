<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['username' => 'admin'], // Search by username since it's unique
            [
                'first_name'     => 'System',
                'middle_initial' => 'S',
                'last_name'      => 'Administrator',
                'extension_name' => null,
                'username'       => 'admin',
                'email'          => 'admin@example.com',
                'password'       => Hash::make('admin123'), // Use a secure password in production
                'role'           => 'admin', // Matches the key in your Login.vue
            ]
        );

        $this->command->info('Admin user with detailed name fields created successfully!');
    }
}
