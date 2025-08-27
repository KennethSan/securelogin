<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TestUserSeeder extends Seeder
{
    public function run(): void
    {
        // Delete existing test user if it exists
        User::where('email', 'test@example.com')->delete();
        
        // Create test user
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('SecurePass123!'),
            'email_verified_at' => now(),
        ]);
        
        $this->command->info("Test user created successfully!");
        $this->command->info("Email: test@example.com");
        $this->command->info("Password: SecurePass123!");
        $this->command->info("User ID: {$user->id}");
    }
}