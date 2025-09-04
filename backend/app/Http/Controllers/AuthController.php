<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => [
                    'required',
                    'string',
                    'min:10',
                    'confirmed',
                    'regex:/[a-z]/',      // must contain lowercase
                    'regex:/[A-Z]/',      // must contain uppercase
                    'regex:/[0-9]/',      // must contain number
                    'regex:/[@$!%*#?&]/', // must contain special character
                ],
            ], [
                'password.min' => 'Password must be at least 10 characters long.',
                'password.confirmed' => 'Password confirmation does not match.',
                'password.regex' => 'Password must contain at least one lowercase letter, one uppercase letter, one number, and one special character (@$!%*#?&).',
                'email.unique' => 'This email address is already registered.',
                'name.required' => 'Name is required.',
                'email.required' => 'Email is required.',
                'email.email' => 'Please enter a valid email address.',
            ]);

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
            ]);

            // Send email verification notification
            $user->sendEmailVerificationNotification();

            // Log successful registration
            Log::info('User registered successfully', [
                'user_id' => $user->id,
                'email' => $user->email,
                'ip' => $request->ip()
            ]);

            // Create API token for the user
            $token = $user->createToken('auth-token')->plainTextToken;

            return response()->json([
                'user' => $user,
                'token' => $token,
                'message' => 'Registration successful. Please check your email for verification link.'
            ], 201);

        } catch (ValidationException $e) {
            Log::warning('Registration validation failed', [
                'email' => $request->email ?? 'unknown',
                'errors' => $e->errors(),
                'ip' => $request->ip()
            ]);

            return response()->json([
                'message' => 'Registration failed. Please check your input and try again.',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('Registration process failed with exception', [
                'email' => $request->email ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Registration failed due to server error. Please try again.',
                'debug' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    public function login(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email',
                'password' => 'required',
            ]);

            // Log login attempt with more details
            Log::info('Login attempt started', [
                'email' => $request->email,
                'ip' => $request->ip(),
                'user_agent' => $request->header('User-Agent'),
                'timestamp' => now()
            ]);

            // Find user by email
            $user = User::where('email', $request->email)->first();

            if (!$user) {
                Log::warning('Login failed - user not found', [
                    'email' => $request->email,
                    'ip' => $request->ip()
                ]);
                
                return response()->json([
                    'message' => 'The provided credentials are incorrect.'
                ], 401);
            }

            if (!Hash::check($request->password, $user->password)) {
                Log::warning('Login failed - invalid password', [
                    'email' => $request->email,
                    'user_id' => $user->id,
                    'ip' => $request->ip()
                ]);
                
                return response()->json([
                    'message' => 'The provided credentials are incorrect.'
                ], 401);
            }

            // Check if email is verified
            if (!$user->hasVerifiedEmail()) {
                Log::info('Login blocked - email not verified', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'ip' => $request->ip()
                ]);
                
                return response()->json([
                    'message' => 'Please verify your email address before logging in.',
                    'email_verification_required' => true
                ], 403);
            }

            // Create API token for the user
            $token = $user->createToken('auth-token')->plainTextToken;

            // Log successful login
            Log::info('User logged in successfully', [
                'user_id' => $user->id,
                'email' => $user->email,
                'ip' => $request->ip(),
                'token_created' => true
            ]);

            // Handle both session and token-based auth
            if ($request->wantsJson()) {
                return response()->json([
                    'user' => $user,
                    'token' => $token,
                    'message' => 'Login successful'
                ], 200);
            }

            // For non-API requests, use Laravel's built-in Auth to handle session
            Auth::login($user, $request->has('remember'));
            $request->session()->regenerate();

            return response()->json([
                'user' => $user,
                'token' => $token,
                'message' => 'Login successful',
                'redirect' => '/dashboard'
            ], 200);

        } catch (\Exception $e) {
            Log::error('Login process failed with exception', [
                'email' => $request->email ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'An error occurred during login. Please try again.'
            ], 500);
        }
    }

    public function user(Request $request)
    {
        return response()->json($request->user());
    }

    public function logout(Request $request)
    {
        $user = $request->user();
        
        if ($user) {
            // Log logout
            Log::info('User logged out', [
                'user_id' => $user->id,
                'email' => $user->email,
                'ip' => $request->ip()
            ]);
            
            // Delete current access token if using token auth
            if ($request->bearerToken()) {
                $request->user()->currentAccessToken()->delete();
            }
        }

        // Handle session logout if using session-based auth
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Logout successful'], 200);
    }

    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        // Log password reset request
        Log::info('Password reset requested', [
            'email' => $request->email,
            'ip' => $request->ip()
        ]);

        $status = Password::sendResetLink(
            $request->only('email')
        );

        if ($status === Password::RESET_LINK_SENT) {
            return response()->json(['message' => __($status)]);
        }

        throw ValidationException::withMessages([
            'email' => [__($status)],
        ]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => [
                'required', 
                'confirmed',
                'min:10',
                'regex:/[a-z]/',
                'regex:/[A-Z]/',
                'regex:/[0-9]/',
                'regex:/[@$!%*#?&]/',
            ],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) use ($request) {
                $user->forceFill([
                    'password' => Hash::make($password)
                ]);

                $user->save();

                // Log password reset
                Log::info('Password reset completed', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'ip' => $request->ip()
                ]);
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json(['message' => __($status)]);
        }

        throw ValidationException::withMessages([
            'email' => [__($status)],
        ]);
    }

    public function sendVerificationEmail(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email already verified.']);
        }

        $request->user()->sendEmailVerificationNotification();

        Log::info('Verification email sent', [
            'user_id' => $request->user()->id,
            'email' => $request->user()->email,
            'ip' => $request->ip()
        ]);

        return response()->json(['message' => 'Verification link sent!']);
    }

    public function verifyEmail(EmailVerificationRequest $request)
    {
        $request->fulfill();

        Log::info('Email verified successfully', [
            'user_id' => $request->user()->id,
            'email' => $request->user()->email,
            'ip' => $request->ip()
        ]);

        event(new Verified($request->user()));

        return response()->json(['message' => 'Email verified successfully.']);
    }

    public function verifyEmailSigned(Request $request, $id, $hash)
    {
        // Validate the signature and expiration
        if (! URL::hasValidSignature($request)) {
            return response()->json([
                'message' => 'Invalid or expired verification link.',
                'redirect' => '/verification-error'
            ], 403);
        }

        $user = User::find($id);
        if (! $user) {
            return response()->json([
                'message' => 'User not found.',
                'redirect' => '/verification-error'
            ], 404);
        }

        // Ensure the hash matches the user's email
        if (! hash_equals((string) $hash, sha1($user->email))) {
            return response()->json([
                'message' => 'Invalid verification hash.',
                'redirect' => '/verification-error'
            ], 403);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email already verified.',
                'redirect' => '/login?verified=true'
            ]);
        }

        $user->markEmailAsVerified();
        event(new Verified($user));

        Log::info('Email verified successfully', [
            'user_id' => $user->id,
            'email' => $user->email,
            'ip' => $request->ip()
        ]);

        // Create token so frontend can auto-login if desired
        $token = $user->createToken('verification-token')->plainTextToken;

        return response()->json([
            'message' => 'Email verified successfully.',
            'redirect' => '/login?verified=true',
            'token' => $token,
            'user' => $user
        ]);
    }

    public function devVerifyEmail(Request $request)
    {
        // Only allow this in local development environment
        if (config('app.env') !== 'local') {
            return response()->json(['message' => 'This endpoint is only available in development mode.'], 403);
        }

        $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'User not found with this email address.'], 404);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email is already verified.'], 200);
        }

        // Mark email as verified
        $user->markEmailAsVerified();

        Log::info('Email manually verified via development endpoint', [
            'user_id' => $user->id,
            'email' => $user->email,
            'ip' => $request->ip()
        ]);

        return response()->json([
            'message' => 'Email has been successfully verified.',
            'user' => $user
        ], 200);
    }

    // Simple test login without CSRF protection for debugging
    public function testLogin(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Invalid credentials'
            ], 401);
        }

        // Create API token
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
            'message' => 'Test login successful - no CSRF required!'
        ], 200);
    }

    // NEW: Debug login that completely bypasses all middleware
    public function debugLogin(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email',
                'password' => 'required',
            ]);

            Log::info('Debug login attempt', [
                'email' => $request->email,
                'ip' => $request->ip(),
                'middleware_bypassed' => true
            ]);

            $user = User::where('email', $request->email)->first();

            if (!$user) {
                Log::warning('Debug login failed - user not found', [
                    'email' => $request->email
                ]);
                return response()->json([
                    'message' => 'User not found',
                    'debug_info' => 'No user exists with this email'
                ], 401);
            }

            if (!Hash::check($request->password, $user->password)) {
                Log::warning('Debug login failed - invalid password', [
                    'email' => $request->email,
                    'user_id' => $user->id
                ]);
                return response()->json([
                    'message' => 'Invalid password',
                    'debug_info' => 'Password does not match'
                ], 401);
            }

            if (!$user->hasVerifiedEmail()) {
                Log::info('Debug login blocked - email not verified', [
                    'user_id' => $user->id,
                    'email' => $user->email
                ]);
                return response()->json([
                    'message' => 'Email not verified',
                    'debug_info' => 'User must verify email before login'
                ], 403);
            }

            $token = $user->createToken('auth-token')->plainTextToken;

            Log::info('Debug login successful', [
                'user_id' => $user->id,
                'email' => $user->email,
                'token_created' => true
            ]);

            return response()->json([
                'user' => $user,
                'token' => $token,
                'message' => 'Debug login successful - all middleware bypassed!',
                'debug_info' => [
                    'middleware_bypassed' => true,
                    'csrf_skipped' => true,
                    'session_handled' => false
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Debug login exception', [
                'email' => $request->email ?? 'unknown',
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Debug login error',
                'debug_info' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Development endpoint to generate a valid verification URL for testing
     * Only available in local environment
     */
    public function generateVerificationUrl(Request $request)
    {
        if (app()->environment() !== 'local') {
            return response()->json(['error' => 'Not available in production'], 403);
        }

        $userId = $request->get('user_id', 3);
        $user = User::find($userId);
        
        if (!$user) {
            // Create a test user if one doesn't exist
            $user = User::create([
                'name' => 'Test Verification User',
                'email' => 'test-verification@example.com',
                'password' => Hash::make('SecurePass123!'),
                'email_verified_at' => null,
            ]);
        }

        // Generate a proper verification URL
        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addHour(),
            [
                'id' => $user->id,
                'hash' => sha1($user->email),
            ]
        );

        return response()->json([
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'email_verified' => $user->hasVerifiedEmail(),
                'hash' => sha1($user->email),
            ],
            'verification_url' => $verificationUrl,
            'test_instructions' => [
                'Copy the verification_url and test it with:',
                'curl -k "' . $verificationUrl . '"'
            ]
        ]);
    }
}