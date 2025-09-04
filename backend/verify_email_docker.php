<?php
/**
 * Direct email verification script for development
 * Run this inside Docker to verify emails directly in the database
 */

require_once __DIR__ . '/vendor/autoload.php';

// Load Laravel application
$app = require_once __DIR__ . '/bootstrap/app.php';

// Boot the application
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Log;

// Email to verify
$emailToVerify = $argv[1] ?? 'skenneth695@gmail.com';

try {
    echo "🔍 Looking for user with email: {$emailToVerify}\n";
    
    // Find the user
    $user = User::where('email', $emailToVerify)->first();
    
    if (!$user) {
        echo "❌ User not found with email: {$emailToVerify}\n";
        echo "📋 Available users in database:\n";
        $users = User::all(['id', 'name', 'email', 'email_verified_at']);
        foreach ($users as $u) {
            $verified = $u->email_verified_at ? '✅ Verified' : '❌ Not verified';
            echo "   - {$u->email} ({$u->name}) - {$verified}\n";
        }
        exit(1);
    }
    
    if ($user->hasVerifiedEmail()) {
        echo "✅ Email is already verified for: {$emailToVerify}\n";
        echo "User details:\n";
        echo "- ID: {$user->id}\n";
        echo "- Name: {$user->name}\n";
        echo "- Email: {$user->email}\n";
        echo "- Verified at: {$user->email_verified_at}\n";
        exit(0);
    }
    
    // Mark email as verified
    $user->markEmailAsVerified();
    
    // Log the verification
    Log::info('Email manually verified via development script', [
        'user_id' => $user->id,
        'email' => $user->email,
        'verified_by' => 'docker_development_script'
    ]);
    
    echo "✅ Email successfully verified for: {$emailToVerify}\n";
    echo "User details:\n";
    echo "- ID: {$user->id}\n";
    echo "- Name: {$user->name}\n";
    echo "- Email: {$user->email}\n";
    echo "- Verified at: {$user->email_verified_at}\n";
    
} catch (Exception $e) {
    echo "❌ Error verifying email: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}