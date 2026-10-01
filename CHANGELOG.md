# Changelog

All notable changes to this project are documented here.

## [1.0.0] — 2026-10-01

### Added

- Settings registry (`settings` table, `SettingsService`, `setting()` helper)
- Web installer wizard (`/install`) and CLI `php spark s3:install`
- Release zip script, GitHub Actions CI
- Language stubs (`app/Language/id`, `en`)
- Production hardening: logo upload allowlist, `installer.enabled`, HTTPS cookie flags, schedule job exception handling

### Changed

- Branding reads `school.name` / `school.logo_path` from settings with legacy `app_settings` fallback
- Neutral default school name (no school-specific branding in new installs)
- Default seed is empty template only (**hari** Senin–Sabtu; timeslot dikonfigurasi per sekolah lewat UI)
- `docs/` excluded from the repository (local-only / not shipped to clones)
- Database migrations squashed to a single baseline `CreateInitialSchema` (fresh install only)

### Removed

- Legacy demo SQL dump and `SmartSchoolSchedulingSeeder`
- Bundled school logo asset from previous demo branding
- Docker / docker-compose stubs (install via Laragon/XAMPP/VPS manual saja)
- Incremental migration chain (2026-07 / 2026-09) — **existing databases must reinstall** (wipe + migrate) rather than upgrade in place

### Security

- Forced change-password POST gated by `must_change_password`
- Non-admin kurikulum cannot modify/delete admin users or CSV-import kurikulum roles
- `public/uploads/.htaccess` denies PHP execution
