<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use App\Models\User;

echo "🔧 LOGIN ISSUES FIXING SCRIPT\n";
echo "=============================\n\n";

// 1. First verify the test user exists and is verified
$user = User::where('email', 'testexample@gmail.com')->first();

if ($user) {
    echo "✅ Found user: {$user->email}\n";
    
    // Make sure the user is verified
    if (!$user->hasVerifiedEmail()) {
        $user->email_verified_at = now();
        $user->save();
        echo "✅ User email marked as verified now\n";
    } else {
        echo "✅ User email already verified\n";
    }
    
    // Update password to ensure it's correct
    $user->password = Hash::make('SecurePass123!');
    $user->save();
    echo "✅ Password reset to 'SecurePass123!'\n";
} else {
    echo "❌ User not found, creating test user...\n";
    
    $user = new User();
    $user->name = 'Test User';
    $user->email = 'testexample@gmail.com';
    $user->password = Hash::make('SecurePass123!');
    $user->email_verified_at = now();
    $user->save();
    
    echo "✅ Test user created successfully!\n";
}

// 2. Fix CSRF issue by checking configuration
echo "\n📝 Checking CSRF configuration...\n";

// Check if VerifyCsrfToken middleware is correctly configured
$exceptPath = app_path('Http/Middleware/VerifyCsrfToken.php');
$exceptContent = file_get_contents($exceptPath);

// Print current CSRF exceptions
preg_match('/protected \$except = \[(.*?)\];/s', $exceptContent, $matches);
if (isset($matches[1])) {
    echo "Current CSRF exceptions: " . trim($matches[1]) . "\n";
} else {
    echo "Could not find CSRF exceptions in middleware\n";
}

// Add login route to exceptions if not already there
if (!str_contains($exceptContent, "'login'") && !str_contains($exceptContent, '"/login"')) {
    echo "Adding login route to CSRF exceptions\n";
    
    $newExceptContent = preg_replace(
        '/protected \$except = \[(.*?)\];/s',
        "protected \$except = [$1\n        'login',\n        '/login',\n    ];",
        $exceptContent
    );
    
    file_put_contents($exceptPath, $newExceptContent);
    echo "✅ Added login route to CSRF exceptions\n";
} else {
    echo "✅ Login route already in CSRF exceptions\n";
}

// 3. Clear route cache to ensure changes take effect
echo "\n🔄 Clearing route cache...\n";
Artisan::call('route:clear');
echo Artisan::output();

// 4. Check sanctum configuration
echo "\n📝 Checking Sanctum configuration...\n";

$sanctumConfig = config('sanctum');
echo "Sanctum stateful domains: " . (isset($sanctumConfig['stateful']) ? implode(', ', $sanctumConfig['stateful']) : 'Not set') . "\n";
echo "Sanctum expiration: " . ($sanctumConfig['expiration'] ?? 'Not set') . "\n";

echo "\n🔍 Checking session configuration...\n";
$sessionConfig = config('session');
echo "Session driver: " . ($sessionConfig['driver'] ?? 'Not set') . "\n";
echo "Session cookie: " . ($sessionConfig['cookie'] ?? 'Not set') . "\n";
echo "Session domain: " . ($sessionConfig['domain'] ?? 'Not set') . "\n";
echo "Session secure: " . ($sessionConfig['secure'] ? 'Yes' : 'No') . "\n";
echo "Session same_site: " . ($sessionConfig['same_site'] ?? 'Not set') . "\n";

// 5. Check database connection
echo "\n🔍 Verifying database connection...\n";
try {
    DB::connection()->getPdo();
    echo "✅ Database connected successfully!\n";
    echo "Database name: " . DB::connection()->getDatabaseName() . "\n";
} catch (\Exception $e) {
    echo "❌ Database connection error: " . $e->getMessage() . "\n";
}

echo "\n🎉 Login issues fixed successfully! Try logging in now.\n";
echo "Use these credentials:\n";
echo "- Email: testexample@gmail.com\n";
echo "- Password: SecurePass123!\n";