<?php

require_once __DIR__ . '/vendor/autoload.php';

// Override database configuration to use SQLite for this test
putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE=' . __DIR__ . '/database/database.sqlite');

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Carbon;

// Ensure SQLite database file exists
$dbPath = __DIR__ . '/database/database.sqlite';
if (!file_exists($dbPath)) {
    echo "Creating SQLite database file...\n";
    touch($dbPath);
    
    // Run migrations
    echo "Running migrations...\n";
    shell_exec('php artisan migrate --force');
}

// Find user with ID 3 or create one for testing
$user = User::find(3);
if (!$user) {
    $user = User::create([
        'name' => 'Test Verification User',
        'email' => 'verification-test@example.com',
        'password' => \Illuminate\Support\Facades\Hash::make('SecurePass123!'),
        'email_verified_at' => null, // Not verified yet
    ]);
    echo "✅ Created test user with ID: {$user->id}\n";
} else {
    echo "✅ Using existing user with ID: {$user->id}\n";
}

echo "User Details:\n";
echo "- ID: {$user->id}\n";
echo "- Email: {$user->email}\n";
echo "- Verified: " . ($user->hasVerifiedEmail() ? 'Yes' : 'No') . "\n\n";

// Generate proper verification URL
$verificationUrl = URL::temporarySignedRoute(
    'verification.verify',
    Carbon::now()->addMinutes(60), // Valid for 1 hour
    [
        'id' => $user->id,
        'hash' => sha1($user->email),
    ]
);

echo "✅ Generated Valid Verification URL:\n";
echo $verificationUrl . "\n\n";

// Extract just the path and query for testing
$urlParts = parse_url($verificationUrl);
$testUrl = "https://localhost:8443" . $urlParts['path'] . '?' . $urlParts['query'];

echo "🔄 Testing the verification URL...\n";
echo "URL: $testUrl\n\n";

// Test the URL with curl
$curlCommand = 'curl -k -s "' . $testUrl . '"';
echo "Command: $curlCommand\n\n";

// Execute the curl command
$output = shell_exec($curlCommand);
echo "Response:\n";
echo $output . "\n\n";

// Check if user is now verified
$user->refresh();
echo "User verification status after test:\n";
echo "- Verified: " . ($user->hasVerifiedEmail() ? 'Yes' : 'No') . "\n";

if ($user->hasVerifiedEmail()) {
    echo "✅ Email verification successful!\n";
} else {
    echo "❌ Email verification failed. Let's check what happened...\n";
    
    // Show response analysis
    if (strpos($output, 'Email verified successfully') !== false) {
        echo "✅ API responded with success message!\n";
    } elseif (strpos($output, 'InvalidSignatureException') !== false) {
        echo "❌ Signature validation failed\n";
    } elseif (strpos($output, '"message"') !== false) {
        echo "ℹ️ API returned a JSON response\n";
    } else {
        echo "ℹ️ Unexpected response format\n";
    }
}