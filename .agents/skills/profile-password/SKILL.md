---
name: profile-password
description: Implement profile and password management for S3 v2.0 — ProfileController (edit profil, ganti password sendiri), forced password change after login (must_change_password), and Kurikulum reset user password to default with mandatory change on next login.
---

# Profile & Password Management (v2.0)

> **PRD Reference**: `docs/PRD.md` Section 3.3, Section 7.2, Section 9.2

## Overview

Shared profile module for all roles + Kurikulum-only password reset for other users.

## Components

### 1. ProfileController (`app/Controllers/ProfileController.php`)

```php
public function index(): string       // GET /profile — show edit form
public function update(): Response    // POST /profile — update nama, email, no_telp
public function changePassword(): Response  // POST /profile/password
```

**Editable fields** (on `users`): `nama`, `email`, `no_telp` — NOT `nip`, NOT `role`.

**Change password** (self):
```
1. Validate: password_lama, password_baru, password_konfirmasi
2. password_verify(password_lama, users.password)
3. password_baru === password_konfirmasi, min 6 chars
4. password_hash(password_baru, PASSWORD_BCRYPT)
5. Set must_change_password = 0
6. Flash success, redirect back
```

### 2. AuthController — Forced Change (`must_change_password`)

When `users.must_change_password = 1` after login or reset:
- Redirect to `/auth/change-password` before any other route
- Middleware/check in AuthFilter or dedicated filter: if `must_change_password` and not on change-password route → redirect
- Form: `password_baru`, `password_konfirmasi` only (no old password required after reset)
- On success: clear flag, redirect to role dashboard

### 3. Kurikulum User Reset (`Kurikulum\UserController`)

```php
public function resetPassword(int $id): Response  // POST /kurikulum/users/{id}/reset-password
```

**Logic**:
```
1. Load target user (not self — optional safeguard)
2. default = schedule_config 'default_password' or 'password123'
3. users.password = password_hash(default)
4. users.must_change_password = 1
5. Flash: "Password direset. User wajib ganti saat login berikutnya."
```

### 4. Views

- `app/Views/profile/index.php` — edit form + link/section ganti password
- `app/Views/auth/change_password.php` — forced change (minimal layout, no sidebar until done)

### 5. Routes

```
GET  /profile              → ProfileController::index
POST /profile              → ProfileController::update
POST /profile/password     → ProfileController::changePassword
POST /kurikulum/users/(:num)/reset-password → UserController::resetPassword
```

## User CRUD Integration (Kurikulum)

On **create user**:
- Hash password (default `password123`)
- Set `must_change_password = 1` (recommended) OR `0` if admin sets custom password

On **user list**: "Reset Password" button per row → POST reset endpoint with CSRF.

## Security

- [ ] Only Kurikulum can reset others' passwords
- [ ] Users can only change own password via `/profile/password`
- [ ] Never return plaintext password in response
- [ ] Validate CSRF on all POST

## Testing Checklist

- [ ] All roles can edit own profile fields
- [ ] Self password change requires correct old password
- [ ] Forced change blocks dashboard access
- [ ] Kurikulum reset sets default + must_change flag
- [ ] User with reset password must change before using app
