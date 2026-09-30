# Security Policy

## Supported versions

Security fixes are applied to the latest release on the default branch.

## Reporting a vulnerability

Please report security issues privately (do not open a public issue with exploit details). Include steps to reproduce, impact, and suggested fix if known.

## Practices in this project

- Passwords: bcrypt via `password_hash()` / `password_verify()`
- CSRF on state-changing HTTP requests (including installer forms)
- Role-based filters for Kurikulum, Guru, and Kepala Sekolah modules
- Login throttling on `auth/login`

After installation, remove public access to `/install` (enforced via `writable/installed.lock`).
