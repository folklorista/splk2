# SPLK2 API - Zbývající Kroky (Roadmap)

**Status**: E2E test funguje ✅

---

## 📋 Co Zbývá (12 Bodů)

### 🔴 VYSOKÁ PRIORITA (Doporučuju hned)

#### 1. ✅ Implementovat Unit Testy (HOTOVO)
- **Co**: ✅ Napsat 10-15 unit testů na RuleValidator
- **Status**: DOKONČENO - 40 unit testů
- **Kde**: `api/tests/Unit/RuleValidatorTest.php`
- **Pokryto**:
  - ✅ Validation constraints (required, type, length, unique, enum, min/max)
  - ✅ Hook execution (beforeCreate, beforeDelete)
  - ✅ Error handling
  - ✅ Database mocking
  - ✅ UPDATE partial validation
- **Čas**: ⏱️ ~2 hodiny
- **Priority**: ⭐⭐⭐

```bash
# Spustit:
cd api && ./vendor/bin/phpunit tests/Unit/
```

---

#### 2. Nastavit Environment Variables (.env)
- **Co**: Nahradit hardcoded hodnoty v config/config.local.php
- **Soubory**:
  - `api/config/config.local.php` - Upravit na env vars
  - `api/.env.example` - Vytvořit template
  - `.env` - Lokální (gitignore)
- **Očekávaný výstup**: 
  ```php
  // Místo:
  'host' => 'localhost',
  
  // Mělo by být:
  'host' => $_ENV['DB_HOST'] ?? 'localhost',
  ```
- **Benefit**: Dev/prod switch bez změny kódu, bezpečnost (secrets nejsou v gitu)
- **Čas**: 30 minut
- **Priority**: ⭐⭐⭐ (Bezpečnost)

```bash
# Template:
cp api/config/config.local.php api/.env.example
# Upravit .env.example, vytvořit .env (gitignore)
# Ověřit že .env není v .gitignore - PŘIDAT!
```

---

#### 3. Implementovat Health Check Endpoint
- **Co**: Přidat GET /health endpoint
- **Soubor**: `api/index.php` (přidat route)
- **Vrátí**:
  ```json
  {
    "status": "operational",
    "database": "OK",
    "uptime": "12345",
    "timestamp": "2025-01-15T10:30:00Z"
  }
  ```
- **Benefit**: Monitoring, load balancer health checks, alerting
- **Čas**: 30 minut
- **Priority**: ⭐⭐⭐ (Production-ready)

```bash
# Test:
curl http://localhost:8000/health
```

---

### 🟡 STŘEDNÍ PRIORITA (Dělat později)

#### 4. Nastavit GitHub Actions CI/CD
- **Co**: Auto-run testy na každý push a PR
- **Soubor**: `.github/workflows/api-tests.yml`
- **Dělá**:
  - Spustí API server
  - Nainstaluje dependencies
  - Spustí E2E testy
  - Spustí unit testy
  - Vykáže výsledky
- **Čas**: 1.5 hodiny
- **Priority**: ⭐⭐ (Nice to have)

```yaml
# Template je v api/tests/README.md
# Kopírovat do .github/workflows/api-tests.yml
```

---

#### 5. Přidat API Documentation Web UI
- **Co**: Swagger UI nebo ReDoc pro openapi.yaml
- **Soubor**: Nová route GET /docs
- **Vrátí**: Interactive API browser
- **Benefit**: Users vidí API live, testují endpointy z UI
- **Čas**: 1 hodina
- **Priority**: ⭐⭐ (Marketing/UX)

```bash
# Možnosti:
# 1. Swagger UI - npm install swagger-ui-express
# 2. ReDoc - npm install redoc
# 3. nebo statická HTML stránka s ReDoc CDN (jednodušší)
```

---

#### 6. Implementovat Rate Limiting
- **Co**: Max 100 requests/min per IP/user
- **Soubor**: `api/src/RateLimiter.php` + middleware v index.php
- **Benefit**: Ochrana proti DDoS, API abuse
- **Čas**: 1.5 hodiny
- **Priority**: ⭐⭐ (Bezpečnost)

```bash
# Soubor: api/src/RateLimiter.php
# Test: Spustit 101 requestů, 101. dostane 429 (Too Many Requests)
```

---

#### 7. Implementovat Soft Deletes
- **Co**: is_deleted flag místo hard delete
- **Databáze**: 
  - ALTER TABLE users ADD is_deleted TINYINT(1) DEFAULT 0;
  - Stejně pro items, categories, groups, ...
- **Kód**: 
  - Upravit Database.php getAll() - filtrovat is_deleted = 0
  - Upravit delete() - SET is_deleted = 1
  - Přidat restore() endpoint
- **Benefit**: Obnovitelná data, audit trail, GDPR compliance
- **Čas**: 2 hodiny
- **Priority**: ⭐⭐ (Data safety)

```bash
# Migration script v api/migrations/add-soft-deletes.sql
# Test: DELETE /users/1 → user existuje ale je "deleted"
```

---

### 🔵 NÍZKÁ PRIORITA (Bonusové features)

#### 8. Change Tracking (Audit Detail)
- **Co**: Ukládat before/after values v audit_logs
- **Databáze**: Přidat JSONB sloupec old_values a new_values v audit_logs
- **Benefit**: "Kdo co změnil a z čeho na co"
- **Čas**: 2 hodiny
- **Priority**: ⭐ (Nice to have)

```bash
# ALTER TABLE audit_logs ADD old_values JSON, ADD new_values JSON;
# Test: Změnit položku, zkontrolovat audit_logs - vidět before/after
```

---

#### 9. Role-Based Access Control (RBAC)
- **Co**: Vynutit roles na operace
- **Databáze**: users_roles tabulka existuje, jen ji používat
- **Kód**: 
  - Middleware pro check role
  - Table rules pro RBAC (custom validateRole method)
- **Benefit**: Admin/user/guest role enforcement
- **Čas**: 3 hodiny
- **Priority**: ⭐ (Security)

```bash
# Endpoint security:
# POST /users - jen admin
# PUT /users/{id} - jen admin nebo sám sobě
# DELETE /users/{id} - jen admin
```

---

#### 10. Webhook System
- **Co**: POST notifications na external URLs
- **Databáze**: Nová tabulka webhooks (url, events, active)
- **Kód**: Volat webhook v afterCreate/afterUpdate/afterDelete hooks
- **Benefit**: Real-time integraci se 3rd party systémy
- **Čas**: 3 hodiny
- **Priority**: ⭐ (Advanced)

```bash
# Webhook event: user.created → POST https://example.com/webhooks/user-created
```

---

#### 11. File Uploads
- **Co**: Implementovat file uploads (tabulka existuje)
- **Endpoint**: POST /files/upload
- **Benefit**: Attachments, dokumenty v systému
- **Čas**: 2.5 hodiny
- **Priority**: ⭐ (Feature)

```bash
# Validace: max 10MB, whitelisted extensions
# Storage: /public/uploads/
# Database: Zaznamenat user_id, timestamp, filename
```

---

#### 12. GraphQL Layer (Optional)
- **Co**: Vrstva nad REST API
- **Benefit**: Query builder, efficient data fetching
- **Čas**: 4+ hodiny
- **Priority**: ⭐ (Overkill pro malý projekt)

```bash
# Knihovna: graphql-core + webonyx/graphql-php
# Alternativa: Přeskočit, REST je dost
```

---

## 📊 Souhrn (Co je kdy dělat)

### Týden 1 (MUSÍ SE DĚLAT)
```
- [ ] Unit testy (2h)
- [ ] .env config (0.5h)
- [ ] Health check (0.5h)
- [ ] Total: 3 hodiny
```

### Týden 2-3 (BY MĚLO)
```
- [ ] GitHub Actions (1.5h)
- [ ] API Docs UI (1h)
- [ ] Rate limiting (1.5h)
- [ ] Soft deletes (2h)
- [ ] Total: 6 hodin
```

### Později (NICE TO HAVE)
```
- [ ] Change tracking (2h)
- [ ] RBAC (3h)
- [ ] Webhooks (3h)
- [ ] File uploads (2.5h)
- [ ] GraphQL (4h+)
```

---

## 🎯 Co Rekomenduju

**Abys měl fully production-ready API:**

1. ✅ **E2E test** - Hotovo!
2. **Unit testy** - Doporučuju HNED (2h, dá ti confidence)
3. **Environment config** - Bezpečnost (0.5h)
4. **Health check** - Production-ready (0.5h)
5. **GitHub Actions** - CI/CD (1.5h)

**Celkem 5.5 hodin → Máš production-ready API s testy, CI/CD a monitoring.**

Zbytek (rate limiting, soft deletes, RBAC) jsou "nice to have" podle potřeby.

---

## 📝 Jak Použít Tento Dokument

V každé session si vezmi **jeden bod** (nebo více pokud jsou malé):

```bash
# Session 1:
# Pracuj na: Unit testy

# Session 2:
# Pracuj na: Environment config + Health check

# Session 3:
# Pracuj na: GitHub Actions
```

Každý bod má:
- ✅ Co se má dělat
- ✅ Očekávaný výstup
- ✅ Čas odhadu
- ✅ Benefit
- ✅ Testovací příkaz

---

## 🚀 Quick Links

- E2E test: `api/RUN_E2E_TEST.md`
- Test framework: `api/tests/README.md`
- API docs: `api/docs/API.md`
- Reusability: `api/REUSABLE.md`
- Table rules: `api/config/table-rules.php`

---

**Tip**: Pokud si chceš tento soupis aktualizovat (přidat body, změnit priority), řekni mi a upravím. Vede to v repo jako NEXT_STEPS.md pro budoucí reference.

---

**Status**: 
- ✅ Architektura (RuleValidator, table-rules)
- ✅ Dokumentace (OpenAPI, API.md, příklady)
- ✅ E2E test (funguje)
- ✅ Unit testy (40 testů, všechny prošly)
- ⏳ Production config (zbývá)
- ⏳ CI/CD (zbývá)
