#!/bin/bash

# Change Tracking E2E Test Runner Script

API_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
API_URL="http://localhost:8000"
API_PROCESS_FILE="/tmp/api-change-tracking-test.pid"

# Cleanup function
cleanup() {
    if [ -f "$API_PROCESS_FILE" ]; then
        API_PID=$(cat "$API_PROCESS_FILE")
        kill $API_PID 2>/dev/null || true
        rm "$API_PROCESS_FILE"
    fi
}
trap cleanup EXIT

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo -e "${YELLOW}=================================================${NC}"
echo -e "${YELLOW}   SPLK2 API - Change Tracking E2E Test${NC}"
echo -e "${YELLOW}=================================================${NC}"

# Step 1: Check PHP
echo -e "\n${YELLOW}[1/4]${NC} Checking PHP..."
if ! command -v php &> /dev/null; then
    echo -e "${RED}ERROR: PHP not found${NC}"
    exit 1
fi
PHP_VERSION=$(php -v | head -n 1)
echo -e "${GREEN}✓${NC} PHP found: $PHP_VERSION"

# Step 2: Check Composer
echo -e "\n${YELLOW}[2/4]${NC} Checking Composer dependencies..."
cd "$API_DIR"
if [ ! -d "$API_DIR/vendor" ] || [ ! -f "$API_DIR/vendor/bin/phpunit" ]; then
    echo -e "${YELLOW}Installing dependencies...${NC}"
    composer install
    if [ ! -f "$API_DIR/vendor/bin/phpunit" ]; then
        echo -e "${RED}ERROR: phpunit installation failed${NC}"
        exit 1
    fi
fi
echo -e "${GREEN}✓${NC} Composer dependencies ready"

# Step 2.5: Apply migrations
echo -e "\n${YELLOW}[2.5/4]${NC} Applying database migrations..."
cd "$API_DIR"
php apply-migrations.php
if [ $? -ne 0 ]; then
    echo -e "${RED}ERROR: Migration failed${NC}"
    exit 1
fi
echo -e "${GREEN}✓${NC} Migrations applied"

# Step 3: Start API Server
echo -e "\n${YELLOW}[3/4]${NC} Starting API server..."

# Kill existing process
if lsof -Pi :8000 -sTCP:LISTEN -t >/dev/null 2>&1; then
    echo -e "${YELLOW}Stopping existing API server...${NC}"
    lsof -ti:8000 | xargs kill -9 2>/dev/null || true
    sleep 1
fi

# Start API in background
cd "$API_DIR"
php -S localhost:8000 -t public -r public/router.php > /tmp/api-change-tracking.log 2>&1 &
API_PID=$!
echo $API_PID > "$API_PROCESS_FILE"

# Wait for API
echo -e "${YELLOW}Waiting for API to start...${NC}"
MAX_ATTEMPTS=10
ATTEMPT=0
while [ $ATTEMPT -lt $MAX_ATTEMPTS ]; do
    if curl -s "$API_URL/login" -X POST -H "Content-Type: application/json" \
        -d '{"email":"test","password":"test"}' > /dev/null 2>&1; then
        echo -e "${GREEN}✓${NC} API server is running on $API_URL (PID: $API_PID)"
        break
    fi
    ATTEMPT=$((ATTEMPT + 1))
    if [ $ATTEMPT -lt $MAX_ATTEMPTS ]; then
        sleep 1
    fi
done

if [ $ATTEMPT -eq $MAX_ATTEMPTS ]; then
    echo -e "${RED}ERROR: API server failed to start${NC}"
    cat /tmp/api-change-tracking.log
    exit 1
fi

# Step 4: Run Change Tracking Tests
echo -e "\n${YELLOW}[4/4]${NC} Running Change Tracking E2E tests..."
echo -e "${YELLOW}=================================================${NC}\n"

cd "$API_DIR"

# Run the test with colorized output
./vendor/bin/phpunit tests/Integration/ChangeTrackingE2ETest.php --testdox
TEST_EXIT_CODE=$?

echo -e "\n${YELLOW}=================================================${NC}"
echo -e "\n${YELLOW}Cleaning up...${NC}"

# Results
if [ $TEST_EXIT_CODE -eq 0 ]; then
    echo -e "${GREEN}✓ All Change Tracking E2E tests passed!${NC}"
    echo ""
    echo "What the test verified:"
    echo "  ✓ User registration and authentication"
    echo "  ✓ CREATE - new_values captured in audit log"
    echo "  ✓ UPDATE - old_values and new_values captured"
    echo "  ✓ DELETE - old_values captured in audit log"
    echo "  ✓ Full audit trail is complete and accurate"
    echo ""
    echo -e "${BLUE}Change Tracking Implementation: PRODUCTION READY ✓${NC}"
    echo ""
    exit 0
else
    echo -e "${RED}✗ Change Tracking E2E tests failed${NC}"
    echo ""
    echo "For debugging:"
    echo "  1. Check API logs: tail -f /tmp/api-change-tracking.log"
    echo "  2. Run API manually: php -S localhost:8000 -t public"
    echo ""
    exit 1
fi
