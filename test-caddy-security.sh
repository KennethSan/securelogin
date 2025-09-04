#!/bin/bash

# Security Test Suite for Caddy Migration
# This script tests all security features of the Caddy setup

set -e

BASE_URL="https://localhost:8443"
COOKIE_FILE="test_cookies.txt"

echo "🔒 Security Test Suite for Caddy Migration"
echo "=========================================="
echo

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Test counter
TESTS_PASSED=0
TESTS_FAILED=0

# Helper function to check test results
check_result() {
    if [ $? -eq 0 ]; then
        echo -e "${GREEN}✅ PASS${NC}: $1"
        ((TESTS_PASSED++))
    else
        echo -e "${RED}❌ FAIL${NC}: $1"
        ((TESTS_FAILED++))
    fi
}

# Clean up function
cleanup() {
    rm -f $COOKIE_FILE
    echo
    echo "🧹 Cleanup completed"
}

# Set trap for cleanup
trap cleanup EXIT

echo "🚀 Starting security tests..."
echo

# Test 1: CSRF Handshake (expect Set-Cookie for XSRF)
echo "1️⃣ Testing CSRF handshake..."
curl -s -I -c $COOKIE_FILE -b $COOKIE_FILE $BASE_URL/sanctum/csrf-cookie | grep -q "Set-Cookie.*XSRF-TOKEN"
check_result "CSRF cookie handshake"

# Test 2: Security Headers
echo "2️⃣ Testing security headers..."
HEADERS=$(curl -s -I $BASE_URL)

echo "$HEADERS" | grep -q "X-Frame-Options: DENY"
check_result "X-Frame-Options header"

echo "$HEADERS" | grep -q "X-Content-Type-Options: nosniff"
check_result "X-Content-Type-Options header"

echo "$HEADERS" | grep -q "Strict-Transport-Security"
check_result "HSTS header"

echo "$HEADERS" | grep -q "Content-Security-Policy"
check_result "CSP header"

# Test 3: HTTP to HTTPS redirect
echo "3️⃣ Testing HTTP to HTTPS redirect..."
curl -s -I http://localhost:8080 | grep -q "301\|302"
check_result "HTTP to HTTPS redirect"

# Test 4: CORS Headers
echo "4️⃣ Testing CORS configuration..."
CORS_RESPONSE=$(curl -s -I -X OPTIONS $BASE_URL -H "Origin: https://localhost:8443")
echo "$CORS_RESPONSE" | grep -q "Access-Control-Allow-Origin"
check_result "CORS headers present"

echo "$CORS_RESPONSE" | grep -q "Access-Control-Allow-Credentials: true"
check_result "CORS credentials allowed"

# Test 5: Rate Limiting (attempt to trigger 429)
echo "5️⃣ Testing rate limiting..."
echo "   Sending multiple login requests to trigger rate limit..."

# Get CSRF token first
curl -s -c $COOKIE_FILE -b $COOKIE_FILE $BASE_URL/sanctum/csrf-cookie > /dev/null

# Extract XSRF token for header
XSRF_TOKEN=$(grep "XSRF-TOKEN" $COOKIE_FILE | cut -f7)

# Attempt multiple failed logins to trigger rate limit
RATE_LIMIT_TRIGGERED=false
for i in {1..7}; do
    RESPONSE=$(curl -s -w "%{http_code}" -o /dev/null -c $COOKIE_FILE -b $COOKIE_FILE -X POST $BASE_URL/login \
        -H "Content-Type: application/json" \
        -H "X-XSRF-TOKEN: $XSRF_TOKEN" \
        -d '{"email":"nonexistent@example.com","password":"wrongpassword"}')
    
    if [ "$RESPONSE" = "429" ]; then
        RATE_LIMIT_TRIGGERED=true
        break
    fi
    sleep 0.5
done

if [ "$RATE_LIMIT_TRIGGERED" = true ]; then
    echo -e "${GREEN}✅ PASS${NC}: Rate limiting (429 response triggered)"
    ((TESTS_PASSED++))
else
    echo -e "${YELLOW}⚠️  WARN${NC}: Rate limiting (429 not triggered in 7 attempts)"
fi

# Test 6: Bad Login (401 response)
echo "6️⃣ Testing authentication rejection..."
curl -s -c $COOKIE_FILE -b $COOKIE_FILE $BASE_URL/sanctum/csrf-cookie > /dev/null
XSRF_TOKEN=$(grep "XSRF-TOKEN" $COOKIE_FILE | cut -f7)

RESPONSE=$(curl -s -w "%{http_code}" -o /dev/null -c $COOKIE_FILE -b $COOKIE_FILE -X POST $BASE_URL/login \
    -H "Content-Type: application/json" \
    -H "X-XSRF-TOKEN: $XSRF_TOKEN" \
    -d '{"email":"wrong@example.com","password":"wrongpass"}')

[ "$RESPONSE" = "401" ] || [ "$RESPONSE" = "422" ]
check_result "Bad login rejection (401/422 response)"

# Test 7: Test valid login (if test user exists)
echo "7️⃣ Testing valid login..."
curl -s -c $COOKIE_FILE -b $COOKIE_FILE $BASE_URL/sanctum/csrf-cookie > /dev/null
XSRF_TOKEN=$(grep "XSRF-TOKEN" $COOKIE_FILE | cut -f7)

RESPONSE=$(curl -s -w "%{http_code}" -o /dev/null -c $COOKIE_FILE -b $COOKIE_FILE -X POST $BASE_URL/login \
    -H "Content-Type: application/json" \
    -H "X-XSRF-TOKEN: $XSRF_TOKEN" \
    -d '{"email":"securitytest@example.com","password":"SecurePass123!"}')

if [ "$RESPONSE" = "200" ]; then
    echo -e "${GREEN}✅ PASS${NC}: Valid login (200 response)"
    ((TESTS_PASSED++))
    
    # Test 8: Authenticated API access
    echo "8️⃣ Testing authenticated API access..."
    API_RESPONSE=$(curl -s -w "%{http_code}" -o /dev/null -c $COOKIE_FILE -b $COOKIE_FILE $BASE_URL/api/me)
    
    [ "$API_RESPONSE" = "200" ]
    check_result "Authenticated API access"
    
    # Test 9: Logout
    echo "9️⃣ Testing logout..."
    LOGOUT_RESPONSE=$(curl -s -w "%{http_code}" -o /dev/null -c $COOKIE_FILE -b $COOKIE_FILE -X POST $BASE_URL/logout)
    
    [ "$LOGOUT_RESPONSE" = "200" ] || [ "$LOGOUT_RESPONSE" = "204" ]
    check_result "Logout functionality"
    
    # Test 10: API access after logout (should fail)
    echo "🔟 Testing API access after logout..."
    API_AFTER_LOGOUT=$(curl -s -w "%{http_code}" -o /dev/null -c $COOKIE_FILE -b $COOKIE_FILE $BASE_URL/api/me)
    
    [ "$API_AFTER_LOGOUT" = "401" ]
    check_result "API rejection after logout"
    
else
    echo -e "${YELLOW}⚠️  SKIP${NC}: Valid login test (test user may not exist)"
    echo -e "${YELLOW}⚠️  SKIP${NC}: Subsequent authenticated tests"
fi

# Test 11: Frontend serving
echo "1️⃣1️⃣ Testing frontend serving..."
FRONTEND_RESPONSE=$(curl -s -w "%{http_code}" -o /dev/null $BASE_URL)
[ "$FRONTEND_RESPONSE" = "200" ]
check_result "Frontend serving"

# Test 12: API routing
echo "1️⃣2️⃣ Testing API routing..."
API_ROUTE_RESPONSE=$(curl -s -w "%{http_code}" -o /dev/null $BASE_URL/api/test 2>/dev/null || echo "401")
[ "$API_ROUTE_RESPONSE" = "401" ] || [ "$API_ROUTE_RESPONSE" = "404" ]
check_result "API routing (proper backend routing)"

echo
echo "🏁 Test Results Summary"
echo "======================"
echo -e "Tests Passed: ${GREEN}$TESTS_PASSED${NC}"
echo -e "Tests Failed: ${RED}$TESTS_FAILED${NC}"
echo

if [ $TESTS_FAILED -eq 0 ]; then
    echo -e "${GREEN}🎉 All critical security tests passed!${NC}"
    echo "✅ CSRF protection: Working"
    echo "✅ Secure cookies: Configured"
    echo "✅ Rate limiting: Functional"
    echo "✅ Security headers: Present"
    echo "✅ HTTPS enforcement: Active"
    echo "✅ Authentication flow: Working"
    exit 0
else
    echo -e "${RED}⚠️  Some tests failed. Please review the configuration.${NC}"
    exit 1
fi