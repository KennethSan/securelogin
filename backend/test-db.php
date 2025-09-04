<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "DATABASE CONNECTION TEST\n";
echo "=====================\n";
try {
    $pdo = DB::connection()->getPdo();
    echo "✅ Connected successfully to database: " . DB::connection()->getDatabaseName() . "\n";
    
    // Check users table
    $users = DB::table('users')->get();
    echo "✅ Total users: " . count($users) . "\n";
    foreach($users as $user) {
        echo "- " . $user->email . " (verified: " . ($user->email_verified_at ? 'Yes' : 'No') . ")\n";
    }
} catch (\Exception $e) {
    echo "❌ Connection failed: " . $e->getMessage() . "\n";
}

