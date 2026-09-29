# Changelog

All notable changes to this project are documented here.

## [Unreleased]

### Added

- Settings registry (`settings` table, `SettingsService`, `setting()` helper)
- Web installer wizard (`/install`) and CLI `php spark s3:install`
- Docker / docker-compose stubs, release zip script, GitHub Actions CI
- Language stubs (`app/Language/id`, `en`)

### Changed

- Branding reads `school.name` / `school.logo_path` from settings with legacy `app_settings` fallback
- Neutral default school name (no SMK-specific branding in new installs)
