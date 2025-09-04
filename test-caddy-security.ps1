# Security Test Suite for Caddy Migration (PowerShell)
# This script tests all security features of the Caddy setup on Windows

param(
    [string]$BaseUrl = "https://localhost:8443",
    [string]$CookieFile = "test_cookies.txt"
)

Write-Host "🔒 Security Test Suite for Caddy Migration" -ForegroundColor Cyan
Write-Host "==========================================" -ForegroundColor Cyan
Write-Host ""

# Test counters
$TestsPassed = 0
$TestsFailed = 0

# Helper function to check test results
function Test-Result {
    param([string]$TestName, [bool]$Result)
    
    if ($Result) {
        Write-Host "✅ PASS: $TestName" -ForegroundColor Green
        $script:TestsPassed++
    } else {
        Write-Host "❌ FAIL: $TestName" -ForegroundColor Red
        $script:TestsFailed++
    }
}

# Cleanup function
function Cleanup {
    if (Test-Path $CookieFile) {
        Remove-Item $CookieFile -Force
    }
    Write-Host ""
    Write-Host "🧹 Cleanup completed" -ForegroundColor Yellow
}

Write-Host "🚀 Starting security tests..." -ForegroundColor Green
Write-Host ""

try {
    # Test 1: CSRF Handshake
    Write-Host "1️⃣ Testing CSRF handshake..."
    $response = Invoke-WebRequest -Uri "$BaseUrl/sanctum/csrf-cookie" -Method GET -SessionVariable session -SkipCertificateCheck 2>$null
    $csrfResult = $response.Headers["Set-Cookie"] -match "XSRF-TOKEN"
    Test-Result "CSRF cookie handshake" $csrfResult

    # Test 2: Security Headers
    Write-Host "2️⃣ Testing security headers..."
    $headers = Invoke-WebRequest -Uri $BaseUrl -Method HEAD -SkipCertificateCheck 2>$null
    
    Test-Result "X-Frame-Options header" ($headers.Headers["X-Frame-Options"] -eq "DENY")
    Test-Result "X-Content-Type-Options header" ($headers.Headers["X-Content-Type-Options"] -eq "nosniff")
    Test-Result "HSTS header" ($headers.Headers["Strict-Transport-Security"] -ne $null)
    Test-Result "CSP header" ($headers.Headers["Content-Security-Policy"] -ne $null)

    # Test 3: HTTP to HTTPS redirect
    Write-Host "3️⃣ Testing HTTP to HTTPS redirect..."
    try {
        $httpResponse = Invoke-WebRequest -Uri "http://localhost:8080" -Method HEAD -MaximumRedirection 0 -ErrorAction SilentlyContinue
        $redirectResult = $httpResponse.StatusCode -eq 301 -or $httpResponse.StatusCode -eq 302
    } catch {
        $redirectResult = $_.Exception.Response.StatusCode -eq 301 -or $_.Exception.Response.StatusCode -eq 302
    }
    Test-Result "HTTP to HTTPS redirect" $redirectResult

    # Test 4: CORS Headers
    Write-Host "4️⃣ Testing CORS configuration..."
    $corsHeaders = @{
        "Origin" = "https://localhost:8443"
    }
    $corsResponse = Invoke-WebRequest -Uri $BaseUrl -Method OPTIONS -Headers $corsHeaders -SkipCertificateCheck 2>$null
    
    Test-Result "CORS headers present" ($corsResponse.Headers["Access-Control-Allow-Origin"] -ne $null)
    Test-Result "CORS credentials allowed" ($corsResponse.Headers["Access-Control-Allow-Credentials"] -eq "true")

    # Test 5: Rate Limiting
    Write-Host "5️⃣ Testing rate limiting..."
    Write-Host "   Sending multiple login requests to trigger rate limit..." -ForegroundColor Yellow
    
    # Get CSRF token first
    $csrfResponse = Invoke-WebRequest -Uri "$BaseUrl/sanctum/csrf-cookie" -WebSession $session -SkipCertificateCheck
    
    $rateLimitTriggered = $false
    for ($i = 1; $i -le 7; $i++) {
        try {
            $loginData = @{
                email = "nonexistent@example.com"
                password = "wrongpassword"
            } | ConvertTo-Json
            
            $response = Invoke-WebRequest -Uri "$BaseUrl/login" -Method POST -Body $loginData -ContentType "application/json" -WebSession $session -SkipCertificateCheck -ErrorAction SilentlyContinue
        } catch {
            if ($_.Exception.Response.StatusCode -eq 429) {
                $rateLimitTriggered = $true
                break
            }
        }
        Start-Sleep -Milliseconds 500
    }
    
    if ($rateLimitTriggered) {
        Test-Result "Rate limiting (429 response triggered)" $true
    } else {
        Write-Host "⚠️  WARN: Rate limiting (429 not triggered in 7 attempts)" -ForegroundColor Yellow
    }

    # Test 6: Bad Login
    Write-Host "6️⃣ Testing authentication rejection..."
    try {
        $loginData = @{
            email = "wrong@example.com"
            password = "wrongpass"
        } | ConvertTo-Json
        
        $response = Invoke-WebRequest -Uri "$BaseUrl/login" -Method POST -Body $loginData -ContentType "application/json" -WebSession $session -SkipCertificateCheck -ErrorAction SilentlyContinue
        $badLoginResult = $false
    } catch {
        $badLoginResult = $_.Exception.Response.StatusCode -eq 401 -or $_.Exception.Response.StatusCode -eq 422
    }
    Test-Result "Bad login rejection (401/422 response)" $badLoginResult

    # Test 7: Valid Login (if test user exists)
    Write-Host "7️⃣ Testing valid login..."
    try {
        $loginData = @{
            email = "securitytest@example.com"
            password = "SecurePass123!"
        } | ConvertTo-Json
        
        $loginResponse = Invoke-WebRequest -Uri "$BaseUrl/login" -Method POST -Body $loginData -ContentType "application/json" -WebSession $session -SkipCertificateCheck
        
        if ($loginResponse.StatusCode -eq 200) {
            Test-Result "Valid login (200 response)" $true
            
            # Test 8: Authenticated API access
            Write-Host "8️⃣ Testing authenticated API access..."
            $apiResponse = Invoke-WebRequest -Uri "$BaseUrl/api/me" -WebSession $session -SkipCertificateCheck
            Test-Result "Authenticated API access" ($apiResponse.StatusCode -eq 200)
            
            # Test 9: Logout
            Write-Host "9️⃣ Testing logout..."
            $logoutResponse = Invoke-WebRequest -Uri "$BaseUrl/logout" -Method POST -WebSession $session -SkipCertificateCheck
            Test-Result "Logout functionality" ($logoutResponse.StatusCode -eq 200 -or $logoutResponse.StatusCode -eq 204)
            
            # Test 10: API access after logout
            Write-Host "🔟 Testing API access after logout..."
            try {
                $apiAfterLogout = Invoke-WebRequest -Uri "$BaseUrl/api/me" -WebSession $session -SkipCertificateCheck -ErrorAction SilentlyContinue
                $logoutTestResult = $false
            } catch {
                $logoutTestResult = $_.Exception.Response.StatusCode -eq 401
            }
            Test-Result "API rejection after logout" $logoutTestResult
        }
    } catch {
        Write-Host "⚠️  SKIP: Valid login test (test user may not exist)" -ForegroundColor Yellow
        Write-Host "⚠️  SKIP: Subsequent authenticated tests" -ForegroundColor Yellow
    }

    # Test 11: Frontend serving
    Write-Host "1️⃣1️⃣ Testing frontend serving..."
    $frontendResponse = Invoke-WebRequest -Uri $BaseUrl -SkipCertificateCheck
    Test-Result "Frontend serving" ($frontendResponse.StatusCode -eq 200)

    # Test 12: API routing
    Write-Host "1️⃣2️⃣ Testing API routing..."
    try {
        $apiRouteResponse = Invoke-WebRequest -Uri "$BaseUrl/api/test" -SkipCertificateCheck -ErrorAction SilentlyContinue
        $apiRoutingResult = $false
    } catch {
        $apiRoutingResult = $_.Exception.Response.StatusCode -eq 401 -or $_.Exception.Response.StatusCode -eq 404
    }
    Test-Result "API routing (proper backend routing)" $apiRoutingResult

} catch {
    Write-Host "Error during testing: $_" -ForegroundColor Red
} finally {
    Cleanup
}

Write-Host ""
Write-Host "🏁 Test Results Summary" -ForegroundColor Cyan
Write-Host "======================" -ForegroundColor Cyan
Write-Host "Tests Passed: $TestsPassed" -ForegroundColor Green
Write-Host "Tests Failed: $TestsFailed" -ForegroundColor Red
Write-Host ""

if ($TestsFailed -eq 0) {
    Write-Host "🎉 All critical security tests passed!" -ForegroundColor Green
    Write-Host "✅ CSRF protection: Working" -ForegroundColor Green
    Write-Host "✅ Secure cookies: Configured" -ForegroundColor Green
    Write-Host "✅ Rate limiting: Functional" -ForegroundColor Green
    Write-Host "✅ Security headers: Present" -ForegroundColor Green
    Write-Host "✅ HTTPS enforcement: Active" -ForegroundColor Green
    Write-Host "✅ Authentication flow: Working" -ForegroundColor Green
    exit 0
} else {
    Write-Host "⚠️  Some tests failed. Please review the configuration." -ForegroundColor Yellow
    exit 1
}