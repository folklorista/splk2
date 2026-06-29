# E2E Workflow Test - Spuštění

Kompletní test user journey: Register → Login → Create → Delete s kontrolami business rules.

## Prerequisity

1. **PHP 8.0+** s MySQL support
2. **MySQL běží** a databáze existuje
3. **Composer** nainstalován a `composer install` spuštěn

## Krok za Krokem

### 1. Spustit API server

V terminálu v `api/` adresáři:

```bash
cd /path/to/splk2/api
php -S localhost:8000 -t public
```

Ověřit že API běží:
```bash
curl http://localhost:8000/login
# Mělo by vrátit 400, ne connection error
```

### 2. V jiném terminálu spustit test

```bash
cd /path/to/splk2/api

# Spustit konkrétní E2E test
./vendor/bin/phpunit tests/Integration/E2EWorkflowTest.php

# Nebo spustit všechny testy
./vendor/bin/phpunit
```

## Co Test Dělá

```
┌─────────────────────────────────────────────────────────────┐
│              E2E WORKFLOW TEST - KROKY                       │
├─────────────────────────────────────────────────────────────┤
│ 1. Register    → POST /register                             │
│    ✓ User created with ID                                   │
├─────────────────────────────────────────────────────────────┤
│ 2. Login       → POST /login                                │
│    ✓ Token received                                         │
├─────────────────────────────────────────────────────────────┤
│ 3. Create Cat  → POST /categories                           │
│    ✓ Category created with ID                               │
├─────────────────────────────────────────────────────────────┤
│ 4. Create Item → POST /items (with category_id)            │
│    ✓ Item created with ID                                   │
├─────────────────────────────────────────────────────────────┤
│ 5. Delete Cat  → DELETE /categories/{id}                    │
│    ✗ FAILS (409) - Business Rule: Category has items!      │
│    ✓ Correctly prevented                                    │
├─────────────────────────────────────────────────────────────┤
│ 6. Delete Item → DELETE /items/{id}                         │
│    ✓ Item deleted                                           │
├─────────────────────────────────────────────────────────────┤
│ 7. Delete Cat  → DELETE /categories/{id}                    │
│    ✓ NOW succeeds (category is empty)                       │
├─────────────────────────────────────────────────────────────┤
│ 8. Cleanup     → GET /categories/{id}, GET /items/{id}     │
│    ✓ Both return 404 (properly deleted)                     │
└─────────────────────────────────────────────────────────────┘
```

## Expected Output

```
=== STEP 1: Register New User ===
Request: POST /register
Email: e2e-test-801000@example.com
Response Status: 201
Response: {
  "status": 201,
  "message": "Record created",
  "data": {
    "id": 42
  },
  "error": null
}
✓ User registered successfully (ID: 42)

=== STEP 2: Login User ===
Request: POST /login
Email: e2e-test-801000@example.com
Response Status: 200
✓ Login successful
Token: eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...

=== STEP 3: Create Category ===
Request: POST /categories
Response Status: 201
✓ Category created successfully (ID: 15)

=== STEP 4: Create Item ===
Request: POST /items
Response Status: 201
✓ Item created successfully (ID: 42)

=== STEP 5: Try Delete Category (Should Fail) ===
Request: DELETE /categories/15
Response Status: 409
✓ Correctly prevented deletion (business rule enforced)
  Error: Cannot delete category with items. Remove items first.

=== STEP 6: Delete Item ===
Request: DELETE /items/42
Response Status: 200
✓ Item deleted successfully

=== STEP 7: Delete Category (Now Succeeds) ===
Request: DELETE /categories/15
Response Status: 200
✓ Category deleted successfully

=== STEP 8: Verify Cleanup ===
Request: GET /categories/15
Response Status: 404
✓ Category properly deleted (not found)

Request: GET /items/42
Response Status: 404
✓ Item properly deleted (not found)

Tests: 8 passed ✓
```

## Co To Testuje

### ✅ Validation
- Registrace s validací (email, password length, jména)
- Login s validací

### ✅ Business Rules
- **Klíčový test**: Nelze smazat kategorii, která má items
- Výchozí chybový kód: 409 (Conflict)
- Error message je jasný

### ✅ CRUD Operations
- CREATE: User, Category, Item
- READ: GET endpoints po vytvoření
- UPDATE: (neintegrováno v tomto testu, ale funguje)
- DELETE: Postupné mazání

### ✅ Database State
- Verifikace že data jsou opravdu v DB (GET po CREATE)
- Verifikace že data jsou vymazána (404 po DELETE)

### ✅ Authentication
- Token flow (register → login → use token)
- Token je validní pro všechny operace

## Troubleshooting

### "Cannot connect to API"
```
ERROR: Cannot connect to API at http://localhost:8000
```

**Řešení**: Spustit API server v jiném terminálu
```bash
cd api && php -S localhost:8000 -t public
```

### "401 Unauthorized"
```
Response Status: 401
Error: You must be logged in
```

**Řešení**: Token není nastavený nebo je neplatný. Zkontrolovat Login step.

### "400 Validation failed"
```
Response Status: 400
Error: email already exists
```

**Řešení**: Test email se předtím použil. Zkontrolovat test je idempotent (generuje nový email).

### "Database error"
```
Response Status: 500
Error: Database error
```

**Řešení**: 
1. Zkontrolovat MySQL běží
2. Zkontrolovat database config v `config/config.local.php`
3. Zkontrolovat database existuje: `mysql -u splk -p splk -e "SELECT 1;"`

## Rozšíření Testu

### Přidat další scenáře:

```php
// Test: Update category
public function test_UpdateCategory(): void {
    $response = $this->put("/categories/{$this->categoryId}", [
        'name' => 'Updated Category Name'
    ]);
    $this->assertEquals(200, $response['status']);
}

// Test: Search items
public function test_SearchItems(): void {
    $response = $this->get("/items?search=E2E-TEST");
    $this->assertGreaterThan(0, count($response['data']));
}

// Test: Pagination
public function test_PaginationWithLimit(): void {
    $response = $this->get("/items?limit=5&offset=0");
    $this->assertLessThanOrEqual(5, count($response['data']));
}
```

## CI/CD Integration

### GitHub Actions

Přidej do `.github/workflows/test.yml`:

```yaml
- name: Run E2E Tests
  run: |
    cd api
    php -S localhost:8000 -t public &
    sleep 2
    ./vendor/bin/phpunit tests/Integration/E2EWorkflowTest.php
```

## Metriky

- **Počet testů**: 8 steps
- **Doba běhu**: ~2-3 sekundy
- **Coverage**: CRUD, auth, business rules
- **False positives**: Nízké (konkrétní data, specifické IDs)

---

**Status**: ✓ Ready to run

Spustit: `./vendor/bin/phpunit tests/Integration/E2EWorkflowTest.php`
