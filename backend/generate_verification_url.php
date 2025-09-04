<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

use Illuminate\Support\Facades\URL;
use Illuminate\Support\Carbon;

// Test data - let's use the same user ID from your original URL
$userId = 3;
$userEmail = 'verification-test@example.com';
$emailHash = sha1($userEmail);

echo "🔧 Generating Valid Email Verification URL\n";
echo "======================================\n\n";

echo "Test Parameters:\n";
echo "- User ID: $userId\n";
echo "- Email: $userEmail\n";
echo "- Email Hash: $emailHash\n\n";

// Generate proper verification URL using Laravel's URL signing
try {
    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        Carbon::now()->addMinutes(60), // Valid for 1 hour
        [
            'id' => $userId,
            'hash' => $emailHash,
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
    echo "Testing with curl...\n";
    $curlCommand = 'curl -k -v "' . $testUrl . '"';
    $output = shell_exec($curlCommand . ' 2>&1');
    
    echo "Response:\n";
    echo "==========\n";
    echo $output . "\n";

    // Analyze the response
    echo "\n📊 Response Analysis:\n";
    echo "===================\n";
    
    if (strpos($output, 'HTTP/1.1 200 OK') !== false || strpos($output, 'HTTP/2 200') !== false) {
        echo "✅ HTTP Status: 200 OK\n";
    } elseif (strpos($output, 'HTTP/1.1 403') !== false || strpos($output, 'HTTP/2 403') !== false) {
        echo "❌ HTTP Status: 403 Forbidden\n";
    } elseif (strpos($output, 'HTTP/1.1 422') !== false || strpos($output, 'HTTP/2 422') !== false) {
        echo "⚠️ HTTP Status: 422 Unprocessable Entity\n";
    }
    
    if (strpos($output, 'InvalidSignatureException') !== false) {
        echo "❌ Signature validation failed\n";
    } elseif (strpos($output, 'Email verified successfully') !== false) {
        echo "✅ Email verification successful!\n";
    } elseif (strpos($output, '"message"') !== false) {
        echo "ℹ️ API returned a JSON response\n";
    }

} catch (Exception $e) {
    echo "❌ Error generating URL: " . $e->getMessage() . "\n";
}

echo "\n🎯 Key Points:\n";
echo "=============\n";
echo "1. The URL above uses a proper Laravel-generated signature\n";
echo "2. It's valid for 1 hour from generation time\n";
echo "3. The signature is based on your APP_KEY in the .env file\n";
echo "4. Compare this with your original URL to see the difference\n\n";

echo "💡 Your Original URL Issues:\n";
echo "===========================\n";
echo "Original: https://localhost:8443/api/email/verify/3/e64da1c07bab4e9a6d23491dccdb781b1f0c2dc9?expires=1756821161&signature=1e05d3788633cb2c1784f99d6e570d81528fd696386e4e8e8767f4865706ab91\n\n";
echo "Problems:\n";
echo "- The signature was not generated with the correct APP_KEY\n";
echo "- The expires timestamp may be incorrect\n";
echo "- The hash might not match the actual user's email\n";