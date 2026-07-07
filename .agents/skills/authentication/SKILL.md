---
name: authentication
description: Implement authentication for S3 v2.0 — login via users table (NIP + password), roles guru/kurikulum/kepala_sekolah, optional guru profile (guru_id in session), must_change_password flow, CI4 Filters (KurikulumFilter, GuruFilter, KepalaSekolahFilter), and logout. No murid role.
---

# Authentication & Authorization (v2.0)

> **PRD Reference**: `docs/PRD.md` Section 3, Section 7.1, Section 10

## Overview

Session-based auth via tabel `users`. All roles login with **NIP + password**. Teaching staff may have optional `guru` profile.

## Components

### 1. AuthController (`app/Controllers/AuthController.php`)

```php
public function index(): string           // GET /auth/login
public function login(): Response         // POST /auth/login
public function logout(): Response        // POST /auth/logout
public function changePasswordForm(): string  // GET /auth/change-password
public function changePassword(): Response  // POST /auth/change-password
```

**Login Logic** (exact order):
```
1. Validate: nip (required), password (required)
2. Find users WHERE nip = ? AND deleted_at IS NULL AND is_active = 1
3. Not found → error "NIP atau password salah"
4. password_verify() → fail → same error (no user enumeration)
5. If must_change_password = 1 → set session minimal, redirect /auth/change-password
6. Lookup guru WHERE user_id = users.id AND deleted_at IS NULL → guru_id or null
7. Regenerate session ID
8. Set session:
     user_id, role (users.role), nama, guru_id (nullable), logged_in = true
9. Redirect by role:
     kurikulum  → /kurikulum/dashboard
     guru       → /guru/dashboard
     kepala_sekolah → /kepala-sekolah/dashboard
```

**Dual-role Kurikulum+mengajar**: `role = kurikulum` AND `guru_id` set → can access both kurikulum routes and guru jadwal routes (via GuruFilter).

### 2. Filters (`app/Filters/`)

#### AuthFilter
- `logged_in === true` else redirect `/auth/login`
- Apply globally except `/auth/*`

#### KurikulumFilter
- `role === 'kurikulum'`

#### GuruFilter
- `role === 'guru'` OR (`role === 'kurikulum'` AND `guru_id` not null)

#### KepalaSekolahFilter
- `role === 'kepala_sekolah'`

### 3. Routes (`app/Config/Routes.php`)

```php
$routes->get('auth/login', 'AuthController::index');
$routes->post('auth/login', 'AuthController::login');
$routes->post('auth/logout', 'AuthController::logout');
$routes->get('auth/change-password', 'AuthController::changePasswordForm', ['filter' => 'auth']);
$routes->post('auth/change-password', 'AuthController::changePassword', ['filter' => 'auth']);

$routes->group('kurikulum', ['filter' => 'kurikulum'], function ($routes) { ... });
$routes->group('guru', ['filter' => 'guru'], function ($routes) { ... });
$routes->group('kepala-sekolah', ['filter' => 'kepala_sekolah'], function ($routes) { ... });
```

### 4. Filter Registration (`app/Config/Filters.php`)

```php
'auth'            => \App\Filters\AuthFilter::class,
'kurikulum'       => \App\Filters\KurikulumFilter::class,
'guru'            => \App\Filters\GuruFilter::class,
'kepala_sekolah'  => \App\Filters\KepalaSekolahFilter::class,
```

### 5. Login View (`app/Views/auth/login.php`)

- Fields: **NIP**, Password (not NIS)
- CSRF token, `esc()` on all output
- No self-registration link

## Removed (v1.1)

- `murid` table login (NIS)
- `is_admin` flag on guru
- `AdminFilter`, `MuridFilter`
- Routes `/admin/*`, `/murid/*`
- `user_type` session key → use `role`

## Security

- [ ] `password_verify()` only
- [ ] `$session->regenerate()` on login
- [ ] `$session->destroy()` on logout
- [ ] CSRF on all forms
- [ ] Block inactive users (`is_active = 0`)
- [ ] `must_change_password` blocks dashboard until changed

## Testing Checklist

- [ ] Kurikulum login → `/kurikulum/dashboard`
- [ ] Guru login → `/guru/dashboard`
- [ ] Kepala Sekolah login → `/kepala-sekolah/dashboard`
- [ ] Kurikulum with guru profile can access `/guru/jadwal`
- [ ] Kurikulum without guru profile cannot access `/guru/*`
- [ ] Wrong password / unknown NIP → error
- [ ] `must_change_password` redirects before dashboard
- [ ] Logout clears session
