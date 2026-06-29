# 🚀 Run E2E Test - Super Quick Start

## Shortest Way (One Command)

```bash
cd api
./run-e2e-test.sh
```

Done! ✓

---

## Manual Way (If Script Fails)

### Terminal 1: Start API

```bash
cd api
php -S localhost:8000 -t public
```

### Terminal 2: Run Test

```bash
cd api
./vendor/bin/phpunit tests/Integration/E2EWorkflowTest.php
```

---

## What It Tests

```
Register new user
  ↓
Login (get token)
  ↓
Create category
  ↓
Create item in category
  ↓
Try to delete category
  ├─ ✗ FAILS (409) - Category has items! ← Business rule working!
  └─ Error: "Cannot delete category with items"
  ↓
Delete item
  ↓
Delete category (now empty)
  ├─ ✓ SUCCESS ← Cleanup works!
  └─ Category is gone
  ↓
Verify: GET deleted items → 404
```

---

## Expected Output

```
PASS  Tests\Integration\E2EWorkflowTest
 ✓ test 01 register new user
 ✓ test 02 login user
 ✓ test 03 create category
 ✓ test 04 create item
 ✓ test 05 try delete category with items (should fail)
 ✓ test 06 delete item
 ✓ test 07 delete category (now succeeds)
 ✓ test 08 verify cleanup

Tests: 8 passed ✓
```

---

## If It Fails

### "Cannot connect to API"
→ Start API in another terminal first

### "401 Unauthorized"
→ Token not received from login step

### "Database error"
→ Check MySQL is running and database exists

### "400 Validation failed"
→ Check validation rules in config/table-rules.php

---

## Detailed Instructions

See: `tests/E2E_INSTRUCTIONS.md`

---

## What You've Verified

After test passes, you know that:

✅ **Registration** works (validation, data stored)
✅ **Authentication** works (token generation and use)
✅ **CRUD** works (create, read, update, delete)
✅ **Foreign keys** work (item → category)
✅ **Business rules** work (can't delete category with items)
✅ **Error handling** works (correct HTTP codes and messages)
✅ **Database state** is correct (cleaned up properly)

**Your API is working correctly!** 🎉
