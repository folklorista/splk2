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

#### 2. ✅ Nastavit Environment Variables (.env) (HOTOVO)
- **Co**: ✅ Nahradit hardcoded hodnoty v config/config.local.php
- **Status**: DOKONČENO
- **Soubory**:
  - ✅ `api/config/config.local.php` - Čte z env proměnných s fallback defaults
  - ✅ `api/.env.example` - Template se všemi parametry
  - ✅ `.env` - Lokální config (přidáno do .gitignore)
- **Konfigurovatelné parametry**:
  - `DB_HOST`, `DB_USERNAME`, `DB_PASSWORD`, `DB_NAME`
  - `JWT_SECRET`
  - `LOG_PATH`, `LOG_LEVEL`
- **Benefit**: Dev/prod switch bez změny kódu, bezpečnost (secrets nejsou v gitu)
- **Čas**: ⏱️ ~30 minut
- **Priority**: ⭐⭐⭐ (Bezpečnost)

```bash
# Template:
cp api/config/config.local.php api/.env.example
# Upravit .env.example, vytvořit .env (gitignore)
# Ověřit že .env není v .gitignore - PŘIDAT!
```

---

#### 3. ✅ Implementovat Health Check Endpoint (HOTOVO)
- **Co**: ✅ Přidat GET /health endpoint
- **Status**: DOKONČENO
- **Soubory**: `api/index.php` a `api/public/index.php`
- **Vrátí**:
  ```json
  {
    "status": "operational",
    "database": "OK",
    "uptime": 1234,
    "timestamp": "2026-06-29T11:02:37+00:00",
    "version": "1.0.0"
  }
  ```
- **Benefit**: Monitoring, load balancer health checks, alerting
- **Čas**: ⏱️ ~30 minut
- **Priority**: ⭐⭐⭐ (Production-ready)

```bash
# Test:
curl http://localhost:8000/health
```

---

### 🟡 STŘEDNÍ PRIORITA (Dělat později)

#### 4. ✅ Nastavit GitHub Actions CI/CD (HOTOVO)
- **Co**: ✅ Auto-run testy na každý push a PR
- **Status**: DOKONČENO
- **Soubor**: `.github/workflows/api-tests.yml`
- **Dělá**:
  - ✅ Nainstaluje PHP 8.3 + Composer dependencies
  - ✅ Setup MySQL 8.0 service
  - ✅ Spustí unit testy (RuleValidator, constraints, hooks)
  - ✅ Spustí API server na pozadí
  - ✅ Spustí E2E testy (CRUD, auth, tree operations)
  - ✅ Ověří health check endpoint
  - ✅ Vykáže code coverage
- **Triggers**: Push na master/develop, PRs na master
- **Čas**: ⏱️ ~1.5 hodiny
- **Priority**: ⭐⭐ (Nice to have)

```yaml
# Template je v api/tests/README.md
# Kopírovat do .github/workflows/api-tests.yml
```

---

#### 5. ✅ Přidat API Documentation Web UI (HOTOVO)
- **Co**: ✅ ReDoc UI pro openapi.yaml
- **Status**: DOKONČENO
- **Soubory**: 
  - `api/public/index.php` - GET /docs route
  - `api/public/openapi.yaml` - Schema file
- **Vrátí**: ✅ Interactive API browser s ReDoc
- **Endpointy**:
  - `GET /docs` → ReDoc UI (HTML + CDN)
  - `GET /openapi.yaml` → OpenAPI 3.1 spec
- **Benefit**: Users vidí API live, testují endpointy z UI
- **Čas**: ⏱️ ~1 hodina
- **Priority**: ⭐⭐ (Marketing/UX)

---

#### 6. ✅ Implementovat Rate Limiting (HOTOVO)
- **Co**: ✅ Max 100 requests/min per IP
- **Status**: DOKONČENO
- **Soubor**: `api/src/RateLimiter.php` + middleware v index.php (public/index.php)
- **Omezení**:
  - 100 requests/minute per IP address
  - Vrátí HTTP 429 Too Many Requests když překročeno
  - Exempt endpoints: /login, /register, /health, /docs, /openapi.yaml
- **HTTP Headers**:
  - `X-RateLimit-Limit`: 100
  - `X-RateLimit-Remaining`: zbývající requests
  - `X-RateLimit-Reset`: timestamp reset
- **Benefit**: Ochrana proti DDoS a API abuse
- **Čas**: ⏱️ ~1.5 hodiny
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

#### 12. ⏸️ GraphQL Layer (PŘESKOČIT PRO TEĎKA)
- **Status**: Architektonicky otevřeno, prakticky ne
- **Strategie**:
  - 🚫 **Teď**: Neimplementovat
  - 🏗️ **Architektonicky**: Nezavírat cestu (keep services clean)
  - ⚙️ **Prakticky**: Držet Angular services, čisté modely, REST endpointy
  - 🔮 **Později**: Zvážit pokud REST začne bolet
- **Benefit**: Query builder, efficient data fetching (pokud bude potřeba)
- **Čas**: 4+ hodiny (zatím přeskočeno)
- **Priority**: ⭐ (Možné budoucně, ale ne teď)

```bash
# Poznámka: REST je dost pro MVP
# Pokud se bude potřeba: webonyx/graphql-php
# Klientská strana: Apollo, Relay, nebo vlastní resolver
```

---

## 📊 Souhrn (Co je kdy dělat)

### Týden 1 (MUSÍ SE DĚLAT) ✅ DOKONČENO
```
- [x] Unit testy (2h) ✅
- [x] .env config (0.5h) ✅
- [x] Health check (0.5h) ✅
- [x] Total: 3 hodiny (HOTOVO!)
```

### Týden 2-3 (BY MĚLO) - POKROK: 4/6 HODIN ✅
```
- [x] GitHub Actions (1.5h) ✅
- [x] API Docs UI (1h) ✅
- [x] Rate limiting (1.5h) ✅
- [ ] Soft deletes (2h) ← NEXT
- [ ] Total: 6 hodin (4h done, 2h remaining)
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
2. ✅ **Unit testy** - HOTOVO! (40 testů, 75+ assertions)
3. ✅ **Environment config** - HOTOVO! (.env, fallback defaults)
4. ✅ **Health check** - HOTOVO! (GET /health endpoint)
5. **GitHub Actions** - CI/CD (1.5h) ← NEXT!

**Celkem ~3 hodiny HOTOVO! Zbývá CI/CD (1.5h) → Máš production-ready API s testy, monitoring a CI/CD.**

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
- ✅ Dokumentace (OpenAPI, API.md, příklady, ReDoc UI)
- ✅ E2E test (funguje)
- ✅ Unit testy (40 testů, všechny prošly)
- ✅ Environment config (.env, fallback defaults)
- ✅ Health check endpoint (GET /health, vrací JSON status)
- ✅ CI/CD - GitHub Actions (auto-run testů na push/PR)
- ✅ API Docs UI (GET /docs, GET /openapi.yaml)
- ✅ Rate limiting (100 requests/min per IP, vrací 429)
- ⏳ Soft deletes (zbývá - 2h)
- ⏳ Advanced features: Change tracking, RBAC, webhooks, file uploads
