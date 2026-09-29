# Audit Fase 2 — Temuan Keamanan

> Tanggal: 2026-09-29  
> Referensi: OWASP Top 10 2021, OWASP ASVS L2, praktik CI4  
> Branch kerja: `audit/phase-2-security` (dilanjutkan dari phase-1)

## Ringkasan eksekutif

Skor risiko awal: **High**. Permukaan serangan utama: password default tebakable, CSRF exempt pada generate, generate sinkron tanpa lock (DoS/race), debug toolbar di required filters, cookie Secure=false, tidak ada login throttle, Excel formula injection, document root misconfig risk untuk `docs/`.

Setelah perbaikan Fase 2 (lihat status): Critical ditutup untuk password default; High ditutup untuk CSRF exempt (dipulihkan), generate lock, secure headers; Medium sebagian ditutup.

## Temuan

| ID | Severity | Kategori OWASP | Lokasi | Deskripsi | Dampak | Bukti/PoC (aman) | Perbaikan | Effort | Status |
|----|----------|----------------|--------|-----------|--------|------------------|-----------|--------|--------|
| SEC-001 | Critical | A07 Auth Failures | `UserController.php:30,167`, `GuruProvisioningService.php:212` | Password default statis `password123` | Account takeover jika email diketahui | Buat user → password selalu `password123` | Password acak sekali-pakai + must_change | S | Fixed |
| SEC-002 | High | A01 Broken Access | `Filters.php:80` | CSRF exempt `kurikulum/schedule/generate` | CSRF trigger generate mahal | Inspect Filters globals except | Hapus except; generate jadi enqueue cepat | S | Fixed |
| SEC-003 | High | A04 Insecure Design | `ScheduleController.php:86-111` | Generate sinkron tanpa mutex | Race/DoS dua generate bersamaan | Double-submit AJAX | Lock per TA + background job (F3) | M | Partial (lock F2; job F3) |
| SEC-004 | High | A05 Misconfig | `Filters.php:61-64` | DebugToolbar di required after | Info leak di production | Request dengan CI_ENVIRONMENT=production masih load toolbar class | Toolbar hanya non-production | S | Fixed |
| SEC-005 | Medium | A07 Auth Failures | `AuthController.php:26-46` | Tidak ada rate limit login | Brute force | Repeated POST login | Throttle filter per IP+email | S | Fixed |
| SEC-006 | Medium | A05 Misconfig | `Cookie.php:57`, `App.php:160` | Secure cookie false; HTTPS opsional | Session hijack di HTTP | Config values | SecureHeaders + dokumentasi force HTTPS | S | Fixed |
| SEC-007 | Medium | A03 Injection | `ExcelExporter.php` setCellValue | Formula injection Excel | RCE/data theft di Excel victim | Cell value `=cmd\|…` | Prefix `'` / sanitize leading `=+-@` | S | Fixed |
| SEC-008 | Medium | A05 Misconfig | document root | `docs/database/*.sql` bisa terekspos jika docroot salah | Data & hash bocor | Akses `/docs/...` jika root=repo | Root `.htaccess` deny + deploy docs | S | Fixed |
| SEC-009 | Medium | A01 Broken Access | Guru jadwal routes | Perlu pastikan hanya `guru_id` session | IDOR jadwal guru lain | Ganti ID di URL (jika ada) | Guru export/view pakai session only | S | Verified OK |
| SEC-010 | Low | A05 Misconfig | PdfExporter | Remote assets DomPDF | SSRF | Options | Sudah `isRemoteEnabled=false` | — | OK |
| SEC-011 | Medium | A04 Insecure Design | Password policy | min_length 6 lemah | Weak passwords | change-password validation | Naikkan min 8 + docs | S | Fixed |
| SEC-012 | Info | A08 Integrity | composer | Perlu audit supply chain | Vulnerable deps | `composer audit` | Jalankan di CI | S | Deferred CI F4 |

## Supply chain (composer audit 2026-09-29)

| Package | Action |
|---------|--------|
| codeigniter4/framework | Bumped to **^4.7.4** (CVE-2026-63221..63223) |
| dompdf/dompdf | Bumped to **^3.1.6** |
| phpoffice/phpspreadsheet | Bumped to **^5.8.1** (resolved 5.10.0) |

Re-run `composer audit` after deploy.

## Verifikasi

1. Buat user baru → flash menampilkan password acak sekali; login dengan `password123` gagal.
2. POST generate tanpa CSRF token → ditolak.
3. Login gagal 6× berturut → throttle.
4. Export Excel dengan nama mapel `=1+1` → sel di-escape.
