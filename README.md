# Smart School Scheduling (S3)

Aplikasi web untuk **membuat jadwal pelajaran SMK secara otomatis**. Cukup isi data guru, kelas, mata pelajaran, dan ruangan — sistem akan menyusun jadwal satu minggu penuh tanpa bentrok, lalu bisa diedit, dilihat, dan diekspor ke PDF/Excel.

Dibangun untuk kebutuhan sekolah kejuruan (multi-jurusan, lab, jam pelajaran berbeda tiap hari).

---

## Apa yang dilakukan aplikasi ini?

Bayangkan menyusun jadwal manual di papan tulis: guru A tidak boleh mengajar dua kelas di jam yang sama, kelas X tidak boleh punya dua mapel bersamaan, lab hanya dipakai satu kelas, dan setiap kelas butuh jumlah jam pelajaran tertentu per minggu. Semua itu rumit jika dikerjakan tangan.

**S3** mengerjakan pekerjaan itu dengan komputer. Sistem memakai dua langkah:

1. **CSP** — menyusun jadwal yang **pasti valid** (tidak melanggar aturan wajib).
2. **GA** — menyempurnakan jadwal agar **lebih nyaman** (jam kosong sedikit, mapel berat di pagi, dan sebagainya).

Hasilnya: jadwal siap pakai untuk guru, kurikulum, dan kepala sekolah.

---

## Aturan penjadwalan: HC dan SC

### HC — Hard Constraint (aturan wajib)

Ini **tidak boleh dilanggar**. Jika dilanggar, jadwal dianggap salah dan tidak disimpan.

| Kode | Artinya (bahasa sederhana) |
|------|----------------------------|
| **HC-1** | Satu guru tidak boleh mengajar di dua tempat pada jam yang sama |
| **HC-2** | Satu kelas tidak boleh menerima dua mata pelajaran pada jam yang sama |
| **HC-3** | Satu ruangan tidak boleh dipakai dua kelas pada jam yang sama |
| **HC-4** | Guru yang memblokir hari tertentu tidak dijadwalkan di hari itu |
| **HC-5** | Jumlah jam pelajaran per mapel per kelas harus sesuai kurikulum |
| **HC-6** | Hanya guru yang memang mengajar mapel itu yang boleh ditugaskan |
| **HC-7** | Mapel praktik di lab pool jurusan; `lab_id` = lab utama; satu lab per hari per mapel; mapel umum di ruang kelas |
| **HC-8** | Pelajaran hanya ditempatkan di jam operasional sekolah (bukan istirahat/upacara) |

### SC — Soft Constraint (aturan preferensi)

Ini **diusahakan** oleh sistem, tapi boleh dikorbankan jika tidak ada solusi yang lebih baik. Semakin tinggi bobot, semakin diprioritaskan.

| Kode | Artinya (bahasa sederhana) | Prioritas |
|------|----------------------------|-----------|
| **SC-1** | Guru jangan punya banyak jam kosong di antara mengajar | Tinggi |
| **SC-2** | Siswa jangan punya banyak jam kosong di tengah hari | Tinggi |
| **SC-3** | Mata pelajaran tersebar merata sepanjang minggu | Sedang |
| **SC-4** | Mapel berat (misal Matematika) diutamakan di jam pagi | Sedang |
| **SC-5** | Mapel ringan/fisik diutamakan di jam sore | Sedang |
| **SC-6** | Beban mengajar guru seimbang antar hari | Sedang |
| **SC-7** | Preferensi hari/jam guru | `guru_preferensi` + UI Guru + GA penalty |
| **SC-8** | Minim perpindahan ruangan (terutama ke lab) | Sedang |
| **SC-9** | Guru yang sama tetap mengajar kelas itu (kontinuitas) | Rendah |
| **SC-10** | Mapel di jam pertama tidak monoton setiap hari | Rendah |
| **SC-11** | Pemakaian lab merata antar jurusan | Sedang |

---

## Siapa yang bisa memakai aplikasi?

| Peran | Bisa apa? |
|-------|-----------|
| **Kurikulum** | Kelola semua data sekolah (guru, kelas, mapel, ruangan, jam sekolah), generate jadwal, edit jadwal manual, reset password user, lihat & ekspor jadwal |
| **Guru** | Lihat jadwal mengajar sendiri, atur preferensi hari/jam (SC-7), total jam/minggu, ekspor PDF/Excel |
| **Kepala Sekolah** | Lihat semua jadwal (per kelas/guru/ruangan), laporan jam mengajar guru, ekspor PDF/Excel |

> Tidak ada login untuk siswa/murid.

---

## Fitur utama

### Data master (Kurikulum)
- Tahun ajaran, jurusan, ruangan (termasuk lab)
- Guru + mapel yang diampu + hari yang diblokir
- Kelas + kebutuhan jam pelajaran per mapel
- Mata pelajaran (umum & kejuruan)
- Pengaturan jam sekolah per hari (timeslot: JP, istirahat, upacara, dll.)

### Penjadwalan
- Generate jadwal otomatis (CSP + GA)
- Atur parameter algoritma (ukuran populasi, generasi, dll.)
- Lihat hasil per kelas, guru, atau ruangan
- **Edit manual** — tambah/hapus/swap slot jadwal (tukar slot, mapel, atau guru) dengan validasi aturan HC
- Riwayat proses generate (log) — setiap generate membuat **history terpisah**; **publish** manual ke Guru & Kepala Sekolah (partial diizinkan dengan peringatan)
- Reset jadwal tahun ajaran

### Ekspor
- Jadwal ke **PDF** dan **Excel** (per kelas, guru, ruangan)
- Laporan jam mengajar guru (Kepala Sekolah)

### Akun & keamanan
- Login dengan email + password
- Ganti password sendiri
- Kurikulum bisa reset password user ke default

---

## Persyaratan perangkat

### Minimum (untuk dipakai di sekolah / server kecil)

| Komponen | Spesifikasi |
|----------|-------------|
| **Prosesor** | 2 core |
| **RAM** | 4 GB (8 GB disarankan saat generate jadwal) |
| **Penyimpanan** | 500 MB kosong |
| **Sistem operasi** | Windows 10/11, Linux (Ubuntu 22+), atau macOS |
| **PHP** | 8.2 atau lebih baru |
| **Database** | MySQL 8 / MariaDB 10.6+ |
| **Web server** | Apache, Nginx, atau mode bawaan PHP (`php spark serve`) |
| **Browser** | Chrome, Firefox, atau Edge versi terbaru |

### Ekstensi PHP yang harus aktif
`intl`, `mbstring`, `json`, `mysqlnd` (untuk MySQL), `curl`, `gd` atau `imagick` (untuk ekspor), `zip` (untuk Excel)

### Perangkat lunak tambahan (untuk instalasi)
- **Composer** — pengelola paket PHP ([getcomposer.org](https://getcomposer.org))
- **Git** — untuk mengunduh kode dari GitHub (opsional jika pakai file ZIP)

---

## Cara instalasi

Langkah umum sama di semua perangkat: unduh kode → pasang dependensi → atur database → jalankan perintah setup.

### Opsi A — Windows (paling mudah untuk pemula)

Disarankan memakai **Laragon** atau **XAMPP** (sudah berisi Apache, MySQL, PHP).

1. **Pasang Laragon/XAMPP** dan pastikan PHP ≥ 8.2 aktif.
2. **Pasang Composer** dari [getcomposer.org](https://getcomposer.org/download/).
3. **Unduh proyek** — clone dengan Git atau unduh ZIP dari GitHub, lalu ekstrak ke folder misalnya `C:\laragon\www\smart-school-scheduling`.
4. **Buka terminal** di folder proyek, jalankan:
   ```bash
   composer install
   ```
5. **Buat file pengaturan** `.env` di folder utama proyek (salin isi dari `.env` di komputer pengembang, atau buat baru) dan sesuaikan:
   ```ini
   app.baseURL = 'http://localhost/smart-school-scheduling/public/'
   database.default.hostname = localhost
   database.default.database = smart_school_scheduling
   database.default.username = root
   database.default.password =
   ```
   Sesuaikan `baseURL` dengan alamat situs Anda di Laragon/XAMPP.
6. **Buat database kosong** lewat phpMyAdmin: buat database bernama `smart_school_scheduling`.
7. **Setup tabel & data awal:**
   ```bash
   php spark migrate
   php spark db:seed
   ```
8. **Buka browser** ke alamat `baseURL` yang Anda atur (contoh: `http://smart-school-scheduling.test` jika pakai Laragon).

### Opsi B — Linux (Ubuntu/Debian)

```bash
# Pasang PHP, MySQL, Composer (contoh Ubuntu)
sudo apt update
sudo apt install php8.2 php8.2-mysql php8.2-mbstring php8.2-intl php8.2-curl php8.2-zip php8.2-gd mysql-server composer git

# Clone proyek
git clone https://github.com/sk8dvlpr/smart-school-scheduling.git
cd smart-school-scheduling

composer install

# Buat file .env (salin dari komputer lain atau buat baru), lalu edit pengaturan database

# Buat database
sudo mysql -e "CREATE DATABASE smart_school_scheduling CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"

php spark migrate
php spark db:seed
```

Jalankan dengan web server Apache/Nginx (arahkan document root ke folder **`public`**) atau untuk uji cepat:

```bash
php spark serve
```

Lalu buka `http://localhost:8080`.

### Opsi C — macOS

```bash
# Dengan Homebrew
brew install php@8.2 composer mysql

git clone https://github.com/sk8dvlpr/smart-school-scheduling.git
cd smart-school-scheduling
composer install
# Buat file .env, lalu edit pengaturan database dan baseURL

mysql -u root -e "CREATE DATABASE smart_school_scheduling CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"
php spark migrate
php spark db:seed
php spark serve
```

---

## Cara menjalankan sehari-hari

### Mode pengembangan / uji di laptop
```bash
cd smart-school-scheduling
php spark serve
```
Buka browser: `http://localhost:8080`

### Mode produksi (server sekolah)
- Arahkan web server ke folder **`public`** (bukan folder utama proyek).
- Pastikan MySQL berjalan.
- Folder `writable` harus bisa ditulis oleh web server (izin folder).

### Login pertama kali

Setelah `db:seed`, gunakan akun berikut (data dari instalasi default):

| Peran | Email | Password default |
|-------|-------|------------------|
| Kurikulum | `admin@smktunas.sch.id` | `password123` |
| Kepala Sekolah | `kepsek@smktunas.sch.id` | `password123` |

> Segera ganti password setelah login pertama di lingkungan produksi.

### Alur kerja singkat (Kurikulum)

1. Login sebagai Kurikulum.
2. Pastikan data master lengkap (tahun ajaran aktif, guru, kelas, mapel, jam sekolah).
3. Menu **Jadwal** → **Generate** untuk membuat jadwal otomatis.
4. **Publish** jadwal dari halaman log/history agar Guru & Kepala Sekolah dapat melihat hasil.
5. Periksa hasil per kelas/guru; edit manual (tambah/hapus/swap) jika perlu.
6. Ekspor PDF/Excel untuk dibagikan.

---

## Struktur folder penting

| Folder / file | Fungsi |
|---------------|--------|
| `public/` | Titik masuk website — ini yang diarahkan web server |
| `app/` | Logika aplikasi (kode utama) |
| `docs/` | Dokumentasi & dump database awal |
| `docs/database/smart_school_scheduling.sql` | Data contoh untuk instalasi baru |
| `.env` | Pengaturan database & URL (buat manual di folder utama) |
| `writable/` | Log, cache, upload — harus bisa ditulis |

---

## Pemecahan masalah umum

| Masalah | Solusi |
|---------|--------|
| Halaman putih / error 500 | Cek `writable/logs/`, pastikan ekstensi PHP lengkap |
| Tidak bisa konek database | Periksa username/password di `.env`, pastikan MySQL jalan |
| CSS/JS tidak muncul | Periksa `app.baseURL` di `.env` sesuai alamat browser |
| Generate jadwal lama | Normal untuk banyak kelas; naikkan RAM atau kurangi parameter populasi di config jadwal |
| `migrate` gagal | Pastikan database kosong sudah dibuat, user MySQL punya hak CREATE TABLE |

---

## Dokumentasi teknis

- [PRD lengkap](docs/PRD.md) — spesifikasi produk
- [Referensi HC & SC](docs/CSP-GA-Constraints-Parameters.md) — detail algoritma

---

## Lisensi

Proyek ini memakai kerangka [CodeIgniter 4](https://codeigniter.com) (MIT). Paket pihak ketiga: DomPDF, PhpSpreadsheet — lihat `composer.json`.
