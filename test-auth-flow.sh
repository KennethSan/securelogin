#!/bin/bash

echo "🔐 AUTHENTICATION FLOW TEST"
echo "=========================="
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Base URL - make sure to use your actual Caddy server URL
BASE_URL="https://localhost:8443"

# Clean start
rm -f auth-test-cookies.txt
echo "🧹 Cleaned previous test cookies"
echo ""

echo -e "${BLUE}=== 1. CSRF HANDSHAKE TEST ===${NC}"
echo "Getting CSRF token from /sanctum/csrf-cookie..."
curl -k -i -c auth-test-cookies.txt -b auth-test-cookies.txt "$BASE_URL/sanctum/csrf-cookie"
echo -e "\n${GREEN}✅ CSRF token retrieved${NC}"

# Extract CSRF token from cookies
CSRF_TOKEN=$(grep -F "XSRF-TOKEN" auth-test-cookies.txt | cut -f7)
if [ -z "$CSRF_TOKEN" ]; then
    echo -e "${RED}❌ Failed to extract CSRF token from cookies${NC}"
    exit 1
fi
echo "CSRF token: ${CSRF_TOKEN:0:30}..."
echo ""

echo -e "${BLUE}=== 2. BAD LOGIN TEST (Should return 401) ===${NC}"
echo "Testing login with wrong credentials..."
curl -k -i -c auth-test-cookies.txt -b auth-test-cookies.txt \
  -X POST "$BASE_URL/login" \
  -H "Content-Type: application/json" \
  -H "X-XSRF-TOKEN: $CSRF_TOKEN" \
  -d '{"email":"wrong@example.com","password":"WrongPass123!"}'

echo ""
echo -e "${BLUE}=== 3. GOOD LOGIN TEST (Should return 200) ===${NC}"
echo "Testing login with correct credentials..."
curl -k -i -c auth-test-cookies.txt -b auth-test-cookies.txt \
  -X POST "$BASE_URL/login" \
  -H "Content-Type: application/json" \
  -H "X-XSRF-TOKEN: $CSRF_TOKEN" \
  -d '{"email":"testexample@gmail.com","password":"SecurePass123!"}'

echo ""
echo -e "${BLUE}=== 4. AUTH PROBE TEST (Should return 200 + User JSON) ===${NC}"
echo "Testing authenticated API endpoint..."
curl -k -i -c auth-test-cookies.txt -b auth-test-cookies.txt \
  "$BASE_URL/api/me" \
  -H "X-XSRF-TOKEN: $CSRF_TOKEN"

echo ""
echo -e "${BLUE}=== 5. THROTTLING TEST ===${NC}"
echo "Testing rate limiting (5 rapid requests)..."
for i in {1..6}; do
    echo "Request $i:"
    curl -k -i -c auth-test-cookies.txt -b auth-test-cookies.txt \
      -X POST "$BASE_URL/login" \
      -H "Content-Type: application/json" \
      -H "X-XSRF-TOKEN: $CSRF_TOKEN" \
      -d '{"email":"throttle@example.com","password":"ThrottleTest123!"}'
    echo ""
    if [ $i -lt 6 ]; then
      echo "Waiting 0.5 seconds..."
      sleep 0.5
    fi
done

echo ""
echo -e "${BLUE}=== 6. LOGOUT TEST (Should return 200, then /api/me → 401) ===${NC}"
echo "Testing logout..."
curl -k -i -c auth-test-cookies.txt -b auth-test-cookies.txt \
  -X POST "$BASE_URL/logout" \
  -H "X-XSRF-TOKEN: $CSRF_TOKEN"

echo ""
echo "Testing API access after logout (should be 401)..."
curl -k -i -c auth-test-cookies.txt -b auth-test-cookies.txt \
  "$BASE_URL/api/me" \
  -H "X-XSRF-TOKEN: $CSRF_TOKEN"

echo ""
echo -e "${BLUE}=== 7. PASSWORD RESET TEST ===${NC}"
echo "Triggering password reset..."
curl -k -i -c auth-test-cookies.txt -b auth-test-cookies.txt \
  -X POST "$BASE_URL/forgot-password" \
  -H "Content-Type: application/json" \
  -H "X-XSRF-TOKEN: $CSRF_TOKEN" \
  -d '{"email":"testexample@gmail.com"}'

echo ""
echo "Checking password reset mail logs..."
docker exec secure-app-backend sh -c "grep -n \"Reset Password\" storage/logs/laravel.log | tail -5"

echo ""
echo "🔐 AUTHENTICATION FLOW TEST COMPLETE"
echo "=================================="