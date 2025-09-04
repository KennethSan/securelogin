<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Hash;

// Create test user
$user = User::create([
    'name' => 'Security Test User',
    'email' => 'securitytest@example.com',
    'password' => Hash::make('SecurePass123!'),
    'email_verified_at' => now(),
]);

echo "✅ Test user created successfully!\n";
echo "Email: " . $user->email . "\n";
echo "Password: SecurePass123!\n";
echo "Email verified: " . ($user->hasVerifiedEmail() ? 'Yes' : 'No') . "\n";
echo "User ID: " . $user->id . "\n";