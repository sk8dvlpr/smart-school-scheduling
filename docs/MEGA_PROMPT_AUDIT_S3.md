# MEGA PROMPT — Audit & Productization: Smart School Scheduling (S3)

> Tempel seluruh isi file ini ke Claude Code (atau agent coding lain) di root repo proyek.
> Prompt ini dibagi menjadi FASE. Agent WAJIB menyelesaikan satu fase, melapor, lalu menunggu konfirmasi sebelum lanjut ke fase berikutnya (kecuali diminta "jalankan semua").

---

## 0. PERAN & KONTEKS

Kamu adalah **Principal Engineer + Application Security Auditor + Product Architect** untuk aplikasi PHP berbasis **CodeIgniter 4**.

**Aplikasi:** Smart School Scheduling (S3) — aplikasi web pembuat jadwal pelajaran SMK otomatis.
- Stack: CodeIgniter 4, PHP ≥ 8.2, MySQL 8 / MariaDB 10.6+, DomPDF, PhpSpreadsheet.
- Algoritma: **CSP** (menghasilkan jadwal valid) → **GA** (optimasi soft constraint).
- Aturan: HC-1..HC-8 (hard constraint, tidak boleh dilanggar), SC-1..SC-12 (soft constraint berbobot).
- Peran: **Kurikulum** (admin), **Guru**, **Kepala Sekolah**. Tidak ada login siswa.
- Fitur: data master (tahun ajaran, jurusan, ruangan/lab, guru, kelas, mapel, timeslot), generate jadwal (CSP+GA), edit manual (tambah/hapus/swap dengan validasi HC), history generate + publish manual, ekspor PDF/Excel, laporan jam mengajar, login email+password, reset password.
- Dokumen acuan yang ada di repo: `README.md`, `docs/PRD.md`, `docs/CSP-GA-Constraints-Parameters.md`, `docs/database/smart_school_scheduling.sql`.

**Tujuan besar (dua sekaligus):**
1. **Audit menyeluruh** — keamanan, performa, bug/minor issue, kualitas kode, UX, dan peningkatan.
2. **Productization** — ubah dari aplikasi khusus satu sekolah ("SMK Tunas") menjadi **produk open-source yang bisa didistribusikan/dijual ke banyak lembaga sekolah**, dengan **installer ala WordPress** (first-run setup wizard) dan **menu Pengaturan** untuk mengubah konfigurasi setelahnya.

---

## 1. ATURAN KERJA (WAJIB DIPATUHI)

1. **Baca dulu, baru ubah.** Pelajari seluruh struktur repo (`app/`, `public/`, `docs/`, `writable/`, `composer.json`, migrations, seeders, routes, filters, config) sebelum menyimpulkan apa pun.
2. **Jangan mengarang.** Setiap temuan HARUS menyertakan bukti: `path/file.php:baris` + potongan kode singkat. Jika tidak yakin, tandai sebagai "perlu verifikasi", jangan dianggap fakta.
3. **Jangan merusak perilaku yang ada.** Algoritma CSP/GA dan aturan HC/SC adalah inti produk. Setiap perubahan pada area ini wajib dibuktikan lewat test (hasil jadwal tetap 0 pelanggaran HC).
4. **Perubahan kecil & terisolasi.** Satu perbaikan = satu commit logis dengan pesan jelas (Conventional Commits). Buat branch: `audit/phase-N-nama`.
5. **Backup dulu.** Sebelum migrasi struktural, buat migration yang reversible (`up()` dan `down()`), jangan edit migration lama yang sudah dirilis.
6. **Tanpa secret di repo.** Jangan pernah commit `.env`, kredensial, atau kunci. Jika ditemukan di history git, laporkan dan sarankan rotasi + pembersihan history.
7. **Bahasa:** laporan & dokumentasi pengguna dalam **Bahasa Indonesia**; kode, nama variabel, komentar teknis, dan pesan commit dalam **Bahasa Inggris**. UI harus siap i18n (lihat Fase 4).
8. **Setiap akhir fase** keluarkan: (a) ringkasan, (b) daftar file yang diubah, (c) cara verifikasi, (d) risiko/hal yang belum selesai, (e) pertanyaan yang butuh keputusan saya.
9. **Jika ada keputusan arsitektur besar** yang ambigu, berhenti dan tanyakan dengan 2–3 opsi + rekomendasi. Jangan memilih diam-diam.

---

## FASE 1 — RECONNAISSANCE & PETA SISTEM (baca-saja, tanpa mengubah kode)

Hasilkan `docs/audit/00-system-map.md` berisi:

- Pohon direktori beserta fungsi tiap folder/modul.
- Daftar seluruh **route** (method, URL, controller@method, filter/auth yang menempel, peran yang diizinkan).
- Daftar **controller, model, library/service, filter, helper, command (spark)**.
- **Skema database aktual** dari migrations (bandingkan dengan `docs/database/*.sql` — laporkan selisihnya), termasuk index, foreign key, tipe data, charset/collation.
- Alur data utama: login → generate → publish → view → export, digambar dengan **diagram Mermaid**.
- Daftar dependensi Composer + versi + status kerentanan (`composer audit`) + paket yang usang (`composer outdated`).
- Lokasi semua **hardcoded** nilai spesifik sekolah: nama sekolah "SMK Tunas", domain email `smktunas.sch.id`, logo, alamat, jurusan, jam sekolah, kop surat PDF, judul halaman, footer, akun default, dll. (`grep -rniE "tunas|smktunas|password123|admin@" .`). Tabelkan: file, baris, apa yang harus dijadikan konfigurasi.
- Titik masuk input user (form, query string, JSON/AJAX, upload file, import).

---

## FASE 2 — AUDIT KEAMANAN (Security)

Gunakan **OWASP Top 10 (2021)**, **OWASP ASVS L2**, dan praktik keamanan CodeIgniter 4. Untuk setiap area, periksa dan laporkan.

### 2.1 Autentikasi & Sesi
- Hashing password (harus `password_hash` Argon2id/bcrypt; cek cost). Cek adanya MD5/SHA1/plaintext.
- **Akun default** (`admin@smktunas.sch.id` / `password123`): wajib dihapus dari seeder produksi. Ganti dengan paksaan pembuatan admin saat instalasi.
- Brute-force protection: rate limit/throttling login, lockout sementara, delay progresif.
- Session: `regenerateId` saat login, cookie flags (`Secure`, `HttpOnly`, `SameSite`), timeout idle & absolut, invalidasi saat logout & ganti password.
- Fitur ganti/reset password: kebijakan password (panjang minimum, blacklist umum), **paksa ganti password saat pertama login setelah reset ke default**, reset default tidak boleh berupa string statis yang bisa ditebak (gunakan token acak sekali pakai atau password acak yang ditampilkan sekali).
- Enumerasi user lewat pesan error login/reset.

### 2.2 Otorisasi (RBAC)
- Periksa **setiap route & setiap aksi** apakah dilindungi filter peran. Cari **IDOR**: apakah Guru bisa mengakses jadwal/data guru lain dengan mengganti ID? Apakah Kepala Sekolah bisa memanggil endpoint tulis milik Kurikulum?
- Pastikan pengecekan peran dilakukan **di sisi server**, bukan hanya menyembunyikan menu.
- Pastikan data jadwal yang belum di-**publish** tidak bocor ke Guru/Kepsek via URL langsung, ekspor, atau API.

### 2.3 Injeksi & Validasi Input
- **SQL Injection**: cari `$db->query()` dengan string concatenation, `where()` dengan input mentah, `orderBy`/`groupBy` dinamis dari input user, `LIKE` tanpa escape. Wajib query binding/Query Builder.
- **XSS**: cek semua output di view (`esc()` konsisten? ada `<?= $var ?>` mentah?), output di PDF/Excel, JSON yang di-render via `innerHTML`, atribut & konteks JS. Cek CSP header.
- **CSRF**: filter CSRF aktif global? Endpoint AJAX (generate, swap, hapus slot, publish) menyertakan token? Perubahan state lewat GET?
- **Mass assignment**: `$allowedFields` model, `insert($this->request->getPost())` tanpa whitelist (mis. user bisa mengubah `role`).
- **CSV/Formula Injection** pada ekspor Excel (sel diawali `=`, `+`, `-`, `@`) dan **HTML injection** pada DomPDF.
- Validasi server-side untuk semua field (rules CI4 `Validation`), termasuk batas panjang, tipe, enum.
- Open redirect, header injection, SSRF (DomPDF `isRemoteEnabled` harus `false`).

### 2.4 Upload & File
- Jika ada upload (logo, import): validasi MIME nyata (bukan ekstensi), ukuran, nama file acak, simpan di luar `public/` atau non-eksekusi. Cegah PHP webshell.
- Path traversal pada unduhan/ekspor.

### 2.5 Konfigurasi & Deployment
- `CI_ENVIRONMENT` default harus `production`; pastikan **debug toolbar & error detail mati** di produksi.
- Pastikan `.env`, `writable/`, `app/`, `vendor/`, `docs/`, `*.sql`, `composer.*` **tidak dapat diakses via web**. Sediakan `.htaccess` root pelindung + contoh config Nginx. **`docs/database/*.sql` berisi data awal — pastikan tidak ikut terekspos.**
- Security headers: `Content-Security-Policy`, `X-Frame-Options`/`frame-ancestors`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`, `Strict-Transport-Security`.
- Paksa HTTPS (`forceGlobalSecureRequests`) sebagai opsi konfigurasi.
- Encryption key CI4 (`encryption.key`) — dibuat unik per instalasi, bukan bawaan repo.
- Izin file/folder (`writable/` saja yang writable).
- Logging: jangan log password/token/PII; rotasi log; audit log aksi sensitif.

### 2.6 Integritas Data & Logika Bisnis
- **Race condition / TOCTOU** saat dua Kurikulum melakukan edit manual/generate bersamaan → butuh transaksi + locking (`SELECT ... FOR UPDATE`) atau optimistic locking.
- Proses generate: apakah bisa dipicu berulang (DoS internal)? Batasi satu proses generate aktif per tahun ajaran.
- Validasi HC pada edit manual dilakukan **di server** dan atomik (transaksi), bukan hanya di JS.
- Pastikan "reset jadwal" dan operasi destruktif butuh konfirmasi + re-auth password + tercatat di audit log.
- Privasi data (data pribadi guru: NIP, email) — minimisasi & kontrol akses.

### 2.7 Supply Chain
- `composer audit`, cek paket abandoned, pin versi, lisensi kompatibel dengan rencana open-source.
- Cek `composer.json` scripts yang berbahaya; cek `vendor/` tidak ikut ter-commit.

**Format temuan (WAJIB seragam)** — simpan di `docs/audit/01-security-findings.md`:

| ID | Severity (Critical/High/Medium/Low/Info) | Kategori OWASP | Lokasi (file:baris) | Deskripsi | Dampak | Bukti/PoC (aman) | Perbaikan yang direkomendasikan | Effort (S/M/L) |

Sertakan ringkasan eksekutif + skor risiko keseluruhan. **Setelah laporan disetujui**, implementasikan perbaikan berurutan dari Critical → Low, tiap perbaikan disertai test regresi.

---

## FASE 3 — AUDIT PERFORMA & OPTIMASI

### 3.1 Database
- Aktifkan & analisis query: cari **N+1 query**, query di dalam loop, `SELECT *` tak perlu, tidak ada pagination.
- Jalankan `EXPLAIN` pada query terberat (tampilan jadwal per kelas/guru/ruangan, validasi HC, laporan jam guru). Usulkan **index** (composite index untuk pola `tahun_ajaran_id, hari, jam_ke, guru_id/kelas_id/ruangan_id`), foreign key, tipe data tepat.
- Pertimbangkan tabel/cache ringkasan untuk laporan.
- Gunakan transaksi + **batch insert** (`insertBatch`) saat menyimpan hasil generate (bukan insert satu per satu).
- Cek engine (InnoDB), charset (utf8mb4), collation konsisten.

### 3.2 Algoritma CSP + GA (bagian paling kritis)
- Profil: waktu & memori pada beberapa skala (mis. 12, 30, 60, 100 kelas). Buat **benchmark script reproducible** (`php spark benchmark:generate --classes=N --seed=S`).
- Identifikasi hotspot: fungsi fitness, pengecekan bentrok (gunakan struktur data hash/bitset/array terindeks alih-alih pencarian linear), deep copy kromosom, sorting berulang.
- Optimasi yang layak dievaluasi: heuristik CSP **MRV/degree/LCV**, forward checking/AC-3, incremental fitness evaluation (delta), caching penalti, early termination/stagnation detection, elitism, adaptive mutation rate, seeding populasi awal dari CSP, **determinisme via seed** (untuk reproduksibilitas & debugging).
- **Proses generate tidak boleh memblokir request HTTP.** Pindahkan ke **background job** (spark command + queue sederhana berbasis tabel DB, tanpa mewajibkan Redis) dengan **progress polling** (persen, generasi, fitness terbaik) dan tombol batalkan. Perhatikan `max_execution_time` & `memory_limit` di shared hosting — sediakan mode fallback.
- Pastikan hasil optimasi **tidak menurunkan kualitas** (bandingkan skor fitness & jumlah pelanggaran HC sebelum/sesudah pada seed yang sama).
- Tampilkan metrik: sebelum/sesudah (waktu, memori, skor).

### 3.3 Web & Frontend
- Ukuran aset, minify/bundle, cache header aset statis, gzip/brotli, lazy-load tabel besar, hindari render ribuan sel tanpa virtualisasi.
- Audit Lighthouse (Performance, Accessibility, Best Practices, SEO) pada halaman utama.
- Ekspor PDF/Excel: streaming/chunking, batasi memori DomPDF, opsi ekspor massal via job.
- Caching: CI4 cache (file/redis opsional) untuk data master yang jarang berubah; invalidasi benar.
- Konfigurasi produksi: OPcache, `composer install --no-dev --optimize-autoloader`, `php spark optimize` (jika tersedia di versi CI4 yang dipakai), `Config\Routing::$autoRoute = false`.

Keluaran: `docs/audit/02-performance-report.md` (metodologi, angka sebelum/sesudah, rekomendasi berprioritas) + implementasi optimasi yang aman.

---

## FASE 4 — AUDIT BUG, KUALITAS KODE, UX, AKSESIBILITAS

### 4.1 Bug & Logika
- Telusuri edge case: kelas tanpa guru yang mengampu mapel, guru memblokir semua hari, jam kebutuhan > slot tersedia, lab kapasitas kurang, mapel dengan jam ganjil (blok 2–3 JP berurutan), hari dengan jumlah JP berbeda (Jumat pendek, upacara Senin), pergantian tahun ajaran, hapus data master yang sudah dipakai jadwal (integritas referensial).
- Pastikan **konsistensi HC-1..HC-8** antara CSP, GA (crossover/mutasi tidak menghasilkan pelanggaran), edit manual, dan validasi publish. Buat **validator independen** yang memverifikasi jadwal final dan pakai di test.
- Publish parsial: pastikan peringatan akurat & status konsisten.
- Cek zona waktu, encoding (UTF-8/nama dengan karakter khusus), format tanggal Indonesia.
- Error handling: exception yang tertelan, pesan error bocor, halaman 404/403/500 kustom.

### 4.2 Kualitas Kode
- Struktur: pisahkan **controller tipis**, logika di **Service/Domain layer**, akses data di Model/Repository. Cari fat controller, duplikasi, fungsi >60 baris, magic number (pindahkan ke config/constants/enum).
- Static analysis: **PHPStan (level 6+ bertahap)**, **PHP-CS-Fixer/PHPCS (PSR-12)**, **Rector** (opsional). Jalankan dan laporkan.
- Typed properties, return types, `declare(strict_types=1)` di file baru.
- Dokumentasi PHPDoc pada API internal penting.

### 4.3 Testing
- Buat/lengkapi **PHPUnit** (unit + feature + database test): validator HC, fungsi fitness SC, CSP solver, GA operator, filter RBAC, service edit manual, ekspor.
- Target awal: cakupan ≥ 70% pada `Services/Scheduling`; **property-based/randomized test** (jadwal acak dari seed → 0 pelanggaran HC).
- Siapkan **CI GitHub Actions**: lint, phpstan, phpunit (matrix PHP 8.2/8.3, MySQL 8 & MariaDB 10.6), `composer audit`.

### 4.4 UX & Aksesibilitas
- Alur Kurikulum: apakah wizard data master jelas? Ada validasi kelengkapan sebelum generate (pre-flight check: "Kelas X belum punya guru untuk mapel Y")? Pesan error saat generate gagal harus actionable ("Guru B memblokir Senin-Kamis sehingga mapel Z tidak muat").
- Fitur yang layak ditambahkan (evaluasi & usulkan prioritas): drag-and-drop edit jadwal, tampilan konflik real-time, undo/redo edit manual, perbandingan antar history, impor data massal (Excel/CSV template), duplikasi jadwal dari tahun ajaran sebelumnya, dashboard ringkasan (skor kualitas, jam kosong guru), notifikasi email publish (opsional), mode gelap, responsif mobile, cetak jadwal rapi.
- Aksesibilitas: WCAG 2.1 AA (kontras, label form, keyboard navigation, aria pada tabel jadwal).
- Konsistensi UI, teks Bahasa Indonesia baku, empty state, loading state, konfirmasi aksi destruktif.

Keluaran: `docs/audit/03-bugs-quality-ux.md` (tabel temuan format sama seperti Fase 2 + kategori Bug/Code Smell/UX/A11y) + perbaikan.

---

## FASE 5 — PRODUCTIZATION: DARI "SMK TUNAS" MENJADI PRODUK MULTI-SEKOLAH

### 5.1 Prinsip Desain
- **Satu instalasi = satu lembaga sekolah** (single-tenant per instalasi, seperti WordPress). *Jangan* membuat multi-tenant kecuali saya minta. Namun struktur data harus mendukung **banyak tahun ajaran** dan (opsional) **multi-unit/kampus** dalam satu lembaga — tanyakan ke saya sebelum mengimplementasikan multi-kampus.
- **Nol hardcode** spesifik sekolah. Semua yang teridentifikasi di Fase 1 dipindahkan ke tabel `settings` / config.
- **Fleksibel terhadap jenis satuan pendidikan**: default SMK, tetapi nomenklatur harus dapat dikonfigurasi (SMK/SMA/MA/SMP) — jurusan bisa dinonaktifkan untuk SMP/SMA umum, istilah "Kurikulum" dapat diganti label.
- **Aman by default**, mudah bagi non-teknis (guru IT sekolah) tanpa terminal jika memungkinkan (target shared hosting/cPanel).

### 5.2 Installer Ala WordPress (First-Run Wizard)

Buat modul `Installer` (`app/Modules/Installer` atau setara) dengan URL `/install`. Perilaku:

**Deteksi instalasi:** jika file penanda `writable/installed.lock` (atau flag di DB) belum ada → semua request diarahkan (filter global) ke `/install`. Setelah selesai → `/install` **mengembalikan 404/redirect** dan tidak bisa dijalankan ulang tanpa menghapus lock secara manual. **Cegah race**: gunakan lock saat proses instalasi berjalan.

**Langkah wizard (stepper, dapat kembali ke langkah sebelumnya, state disimpan aman di session):**

1. **Selamat datang & pilih bahasa** (Indonesia/English).
2. **Pemeriksaan persyaratan sistem (pre-flight):** versi PHP ≥ 8.2, ekstensi (`intl`, `mbstring`, `json`, `mysqlnd`, `curl`, `gd`/`imagick`, `zip`), izin tulis (`writable/`, folder `.env`), `memory_limit`, `max_execution_time`, mod_rewrite/Nginx try_files, HTTPS. Tampilkan hijau/kuning/merah + solusi jelas per item. Blokir lanjut jika ada yang merah (wajib).
3. **Konfigurasi database:** host, port, nama DB, user, password, prefix tabel (opsional). Tombol **"Uji koneksi"** (AJAX). Opsi "buat database jika belum ada" jika user punya hak. Validasi versi MySQL/MariaDB. Deteksi database yang sudah berisi tabel S3 (tawarkan batal/gunakan ulang, jangan menimpa diam-diam).
4. **Profil lembaga:** nama sekolah, jenjang/jenis (SMK/SMA/dll.), NPSN, alamat, kota, telepon, email resmi, website, nama Kepala Sekolah, upload **logo** (validasi aman), tahun ajaran & semester aktif, zona waktu, bahasa default.
5. **Akun administrator (Kurikulum) pertama:** nama, email, password (dengan indikator kekuatan & kebijakan minimum), **tanpa akun default apa pun**.
6. **Template awal (pilih salah satu):**
   - *Kosong* (mulai dari nol),
   - *Template SMK* (jurusan/mapel/jam contoh generik yang dapat diedit — **bukan** data SMK Tunas),
   - *Template SMA/MA* / *SMP* (opsional),
   - *Data demo* (untuk uji coba, bisa dihapus dengan satu klik).
7. **Pengaturan jam sekolah awal:** jumlah hari efektif (5/6), durasi JP, jam mulai, istirahat, upacara/apel — dengan preview grid mingguan; bisa dilewati & diatur nanti.
8. **Ringkasan & konfirmasi → Install.** Proses: tulis `.env` (generate `encryption.key` acak, set `CI_ENVIRONMENT=production`, baseURL otomatis terdeteksi tapi bisa diedit) → jalankan **migration** programatik → seed template terpilih → simpan settings → buat admin → buat `installed.lock` → tampilkan progres real-time & log; **rollback bersih jika gagal di tengah**.
9. **Selesai:** tautan login, checklist keamanan pasca-instal (hapus/lock installer, HTTPS, backup), opsi unduh ringkasan konfigurasi (tanpa password).

**Persyaratan teknis installer:**
- Berfungsi **tanpa `.env` dan tanpa DB awal** (bootstrap ringan yang tidak menyentuh model/DB sebelum terkonfigurasi).
- Tetap berfungsi di shared hosting **tanpa akses terminal/Composer** → distribusi rilis harus menyertakan `vendor/` (lihat 5.6).
- Proteksi: CSRF pada wizard, validasi ketat, tidak pernah menampilkan password di respons/log, `.env` dibuat dengan izin 0640/0600 jika memungkinkan.
- Dukung juga **instalasi CLI non-interaktif** untuk DevOps: `php spark s3:install --db-host=... --admin-email=... --no-interaction` dan variabel lingkungan (Docker).
- Idempoten & aman diulang bila gagal (resume atau bersih).
- **Upgrade path:** `/install` mendeteksi versi terpasang vs versi kode → jika berbeda, arahkan ke halaman **"Upgrade database"** (menjalankan migration yang tertunda dengan backup peringatan). Simpan `app_version` & `db_schema_version` di settings.

### 5.3 Menu Pengaturan (Settings) — konfigurasi ulang setelah instalasi

Hanya peran **Kurikulum/Admin**. Bagi menjadi tab, tiap perubahan divalidasi, tercatat di audit log, dan menampilkan konfirmasi:

1. **Profil Lembaga** — semua data langkah 4 (nama, logo, alamat, kop surat, NPSN, kepala sekolah, tanda tangan/nama pejabat untuk PDF).
2. **Akademik** — tahun ajaran & semester, jenjang, terminologi/label kustom (mis. "Kurikulum" → "Wakasek Kurikulum"), aktifkan/nonaktifkan modul jurusan/lab.
3. **Jam & Kalender Sekolah** — timeslot per hari, jenis slot (JP/istirahat/upacara/lainnya), hari efektif, hari libur (opsional).
4. **Aturan Penjadwalan** — **bobot SC-1..SC-12 dapat diatur** (slider + preset "Seimbang / Hemat jam kosong guru / Prioritas siswa"), aktif/nonaktif tiap SC, klasifikasi mapel "berat/ringan/fisik" yang dapat diedit, parameter GA (populasi, generasi, mutasi, crossover, elitism, seed, batas waktu). HC tetap wajib & tidak dapat dinonaktifkan (tampilkan sebagai terkunci + penjelasan).
5. **Keamanan** — kebijakan password, timeout sesi, batas percobaan login, paksa HTTPS, 2FA (TOTP, opsional/roadmap), daftar sesi aktif.
6. **Email/Notifikasi** (opsional) — SMTP + tombol uji kirim; template email reset password/publish.
7. **Tampilan & Bahasa** — logo, warna aksen, bahasa default, format tanggal, zona waktu.
8. **Ekspor/Cetak** — template kop PDF, ukuran kertas/orientasi, tanda tangan, footer, nama file.
9. **Data & Pemeliharaan** — backup/restore DB (ekspor SQL/ZIP terenkripsi opsional), impor massal (template Excel), bersihkan data demo, reset tahun ajaran (dengan re-auth), lihat log audit, info sistem (versi, PHP, ekstensi, status queue), cek pembaruan (opsional, nonaktif default & tanpa telemetri tersembunyi).
10. **Manajemen Pengguna** — CRUD user, peran, reset password (token/acak sekali pakai), nonaktifkan akun.

**Arsitektur settings:**
- Tabel `settings` (`group`, `key`, `value` JSON/teks, `type`, `is_secret` terenkripsi, `updated_by`, `updated_at`) + service `SettingsService` dengan **cache** & invalidasi + helper `setting('school.name')`.
- Definisi settings dideklaratif (registry: key, tipe, default, validasi, label i18n) sehingga UI dapat dihasilkan dan aman.
- Rahasia (SMTP password) disimpan terenkripsi dengan `encryption.key`; tidak pernah ditampilkan ulang di UI (hanya "••••• (tersimpan)").

### 5.4 Migrasi Instalasi Lama (SMK Tunas) → Struktur Baru
- Buat migration + command `php spark s3:migrate-legacy` yang memindahkan nilai hardcode/data lama ke tabel `settings` **tanpa kehilangan data**. Uji dengan dump `docs/database/smart_school_scheduling.sql`.
- Pindahkan dump data contoh keluar dari jalur distribusi utama → jadikan **seeder/template opsional** (`database/templates/`), bukan data default terpasang.

### 5.5 Internasionalisasi & Kustomisasi
- Gunakan sistem bahasa CI4 (`app/Language/id`, `app/Language/en`); ekstrak semua string UI hardcoded. Default `id`.
- **Theming:** variabel CSS (warna/logo dari settings). Tanpa perlu edit kode untuk branding dasar.
- **Extensibility:** gunakan **CI4 Events** sebagai hook (`s3.schedule.generated`, `s3.schedule.published`, dll.) dan dokumentasikan; struktur folder agar fork/kustomisasi tidak konflik saat upgrade (kode inti vs `app/Custom`).

### 5.6 Distribusi & Packaging
- **Rilis ZIP siap-upload** (GitHub Releases) berisi `vendor/` (production, optimized), aset ter-build, **tanpa** `.git`, `tests/`, `docs/` internal, `.env`, dump data. Buat script `build-release.sh`/GitHub Action yang otomatis.
- Struktur untuk **shared hosting**: sediakan panduan meletakkan `public/` sebagai document root **atau** `index.php` + `.htaccess` di root yang mengarahkan aman ke `public/` sambil melindungi `app/`, `writable/`, `vendor/`.
- **Docker:** `Dockerfile` (php-fpm/apache, ekstensi lengkap), `docker-compose.yml` (app + MySQL + opsional phpMyAdmin), env-driven install.
- Config contoh: Apache `.htaccess`, Nginx server block, Laragon/XAMPP.
- **Versioning SemVer**, `CHANGELOG.md` (Keep a Changelog), tag rilis, skrip upgrade.
- Nama & branding produk netral (evaluasi nama produk; "S3" boleh dipertahankan tetapi tanpa "SMK Tunas").

### 5.7 Persiapan Open Source & Komersial
- **Lisensi:** analisis dan rekomendasikan (dengan pro/kontra) untuk tujuan "open source + boleh dijual/dimonetisasi": MIT/Apache-2.0 (permisif, siapa pun bisa jual ulang), **GPL-3.0/AGPL-3.0** (copyleft; melindungi dari penutupan kode), atau model **open-core/dual-license**. Periksa kompatibilitas lisensi semua dependensi (CI4 MIT, DomPDF LGPL, PhpSpreadsheet MIT). Buat `LICENSE` sesuai pilihan saya dan `THIRD_PARTY_NOTICES.md`. *(Kamu bukan penasihat hukum — tandai bagian ini sebagai rekomendasi teknis, bukan nasihat hukum.)*
- Berkas komunitas: `README.md` baru (Indonesia + English), `CONTRIBUTING.md`, `CODE_OF_CONDUCT.md`, `SECURITY.md` (cara lapor kerentanan bertanggung jawab), `.github/ISSUE_TEMPLATE`, `PULL_REQUEST_TEMPLATE`, `CODEOWNERS`, `SUPPORT.md`.
- **Bersihkan repo:** pastikan tidak ada data pribadi guru/siswa nyata, kredensial, atau nama sekolah di kode & **git history** (sarankan `git filter-repo`/BFG bila perlu). Ganti data contoh dengan **data fiktif**.
- Dokumentasi pengguna (`docs/user/`): panduan instalasi (Windows/Laragon, Linux, macOS, cPanel/shared hosting, Docker), panduan Kurikulum/Guru/Kepsek, FAQ, troubleshooting; dokumentasi developer (`docs/dev/`): arsitektur, ERD, algoritma CSP/GA, cara menambah SC baru, cara kontribusi, cara menjalankan test.
- Opsional komersial: rancang titik ekstensi untuk layanan berbayar (dukungan instalasi, hosting terkelola, plugin premium, lisensi kustomisasi) **tanpa** mengunci fitur inti; jelaskan opsi model bisnis secara singkat.

---

## FASE 6 — VERIFIKASI AKHIR & LAPORAN

1. Jalankan seluruh test, phpstan, cs-fixer, `composer audit`; laporkan hasil.
2. **Uji instalasi bersih end-to-end** di: (a) Docker fresh, (b) Windows/Laragon (petunjuk), (c) simulasi shared hosting tanpa CLI. Buat checklist hasil.
3. **Uji upgrade** dari instalasi lama (dump SMK Tunas) ke versi baru.
4. **Uji generate** pada dataset demo kecil & besar; bandingkan waktu/skor dengan baseline Fase 3.
5. **Re-audit keamanan** singkat terhadap seluruh perubahan baru (terutama installer & settings — permukaan serangan baru!). Pastikan installer terkunci setelah selesai dan tidak dapat dieksploitasi untuk takeover instalasi.
6. Hasilkan `docs/audit/99-final-report.md`:
   - Ringkasan eksekutif (skor sebelum/sesudah: keamanan, performa, kualitas).
   - Semua temuan + status (Fixed / Won't fix / Deferred + alasan).
   - Daftar perubahan arsitektur & migrasi.
   - **Roadmap** prioritas (Now / Next / Later) untuk peningkatan berikutnya.
   - Risiko sisa & rekomendasi operasional untuk sekolah pengguna (backup, HTTPS, update).

---

## KRITERIA SELESAI (Definition of Done)

- [ ] Tidak ada temuan Critical/High yang terbuka.
- [ ] Tidak ada string/data spesifik "SMK Tunas" tersisa di kode, seed default, dan template (kecuali di dokumentasi migrasi).
- [ ] Instalasi bersih via wizard berhasil tanpa menyentuh terminal (kecuali Composer bila memakai source, bukan rilis ZIP).
- [ ] Semua konfigurasi awal dapat diubah dari menu Pengaturan; HC tetap terkunci.
- [ ] Installer terkunci pasca-instal; tidak ada akun/password default.
- [ ] Jadwal hasil generate: **0 pelanggaran HC** (terverifikasi validator independen) pada seluruh dataset uji.
- [ ] Generate berjalan di background dengan progres & pembatalan.
- [ ] CI hijau; cakupan test target tercapai.
- [ ] Rilis ZIP + Docker + dokumentasi lengkap + LICENSE dan berkas komunitas siap dipublikasikan.

---

## FORMAT KOMUNIKASI

- Mulai dengan **Fase 1**. Setelah selesai, laporkan ringkas dan **tanyakan** keputusan-keputusan terbuka (lisensi, multi-kampus, nama produk, kebutuhan email/2FA, target hosting) sebelum Fase 2+.
- Gunakan tabel untuk temuan, diagram Mermaid untuk alur, dan checklist untuk status.
- Bersikap kritis dan jujur: jika sebuah keputusan desain di aplikasi ini buruk, katakan dengan alasan dan alternatif — jangan sekadar menyenangkan saya.
- Jangan menjalankan perintah destruktif (drop database, force push, hapus file besar) tanpa konfirmasi eksplisit.

**Mulai sekarang: jalankan FASE 1.**
