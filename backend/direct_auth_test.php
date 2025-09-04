<?php
/**
 * Development authentication test script
 * This script bypasses all Laravel middleware and directly tests authentication
 */

require_once __DIR__ . '/vendor/autoload.php';

// Load Laravel application
$app = require_once __DIR__ . '/bootstrap/app.php';

// Boot the application
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

// Get input data
$input = json_decode(file_get_contents('php://input'), true);
$email = $input['email'] ?? '';
$password = $input['password'] ?? '';

// Set headers for JSON response
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://localhost:8443');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-CSRF-TOKEN, X-XSRF-TOKEN, Accept, Origin');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH');

// Handle OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($email) && !empty($password)) {
    try {
        // Find user
        $user = User::where('email', $email)->first();
        
        if (!$user) {
            Log::warning('Direct auth test - user not found', ['email' => $email]);
            http_response_code(401);
            echo json_encode([
                'message' => 'Invalid credentials',
                'debug' => 'User not found'
            ]);
            exit();
        }
        
        if (!Hash::check($password, $user->password)) {
            Log::warning('Direct auth test - invalid password', ['email' => $email]);
            http_response_code(401);
            echo json_encode([
                'message' => 'Invalid credentials',
                'debug' => 'Password incorrect'
            ]);
            exit();
        }
        
        if (!$user->hasVerifiedEmail()) {
            Log::info('Direct auth test - email not verified', ['email' => $email]);
            http_response_code(403);
            echo json_encode([
                'message' => 'Please verify your email address first',
                'debug' => 'Email not verified'
            ]);
            exit();
        }
        
        // Create token
        $token = $user->createToken('direct-auth-test')->plainTextToken;
        
        Log::info('Direct auth test - success', ['email' => $email, 'user_id' => $user->id]);
        
        http_response_code(200);
        echo json_encode([
            'message' => 'Login successful',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified' => $user->hasVerifiedEmail()
            ],
            'token' => $token,
            'debug' => 'Direct authentication bypass - no CSRF required'
        ]);
        exit();
        
    } catch (Exception $e) {
        Log::error('Direct auth test error', ['error' => $e->getMessage()]);
        http_response_code(500);
        echo json_encode([
            'message' => 'Authentication error',
            'debug' => $e->getMessage()
        ]);
        exit();
    }
}

// Handle GET request or invalid method
http_response_code(405);
echo json_encode([
    'message' => 'Method not allowed',
    'debug' => 'Use POST with email and password'
]);