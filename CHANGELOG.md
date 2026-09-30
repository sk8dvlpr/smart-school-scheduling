# Changelog

All notable changes to this project are documented here.

## [Unreleased]

### Added

- Settings registry (`settings` table, `SettingsService`, `setting()` helper)
- Web installer wizard (`/install`) and CLI `php spark s3:install`
- Release zip script, GitHub Actions CI
- Language stubs (`app/Language/id`, `en`)

### Changed

- Branding reads `school.name` / `school.logo_path` from settings with legacy `app_settings` fallback
- Neutral default school name (no school-specific branding in new installs)
- Default seed is empty template only (hari + timeslot); removed school-specific demo dump/seeder
- `docs/` excluded from the repository (local-only / not shipped to clones)

### Removed

- Legacy demo SQL dump and `SmartSchoolSchedulingSeeder`
- Bundled school logo asset from previous demo branding
- Docker / docker-compose stubs (install via Laragon/XAMPP/VPS manual saja)
