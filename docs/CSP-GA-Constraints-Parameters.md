# Constraint & Parameter Referensi — Algoritma CSP + GA
## Smart School Scheduling System (SMK Multi-Jurusan)

> Dokumen referensi untuk implementasi mesin penjadwalan hybrid **Constraint Satisfaction Problem (CSP)** + **Genetic Algorithm (GA)**. Bisa langsung dipakai sebagai acuan teknis oleh developer maupun AI coding agent (Claude Code) saat membangun modul scheduler.

---

## 1. Filosofi Pembagian Constraint

| Aspek | Hard Constraint (HC) | Soft Constraint (SC) |
|---|---|---|
| Peran | Menjamin solusi **valid/legal** | Mencari solusi **paling optimal** |
| Tahap eksekusi | CSP — constraint filtering, generation, repair | GA — fitness function |
| Pelanggaran | Solusi **ditolak** (infeasible) | Solusi **tetap diterima**, hanya turun skor |
| Sifat | Mutlak, tidak bisa ditawar | Preferensi, berbobot (weighted) |

**Prinsip inti:** HC divalidasi saat inisialisasi kromosom & operator *repair*, sehingga populasi GA sudah 100% legal sejak awal. GA lalu murni fokus mengoptimalkan kualitas jadwal lewat SC. Ini membuat konvergensi jauh lebih cepat dibanding memasukkan HC sebagai penalti besar di fitness function.

---

## 2. Hard Constraint (HC)

| Kode | Constraint | Deskripsi | Validasi di |
|---|---|---|---|
| HC-1 | Konflik guru | 1 guru tidak boleh mengajar 2 kelas/mapel di slot waktu yang sama | Inisialisasi + repair |
| HC-2 | Konflik kelas (rombel) | 1 kelas tidak boleh menerima 2 mapel di slot yang sama | Inisialisasi + repair |
| HC-3 | Konflik ruangan | 1 ruangan tidak dipakai 2 kelas/mapel sekaligus | Inisialisasi + repair |
| HC-4 | Ketersediaan guru | Guru hanya dijadwalkan di slot sesuai ketersediaannya | Domain filtering (CSP) |
| HC-5 | Jumlah jam per mapel sesuai kurikulum | Total JP/minggu per mapel per kelas harus persis sesuai struktur kurikulum | Constraint propagation |
| HC-6 | Kesesuaian guru–mapel (SK Pembagian Tugas) | Hanya guru yang ditugaskan yang boleh mengajar mapel itu di kelas tsb | Domain filtering (CSP) |
| HC-7 | Kesesuaian ruang–mapel/jurusan | Mapel produktif/praktik di lab pool jurusan; lab utama di `kelas_mapel.lab_id`; satu lab per hari per kelas_mapel | Domain filtering (CSP) |
| HC-8 | Jam operasional sekolah | Semua slot berada dalam rentang jam sekolah resmi | Domain filtering (CSP) |

---

## 3. Soft Constraint (SC) — dengan Bobot Rekomendasi

Bobot menggunakan skala **1–10** (semakin tinggi = semakin diprioritaskan GA). Nilai ini adalah titik awal yang **disarankan untuk dikalibrasi ulang** setelah beberapa kali uji coba (lihat §6).

| Kode | Constraint | Bobot (Wi) | Rasional |
|---|---|---|---|
| SC-1 | Minim jam kosong (gap) guru | **9** | Sangat mempengaruhi efisiensi operasional guru |
| SC-2 | Minim jam kosong siswa | **9** | Berpengaruh langsung ke kualitas belajar |
| SC-3 | Distribusi mapel merata dalam seminggu | **7** | Menghindari penumpukan mapel di hari tertentu |
| SC-4 | Mapel berat (kognitif tinggi) di jam awal | **6** | Optimalisasi konsentrasi siswa |
| SC-5 | Mapel ringan/fisik di jam akhir | **5** | Kenyamanan transisi antar mapel |
| SC-6 | Beban mengajar guru seimbang per hari | **7** | Mencegah kelelahan & ketimpangan beban |
| SC-7 | Preferensi hari/jam guru (non-mengikat) | **4** | Soft preference, bukan aturan formal |
| SC-8 | Minim perpindahan ruang berurutan | **5** | Efisiensi perpindahan fisik guru/siswa |
| SC-9 | Kontinuitas guru per kelas | **4** | Kenyamanan psikologis siswa |
| SC-10 | Rotasi/tidak monoton mapel jam pertama | **3** | Variasi, dampak kecil ke kualitas belajar |
| SC-11 | Load balancing lab/bengkel antar jurusan | **6** | Mencegah kontensi alat/daya antar jurusan |

> Total bobot tidak harus = 100; yang penting adalah **rasio relatif** antar constraint. Normalisasi dilakukan di level fungsi penalti, bukan di level bobot mentah.

---

## 4. Fitness Function

### 4.1 Struktur umum

```
Fitness(kromosom) = 1 / (1 + Σ (Wi × Penalty_i(kromosom)))
```

atau versi maksimasi langsung:

```
Fitness(kromosom) = Σ (Wi × Skor_i(kromosom))
```

Di mana:
- `Penalty_i` = jumlah pelanggaran SC-i yang dinormalisasi ke rentang **0–1**
- `Wi` = bobot dari tabel §3
- HC **tidak** masuk ke formula ini — kromosom yang melanggar HC tidak pernah masuk populasi (lihat §5.2)

### 4.2 Contoh normalisasi penalti

```
Penalty_i = (jumlah_pelanggaran_aktual) / (jumlah_pelanggaran_maksimum_mungkin)
```

Ini memastikan tidak ada satu constraint yang mendominasi total fitness hanya karena skalanya lebih besar (mis. jumlah slot kosong vs jumlah rotasi mapel).

---

## 5. Parameter Algoritma

### 5.1 Parameter CSP (Fase Konstruksi & Filtering)

| Parameter | Nilai Rekomendasi | Catatan |
|---|---|---|
| Metode konsistensi | **Arc Consistency (AC-3)** | Untuk mempersempit domain sebelum backtracking |
| Algoritma pencarian dasar | **Backtracking + Forward Checking** | Baseline sebelum GA mengambil alih optimasi |
| Variable ordering heuristic | **MRV (Minimum Remaining Values)** | Prioritaskan variabel dengan domain tersempit dulu (mis. mapel dgn guru terbatas) |
| Value ordering heuristic | **LCV (Least Constraining Value)** | Pilih nilai yang paling sedikit membatasi variabel lain |
| Repair operator | **Local repair (min-conflict)** setelah crossover/mutasi GA | Memperbaiki kromosom yang melanggar HC akibat operator genetik |
| Domain awal per variabel | Hasil filtering HC-4, HC-6, HC-7, HC-8 | Domain sudah tersaring sebelum masuk GA |

### 5.2 Parameter GA (Fase Optimasi)

| Parameter | Nilai Rekomendasi | Rentang Uji Coba | Catatan |
|---|---|---|---|
| **Ukuran populasi** | 100 | 50 – 300 | Skala naik untuk dataset besar (banyak kelas/guru) |
| **Jumlah generasi maksimum** | 500 | 200 – 1000 | Kombinasikan dengan kriteria stagnasi |
| **Metode seleksi** | Tournament Selection (k = 5) | k = 3 – 7 | Lebih stabil dibanding roulette wheel untuk masalah scheduling |
| **Crossover rate (Pc)** | 0.8 | 0.6 – 0.9 | Crossover tinggi karena representasi jadwal butuh eksplorasi kombinasi luas |
| **Metode crossover** | Order Crossover (OX) atau Uniform Crossover per-slot | — | OX menjaga urutan gen dalam blok crossover |
| **Mutation rate (Pm)** | 0.05 – 0.1 | 0.01 – 0.2 | Mutasi lebih tinggi di awal, menurun seiring generasi (adaptive mutation) |
| **Metode mutasi** | Swap mutation (tukar slot) + repair otomatis | — | Setelah mutasi, wajib validasi ulang HC |
| **Elitism** | 5–10% populasi terbaik dipertahankan | 2 – 15% | Mencegah solusi terbaik hilang antar generasi |
| **Kriteria berhenti (termination)** | Konvergensi: tidak ada perbaikan fitness terbaik selama 30–50 generasi berturut-turut, ATAU tercapai generasi maksimum | — | Kombinasi keduanya (early stopping) |
| **Representasi kromosom** | Matriks [kelas × slot_waktu] → berisi (mapel, guru, ruangan) | — | 1 kromosom = 1 jadwal sekolah penuh |
| **Populasi awal** | 100% dihasilkan dari CSP constructive solver (bukan random) | — | Menjamin populasi awal sudah valid HC |
| **Adaptive parameter (opsional)** | Mutation rate naik otomatis jika fitness stagnan > 20 generasi | — | Mencegah local optimum |

### 5.3 Contoh Konfigurasi JSON (siap dipakai di kode)

```json
{
  "csp": {
    "consistency_method": "AC-3",
    "search_algorithm": "backtracking_forward_checking",
    "variable_ordering": "MRV",
    "value_ordering": "LCV",
    "repair_strategy": "min_conflict"
  },
  "ga": {
    "population_size": 100,
    "max_generations": 500,
    "selection_method": "tournament",
    "tournament_size": 5,
    "crossover_rate": 0.8,
    "crossover_method": "order_crossover",
    "mutation_rate": 0.08,
    "mutation_method": "swap_with_repair",
    "elitism_ratio": 0.1,
    "stagnation_limit": 40,
    "adaptive_mutation": true,
    "adaptive_mutation_trigger_generations": 20,
    "adaptive_mutation_increment": 0.02
  },
  "soft_constraint_weights": {
    "SC-1_teacher_gap": 9,
    "SC-2_student_gap": 9,
    "SC-3_subject_distribution": 7,
    "SC-4_heavy_subject_morning": 6,
    "SC-5_light_subject_afternoon": 5,
    "SC-6_teacher_load_balance": 7,
    "SC-7_teacher_preference": 4,
    "SC-8_room_transition": 5,
    "SC-9_teacher_continuity": 4,
    "SC-10_first_slot_rotation": 3,
    "SC-11_lab_load_balance": 6,
    "sc_lab_preference": 5
  }
}
```

---

## 6. Rekomendasi Proses Kalibrasi

1. **Baseline run** dengan parameter default di atas.
2. Analisis distribusi pelanggaran SC per kategori — kalau satu SC selalu paling tinggi pelanggarannya, naikkan bobotnya secara bertahap (±1–2 poin) lalu re-run.
3. Lakukan **grid search kecil** untuk `mutation_rate` (0.01, 0.05, 0.1, 0.15) × `population_size` (50, 100, 200) untuk menemukan titik konvergensi tercepat dengan fitness terbaik.
4. Simpan histori fitness per generasi (best, average, worst) untuk mendeteksi *premature convergence* — jika average mendekati best terlalu cepat, naikkan mutation rate.
5. Untuk dataset SMK dengan banyak jurusan (lab/bengkel terbatas), naikkan bobot **SC-13** sebagai prioritas kedua setelah SC-1/SC-2.

---

## 7. Skema Integrasi ke Backend (CodeIgniter 4 / MySQL)

Constraint di atas idealnya dipetakan ke tabel-tabel berikut (sudah selaras dengan skema Smart School Scheduling sebelumnya):

- `hard_constraints_log` — mencatat pelanggaran HC yang berhasil dihindari saat generate (untuk audit trail)
- `soft_constraint_weights` — tabel konfigurasi bobot SC yang bisa diubah admin tanpa redeploy kode
- `ga_run_history` — menyimpan parameter run (population size, generations, dsb) beserta fitness score akhir, untuk keperluan reproducibility dan perbandingan antar run

---

*Dokumen ini adalah materi referensi teknis, bukan kode final. Nilai parameter perlu dikalibrasi ulang sesuai skala data riil (jumlah kelas, guru, ruangan) di sekolah target.*
