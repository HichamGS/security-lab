# Laravel 11 Security Testing Lab

A dockerized REST API security-testing lab with intentional vulnerabilities for learning and testing purposes.

> **⚠️ SECURITY WARNING**: This lab contains **intentional security vulnerabilities**. Never deploy this code to production or expose it to the internet. This lab binds only to `localhost` and should only be used for testing your own code in an isolated environment.

---

## Quick Start

### Prerequisites

- Docker and Docker Compose installed
- Git (optional, for cloning)

### Step 1: Navigate to the Lab Directory

```bash
cd /workspace/security-lab
```

### Step 2: Build and Start Containers

```bash
docker compose up -d --build
```

This starts:
- **nginx** - Web server (port 8080 on localhost)
- **php-fpm** - PHP 8.3-FPM application server
- **mysql:8** - Database server
- **redis:7** - Cache/session store
- **adminer** - Database GUI (port 8081 on localhost)

### Step 3: Install Dependencies (First Time Only)

If dependencies aren't already installed:

```bash
docker compose exec php-fpm composer install --working-dir=/var/www/html
```

### Step 4: Configure Environment

The `.env` file is pre-configured. Verify it exists:

```bash
docker compose exec php-fpm cat /var/www/html/.env
```

Expected database/redis config:
```
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=laravel
DB_USERNAME=laravel
DB_PASSWORD=secret

REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379
```

### Step 5: Run Migrations and Seeders

```bash
docker compose exec php-fpm php artisan migrate:fresh --seed
```

This creates:
- Users table with `is_admin` boolean
- Notes table with user ownership
- Test users: `alice@lab.test`, `bob@lab.test`, `admin@lab.test` (all with password: `password`)
- 3 notes each for alice and bob

### Step 6: Verify Installation

Run the test suite:

```bash
docker compose exec php-fpm php artisan test
```

Check container configuration:

```bash
docker compose config
```

---

## Accessing the Application

| Service | URL | Credentials |
|---------|-----|-------------|
| API | http://localhost:8080 | - |
| Adminer | http://localhost:8081 | mysql: laravel/secret |
| Telescope | http://localhost:8080/telescope | Any authenticated user |

---

## Test Users

| Email | Password | Role |
|-------|----------|------|
| alice@lab.test | password | Regular user |
| bob@lab.test | password | Regular user |
| admin@lab.test | password | Admin (is_admin=true) |

---

## API Endpoints

### Authentication (Sanctum)

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/register` | Register new user |
| POST | `/api/login` | Login and get token |
| POST | `/api/logout` | Logout (revoke token) |
| GET | `/api/me` | Get current user |

### Notes Resource

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/notes` | List all notes (authenticated) |
| POST | `/api/notes` | Create note |
| GET | `/api/notes/{id}` | Get specific note |
| PUT/PATCH | `/api/notes/{id}` | Update note |
| DELETE | `/api/notes/{id}` | Delete note |

### Users Resource (Vulnerable!)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/users/{user}` | Get user (**IDOR vulnerability**) |
| PUT/PATCH | `/api/users/{user}` | Update user (**Mass-assignment vulnerability**) |

---

## Vulnerabilities in This Lab

### 1. Mass Assignment Vulnerability

**Location**: `app/Http/Controllers/UserController.php` - `update()` method

**What's Wrong**:
```php
// VULNERABLE CODE
public function update(Request $request, User $user)
{
    $user->update($request->all()); // No field whitelist!
    return response()->json($user);
}
```

The controller uses `$request->all()` without filtering, allowing attackers to modify any fillable field including `is_admin`.

**How to Exploit**:

Using curl:
```bash
source attacker/curl/00-vars.sh

# Login as alice
TOKEN=$(curl -s -X POST "$API_URL/api/login" \
  -H "Content-Type: application/json" \
  -d '{"email":"alice@lab.test","password":"password"}' | jq -r '.token')

# Escalate privileges by setting is_admin=true
curl -X PUT "$API_URL/api/users/1" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"is_admin":true}'
```

Using Postman:
1. Import `attacker/postman/Security-Lab-Collection.json`
2. Run "Login as Alice" request
3. Save the token from response
4. Run "Mass-Assignment Attack" request with `{"is_admin": true}`

**Impact**: 
- Privilege escalation (regular user becomes admin)
- Unauthorized modification of sensitive fields
- Complete account takeover

**The Fix**:
```php
// SECURE CODE
public function update(Request $request, User $user)
{
    $validated = $request->only(['name', 'email']); // Whitelist allowed fields
    $user->update($validated);
    return response()->json($user);
}
```

Or use a Form Request with explicit rules.

---

### 2. IDOR (Insecure Direct Object Reference) / BOLA

**Location**: `app/Http/Controllers/UserController.php` - `show()` method

**What's Wrong**:
```php
// VULNERABLE CODE
public function show(User $user)
{
    // No ownership check! Any user can view any other user
    return response()->json($user);
}
```

There's no authorization check verifying that the requesting user owns or has permission to view the requested resource.

**How to Exploit**:

Using curl:
```bash
source attacker/curl/00-vars.sh

# Login as alice
TOKEN=$(curl -s -X POST "$API_URL/api/login" \
  -H "Content-Type: application/json" \
  -d '{"email":"alice@lab.test","password":"password"}' | jq -r '.token')

# View bob's data (user ID 2) without permission
curl "$API_URL/api/users/2" \
  -H "Authorization: Bearer $TOKEN"
```

Using Postman:
1. Import the collection
2. Login as alice
3. Run "IDOR Probe - View Other User" with different user IDs

**Impact**:
- Information disclosure (view other users' data)
- Privacy violations
- Potential for further attacks using exposed data

**The Fix**:
```php
// SECURE CODE
public function show(User $user)
{
    // Check if current user is viewing their own profile OR is admin
    if ($user->id !== auth()->id() && !auth()->user()->is_admin) {
        abort(403, 'Unauthorized access');
    }
    return response()->json($user);
}
```

Or use a Policy class with proper authorization.

---

### 3. Exposed Debug Tooling (Telescope)

**Location**: `app/Providers/TelescopeServiceProvider.php`

**What's Wrong**:
```php
// INTENTIONALLY WEAK GATE
Gate::define('viewTelescope', function ($user) {
    return app()->environment('local') && $user; // Any authenticated user!
});
```

In local environment, ANY authenticated user can access Telescope, which exposes:
- All API requests and responses
- Database queries
- Exception logs
- Mail previews
- Cache operations

**How to Exploit**:

1. Login with any valid credentials
2. Navigate to `http://localhost:8080/telescope`
3. Browse all debug information

**Impact**:
- Exposure of sensitive API tokens
- Database structure and query patterns revealed
- Other users' request data visible
- Potential credential harvesting

**The Fix**:
```php
// SECURE CODE - Restrict to specific admins
Gate::define('viewTelescope', function ($user) {
    return app()->environment('local') && $user->is_admin === true;
});
```

Better yet, disable Telescope entirely in non-development environments.

---

### 4. Missing Rate Limiting (To Test)

**Location**: `routes/api.php`

**What to Test**:
Repeated login attempts to check for rate limiting:

```bash
source attacker/curl/00-vars.sh

for i in {1..20}; do
  echo "Attempt $i:"
  curl -s -X POST "$API_URL/api/login" \
    -H "Content-Type: application/json" \
    -d '{"email":"alice@lab.test","password":"wrongpassword"}' | jq '.message'
done
```

**Expected Behavior**: Should see rate limiting kick in after several attempts.

**If Not Protected**: Add rate limiting middleware to auth routes.

---

## Running Tests

### Run All Tests

```bash
docker compose exec php-fpm php artisan test
```

### Run Specific Vulnerability Tests

```bash
# Test that Note IDOR protection works (should PASS)
docker compose exec php-fpm php artisan test --filter="note_idor_protection"

# Test documenting mass-assignment vulnerability (should FAIL when fixed)
docker compose exec php-fpm php artisan test --filter="mass_assignment_vulnerability"
```

### Understanding the Tests

The Pest tests in `tests/Feature/` include:

1. **`NoteIdorTest.php`** - Proves that Note ownership is properly enforced
   - Should PASS (demonstrates correct implementation)

2. **`UserMassAssignmentTest.php`** - Documents the mass-assignment vulnerability
   - Intentionally tests that the vulnerability EXISTS
   - Will FAIL once someone patches the vulnerability (this is the intended signal!)

---

## Using the Attacker Tools

### Curl Scripts

Navigate to the attacker directory:

```bash
cd /workspace/security-lab/attacker/curl
```

Source the variables file:

```bash
source 00-vars.sh
```

Available scripts:
- `01-login.sh` - Authenticate and get token
- `02-idor-probe.sh` - Test IDOR on notes endpoint
- `03-mass-assignment.sh` - Attempt privilege escalation
- `04-rate-limit-test.sh` - Test rate limiting on login

Example:
```bash
source 00-vars.sh
./01-login.sh alice@lab.test
./03-mass-assignment.sh
```

### Postman Collection

1. Open Postman
2. Import `attacker/postman/Security-Lab-Collection.json`
3. The collection includes:
   - Environment setup
   - Authentication requests
   - IDOR probe requests
   - Mass-assignment attack requests
   - Rate limiting test requests

---

## Architecture Overview

```
┌─────────────┐     ┌─────────────┐
│   nginx     │────▶│   php-fpm   │
│  (port 8080)│     │  (Laravel)  │
└─────────────┘     └──────┬──────┘
                           │
         ┌─────────────────┼─────────────────┐
         ▼                 ▼                 ▼
┌─────────────┐   ┌─────────────┐   ┌─────────────┐
│    mysql    │   │    redis    │   │  telescope  │
│  (port 3306)│   │  (port 6379)│   │  (debug UI) │
└─────────────┘   └─────────────┘   └─────────────┘
```

All services bind to `127.0.0.1` only - not accessible from external networks.

---

## Troubleshooting

### Container Won't Start

```bash
# Check logs
docker compose logs php-fpm
docker compose logs nginx

# Rebuild containers
docker compose down
docker compose up -d --build
```

### Database Connection Issues

```bash
# Reset database
docker compose exec mysql mysql -u laravel -psecret -e "DROP DATABASE IF EXISTS laravel; CREATE DATABASE laravel;"
docker compose exec php-fpm php artisan migrate:fresh --seed
```

### Permission Errors

```bash
# Fix storage permissions
docker compose exec php-fpm chown -R www-data:www-data /var/www/html/storage
docker compose exec php-fpm chmod -R 775 /var/www/html/storage
```

### Clear Caches

```bash
docker compose exec php-fpm php artisan optimize:clear
```

---

## Cleanup

Stop and remove all containers:

```bash
docker compose down
```

Remove volumes (destroys all data):

```bash
docker compose down -v
```

---

## Learning Objectives

After completing this lab, you should understand:

1. How mass-assignment vulnerabilities work and how to prevent them
2. How IDOR/BOLA vulnerabilities allow unauthorized data access
3. Why debug tools must be properly secured even in development
4. How to implement proper authorization using Policies
5. How to write tests that document security requirements
6. How to use both automated (Postman) and manual (curl) testing tools

---

## Ground Rules

1. **Localhost Only**: This lab only binds to `127.0.0.1`. Never expose these ports publicly.

2. **Testing Your Own Code Only**: Use this lab to test security tools, learn vulnerabilities, and practice secure coding - not to attack systems you don't own.

3. **Intentional Vulnerabilities**: The bugs in this lab are deliberate teaching tools. In production code, always:
   - Whitelist fields for mass assignment
   - Implement proper authorization checks
   - Restrict debug tooling access
   - Apply rate limiting

4. **Educational Purpose**: This lab is for learning and testing. Apply these lessons to build more secure applications.

---

## Additional Resources

- [Laravel Security Documentation](https://laravel.com/docs/security)
- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [Laravel Sanctum Docs](https://laravel.com/docs/sanctum)
- [Laravel Telescope Docs](https://laravel.com/docs/telescope)
