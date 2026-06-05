# PRD — AsuhTrack: Full Rewrite ke Custom MVC + HTMX
**Versi:** 3.1.0
**Status:** AKTIF — Panduan AI Coding Assistant (Google Antigravity / Gemini / Claude)
**Scope:** MASTER PRD — Fondasi sampai seluruh modul fitur
**Changelog v3.1.0:** Seluruh skema database disesuaikan dengan `database.sql` aktual produksi. Koreksi nama kolom kritis, logika bisnis reward/pelanggaran bahasa, session keys lengkap, AuthHelper diperbarui ke implementasi berbasis session array, tambah tabel `roles`+`role_permissions`, tambah fitur yang hilang (impor data, profil user, 4 halaman rekap), koreksi password hashing (bcrypt dual-verify).

---

## INSTRUKSI WAJIB UNTUK AI — BACA SEBELUM MENULIS SATU BARIS KOD PUN

### Konteks: Ini Rewrite, Bukan Greenfield

Aplikasi ini **sudah berjalan** di produksi dalam bentuk PHP prosedural. Ada data santri, data pelanggaran, dan user aktif. Tugasmu adalah **membangun ulang arsitekturnya** menjadi MVC — bukan membuat fitur baru.

Yang **TIDAK BOLEH berubah:**
- Struktur dan nama semua tabel database yang sudah ada
- Logika bisnis (kalkulasi poin, alur arsip, sistem izin per-user, dsb.)
- Semua data yang sudah ada di database tetap valid setelah rewrite
- Style / tampilan UI yang sudah ada di `assets/css/style.css`

Yang **BOLEH dan HARUS berubah:**
- Struktur file dan folder (dari prosedural ke MVC)
- Cara routing (dari URL langsung ke file, ke Router sentral)
- Cara render HTML (dari echo/include acak, ke sistem View terstruktur)
- Duplikasi kode (koneksi DB di tiap file, cek session berulang, dsb.)

### Prinsip: Adaptive Consistency

Dokumen ini tidak sempurna. Jika ada celah:

1. **JANGAN berhenti** untuk hal yang jawabannya sudah jelas dari konteks atau dari kode lama
2. **IKUTI POLA** yang sudah terbentuk — satu Controller punya pola method tertentu, semua Controller baru mengikutinya
3. **BUAT file yang diperlukan** meski tidak disebut eksplisit, selama konsisten dengan arsitektur MVC di dokumen ini
4. **CATAT asumsi** di komentar kode: `// [ASUMSI]: penjelasan`
5. **KONSISTENSI** > solusi lebih pintar tapi tidak seragam
6. **Kode lama = referensi logika bisnis yang valid.** Jika ada logika di file prosedural lama yang tidak disebut di PRD ini, ikuti logika tersebut

### Cara Membaca Dokumen Ini

Dokumen dibagi menjadi **Phase** besar dan **Sub-Phase** detail. Saat menerima perintah:
> "Kerjakan Sub-Phase 2.1"

Kerjakan **hanya** sub-phase itu. Boleh buat stub/placeholder untuk koneksi ke sub-phase berikutnya, tapi jangan implementasi logika bisnis yang belum waktunya. Selesaikan checklist sebelum lanjut.

---

### Konvensi Kode — Tidak Boleh Dilanggar

| Aturan | Detail |
|---|---|
| PHP Version | >= 8.1, `declare(strict_types=1)` wajib di semua file PHP |
| Namespace | PSR-4: `App\Core`, `App\Controllers`, `App\Models`, `App\Helpers` |
| SQL | Hanya di Model, wajib prepared statement, zero string concat di query |
| HTML | Hanya di View (`views/`), zero `echo` HTML di Controller |
| Logika bisnis | Hanya di Controller, zero logika di View |
| Type hints | Wajib di semua parameter dan return type |
| Method length | Maksimal ~40 baris, pecah ke private method jika lebih |
| Komentar | Bahasa Indonesia |
| Input sanitasi | Prepared statement untuk SQL, `h()` untuk semua output ke HTML |
| CSRF | Wajib untuk semua form POST |
| Error handling | Wajib try-catch di semua operasi DB dan file I/O |
| Nama method | camelCase, deskriptif — `getByBidang()` bukan `getData()` |

### Konvensi Penamaan

| Konteks | Pola |
|---|---|
| Controller | `NamaController.php` (PascalCase) |
| Model | `NamaModel.php` (PascalCase) |
| View halaman penuh | `views/pages/modul/index.php`, `form.php`, `detail.php` |
| View partial HTMX | `views/pages/modul/_tabel.php`, `_baris.php` (awalan `_`) |
| Komponen reusable | `views/components/nama.php` (snake_case) |

---

## KONTEKS & LATAR BELAKANG

AsuhTrack adalah sistem manajemen kedisiplinan santri untuk Pesantren Syathiby, Bogor. Dipakai oleh ustadz, musyrif (wali asrama kamar), dan admin untuk mencatat pelanggaran, reward prestasi, eksekusi tindakan kebersihan, mencetak rapot evaluasi, dan menganalisis tren kedisiplinan santri secara periodik.

**Masalah sistem lama (PHP prosedural):**
- SQL, logika bisnis, dan HTML tercampur dalam satu file
- Tidak ada routing — URL langsung ke path file fisik
- Setiap halaman reload penuh saat pindah menu
- Koneksi DB, cek session, dan guard izin diulang di setiap file
- Tidak ada pola konsisten — sulit dipelihara dan dikembangkan

**Solusi:** Rewrite total ke Custom MVC PHP 8.1 + HTMX — SPA experience tanpa framework JS berat.

---

## STACK TEKNOLOGI

| Layer | Teknologi | Keterangan |
|---|---|---|
| Backend | PHP >= 8.1 | `match`, named args, readonly props |
| Database Driver | **PDO MySQL** | Prepared statement native. **Catatan:** kode lama memakai MySQLi (`mysqli`). MVC baru menggunakan PDO untuk konsistensi dan keamanan lebih baik. |
| Frontend interaction | HTMX 2.x | Partial HTML swap tanpa JS framework |
| Grafik | Chart.js | Data dari JSON endpoint |
| Alert/Confirm | SweetAlert2 | Gantikan `alert()` dan `confirm()` browser |
| PDF | DomPDF | Generate rapot santri |
| Excel | PhpSpreadsheet | Export data ke `.xlsx` |
| Dependency | Composer 2.x | PSR-4 autoloading |
| Web Server | Apache / Laragon | `.htaccess` mod_rewrite |
| PWA | `manifest.json` + `sw.js` | Install ke Home Screen HP |

> **Keputusan PDO vs MySQLi:** Seluruh kode lama menggunakan MySQLi (prosedural/OOP). MVC baru **sengaja beralih ke PDO** karena: named parameters, konsistensi interface, lebih mudah di-mock untuk unit testing. Logika bisnis dari kode lama tetap dipertahankan 100% — hanya driver koneksi yang berubah.

---

## STRUKTUR FOLDER TARGET (MVC)

```
rekap-mukholif/
│
├── app/                              ← SEMUA KODE BACKEND
│   ├── Core/
│   │   ├── Database.php             ← Singleton koneksi PDO
│   │   ├── Model.php                ← Base class semua Model
│   │   ├── Controller.php           ← Base class semua Controller
│   │   └── Router.php               ← Dispatch URL ke Controller::method
│   │
│   ├── Controllers/
│   │   ├── AuthController.php       ← Login, logout
│   │   ├── DashboardController.php  ← Halaman utama setelah login
│   │   ├── SantriController.php     ← CRUD santri + bulk ops
│   │   ├── JenisPelanggaranController.php ← Katalog jenis & poin
│   │   ├── PelanggaranController.php      ← Input semua 5 bidang
│   │   ├── PelanggaranKebersihanController.php ← Input kebersihan kamar
│   │   ├── EksekusiController.php         ← Tindak lanjut kebersihan
│   │   ├── RewardController.php           ← Input reward santri
│   │   ├── JenisRewardController.php      ← Katalog jenis reward
│   │   ├── RekapController.php            ← Dashboard analitik & grafik
│   │   ├── RapotController.php            ← Generator rapot PDF/PNG
│   │   ├── ArsipController.php            ← Data arsip periode lalu
│   │   ├── ExportController.php           ← Export Excel
│   │   └── PengaturanController.php       ← Users, izin, periode, dll
│   │
│   ├── Models/
│   │   ├── UserModel.php
│   │   ├── SantriModel.php
│   │   ├── JenisPelanggaranModel.php
│   │   ├── PelanggaranModel.php
│   │   ├── PelanggaranKebersihanModel.php
│   │   ├── EksekusiKebersihanModel.php
│   │   ├── RewardModel.php              ← Tabel: daftar_reward
│   │   ├── JenisRewardModel.php         ← Tabel: jenis_reward
│   │   ├── RoleModel.php                ← Tabel: roles + role_permissions
│   │   ├── RekapModel.php
│   │   ├── RapotModel.php               ← Tabel: rapot_kepengasuhan
│   │   ├── ArsipModel.php               ← Tabel: arsip + arsip_data_*
│   │   ├── LogAktifitasModel.php        ← Tabel: log_aktifitas
│   │   ├── LogBahasaModel.php           ← Tabel: log_bahasa
│   │   ├── LogHistoryModel.php          ← Tabel: log_history
│   │   ├── LogResetPoinModel.php        ← Tabel: log_reset_poin
│   │   ├── PermissionModel.php          ← Tabel: permissions + user_permissions
│   │   └── PengaturanModel.php          ← Tabel: pengaturan
│   │
│   ├── Helpers/
│   │   ├── AuthHelper.php            ← Cek izin per-user (session-based)
│   │   ├── FormatHelper.php          ← h(), tanggalIndo(), format poin, dll
│   │   └── ExportHelper.php          ← Wrapper PhpSpreadsheet
│   │
│   └── routes.php                    ← Semua definisi route
│
├── public/                           ← DOCUMENT ROOT (Apache mengarah ke sini)
│   ├── index.php                     ← Entry point tunggal
│   ├── .htaccess                     ← URL rewriting
│   ├── manifest.json                 ← PWA manifest
│   ├── sw.js                         ← Service Worker
│   └── assets/
│       ├── css/
│       │   ├── style.css             ← FILE INI TIDAK BOLEH DIUBAH ISINYA
│       │   └── htmx-transitions.css  ← Animasi HTMX swap (baru, dibuat di Sub-Phase 1.5)
│       ├── js/
│       │   ├── htmx.min.js
│       │   ├── sweetalert.min.js
│       │   ├── chart.min.js
│       │   └── app.js               ← HTMX config global + CSRF inject
│       └── img/
│           ├── logo_aplikasi.png
│           ├── logo_favicon.png
│           └── Kop Syathiby.jpg
│
├── views/
│   ├── layouts/
│   │   ├── main.php                  ← Shell SPA (satu-satunya file yang render <html> penuh)
│   │   ├── header.php                ← Konten <head>: meta, CSS, title
│   │   ├── footer.php                ← Script JS di bawah </body>
│   │   └── sidebar.php              ← Navigasi kiri dengan HTMX hx-get links
│   │
│   ├── pages/
│   │   ├── auth/
│   │   │   └── login.php
│   │   ├── dashboard/
│   │   │   └── index.php
│   │   ├── santri/
│   │   │   ├── index.php
│   │   │   ├── form.php             ← Dipakai untuk create & edit
│   │   │   ├── detail.php
│   │   │   ├── bulk_tambah.php
│   │   │   ├── bulk_edit.php
│   │   │   └── _tabel.php           ← Partial HTMX: hanya tabel + paginasi
│   │   ├── jenis-pelanggaran/
│   │   │   ├── index.php
│   │   │   ├── form.php
│   │   │   └── bulk_edit.php
│   │   ├── pelanggaran/
│   │   │   ├── index.php            ← Dashboard menu 5 bidang
│   │   │   ├── form_input.php       ← Form input bulk (banyak santri sekaligus)
│   │   │   ├── rekap_tabel.php      ← Rekap per bidang
│   │   │   └── detail_santri.php    ← Riwayat pelanggaran satu santri
│   │   ├── pelanggaran-kebersihan/
│   │   │   ├── index.php
│   │   │   ├── form_input.php
│   │   │   └── rekap_tabel.php
│   │   ├── eksekusi/
│   │   │   └── index.php
│   │   ├── reward/
│   │   │   ├── index.php
│   │   │   ├── form_input.php
│   │   │   └── history.php
│   │   ├── jenis-reward/
│   │   │   ├── index.php
│   │   │   ├── form.php
│   │   │   ├── bulk_edit.php
│   │   │   └── bulk_delete.php
│   │   ├── rekap/
│   │   │   ├── index.php
│   │   │   ├── chart.php
│   │   │   ├── pelanggaran_umum.php
│   │   │   ├── detail_pelanggaran.php
│   │   │   ├── kebersihan.php
│   │   │   ├── detail_kebersihan.php
│   │   │   ├── keterlambatan.php
│   │   │   ├── tren_pelanggaran.php
│   │   │   ├── santri_teladan.php
│   │   │   ├── umum.php
│   │   │   ├── karakter.php           ← [WAJIB] Rekap karakter per santri
│   │   │   ├── detail_karakter.php    ← [WAJIB] Detail aspek karakter satu santri
│   │   │   ├── peringkat_kamar.php    ← [WAJIB] Peringkat kebersihan per kamar
│   │   │   └── detail_kamar.php       ← [WAJIB] Detail satu kamar
│   │   ├── rapot/
│   │   │   ├── index.php
│   │   │   ├── preview.php
│   │   │   └── template.php         ← Kanvas desain rapot — struktur HTML dijaga persis
│   │   ├── arsip/
│   │   │   ├── index.php
│   │   │   ├── create.php           ← Form buat arsip baru
│   │   │   ├── view.php             ← Ringkasan / landing satu arsip (summary + link sub-halaman)
│   │   │   ├── pelanggaran.php
│   │   │   ├── kebersihan.php
│   │   │   ├── santri.php           ← [WAJIB] Daftar snapshot santri saat arsip dibuat
│   │   │   └── detail_pelanggaran.php
│   │   └── pengaturan/
│   │       ├── index.php
│   │       ├── users.php
│   │       ├── form_user.php
│   │       ├── profil.php           ← [WAJIB] Edit profil/password user yang sedang login
│   │       ├── izin.php             ← Manajemen izin per-user (bukan per-role)
│   │       ├── periode.php
│   │       ├── reset_poin.php
│   │       ├── log_aktifitas.php
│   │       ├── backup_restore.php
│   │       └── impor_data.php       ← [WAJIB] Import santri dari Excel/CSV
│   │
│   ├── components/
│   │   ├── alert.php                ← Flash message sukses/gagal/warning
│   │   ├── loading.php              ← HTMX loading indicator
│   │   ├── pagination.php           ← Komponen paginasi tabel
│   │   └── search_live.php          ← Input live search HTMX
│   │
│   └── errors/
│       ├── 403.php
│       ├── 404.php
│       ├── 500.php
│       └── offline.php              ← PWA offline fallback
│
├── uploads/
│   ├── foto_santri/
│   └── rapot_generated/
│
├── .env
├── .env.example
├── .gitignore
├── composer.json
├── composer.lock
└── vendor/
```

---

## DATABASE — SKEMA AKTUAL (TIDAK BOLEH DIUBAH STRUKTURNYA)

> Ini adalah skema database yang sudah berjalan di produksi. Model harus mengikuti nama tabel dan kolom **persis** seperti di bawah ini. **Jangan ganti nama kolom, jangan tambah kolom baru tanpa instruksi eksplisit.**

### Tabel: `users`
```sql
CREATE TABLE `users` (
  `id`           INT(11) AUTO_INCREMENT PRIMARY KEY,
  `username`     VARCHAR(50) NOT NULL UNIQUE,
  `nama_lengkap` VARCHAR(100) DEFAULT NULL,
  `password`     VARCHAR(255) NOT NULL,
  `role`         VARCHAR(50) DEFAULT 'user'
);
```
> Kolom `role` berisi nilai string bebas: `'admin'`, `'user'`, `'musyrif'`, `'bahasa'`, `'kesantrian'`, `'akademik'`, `'pgd'`, `'admin1'`. Tidak pakai ENUM. Role di sini hanya label — izin akses sebenarnya dikontrol oleh tabel `permissions` + `user_permissions`. **Exception:** Role `'admin'` (case-insensitive) adalah bypass global — semua permission check dianggap `true` tanpa query ke DB.

---

### Tabel: `roles`
```sql
CREATE TABLE `roles` (
  `id`        VARCHAR(50) NOT NULL PRIMARY KEY,   -- contoh: 'bahasa', 'kesantrian', 'musyrif'
  `role_name` VARCHAR(100) NOT NULL,               -- nama display: 'Bagian Bahasa', dll
  -- kolom lain cek dari DB aktual
);
```
> Tabel ini mendaftarkan role yang valid di sistem. Dipakai sebagai referensi saat admin membuat user baru — sistem akan otomatis copy izin default dari `role_permissions`.

---

### Tabel: `role_permissions`
```sql
CREATE TABLE `role_permissions` (
  `role`          VARCHAR(50) NOT NULL,   -- FK ke roles.id
  `permission_id` INT(11) NOT NULL,       -- FK ke permissions.id
  PRIMARY KEY (`role`, `permission_id`)
);
```
> **Fungsi kritis:** Saat admin membuat user baru atau mengubah role user, sistem otomatis menyalin izin default dari tabel ini ke `user_permissions`. Implementasi di `PengaturanController::userStore()` dan `userUpdate()`:
> ```php
> // Setelah simpan/update user, salin izin dari template role:
> INSERT INTO user_permissions (user_id, permission_id)
> SELECT :userId, permission_id FROM role_permissions WHERE role = :role
> ```

---

### Tabel: `permissions`
```sql
CREATE TABLE `permissions` (
  `id`        INT(11) AUTO_INCREMENT PRIMARY KEY,
  `nama_izin` VARCHAR(100) NOT NULL UNIQUE COMMENT 'Kunci unik izin',
  `deskripsi` VARCHAR(255) DEFAULT NULL,
  `grup`      VARCHAR(50) DEFAULT 'Lainnya' COMMENT 'Pengelompokan di UI admin'
);
```

### Tabel: `user_permissions`
```sql
CREATE TABLE `user_permissions` (
  `user_id`       INT(11) NOT NULL,
  `permission_id` INT(11) NOT NULL,
  PRIMARY KEY (`user_id`, `permission_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE
);
```
> **PENTING:** Sistem izin adalah **per-user**, bukan per-role. Setiap user punya set izin tersendiri yang diatur admin melalui halaman Pengaturan > Izin Akses. Meskipun ada `role_permissions` sebagai template awal, admin bisa mengubah izin per-user secara bebas setelah user dibuat.

---

### Daftar `nama_izin` yang Ada di Database (Jangan Ubah)

| ID | nama_izin | Grup |
|---|---|---|
| 1 | `santri_view` | Manajemen Santri |
| 2 | `santri_create` | Manajemen Santri |
| 3 | `santri_edit` | Manajemen Santri |
| 4 | `santri_delete` | Manajemen Santri |
| 6 | `pelanggaran_bahasa_input` | Input Pelanggaran |
| 7 | `pelanggaran_diniyyah_input` | Input Pelanggaran |
| 8 | `pelanggaran_kesantrian_input` | Input Pelanggaran |
| 9 | `pelanggaran_pengabdian_input` | Input Pelanggaran |
| 10 | `rekap_pelanggaran_umum` | Rekap |
| 11 | `rekap_kebersihan` | Rekap |
| 12 | `rekap_keterlambatan` | Rekap |
| 13 | `rekap_view_statistik` | Rekap |
| 14 | `eksekusi_manage` | Eksekusi |
| 17 | `user_manage` | Pengaturan |
| 19 | `periode_aktif_manage` | Pengaturan |
| 20 | `reset_poin_manage` | Pengaturan |
| 21 | `pelanggaran_tahfidz_input` | Input Pelanggaran |
| 22 | `rekap_view_tahfidz` | Rekap |
| 23 | `rekap_view_bahasa` | Rekap |
| 24 | `rekap_view_diniyyah` | Rekap |
| 25 | `export_laporan` | Laporan |
| 26 | `izin_manage` | Pengaturan |
| 27 | `jenis_pelanggaran_view` | Manajemen Pelanggaran |
| 28 | `jenis_pelanggaran_create` | Manajemen Pelanggaran |
| 29 | `jenis_pelanggaran_edit` | Manajemen Pelanggaran |
| 30 | `jenis_pelanggaran_delete` | Manajemen Pelanggaran |
| 32 | `history_manage` | Pengaturan |
| 33 | `arsip_view` | Arsip |
| 34 | `arsip_create` | Arsip |
| 35 | `arsip_delete` | Arsip |
| 36 | `arsip_export` | Arsip |
| 37 | `rapot_create` | Rapot |
| 38 | `rapot_view` | Rapot |
| 39 | `rapot_cetak` | Rapot |
| 40 | `rapot_delete` | Rapot |
| 41 | `rekap_view_kesantrian` | Rekap |
| 42 | `jenis_reward_view` | Reward |
| 43 | `jenis_reward_create` | Reward |
| 44 | `jenis_reward_edit` | Reward |
| 45 | `jenis_reward_delete` | Reward |
| 46 | `reward_input` | Reward |
| 47 | `reward_history` | Reward |
| 48 | `rekap_detail_santri` | Rekap |
| 49 | `backup_restore_manage` | Pengaturan |
| 50 | `rekap_santri_teladan` | Rekap |
| 51 | `catatan_otomatis` | Rapot |
| 52 | `activity_log_manage` | Pengaturan |
| 54 | `rekap_kamar` | Rekap |

> **Permission 54 `rekap_kamar`:** Dipakai untuk menu Peringkat Kamar dan Detail Kamar di rekap. Jangan dihilangkan.

---

### Tabel: `santri`
```sql
CREATE TABLE `santri` (
  `id`          INT(11) AUTO_INCREMENT PRIMARY KEY,
  `nama`        VARCHAR(100) DEFAULT NULL,
  `kelas`       INT(11) DEFAULT NULL,
  `kamar`       INT(11) DEFAULT NULL,
  `poin_aktif`  INT(11) NOT NULL DEFAULT 0
);
```
> `kelas` dan `kamar` adalah integer (nomor). `poin_aktif` adalah total poin pelanggaran kumulatif dikurangi total reward. Setiap kali ada pelanggaran baru → `poin_aktif` bertambah. Setiap kali reward dicatat → `poin_aktif` berkurang. Setiap hapus pelanggaran → `poin_aktif` berkurang.

---

### Tabel: `jenis_pelanggaran`
```sql
CREATE TABLE `jenis_pelanggaran` (
  `id`               INT(11) AUTO_INCREMENT PRIMARY KEY,
  `nama_pelanggaran` VARCHAR(255) NOT NULL,              -- ← NAMA KOLOM: nama_pelanggaran (BUKAN `nama`)
  `poin`             INT(11) NOT NULL DEFAULT 0,
  `kategori`         VARCHAR(50) DEFAULT NULL,           -- ← NAMA KOLOM: kategori (BUKAN `level`)
                                                         -- Nilai: 'Ringan', 'Sedang', 'Berat'
  `bagian`           VARCHAR(100) DEFAULT NULL           -- 'KESANTRIAN', 'BAHASA', 'DINIYYAH', 'TAHFIDZ', 'Pengabdian'
);
```
> **KRITIKAL:** Nama kolom adalah `nama_pelanggaran` dan `kategori` — bukan `nama` dan `level` seperti di versi PRD lama. Semua query SELECT/INSERT di `JenisPelanggaranModel` **wajib** menggunakan nama kolom yang benar ini. Kolom `bagian` menggunakan huruf kapital tidak konsisten di data lama (ada 'KESANTRIAN', ada 'Pengabdian'). Model harus handle case-insensitive saat filter.

---

### Tabel: `pelanggaran`
```sql
CREATE TABLE `pelanggaran` (
  `id`                   INT(11) AUTO_INCREMENT PRIMARY KEY,
  `santri_id`            INT(11) NOT NULL,
  `jenis_pelanggaran_id` INT(11) NOT NULL,
  `tanggal`              DATETIME NOT NULL,
  `dicatat_oleh`         INT(11) DEFAULT NULL,
  FOREIGN KEY (`santri_id`) REFERENCES `santri`(`id`),
  FOREIGN KEY (`jenis_pelanggaran_id`) REFERENCES `jenis_pelanggaran`(`id`)
);
```
> Tidak ada kolom `poin` atau `bagian` di tabel ini — poin diambil dengan JOIN ke `jenis_pelanggaran`, bidang/bagian juga diambil dari JOIN.

---

### Tabel: `pelanggaran_kebersihan`
```sql
CREATE TABLE `pelanggaran_kebersihan` (
  `id`           INT(11) AUTO_INCREMENT PRIMARY KEY,
  `kamar`        VARCHAR(50) NOT NULL,    -- ← TIPE: VARCHAR(50), BUKAN INT
  `catatan`      TEXT DEFAULT NULL,       -- ← ADA kolom catatan
  `tanggal`      DATETIME NOT NULL,
  `dicatat_oleh` INT(11) DEFAULT NULL,
  FOREIGN KEY (`dicatat_oleh`) REFERENCES `users`(`id`) ON DELETE SET NULL
);
```
> **KRITIKAL:** Kolom `kamar` bertipe `VARCHAR(50)`, bukan `INT`. Query binding untuk `kamar` harus menggunakan string (`s`/PDO::PARAM_STR), bukan integer. Ada kolom `catatan` (TEXT) untuk keterangan tambahan.

---

### Tabel: `eksekusi_kebersihan`
```sql
CREATE TABLE `eksekusi_kebersihan` (
  `id`               INT(11) AUTO_INCREMENT PRIMARY KEY,
  `pelanggaran_id`   INT(11) NOT NULL,   -- FK ke pelanggaran_kebersihan.id
  `kamar`            VARCHAR(50) NOT NULL,
  `jenis_sanksi`     VARCHAR(100) NOT NULL,
  `catatan`          TEXT DEFAULT NULL,
  `tanggal_eksekusi` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `dicatat_oleh`     INT(11) DEFAULT NULL,
  FOREIGN KEY (`pelanggaran_id`) REFERENCES `pelanggaran_kebersihan`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`dicatat_oleh`) REFERENCES `users`(`id`) ON DELETE SET NULL
);
```

---

### Tabel: `jenis_reward`
```sql
CREATE TABLE `jenis_reward` (
  `id`          INT(11) AUTO_INCREMENT PRIMARY KEY,
  `nama_reward` VARCHAR(255) NOT NULL,    -- ← NAMA KOLOM: nama_reward (BUKAN `nama`)
  `poin_reward` INT(11) NOT NULL DEFAULT 0, -- ← NAMA KOLOM: poin_reward (BUKAN `poin`)
  `deskripsi`   TEXT DEFAULT NULL         -- ← ADA kolom deskripsi tambahan
);
```
> **KRITIKAL:** Nama kolom adalah `nama_reward` dan `poin_reward` — bukan `nama` dan `poin`. Semua query di `JenisRewardModel` wajib menggunakan nama kolom yang benar.

---

### Tabel: `daftar_reward`
```sql
CREATE TABLE `daftar_reward` (
  `id`              INT(11) AUTO_INCREMENT PRIMARY KEY,
  `santri_id`       INT(11) NOT NULL,
  `jenis_reward_id` INT(11) NOT NULL,
  `tanggal`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `dicatat_oleh`    INT(11) DEFAULT NULL,
  FOREIGN KEY (`santri_id`) REFERENCES `santri`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`jenis_reward_id`) REFERENCES `jenis_reward`(`id`),
  FOREIGN KEY (`dicatat_oleh`) REFERENCES `users`(`id`) ON DELETE SET NULL
);
```
> Nama tabel di DB adalah `daftar_reward`, bukan `reward`. Model harus pakai nama ini.

---

### Tabel: `rapot_kepengasuhan`
```sql
CREATE TABLE `rapot_kepengasuhan` (
  `id`                            INT(11) AUTO_INCREMENT PRIMARY KEY,
  `santri_id`                     INT(11) NOT NULL,
  `musyrif_id`                    INT(11) DEFAULT NULL,
  `bulan`                         VARCHAR(20) NOT NULL,
  `tahun`                         YEAR(4) NOT NULL,              -- ← TERPISAH dari bulan
  -- === ASPEK IBADAH ===
  `puasa_sunnah`                  TINYINT(1) DEFAULT 0,          -- skala 0-5
  `sholat_duha`                   TINYINT(1) DEFAULT 0,
  `sholat_malam`                  TINYINT(1) DEFAULT 0,
  `sedekah`                       TINYINT(1) DEFAULT 0,
  `sunnah_tidur`                  TINYINT(1) DEFAULT 0,
  `ibadah_lainnya`                TINYINT(1) DEFAULT 0,
  -- === ASPEK AKHLAK ===
  `lisan`                         TINYINT(1) DEFAULT 0,
  `sikap`                         TINYINT(1) DEFAULT 0,
  `kesopanan`                     TINYINT(1) DEFAULT 0,
  `muamalah`                      TINYINT(1) DEFAULT 0,
  -- === ASPEK KEDISIPLINAN ===
  `tidur`                         TINYINT(1) DEFAULT 0,
  `keterlambatan`                 TINYINT(1) DEFAULT 0,
  `seragam`                       TINYINT(1) DEFAULT 0,
  `makan`                         TINYINT(1) DEFAULT 0,
  `arahan`                        TINYINT(1) DEFAULT 0,
  `bahasa_arab`                   TINYINT(1) DEFAULT 0,
  -- === ASPEK KEBERSIHAN ===
  `mandi`                         TINYINT(1) DEFAULT 0,
  `penampilan`                    TINYINT(1) DEFAULT 0,
  `piket`                         TINYINT(1) DEFAULT 0,
  `kerapihan_barang`              TINYINT(1) DEFAULT 0,
  -- === SNAPSHOT DATA SAAT RAPOT DIBUAT ===
  `total_poin_pelanggaran_saat_itu` INT(11) DEFAULT 0,          -- snapshot poin pelanggaran
  `total_poin_reward_saat_itu`      INT(11) DEFAULT 0,          -- snapshot total reward
  -- === METADATA ===
  `catatan_musyrif`               TEXT DEFAULT NULL,
  `dibuat_pada`                   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`santri_id`) REFERENCES `santri`(`id`),
  FOREIGN KEY (`musyrif_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
);
```
> **KRITIKAL:** Tabel ini punya **28 kolom** — jauh lebih banyak dari versi PRD lama yang hanya mendokumentasikan 5 kolom. Kolom `tahun` berdiri sendiri terpisah dari `bulan`. Ada 20 aspek kepengasuhan dengan skala nilai 0-5. Ada 2 kolom snapshot poin/reward saat rapot dibuat. `RapotController::store()` wajib handle semua kolom ini.

---

### Tabel: `arsip`
```sql
CREATE TABLE `arsip` (
  `id`               INT(11) AUTO_INCREMENT PRIMARY KEY,
  `judul`            VARCHAR(255) NOT NULL,
  `tanggal_mulai`    DATE NOT NULL,
  `tanggal_selesai`  DATE NOT NULL,
  `dibuat_pada`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

---

### Tabel: `arsip_data_pelanggaran`
```sql
CREATE TABLE `arsip_data_pelanggaran` (
  `id`                     INT(11) AUTO_INCREMENT PRIMARY KEY,
  `arsip_id`               INT(11) NOT NULL,
  `santri_id`              INT(11) DEFAULT NULL,
  `santri_nama`            VARCHAR(255) NOT NULL,
  `santri_kelas`           VARCHAR(50) DEFAULT NULL,
  `santri_kamar`           VARCHAR(50) DEFAULT NULL,
  `jenis_pelanggaran_id`   INT(11) DEFAULT NULL,
  `jenis_pelanggaran_nama` VARCHAR(255) NOT NULL,   -- ← disalin dari jenis_pelanggaran.nama_pelanggaran
  `bagian`                 VARCHAR(100) DEFAULT NULL,
  `poin`                   INT(11) NOT NULL DEFAULT 0,
  `tanggal`                DATETIME NOT NULL,
  `tipe`                   ENUM('Umum','Kebersihan') NOT NULL,
  FOREIGN KEY (`arsip_id`) REFERENCES `arsip`(`id`) ON DELETE CASCADE
);
```

---

### Tabel: `arsip_data_pelanggaran_kebersihan`
```sql
CREATE TABLE `arsip_data_pelanggaran_kebersihan` (
  `id`                   INT(11) AUTO_INCREMENT PRIMARY KEY,
  `arsip_id`             INT(11) NOT NULL,
  `kamar`                VARCHAR(50) NOT NULL,          -- ← VARCHAR, BUKAN INT
  `catatan`              TEXT DEFAULT NULL,
  `tanggal`              DATETIME NOT NULL,
  `dicatat_oleh_user_id` INT(11) DEFAULT NULL,          -- ← ADA, bukan di PRD lama
  `dicatat_oleh_nama`    VARCHAR(100) DEFAULT NULL,     -- ← ADA, bukan di PRD lama
  FOREIGN KEY (`arsip_id`) REFERENCES `arsip`(`id`) ON DELETE CASCADE
);
```
> **KRITIKAL:** Kolom `jumlah` dan `bagian` yang ada di PRD lama **TIDAK ADA** di database. Kolom yang benar adalah `dicatat_oleh_user_id` dan `dicatat_oleh_nama`. Query `ArsipModel::snapshotKebersihan()` harus menggunakan nama kolom yang benar.

---

### Tabel: `arsip_data_santri`
```sql
CREATE TABLE `arsip_data_santri` (
  `id`                   INT(11) AUTO_INCREMENT PRIMARY KEY,
  `arsip_id`             INT(11) NOT NULL,
  `santri_id`            INT(11) DEFAULT NULL,
  `santri_nama`          VARCHAR(255) NOT NULL,         -- ← NAMA KOLOM: santri_nama (BUKAN `nama`)
  `santri_kelas`         VARCHAR(50) DEFAULT NULL,      -- ← NAMA KOLOM: santri_kelas (BUKAN `kelas`)
  `santri_kamar`         VARCHAR(50) DEFAULT NULL,      -- ← NAMA KOLOM: santri_kamar (BUKAN `kamar`)
  `total_poin_saat_arsip` INT(11) NOT NULL DEFAULT 0,  -- ← NAMA KOLOM: total_poin_saat_arsip (BUKAN `poin`)
  FOREIGN KEY (`arsip_id`) REFERENCES `arsip`(`id`) ON DELETE CASCADE
);
```
> **KRITIKAL:** Semua nama kolom di tabel ini berbeda dari PRD lama. Gunakan nama kolom yang benar saat query.

---

### Tabel: `pengaturan`
```sql
CREATE TABLE `pengaturan` (
  `id`    INT(11) AUTO_INCREMENT PRIMARY KEY,
  `nama`  VARCHAR(100) NOT NULL UNIQUE,  -- Kunci pengaturan
  `nilai` TEXT DEFAULT NULL              -- Nilai pengaturan
);
```
> Kolom bernama `nama` dan `nilai` (bukan `key`/`value`). Contoh isi: `('periode_aktif', '2025-07-04')`.

---

### Tabel: `log_aktifitas`
```sql
CREATE TABLE `log_aktifitas` (
  `id`           INT(11) AUTO_INCREMENT PRIMARY KEY,
  `user_id`      INT(11) DEFAULT NULL,
  `username`     VARCHAR(50) NOT NULL,
  `nama_lengkap` VARCHAR(100) NOT NULL,
  `aksi`         VARCHAR(50) NOT NULL,    -- 'CREATE', 'UPDATE', 'DELETE', 'LOGIN', 'LOGOUT', 'BACKUP', dll
  `fitur`        VARCHAR(50) NOT NULL,    -- nama modul: 'santri', 'pelanggaran', 'auth', dll
  `deskripsi`    TEXT NOT NULL,
  `detail`       LONGTEXT DEFAULT NULL,   -- JSON detail perubahan
  `ip_address`   VARCHAR(45) NOT NULL,
  `user_agent`   TEXT NOT NULL,
  `dibuat_pada`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
);
```

---

### Tabel: `log_bahasa`
```sql
CREATE TABLE `log_bahasa` (
  `id`                   INT(11) AUTO_INCREMENT PRIMARY KEY,
  `santri_id`            INT(11) NOT NULL,
  `jenis_pelanggaran_id` INT(11) NOT NULL,
  `poin_lama`            INT(11) NOT NULL,
  `tanggal_melanggar`    DATETIME NOT NULL,
  `diganti_pada`         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `diganti_oleh`         INT(11) DEFAULT NULL,
  FOREIGN KEY (`santri_id`) REFERENCES `santri`(`id`),
  FOREIGN KEY (`jenis_pelanggaran_id`) REFERENCES `jenis_pelanggaran`(`id`),
  FOREIGN KEY (`diganti_oleh`) REFERENCES `users`(`id`) ON DELETE SET NULL
);
```
> Dipakai untuk audit trail saat pelanggaran bahasa santri di-replace (mekanisme khusus — lihat bagian Logika Bisnis Kritis).

---

### Tabel: `log_history`
```sql
CREATE TABLE `log_history` (
  `id`                   INT(11) AUTO_INCREMENT PRIMARY KEY,
  `user_id`              INT(11) DEFAULT NULL,
  `santri_id`            INT(11) DEFAULT NULL,
  `jenis_pelanggaran_id` INT(11) DEFAULT NULL,
  `aksi`                 VARCHAR(50) NOT NULL,      -- 'HAPUS', 'EDIT', dll
  `poin_sebelum`         INT(11) DEFAULT NULL,
  `tanggal_pelanggaran`  DATETIME DEFAULT NULL,
  `dicatat_pada`         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`santri_id`) REFERENCES `santri`(`id`) ON DELETE SET NULL
);
```

---

### Tabel: `log_reset_poin`
```sql
CREATE TABLE `log_reset_poin` (
  `id_log`                  INT(11) AUTO_INCREMENT PRIMARY KEY,  -- ← PRIMARY KEY: id_log (BUKAN `id`)
  `id_santri`               INT(11) DEFAULT NULL,                -- ← NAMA KOLOM: id_santri (BUKAN `santri_id`)
  `tanggal_reset`           DATE NOT NULL,
  `total_poin_sebelum_reset` INT(11) NOT NULL DEFAULT 0,         -- ← ADA, audit trail poin sebelum reset
  `keterangan`              VARCHAR(255) DEFAULT NULL,
  `di_reset_oleh`           INT(11) DEFAULT NULL,                -- ← NAMA KOLOM: di_reset_oleh (FK ke users.id)
  FOREIGN KEY (`id_santri`) REFERENCES `santri`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`di_reset_oleh`) REFERENCES `users`(`id`) ON DELETE SET NULL
);
```
> **KRITIKAL:** Primary key adalah `id_log`, bukan `id`. FK ke santri adalah `id_santri`, bukan `santri_id`. Ada kolom `total_poin_sebelum_reset` untuk audit trail. `LogResetPoinModel` dan `PengaturanController::resetPoinProcess()` wajib menggunakan nama kolom yang benar.

---

## SISTEM IZIN (RBAC) — PER-USER, BERBASIS SESSION

### Cara Kerja

Sistem izin AsuhTrack bekerja secara **per-user**: setiap user punya set izin masing-masing yang diatur oleh admin melalui UI, bukan default berdasarkan role.

**Alur login → permission:**
1. User login berhasil
2. `AuthController::loginProcess()` query semua izin user dari `user_permissions JOIN permissions`
3. Simpan ke `$_SESSION['permissions']` sebagai **array string `nama_izin`**
4. Seluruh sisa request: `AuthHelper::hasPermission()` cek dari array session ini — **nol query DB**
5. Jika admin mengubah izin user: `AuthHelper::clearPermissionCache()` dipanggil → user harus login ulang untuk dapat izin baru

### Admin Bypass

Role `'admin'` (case-insensitive, di-trim) adalah **bypass global** — semua permission check dianggap `true` tanpa pengecekan apapun.

### Implementasi `AuthHelper::hasPermission()`

```php
public static function hasPermission(string|array $namaIzin): bool
{
    // 1. Admin bypass total (cek dari session, nol query DB)
    $role = $_SESSION['role'] ?? '';
    if (strtolower(trim($role)) === 'admin') {
        return true;
    }

    // 2. Cek dari array session (nol query DB)
    $userPerms = $_SESSION['permissions'] ?? [];
    $required = is_array($namaIzin) ? $namaIzin : [$namaIzin];

    // Return true jika punya SETIDAKNYA SATU dari izin yang diminta
    return !empty(array_intersect($required, $userPerms));
}
```

> **PENTING:** Gunakan implementasi di atas, bukan query DB per pemanggilan. Sistem session-array ini jauh lebih efisien dan sudah terbukti di kode lama (`bootstrap/init.php` dan `guard()` function).

### Memuat Izin Saat Login

```php
// Di AuthController::loginProcess(), setelah verifikasi password berhasil:
$stmt = $pdo->prepare(
    "SELECT p.nama_izin
     FROM user_permissions up
     JOIN permissions p ON p.id = up.permission_id
     WHERE up.user_id = ?"
);
$stmt->execute([$user['id']]);
$permissions = $stmt->fetchAll(PDO::FETCH_COLUMN);  // array of strings
$_SESSION['permissions'] = $permissions;
$_SESSION['login_time'] = time();
```

### Cara Pemakaian di Controller

```php
// Di awal method yang butuh izin:
$this->requirePermission('santri_view');

// Atau untuk array of permissions (akses jika punya setidaknya satu):
$this->requirePermission(['rekap_kebersihan', 'rekap_kamar']);

// Atau untuk cek conditional (bukan block):
if (AuthHelper::hasPermission('santri_delete')) {
    // tampilkan tombol hapus
}
```

### Cara Pemakaian di View/Sidebar

```php
// Di sidebar, tampilkan menu hanya jika punya izin:
<?php if (AuthHelper::hasPermission('santri_view')): ?>
  <a href="/santri" hx-get="/santri" ...>Data Santri</a>
<?php endif; ?>

// Rekap kamar — cek permission khusus:
<?php if (AuthHelper::hasPermission('rekap_kamar')): ?>
  <a href="/rekap/peringkat-kamar" ...>Peringkat Kamar</a>
<?php endif; ?>
```

---

## LOGIKA BISNIS KRITIS — BACA SEBELUM IMPLEMENTASI MODEL

### 1. Reward MENGURANGI `poin_aktif` Santri

> **Ini adalah logika yang SERING disalahpahami.** Reward bukan terpisah dari `poin_aktif`.

```php
// Saat reward dicatat (RewardController::store()):
// 1. Ambil poin_reward dari jenis_reward
// 2. INSERT ke daftar_reward
// 3. UPDATE santri SET poin_aktif = poin_aktif - :poinReward WHERE id = :santriId
```

Reward mengurangi `poin_aktif` santri. Ini berarti santri yang rajak berprestasi poin pelanggaran aktifnya turun. `SantriModel::updatePoinAktif()` harus dipanggil dengan nilai negatif saat reward, atau buat method terpisah `decrementPoin()`.

---

### 2. Pelanggaran Bahasa — Mekanisme Replace & Log (Bukan Simple INSERT)

> Pelanggaran bidang Bahasa tidak di-accumulate. Setiap input baru **menggantikan** pelanggaran bahasa lama santri tersebut. Ini adalah mekanisme unik yang tidak berlaku untuk bidang lain.

**Alur lengkap (wajib dalam satu transaksi MySQL/PDO):**

```
TRANSACTION BEGIN
  1. SELECT semua pelanggaran bahasa santri ini (WHERE santri_id = ? AND jp.bagian LIKE '%BAHASA%')
  2. Untuk setiap pelanggaran lama yang ditemukan:
     a. INSERT ke log_bahasa (backup audit trail):
        - santri_id, jenis_pelanggaran_id, poin_lama, tanggal_melanggar, diganti_oleh
     b. UPDATE santri SET poin_aktif = poin_aktif - poin_lama WHERE id = santri_id
     c. DELETE FROM pelanggaran WHERE id = id_pelanggaran_lama
  3. INSERT pelanggaran baru ke tabel pelanggaran
  4. UPDATE santri SET poin_aktif = poin_aktif + poin_baru WHERE id = santri_id
TRANSACTION COMMIT (atau ROLLBACK jika ada error)
```

Implementasi di `PelanggaranController::store()` harus mendeteksi jika `$bagian === 'bahasa'` dan menjalankan logika khusus ini.

---

### 3. Input Pelanggaran — Bulk (Banyak Santri Sekaligus)

> Form input pelanggaran untuk semua 5 bidang adalah **bulk input** — satu jenis pelanggaran dipilih, kemudian banyak santri dipilih via checkbox sekaligus.

POST body yang dikirim form:
```
santri_ids[]  = [array of int]   ← bukan santri_id tunggal
jenis_pelanggaran_id = int
tanggal = datetime string
```

`PelanggaranController::store()` harus loop melalui `$_POST['santri_ids']`:
```php
$santriIds = array_filter(array_map('intval', $_POST['santri_ids'] ?? []));
foreach ($santriIds as $santriId) {
    // insert satu record per santri_id
    // update poin_aktif masing-masing santri
}
```

---

### 4. Password — Dual Verification + Auto-Upgrade ke bcrypt

> Database berisi campuran: user lama dengan hash SHA-256, user baru dengan bcrypt. Sistem harus support keduanya.

```php
// Di AuthController::loginProcess():
$user = $userModel->findByUsername($username);

$passwordCorrect = false;
$needsRehash = false;

if ($user && password_verify($password, $user['password'])) {
    // Password bcrypt — cara baru
    $passwordCorrect = true;
    if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
        $needsRehash = true;
    }
} elseif ($user && hash('sha256', $password) === $user['password']) {
    // Password SHA-256 — cara lama, upgrade otomatis
    $passwordCorrect = true;
    $needsRehash = true;
}

if ($passwordCorrect) {
    if ($needsRehash) {
        // Auto-upgrade ke bcrypt transparan
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        $userModel->update($user['id'], ['password' => $newHash]);
    }
    // Set session dan lanjut...
}
```

> Saat membuat user baru via `PengaturanController::userStore()`, **selalu** gunakan `password_hash($password, PASSWORD_DEFAULT)` (bcrypt). Tidak perlu SHA-256 untuk user baru.

---

### 5. Inactivity Timeout — 3 Jam (10800 Detik)

`public/index.php` (atau middleware awal di Router) harus cek inactivity timeout di setiap request:

```php
// Setelah session_start():
if (isset($_SESSION['login_time'])) {
    if ((time() - $_SESSION['login_time']) > 10800) {
        // Session expired karena tidak aktif 3 jam
        session_destroy();
        // Redirect ke login
    } else {
        // Update timestamp aktivitas terakhir
        $_SESSION['login_time'] = time();
    }
}
```

> `.env` mendefinisikan `SESSION_LIFETIME=10800` (bukan 7200). Ini adalah nilai aktual yang dipakai di kode produksi.

---

## SPESIFIKASI LAYOUT SPA

### `views/layouts/main.php` — Shell Utama

File ini adalah **satu-satunya** yang merender `<html>` penuh. Struktur:

```html
<!DOCTYPE html>
<html lang="id">
<head>
    <?php include VIEW_PATH . '/layouts/header.php'; ?>
</head>
<body>
    <div id="app-wrapper">
        <?php include VIEW_PATH . '/layouts/sidebar.php'; ?>
        <main id="main-content" class="content-area">
            <?php include VIEW_PATH . '/components/alert.php'; ?>
            <?php echo $content ?? ''; ?>
        </main>
    </div>
    <?php include VIEW_PATH . '/layouts/footer.php'; ?>
</body>
</html>
```

- Variabel `$content` diisi oleh `Controller::view()` via output buffering
- `#main-content` adalah **satu-satunya** HTMX swap target untuk navigasi

### `views/layouts/header.php`

Berisi:
- `<meta charset>`, `<meta viewport>`, `<title>AsuhTrack</title>`
- `<meta name="csrf-token" content="<?= $csrf_token ?>">` ← untuk HTMX auto-inject
- Link CSS: `style.css`, `htmx-transitions.css`
- Link manifest PWA

### `views/layouts/footer.php`

Berisi (urutan penting):
1. `htmx.min.js`
2. `chart.min.js`
3. `sweetalert.min.js`
4. `app.js`

### `public/assets/js/app.js`

```javascript
// 1. CSRF auto-inject ke semua HTMX request
document.body.addEventListener('htmx:configRequest', function(evt) {
    const token = document.querySelector('meta[name="csrf-token"]');
    if (token) evt.detail.headers['X-CSRF-Token'] = token.content;
});

// 2. Handle session expired saat HTMX request
document.body.addEventListener('htmx:responseError', function(evt) {
    if (evt.detail.xhr.status === 401) {
        window.location.href = '/login';
    }
});

// 3. Reinit SweetAlert setelah HTMX swap jika diperlukan
```

### `views/layouts/sidebar.php`

- Menu navigasi kiri vertikal
- Setiap link pakai HTMX Pattern 1 (lihat bagian Integrasi HTMX)
- Tampilkan/sembunyikan menu berdasarkan `AuthHelper::hasPermission()`
- Highlight menu aktif berdasarkan URL saat ini (`$_SERVER['REQUEST_URI']`)
- Menu Rekap > Peringkat Kamar: tampil hanya jika punya `rekap_kamar`

---

## FLASH MESSAGE & SESSION

### Flash Message

```php
// Di Controller::flash():
$_SESSION['flash_message'] = ['type' => $type, 'message' => $message];
// $type: 'success' | 'error' | 'warning' | 'info'
// PENTING: Hanya SATU flash message aktif sekaligus (bukan array)
// Key yang benar: 'flash_message' (BUKAN 'flash')

// Di views/components/alert.php:
// Baca flash_message, tampilkan, lalu unset($_SESSION['flash_message'])
```

### Session Keys

| Key | Isi |
|---|---|
| `$_SESSION['user_id']` | ID user yang login (int) |
| `$_SESSION['username']` | Username |
| `$_SESSION['nama_lengkap']` | Nama lengkap |
| `$_SESSION['role']` | Role label (untuk display dan admin-bypass check) |
| `$_SESSION['csrf_token']` | Token CSRF aktif |
| `$_SESSION['flash_message']` | **Single** flash message: `['type' => ..., 'message' => ...]` |
| `$_SESSION['permissions']` | **Array string** `nama_izin` yang dimiliki user (di-load saat login) |
| `$_SESSION['login_time']` | Unix timestamp login terakhir (untuk inactivity timeout 3 jam) |

> Tidak ada `$_SESSION['periode_aktif']`. Periode aktif diambil langsung dari tabel `pengaturan` WHERE `nama` = `'periode_aktif'` setiap kali dibutuhkan.

---

## ERROR HANDLING GLOBAL

### Di `public/index.php`

```php
set_exception_handler(function(\Throwable $e) {
    if ($_ENV['APP_DEBUG'] === 'true') {
        echo '<pre>' . $e->getMessage() . "\n" . $e->getTraceAsString() . '</pre>';
    } else {
        error_log($e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        http_response_code(500);
        include VIEW_PATH . '/errors/500.php';
    }
    exit;
});
```

### Di Controller Method

```php
try {
    $result = (new SantriModel())->create($data);
    $this->flash('success', 'Data berhasil disimpan');
    $this->redirect('/santri');
} catch (\PDOException $e) {
    error_log('Error create santri: ' . $e->getMessage());
    $this->flash('error', 'Terjadi kesalahan saat menyimpan data');
    $this->redirect('/santri/tambah');
}
```

---

## LOGGING AKTIVITAS

Setiap aksi create/update/delete wajib catat ke `log_aktifitas`. Implementasi di base Controller:

```php
protected function logActivity(
    string $aksi,      // 'CREATE', 'UPDATE', 'DELETE', 'LOGIN', 'LOGOUT', 'BACKUP'
    string $fitur,     // nama modul: 'santri', 'pelanggaran', 'auth', 'backup-restore', dll
    string $deskripsi, // teks deskriptif bahasa Indonesia
    ?array $detail = null  // JSON detail perubahan (opsional)
): void {
    // Insert ke log_aktifitas
    // Ambil user_id, username, nama_lengkap dari session
    // Ambil ip_address dari $_SERVER['REMOTE_ADDR']
    // Ambil user_agent dari $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    // Jangan throw exception jika logging gagal (non-critical)
}
```

---

## MIGRASI DATABASE — SQL ADDITIVE

> Jalankan script ini **setelah** rewrite selesai. Script ini hanya menambah, tidak mengubah atau menghapus yang sudah ada.

```sql
-- Script ini AMAN dijalankan berkali-kali (idempoten)
-- Hanya menambahkan kolom/tabel yang belum ada

-- Tidak ada perubahan wajib untuk versi pertama rewrite.
-- Sistem izin, struktur tabel, dan data tetap dipakai apa adanya.

-- Jika di masa depan perlu kolom baru (misal foto santri),
-- gunakan ALTER TABLE ADD COLUMN IF NOT EXISTS (MySQL 5.7+)
-- Contoh:
-- ALTER TABLE santri ADD COLUMN IF NOT EXISTS foto VARCHAR(255) DEFAULT NULL;
```

---

## INTEGRASI HTMX — POLA WAJIB

### Pattern 1: Navigasi Sidebar (SPA tanpa reload)

```html
<a href="/santri"
   hx-get="/santri"
   hx-target="#main-content"
   hx-swap="innerHTML"
   hx-push-url="true"
   hx-indicator="#page-loader">
  Data Santri
</a>
```

### Pattern 2: Live Search

```html
<input type="text" name="q"
       hx-get="/santri/search"
       hx-target="#tabel-santri"
       hx-trigger="keyup changed delay:400ms"
       hx-indicator="#search-spinner">
```

### Pattern 3: Form Submit tanpa Reload

```html
<form hx-post="/santri/tambah"
      hx-target="#main-content"
      hx-swap="innerHTML"
      hx-push-url="/santri">
  <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
  <button type="submit">Simpan</button>
</form>
```

### Pattern 4: Konfirmasi Hapus dengan SweetAlert2

```html
<button
  hx-post="/santri/hapus/1"
  hx-target="#baris-santri-1"
  hx-swap="outerHTML"
  hx-on:htmx:confirm="
    event.preventDefault();
    Swal.fire({title:'Yakin?',text:'Data akan dihapus',icon:'warning',showCancelButton:true})
      .then(r => { if(r.isConfirmed) event.detail.issueRequest() })
  ">
  Hapus
</button>
```

### Pattern 5: Data JSON untuk Chart.js

```javascript
fetch('/rekap/data-harian?bulan=2025-10')
  .then(r => r.json())
  .then(data => new Chart(ctx, { type: 'bar', data }));
```

### Pattern 6: HTMX Response untuk Redirect

Di `Controller::redirect()`:
```php
if ($this->isHtmxRequest()) {
    header('HX-Redirect: ' . $url);
} else {
    header('Location: ' . $url);
}
exit();
```

---

## JADWAL EKSEKUSI (TARGET 1 MINGGU)

Untuk mempercepat pengerjaan dari estimasi awal 3-4 minggu menjadi **1 minggu**, proyek ini dieksekusi dengan prinsip:
1. **Delegasi Otonom (Fitur `/goal`)**: AI akan mengerjakan satu atau beberapa Phase sekaligus secara otonom tanpa jeda konfirmasi per file.
2. **Lock UI/UX**: Tampilan 100% menggunakan `assets/css/style.css` lama tanpa modifikasi kosmetik.
3. **Pola Boilerplate**: Model dan Controller yang seragam (CRUD) digenerate dengan pola copy-paste yang konsisten.
4. **Prioritas Inti**: Fitur sekunder (impor/ekspor, animasi) diselesaikan di akhir.

**Estimasi Pembagian Hari:**
- **Hari 1:** Phase 1 (Fondasi MVC, Router, Layout) & Phase 2 (Data Santri).
- **Hari 2:** Phase 3 & 4 (Jenis Pelanggaran & Input Pelanggaran 5 Bidang, Replace & Log).
- **Hari 3:** Phase 5 & 6 (Pelanggaran Kebersihan & Reward).
- **Hari 4:** Phase 7 & 8 (Rekap & Rapot PDF).
- **Hari 5:** Phase 9 & 10 (Arsip & Export Excel).
- **Hari 6:** Phase 11 & 12 (Pengaturan Izin, Profil, Reset Poin, PWA).
- **Hari 7:** Testing & Finalisasi.

---

## PHASE 1: FONDASI MVC

### Sub-Phase 1.1 — Konfigurasi & Entry Point

**File yang dibuat:**
- `public/.htaccess`
- `public/index.php`
- `.env` + `.env.example`
- `.gitignore`
- `composer.json`

#### `public/.htaccess`

```apache
Options -Indexes
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.+)$ index.php [QSA,L]
```

#### `public/index.php` — Struktur

```php
<?php
declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH',  ROOT_PATH . '/app');
define('VIEW_PATH', ROOT_PATH . '/views');
define('UPLOAD_PATH', ROOT_PATH . '/uploads');

// 1. Load .env manual (tanpa library) — parse key=value sederhana
// 2. Require composer autoloader
// 3. Set exception handler global
// 4. Start session: cookie_httponly=1, cookie_samesite=Strict, name dari SESSION_NAME env
// 5. Cek inactivity timeout (10800 detik / 3 jam):
//    if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > 10800) {
//        session_destroy(); header('Location: /login'); exit;
//    } elseif (isset($_SESSION['login_time'])) {
//        $_SESSION['login_time'] = time(); // refresh
//    }
// 6. Inisialisasi Router
// 7. require APP_PATH . '/routes.php'
// 8. $router->dispatch()
```

> Tidak ada logika bisnis di sini. Maksimal 40 baris.

#### `.env`

```env
APP_NAME=AsuhTrack
APP_URL=http://localhost/rekap-mukholif/public
APP_ENV=development
APP_DEBUG=true

DB_HOST=localhost
DB_PORT=3306
DB_NAME=rekap_mukholif
DB_USER=root
DB_PASS=

SESSION_NAME=asuhtrack_sess
SESSION_LIFETIME=10800
```

> **Catatan BASE_URL:** Untuk environment yang berbeda-beda path subfolder, pertimbangkan untuk menggunakan BASE_URL dinamis yang dihitung dari `$_SERVER`. Nilai `APP_URL` di .env bisa dijadikan fallback jika deteksi dinamis gagal.

#### `composer.json`

```json
{
  "require": {
    "php": ">=8.1",
    "dompdf/dompdf": "^2.0",
    "phpoffice/phpspreadsheet": "^2.0"
  },
  "autoload": {
    "psr-4": {
      "App\\": "app/"
    }
  }
}
```

**Checklist 1.1:**
- [ ] `composer install` selesai tanpa error
- [ ] `.env` ada di `.gitignore`
- [ ] Apache tidak 403 saat buka `public/`

---

### Sub-Phase 1.2 — Core Classes: Database, Model, Controller

**File yang dibuat:**
- `app/Core/Database.php`
- `app/Core/Model.php`
- `app/Core/Controller.php`

#### `app/Core/Database.php`

- Singleton — satu instance per request
- Baca konfigurasi dari `$_ENV`
- PDO options: `ERRMODE_EXCEPTION`, `FETCH_ASSOC`, charset `utf8mb4`
- Jika gagal konek di production: log error, lempar exception pesan generik

Method:
- `getInstance(): static`
- `getConnection(): PDO`

#### `app/Core/Model.php`

Property wajib:
- `protected string $table` — di-override child class

Method wajib (semua tersedia di child tanpa override):

| Method | Signature | Return |
|---|---|---|
| `findAll` | `(array $where = [], string $order = 'id DESC', int $limit = 0): array` | array |
| `findById` | `(int $id): array\|false` | array atau false |
| `create` | `(array $data): int` | lastInsertId |
| `update` | `(int $id, array $data): bool` | bool |
| `delete` | `(int $id): bool` | bool |
| `query` | `(string $sql, array $params = []): array` | array |
| `execute` | `(string $sql, array $params = []): bool` | bool |
| `count` | `(array $where = []): int` | int |
| `paginate` | `(int $page, int $perPage, array $where = []): array` | `{data, total, currentPage, lastPage, perPage}` |

#### `app/Core/Controller.php`

Method wajib:

```
view(string $view, array $data = []): void
  → Render halaman penuh dengan layout main.php
  → Extract $data, output buffer $content, include main.php

partial(string $view, array $data = []): void
  → Render hanya file view tanpa layout
  → Untuk HTMX response

respond(string $view, array $data = []): void
  → Auto-detect: isHtmxRequest() → partial(), tidak → view()
  → INI yang SEBAIKNYA dipakai di setiap action

redirect(string $url): void
  → HTMX: header HX-Redirect | Non-HTMX: header Location
  → Selalu exit() sesudahnya

json(mixed $data, int $code = 200): void
  → Content-Type: application/json, http_response_code, json_encode, exit

flash(string $type, string $message): void
  → $_SESSION['flash_message'] = ['type' => $type, 'message' => $message]
  → Hanya satu flash message sekaligus

requireAuth(): void
  → Cek $_SESSION['user_id'], redirect ke /login jika tidak ada

requirePermission(string|array $namaIzin): void
  → AuthHelper::hasPermission($namaIzin)
  → Jika false: http_response_code(403), include views/errors/403.php, exit

isHtmxRequest(): bool
  → return isset($_SERVER['HTTP_HX_REQUEST'])

generateCsrfToken(): string
  → bin2hex(random_bytes(32)), simpan ke $_SESSION['csrf_token'], return

validateCsrfToken(): void
  → Bandingkan POST['csrf_token'] dengan SESSION['csrf_token']
  → Jika tidak cocok: http_response_code(419), echo 'CSRF invalid', exit

logActivity(string $aksi, string $fitur, string $deskripsi, ?array $detail = null): void
  → Insert ke log_aktifitas, tidak throw exception jika gagal
```

**Checklist 1.2:**
- [ ] `Database::getInstance()->getConnection()` berhasil konek
- [ ] Child Model bisa panggil `findAll()` tanpa error
- [ ] `Controller::isHtmxRequest()` return benar sesuai header

---

### Sub-Phase 1.3 — Router

**File yang dibuat:**
- `app/Core/Router.php`

Spesifikasi:
- Daftarkan route: `get(string $path, string $controller, string $method)` dan `post(...)`
- Support param dinamis: `/santri/detail/{id}` → extract `$id`
- URL tidak cocok → 404, method tidak cocok → 405
- Auto-strip base path jika app di subfolder

```php
// Cara pakai di routes.php:
$router->get('/santri', 'SantriController', 'index');
$router->get('/santri/detail/{id}', 'SantriController', 'detail');
$router->post('/santri/tambah', 'SantriController', 'store');
$router->dispatch();
```

Cara kerja dispatch:
1. Parse `REQUEST_URI`, strip query string dan base path
2. Loop route sesuai HTTP method
3. Konversi `{param}` ke regex `([^/]+)`
4. Cocok → extract params, instantiate `App\Controllers\{NamaController}`, panggil method dengan params sebagai argumen
5. Tidak cocok → 404

**Checklist 1.3:**
- [ ] Route `/` jalankan DashboardController::index
- [ ] `/santri/detail/5` ekstrak `$id = '5'` dengan benar
- [ ] URL tidak terdaftar → tampilkan 404.php

---

### Sub-Phase 1.4 — Helpers

**File yang dibuat:**
- `app/Helpers/AuthHelper.php`
- `app/Helpers/FormatHelper.php`

#### `AuthHelper`

```php
static isLoggedIn(): bool
static getUserId(): int
static getRole(): string         // hanya untuk display dan admin-bypass, bukan kontrol akses umum
static getNamaLengkap(): string

static hasPermission(string|array $namaIzin): bool
  // Implementasi:
  // 1. Cek role 'admin' → return true
  // 2. Cek $_SESSION['permissions'] (array) → array_intersect
  // ZERO query database
  // Support single string atau array of strings

static clearPermissionCache(): void
  // unset($_SESSION['permissions'])
  // Dipanggil setelah update izin user (user harus login ulang)
```

#### `FormatHelper`

```php
static h(string $str): string
  // htmlspecialchars($str, ENT_QUOTES, 'UTF-8')
  // FUNGSI PALING SERING DIPANGGIL DI VIEW

static tanggalIndo(string $date): string
  // '2025-10-15' → '15 Oktober 2025'

static tanggalPendek(string $date): string
  // '2025-10-15' → '15 Okt 2025'

static tanggalWaktu(string $datetime): string
  // '2025-10-15 08:30:00' → '15 Okt 2025, 08:30'

static poinBadge(int $poin): string
  // Return HTML badge warna sesuai threshold poin
  // Sesuaikan dengan kelas CSS yang ada di style.css lama

static getPeriodeAktif(): string
  // Query tabel pengaturan WHERE nama = 'periode_aktif'
  // Return nilai, contoh: '2025-07-04'
```

**Checklist 1.4:**
- [ ] `AuthHelper::hasPermission('santri_view')` return true untuk user yang punya izin itu
- [ ] `AuthHelper::hasPermission('santri_delete')` return false untuk user yang tidak punya
- [ ] User role 'admin' → semua `hasPermission()` return true tanpa cek DB
- [ ] `FormatHelper::tanggalIndo('2025-10-15')` return '15 Oktober 2025'
- [ ] `FormatHelper::h('<script>')` return `&lt;script&gt;`

---

### Sub-Phase 1.5 — Layout SPA + Auth Module

**File yang dibuat:**
- `views/layouts/main.php`
- `views/layouts/header.php`
- `views/layouts/footer.php`
- `views/layouts/sidebar.php`
- `views/components/alert.php`
- `views/components/loading.php`
- `views/errors/403.php`
- `views/errors/404.php`
- `views/errors/500.php`
- `views/pages/auth/login.php`
- `app/Controllers/AuthController.php`
- `app/Models/UserModel.php`

#### UserModel

```php
protected string $table = 'users';

findByUsername(string $username): array|false
  // SELECT * FROM users WHERE username = ?
  // TIDAK filter by status (tidak ada kolom status di DB)
```

#### AuthController

```php
loginPage(): void
  // Jika sudah login: redirect ke /
  // Generate CSRF token
  // Render login.php

loginProcess(): void
  // validateCsrfToken()
  // Ambil username + password dari POST
  // UserModel::findByUsername()
  // VERIFIKASI PASSWORD (dual-verify):
  //   Coba bcrypt dulu: password_verify($password, $user['password'])
  //   Jika gagal, coba SHA-256 lama: hash('sha256', $password) === $user['password']
  //   Jika SHA-256 cocok: tandai untuk auto-upgrade
  // Jika berhasil:
  //   - Set session: user_id, username, nama_lengkap, role
  //   - Set session: permissions (array dari query user_permissions JOIN permissions)
  //   - Set session: login_time = time()
  //   - Auto-upgrade hash jika perlu (UserModel::update password ke bcrypt)
  //   - logActivity 'LOGIN' di 'auth'
  //   - redirect ke /
  // Jika gagal: flash error, redirect ke /login

logout(): void
  // logActivity 'LOGOUT' di 'auth'
  // session_destroy()
  // Redirect ke /login
```

**Checklist 1.5:**
- [ ] Akses `/` tanpa login → redirect ke `/login`
- [ ] Login kredensial salah → flash error muncul
- [ ] Login berhasil → redirect ke dashboard, session ter-set termasuk `permissions` array
- [ ] Klik logout → session hilang, redirect ke `/login`
- [ ] Sidebar menyembunyikan menu yang tidak punya permission
- [ ] User SHA-256 lama bisa login dan hash-nya otomatis upgrade ke bcrypt

---

### Sub-Phase 1.6 — Dashboard

**File yang dibuat:**
- `app/Controllers/DashboardController.php`
- `views/pages/dashboard/index.php`

DashboardController::index():
- Tampilkan: total santri, total pelanggaran bulan ini, total reward bulan ini, top 5 santri poin tertinggi
- Data dari Model masing-masing (stub return 0 jika tabel belum siap)
- `respond('pages/dashboard/index', $data)`

**Checklist Phase 1 Selesai:**
- [ ] Buka `public/` → dashboard tampil dengan sidebar
- [ ] Klik menu di sidebar → konten berganti **tanpa reload halaman** (cek Network tab)
- [ ] URL tidak ada → halaman 404 custom
- [ ] Akses tanpa login → redirect ke `/login`
- [ ] HTMX request saat session expired → `HX-Redirect` header dikirim
- [ ] Flash message muncul dan auto-hilang setelah satu kali tampil
- [ ] `.env` ada di `.gitignore`
- [ ] Inactivity > 3 jam → otomatis redirect ke login

---

## PHASE 2: MASTER DATA SANTRI

### Sub-Phase 2.1 — Model & CRUD Dasar Santri

**File yang dibuat:**
- `app/Models/SantriModel.php`
- `app/Controllers/SantriController.php`
- `views/pages/santri/index.php`
- `views/pages/santri/form.php`
- `views/pages/santri/detail.php`
- `views/pages/santri/_tabel.php`

#### SantriModel

```php
protected string $table = 'santri';

// Method tambahan di atas yang dari Base Model:
search(string $keyword): array
  // WHERE nama LIKE ? (case-insensitive)

getByKamar(int $kamar): array
  // WHERE kamar = ?

getByKelas(int $kelas): array

getAllWithPoin(): array
  // SELECT *, poin_aktif dari santri, ORDER BY poin_aktif DESC

updatePoinAktif(int $santriId, int $poin): bool
  // UPDATE santri SET poin_aktif = ? WHERE id = ?
  // Dipanggil setiap kali pelanggaran baru dicatat atau dihapus

addPoin(int $santriId, int $tambah): bool
  // UPDATE santri SET poin_aktif = poin_aktif + ? WHERE id = ?
  // Untuk input pelanggaran (tambah poin)

reducePoin(int $santriId, int $kurang): bool
  // UPDATE santri SET poin_aktif = poin_aktif - ? WHERE id = ?
  // Untuk hapus pelanggaran ATAU untuk input reward (reward mengurangi poin)
```

> `poin_aktif` di tabel `santri` adalah total poin kumulatif pelanggaran **dikurangi** reward. Setiap input pelanggaran baru → `addPoin()`. Setiap hapus pelanggaran → `reducePoin()`. Setiap input reward → `reducePoin()` (reward mengurangi poin aktif).

#### SantriController

```php
requirePermission sesuai aksi masing-masing.

index()    → list santri dengan paginasi 20/halaman, respond()
create()   → form tambah (requirePermission: santri_create)
store()    → validasi + simpan + logActivity + redirect
detail()   → detail satu santri + riwayat poinnya
edit()     → form edit pre-fill (requirePermission: santri_edit)
update()   → update + logActivity + redirect
delete()   → hapus + updatePoinAktif ke 0 + logActivity (requirePermission: santri_delete)
search()   → live search santri via HTMX, return partial _tabel.php
```

**Checklist 2.1:**
- [ ] Tabel santri tampil dengan paginasi
- [ ] Tambah santri → muncul di tabel
- [ ] Edit → data ter-update
- [ ] Hapus → hilang dari tabel
- [ ] Live search berfungsi tanpa reload

---

### Sub-Phase 2.2 — Bulk Operations Santri

**File yang dibuat/update:**
- `views/pages/santri/bulk_tambah.php`
- `views/pages/santri/bulk_edit.php`
- Tambah method ke SantriController

```php
bulkCreate()   → form tambah banyak santri (tabel input dinamis)
bulkStore()    → loop insert + logActivity per batch
bulkEdit()     → form edit massal filter per kamar/kelas
bulkUpdate()   → loop update + logActivity
bulkDelete()   → loop hapus + konfirmasi SweetAlert (requirePermission: santri_delete)
```

**Checklist 2.2 (Phase 2 Selesai):**
- [ ] Bulk tambah banyak santri sekaligus berfungsi
- [ ] Bulk edit (naik kelas, pindah kamar) berfungsi
- [ ] Bulk hapus dengan konfirmasi SweetAlert berfungsi

---

## PHASE 3: KATALOG JENIS PELANGGARAN

### Sub-Phase 3.1 — CRUD Jenis Pelanggaran

**File yang dibuat:**
- `app/Models/JenisPelanggaranModel.php`
- `app/Controllers/JenisPelanggaranController.php`
- `views/pages/jenis-pelanggaran/index.php`
- `views/pages/jenis-pelanggaran/form.php`

#### JenisPelanggaranModel

```php
protected string $table = 'jenis_pelanggaran';

// INGAT: nama kolom adalah `nama_pelanggaran` dan `kategori`, BUKAN `nama` dan `level`

getByBagian(string $bagian): array
  // WHERE LOWER(bagian) = LOWER(?)  ← case-insensitive karena data lama tidak konsisten

isUsed(int $id): bool
  // SELECT COUNT(*) FROM pelanggaran WHERE jenis_pelanggaran_id = ?
  // Return true jika > 0

getDaftarBagian(): array
  // Return ['KESANTRIAN', 'BAHASA', 'DINIYYAH', 'TAHFIDZ', 'Pengabdian']
  // Atau query SELECT DISTINCT bagian FROM jenis_pelanggaran

// Contoh query yang benar:
// SELECT id, nama_pelanggaran, poin, kategori, bagian FROM jenis_pelanggaran
// BUKAN: SELECT id, nama, poin, level, bagian FROM jenis_pelanggaran
```

#### JenisPelanggaranController

```php
index()    → list semua, bisa filter per bagian (requirePermission: jenis_pelanggaran_view)
create()   → form tambah (requirePermission: jenis_pelanggaran_create)
store()    → simpan + logActivity + redirect
edit()     → form edit (requirePermission: jenis_pelanggaran_edit)
update()   → update + logActivity + redirect
delete()   → cek isUsed() dulu:
             jika dipakai: flash error, redirect
             jika tidak: hapus + logActivity (requirePermission: jenis_pelanggaran_delete)
```

### Sub-Phase 3.2 — Bulk Edit Jenis Pelanggaran

**File yang dibuat:**
- `views/pages/jenis-pelanggaran/bulk_edit.php`
- Tambah method ke JenisPelanggaranController

```php
bulkEdit()    → tampilkan tabel editable semua jenis pelanggaran (nama_pelanggaran, poin, kategori)
bulkUpdate()  → loop update poin/nama + logActivity
```

**Checklist Phase 3 Selesai:**
- [ ] CRUD satu jenis pelanggaran berfungsi
- [ ] Kolom `nama_pelanggaran` dan `kategori` digunakan dengan benar di semua query
- [ ] Bulk edit poin berfungsi
- [ ] Hapus jenis yang masih dipakai → error flash, tidak terhapus

---

## PHASE 4: INPUT PELANGGARAN

### Sub-Phase 4.1 — Model Pelanggaran

**File yang dibuat:**
- `app/Models/PelanggaranModel.php`

```php
protected string $table = 'pelanggaran';

// JOIN helper — query dengan JOIN ke santri dan jenis_pelanggaran
// INGAT: gunakan jp.nama_pelanggaran (BUKAN jp.nama) dan jp.kategori (BUKAN jp.level)

getByBagian(string $bagian, ?string $periodeAwal = null, ?string $periodeAkhir = null): array
  // SELECT p.*, s.nama, s.kamar, s.kelas,
  //        jp.nama_pelanggaran as jenis_nama, jp.poin, jp.bagian, jp.kategori
  // FROM pelanggaran p
  // JOIN santri s ON s.id = p.santri_id
  // JOIN jenis_pelanggaran jp ON jp.id = p.jenis_pelanggaran_id
  // WHERE LOWER(jp.bagian) = LOWER(?)
  // AND p.tanggal BETWEEN ? AND ? (jika periode disertakan)

getBySantri(int $santriId): array
  // Semua pelanggaran satu santri, join ke jenis_pelanggaran (pakai nama_pelanggaran),
  // ORDER BY tanggal DESC

getTotalPoinSantri(int $santriId, ?string $awal = null, ?string $akhir = null): int
  // SUM(jp.poin) dengan JOIN ke jenis_pelanggaran WHERE p.santri_id = ?

getRekap(string $bagian, string $awal, string $akhir): array
  // GROUP BY santri_id, SUM poin, untuk tabel rekap per bidang

getRekapPerKamar(string $awal, string $akhir): array
  // Agregasi pelanggaran per kamar

create(array $data): int
  // Insert ke pelanggaran
  // Setelah insert: SantriModel::addPoin(santri_id, poin_dari_jenis)
  // (poin diambil dari jenis_pelanggaran.poin via query sebelum insert)
  // Return lastInsertId

createBulk(array $santriIds, int $jenisPelanggaranId, string $tanggal, int $dicatatOleh): array
  // Loop insert untuk setiap santri_id dalam array
  // Untuk setiap santri: insert + addPoin
  // Return array of lastInsertId
  // SELURUH proses dalam SATU transaksi PDO

createBahasa(int $santriId, int $jenisPelanggaranId, string $tanggal, int $dicatatOleh): int
  // LOGIKA KHUSUS bidang Bahasa (Replace & Log):
  // Dalam SATU transaksi:
  //   1. SELECT semua pelanggaran bahasa santri ini
  //   2. Untuk setiap pelanggaran lama:
  //      a. INSERT ke log_bahasa (audit trail)
  //      b. SantriModel::reducePoin(santri_id, poin_lama)
  //      c. DELETE FROM pelanggaran WHERE id = lama_id
  //   3. INSERT pelanggaran baru
  //   4. SantriModel::addPoin(santri_id, poin_baru)
  // Return lastInsertId pelanggaran baru

deleteById(int $id): bool
  // Sebelum hapus: ambil dulu poin dari JOIN
  // SantriModel::reducePoin(santri_id, poin)
  // Kemudian hapus record
```

### Sub-Phase 4.2 — Controller & Views Pelanggaran 5 Bidang

**File yang dibuat:**
- `app/Controllers/PelanggaranController.php`
- `views/pages/pelanggaran/index.php`
- `views/pages/pelanggaran/form_input.php`   ← Form bulk: pilih jenis + checkbox banyak santri
- `views/pages/pelanggaran/rekap_tabel.php`
- `views/pages/pelanggaran/detail_santri.php`

#### PelanggaranController

Route parameter `{bagian}` harus divalidasi: harus salah satu dari `['bahasa', 'diniyyah', 'kesantrian', 'tahfidz', 'pengabdian']`. Jika tidak valid → 404.

```php
index()            → dashboard menu 5 bidang + ringkasan count per bagian
rekap(string $bagian)
  → tabel rekap per bagian (requirePermission sesuai: rekap_view_bahasa, dll)
create(string $bagian)
  → form input bulk + live search santri (requirePermission: pelanggaran_{bagian}_input)
  → form ini menampilkan DAFTAR santri dengan checkbox, bukan single select
store(string $bagian)
  → validateCsrfToken()
  → requirePermission: pelanggaran_{bagian}_input
  → Ambil santri_ids[] dari POST (array), validasi
  → Jika bagian === 'bahasa':
      foreach $santriIds → PelanggaranModel::createBahasa() (Replace & Log)
  → Selain bahasa:
      PelanggaranModel::createBulk($santriIds, $jenisPelanggaranId, $tanggal, $userId)
  → logActivity + redirect
detail(string $bagian, int $santriId)
  → riwayat pelanggaran satu santri di bagian tertentu
edit(int $id)
  → form edit satu record
update(int $id)
  → update record, sesuaikan poin_aktif santri
delete(int $id)
  → PelanggaranModel::deleteById() (mengurangi poin_aktif otomatis)
searchSantri()
  → live search santri untuk field input HTMX, return partial HTML
```

**Checklist Phase 4 Selesai:**
- [ ] Form input menampilkan daftar santri dengan checkbox (bulk)
- [ ] Input pelanggaran semua 5 bagian berfungsi
- [ ] Input pelanggaran bahasa menjalankan mekanisme Replace & Log (cek log_bahasa ter-isi)
- [ ] `poin_aktif` santri bertambah saat pelanggaran dicatat
- [ ] `poin_aktif` santri berkurang saat pelanggaran dihapus
- [ ] Rekap tabel per bagian tampil dengan total poin
- [ ] Live search santri berfungsi saat input

---

## PHASE 5: PELANGGARAN KEBERSIHAN

### Sub-Phase 5.1 — Model & Controller Kebersihan

**File yang dibuat:**
- `app/Models/PelanggaranKebersihanModel.php`
- `app/Models/EksekusiKebersihanModel.php`
- `app/Controllers/PelanggaranKebersihanController.php`
- `app/Controllers/EksekusiController.php`
- `views/pages/pelanggaran-kebersihan/index.php`
- `views/pages/pelanggaran-kebersihan/form_input.php`
- `views/pages/pelanggaran-kebersihan/rekap_tabel.php`
- `views/pages/eksekusi/index.php`

> Kebersihan dicatat per kamar (VARCHAR), bukan per santri. Kolom `kamar` adalah VARCHAR(50) — binding harus string, bukan integer.

#### PelanggaranKebersihanController

```php
index()   → list pelanggaran kebersihan, filter per kamar/tanggal
create()  → form input (requirePermission: eksekusi_manage)
          → form menampilkan dropdown/input kamar (string)
store()   → simpan (kamar as VARCHAR, catatan, tanggal, dicatat_oleh) + logActivity
delete()  → hapus record
rekap()   → rekap per kamar, tampilkan heatmap atau tabel agregasi
```

#### EksekusiController

```php
index()          → list pelanggaran kebersihan yang belum dieksekusi
store(int $id)   → catat eksekusi: jenis_sanksi, catatan, tanggal
                   (requirePermission: eksekusi_manage)
```

**Checklist Phase 5 Selesai:**
- [ ] Input pelanggaran kebersihan per kamar berfungsi (kamar sebagai string)
- [ ] Rekap kebersihan per kamar tampil
- [ ] Catat eksekusi tindakan berfungsi

---

## PHASE 6: REWARD

### Sub-Phase 6.1 — Jenis Reward

**File yang dibuat:**
- `app/Models/JenisRewardModel.php`
- `app/Controllers/JenisRewardController.php`
- `views/pages/jenis-reward/index.php`
- `views/pages/jenis-reward/form.php`
- `views/pages/jenis-reward/bulk_edit.php`

#### JenisRewardModel

```php
protected string $table = 'jenis_reward';

// INGAT: nama kolom adalah `nama_reward` dan `poin_reward`, BUKAN `nama` dan `poin`
// Ada juga kolom `deskripsi`

findAll(array $where = [], ...): array
  // SELECT id, nama_reward, poin_reward, deskripsi FROM jenis_reward ...
```

Pola identik dengan JenisPelanggaranController. Method: index, create, store, edit, update, delete, bulkEdit, bulkUpdate, bulkDelete.

---

### Sub-Phase 6.2 — Input Reward

**File yang dibuat:**
- `app/Models/RewardModel.php`
- `app/Controllers/RewardController.php`
- `views/pages/reward/index.php`
- `views/pages/reward/form_input.php`
- `views/pages/reward/history.php`

#### RewardModel

```php
protected string $table = 'daftar_reward';  // ← NAMA TABEL DI DB ADALAH daftar_reward

getAll(): array
  // SELECT dr.*, s.nama as santri_nama, s.kamar,
  //        jr.nama_reward as jenis_nama, jr.poin_reward as poin  ← PAKAI nama_reward, poin_reward
  // FROM daftar_reward dr
  // JOIN santri s ON s.id = dr.santri_id
  // JOIN jenis_reward jr ON jr.id = dr.jenis_reward_id

getBySantri(int $santriId): array

getTotalPoinRewardSantri(int $santriId): int
  // SUM(jr.poin_reward) dengan JOIN ke jenis_reward WHERE dr.santri_id = ?  ← pakai poin_reward

create(array $data): int
  // INSERT ke daftar_reward
  // SETELAH INSERT: SantriModel::reducePoin(santri_id, poin_reward)  ← REWARD MENGURANGI poin_aktif
  // Return lastInsertId

deleteById(int $id): bool
  // Sebelum hapus: ambil poin_reward dari JOIN
  // SantriModel::addPoin(santri_id, poin_reward)  ← Hapus reward → kembalikan poin
  // Kemudian hapus record
```

#### RewardController

```php
index()        → list reward (requirePermission: reward_history)
create()       → form input (requirePermission: reward_input)
store()        → simpan ke daftar_reward + KURANGI poin_aktif santri + logActivity
delete(int $id) → hapus reward + KEMBALIKAN poin_aktif santri + logActivity
history()      → riwayat reward per santri, bisa filter
searchSantri() → live search santri
```

**Checklist Phase 6 Selesai:**
- [ ] Input reward berfungsi
- [ ] `poin_aktif` santri BERKURANG saat reward dicatat
- [ ] `poin_aktif` santri BERTAMBAH kembali saat reward dihapus
- [ ] Katalog jenis reward CRUD lengkap (pakai kolom `nama_reward` dan `poin_reward`)
- [ ] History reward per santri tampil

---

## PHASE 7: REKAP & ANALITIK

### Sub-Phase 7.1 — Model Rekap

**File yang dibuat:**
- `app/Models/RekapModel.php`

```php
// Semua query analitik — tidak ada $table property, semua pakai query()
// INGAT: gunakan jp.nama_pelanggaran (bukan jp.nama) dan jp.kategori (bukan jp.level)

getSummaryDashboard(): array
  // Count santri aktif, total pelanggaran bulan ini, total reward bulan ini

getDataHarian(string $bulanTahun): array
  // Data per hari dalam satu bulan (format YYYY-MM)
  // JOIN pelanggaran dengan jenis_pelanggaran, GROUP BY DATE(tanggal)

getPelanggaranUmum(string $awal, string $akhir): array
  // Agregasi semua bagian, GROUP BY jp.bagian, SUM poin

getRekap(string $bagian, string $awal, string $akhir): array

getKebersihanPerKamar(string $awal, string $akhir): array
  // Dari tabel pelanggaran_kebersihan, GROUP BY kamar

getKeterlambatan(string $awal, string $akhir): array
  // Filter jenis_pelanggaran.nama_pelanggaran LIKE '%terlambat%' atau '%telat%'

getTrenBulanan(int $tahun): array
  // GROUP BY MONTH(tanggal), untuk grafik tren

getSantriTeladan(int $maxPoin = 10): array
  // Santri dengan poin_aktif <= maxPoin

getTopPelanggaran(int $limit = 10): array
  // Jenis pelanggaran terbanyak, GROUP BY jenis_pelanggaran_id, pakai nama_pelanggaran

getRekapKarakter(string $awal, string $akhir): array
  // Rekap aspek kepengasuhan dari rapot_kepengasuhan
  // JOIN ke santri, filter tanggal via bulan+tahun
  // Untuk halaman rekap/karakter.php

getRekapPeringkatKamar(string $awal, string $akhir): array
  // Rekap pelanggaran kebersihan per kamar, GROUP BY kamar
  // Untuk halaman rekap/peringkat_kamar.php
```

### Sub-Phase 7.2 — Controller & Views Rekap

**File yang dibuat:**
- `app/Controllers/RekapController.php`
- Semua file di `views/pages/rekap/`

```php
index()               → dashboard rekap: summary cards + grafik mini
chart()               → halaman grafik interaktif Chart.js
pelanggaranUmum()     → rekap semua bagian (requirePermission: rekap_pelanggaran_umum)
detailPelanggaran()   → detail per bagian per santri (requirePermission: rekap_detail_santri)
kebersihan()          → rekap per kamar (requirePermission: rekap_kebersihan)
detailKebersihan()    → detail satu kamar
keterlambatan()       → rekap keterlambatan (requirePermission: rekap_keterlambatan)
tren()                → grafik tren bulanan
santriTeladan()       → list santri poin minimal (requirePermission: rekap_santri_teladan)
umum()                → ringkasan konklusif semua modul
karakter()            → rekap aspek kepengasuhan per santri (requirePermission: rekap_detail_santri)
detailKarakter(int $santriId) → detail aspek karakter satu santri (requirePermission: rekap_detail_santri)
peringkatKamar()      → peringkat kebersihan per kamar (requirePermission: rekap_kamar)
detailKamar(string $kamar) → detail kebersihan satu kamar (requirePermission: rekap_kamar)
getHarianData()       → [JSON] data harian untuk Chart.js (requirePermission: rekap_view_statistik)
```

**Checklist Phase 7 Selesai:**
- [ ] Dashboard rekap tampilkan summary stats
- [ ] Chart.js render grafik dari endpoint JSON
- [ ] Semua sub-halaman rekap tampil data yang benar
- [ ] Halaman karakter, detail_karakter tampil data aspek kepengasuhan
- [ ] Halaman peringkat_kamar, detail_kamar tampil data kebersihan
- [ ] Filter tanggal berfungsi

---

## PHASE 8: RAPOT KEPENGASUHAN

### Sub-Phase 8.1 — Model Rapot

**File yang dibuat:**
- `app/Models/RapotModel.php`

```php
protected string $table = 'rapot_kepengasuhan';  // ← NAMA TABEL DI DB

getAll(): array
  // JOIN ke santri untuk dapat nama, kelas, kamar

getBySantri(int $santriId): array

findBySantriDanBulan(int $santriId, string $bulan, int $tahun): array|false
  // WHERE santri_id = ? AND bulan = ? AND tahun = ?

create(array $data): int
  // $data harus include SEMUA 20 kolom aspek + tahun + bulan + snapshot poin
  // Kolom: puasa_sunnah, sholat_duha, sholat_malam, sedekah, sunnah_tidur,
  //        ibadah_lainnya, lisan, sikap, kesopanan, muamalah,
  //        tidur, keterlambatan, seragam, makan, arahan, bahasa_arab,
  //        mandi, penampilan, piket, kerapihan_barang,
  //        total_poin_pelanggaran_saat_itu, total_poin_reward_saat_itu,
  //        catatan_musyrif, bulan, tahun, santri_id, musyrif_id
```

### Sub-Phase 8.2 — Controller & Views Rapot

**File yang dibuat:**
- `app/Controllers/RapotController.php`
- Semua file di `views/pages/rapot/`

```php
index()                  → list rapot (requirePermission: rapot_view)
create(int $santriId)    → form buat rapot dengan 20 aspek + field catatan
                          → Pre-fill: ambil snapshot poin_aktif & total reward saat ini
store()                  → simpan rapot ke rapot_kepengasuhan dengan semua 20 kolom + tahun
                          → logActivity + redirect
preview(int $id)         → preview HTML rapot di browser
generatePdf(int $id)     → DomPDF: stream file PDF ke browser (requirePermission: rapot_cetak)
generatePng(int $id)     → convert ke PNG, kirim sebagai download
delete(int $id)          → hapus record + file fisik (requirePermission: rapot_delete)
bulkGenerate()           → generate rapot massal
bulkDelete()             → hapus massal
getPelanggaran(int $id)  → [JSON] pelanggaran satu santri untuk HTMX form
getReward(int $id)       → [JSON] reward satu santri untuk HTMX form
generateCatatan(int $id) → [JSON] teks catatan otomatis (requirePermission: catatan_otomatis)
```

#### Logika generateCatatan()

```
poin_aktif = 0        → teks pujian maksimal (variasi 3 kalimat, ambil random)
poin_aktif <= 20      → teks positif + sedikit nasihat
poin_aktif <= 50      → teks nasihat
poin_aktif <= 100     → teks peringatan
poin_aktif > 100      → teks peringatan keras
```

**Checklist Phase 8 Selesai:**
- [ ] Form rapot tampilkan semua 20 aspek kepengasuhan dengan input 0-5
- [ ] Preview rapot tampil dengan data benar
- [ ] Generate PDF bisa didownload
- [ ] Generate PNG bisa didownload
- [ ] Bulk generate tidak error

---

## PHASE 9: ARSIP

### Sub-Phase 9.1 — Model & Controller Arsip

**File yang dibuat:**
- `app/Models/ArsipModel.php`
- `app/Controllers/ArsipController.php`
- Semua file di `views/pages/arsip/`

#### ArsipModel

```php
// Tabel primer: arsip
// Tabel data: arsip_data_pelanggaran, arsip_data_pelanggaran_kebersihan, arsip_data_santri
// INGAT nama kolom yang benar di tabel-tabel arsip_data_*

getAll(): array
  // SELECT * FROM arsip ORDER BY id DESC

getById(int $id): array|false

getPelanggaranByArsip(int $arsipId): array
  // SELECT * FROM arsip_data_pelanggaran WHERE arsip_id = ?

getKebersihanByArsip(int $arsipId): array
  // SELECT id, arsip_id, kamar, catatan, tanggal, dicatat_oleh_user_id, dicatat_oleh_nama
  // FROM arsip_data_pelanggaran_kebersihan WHERE arsip_id = ?

getSantriByArsip(int $arsipId): array
  // SELECT id, arsip_id, santri_id, santri_nama, santri_kelas, santri_kamar, total_poin_saat_arsip
  // FROM arsip_data_santri WHERE arsip_id = ?

createArsip(string $judul, string $mulai, string $selesai): int
  // INSERT ke tabel arsip, return lastInsertId

snapshotPelanggaran(int $arsipId, string $mulai, string $selesai): int
  // INSERT INTO arsip_data_pelanggaran
  // SELECT arsipId, s.id, s.nama, s.kelas, s.kamar,
  //        jp.id, jp.nama_pelanggaran,  ← PAKAI nama_pelanggaran
  //        jp.bagian, jp.poin, p.tanggal, 'Umum'
  // FROM pelanggaran p
  // JOIN santri s ON s.id = p.santri_id
  // JOIN jenis_pelanggaran jp ON jp.id = p.jenis_pelanggaran_id
  // WHERE DATE(p.tanggal) BETWEEN ? AND ?
  // Return jumlah row yang disalin

snapshotKebersihan(int $arsipId, string $mulai, string $selesai): int
  // INSERT INTO arsip_data_pelanggaran_kebersihan
  //   (arsip_id, kamar, catatan, tanggal, dicatat_oleh_user_id, dicatat_oleh_nama)
  // SELECT ?, pk.kamar, pk.catatan, pk.tanggal, pk.dicatat_oleh, u.nama_lengkap
  // FROM pelanggaran_kebersihan pk
  // LEFT JOIN users u ON u.id = pk.dicatat_oleh
  // WHERE DATE(pk.tanggal) BETWEEN ? AND ?
  // ← KOLOM BENAR: dicatat_oleh_user_id, dicatat_oleh_nama (BUKAN jumlah/bagian)

snapshotSantri(int $arsipId): int
  // INSERT INTO arsip_data_santri
  //   (arsip_id, santri_id, santri_nama, santri_kelas, santri_kamar, total_poin_saat_arsip)
  // SELECT ?, id, nama, kelas, kamar, poin_aktif
  // FROM santri
  // ← KOLOM BENAR: santri_nama, santri_kelas, santri_kamar, total_poin_saat_arsip
```

#### ArsipController

```php
index()                  → list arsip (requirePermission: arsip_view)
create()                 → form buat arsip baru (requirePermission: arsip_create)
store()                  → jalankan proses snapshot (dalam 1 transaksi) + logActivity
                          → Setelah berhasil: redirect ke view(arsip_id)
view(int $id)            → landing satu arsip: judul, periode, stats jumlah data, link sub-halaman
pelanggaran(int $id)     → view data pelanggaran arsip tertentu
kebersihan(int $id)      → view data kebersihan arsip tertentu
santri(int $id)          → view snapshot santri (santri_nama, santri_kelas, santri_kamar, total_poin_saat_arsip)
detailPelanggaran(int $arsipId, int $santriId)
                         → detail pelanggaran satu santri dari arsip
delete(int $id)          → hapus arsip + semua data terkait (ON DELETE CASCADE otomatis)
                          + logActivity (requirePermission: arsip_delete)
export(int $id)          → export arsip ke Excel (requirePermission: arsip_export)
```

**Checklist Phase 9 Selesai:**
- [ ] Buat arsip → data ter-snapshot ke tabel arsip_data_* dengan nama kolom yang benar
- [ ] View arsip: landing page arsip tampil dengan stats
- [ ] View data pelanggaran, kebersihan, dan santri arsip lama tampil
- [ ] Export arsip ke Excel berfungsi
- [ ] Hapus arsip → CASCADE hapus semua arsip_data_*

---

## PHASE 10: EXPORT EXCEL

### Sub-Phase 10.1 — ExportHelper & Controller

**File yang dibuat:**
- `app/Helpers/ExportHelper.php`
- `app/Controllers/ExportController.php`
- `views/pages/export/index.php`

#### ExportHelper (PhpSpreadsheet)

```php
exportPelanggaran(array $data, string $judul): string
  // Generate file .xlsx sementara di uploads/
  // Return path file

exportSantri(array $data): string

exportRekap(array $data, string $judul): string

// Styling: header bold, alternating row color, auto-width kolom
// Sertakan kop Syathiby.jpg di baris paling atas (sesuai template lama)
```

#### ExportController

```php
index()    → form pilih jenis export + filter tanggal/kamar (requirePermission: export_laporan)
process()  → generate file + stream download + hapus file temp
```

**Checklist Phase 10 Selesai:**
- [ ] Export santri ke Excel berfungsi, bisa dibuka di Excel/LibreOffice
- [ ] Export pelanggaran dengan filter tanggal berfungsi
- [ ] File temp terhapus setelah download

---

## PHASE 11: PENGATURAN LENGKAP

### Sub-Phase 11.1 — User Management + Profil

**File yang dibuat:**
- `app/Controllers/PengaturanController.php` (mulai dibuat di sini)
- `app/Models/RoleModel.php`
- `views/pages/pengaturan/index.php`
- `views/pages/pengaturan/users.php`
- `views/pages/pengaturan/form_user.php`
- `views/pages/pengaturan/profil.php`

#### RoleModel

```php
// Tabel: roles dan role_permissions

getAllRoles(): array
  // SELECT * FROM roles ORDER BY id

getDefaultPermissionsByRole(string $role): array
  // SELECT permission_id FROM role_permissions WHERE role = ?
  // Return array of permission IDs
```

```php
index()         → landing pengaturan, link ke semua sub-menu
userIndex()     → list semua user (requirePermission: user_manage)
userCreate()    → form tambah user (dengan dropdown role dari tabel roles)
userStore()     → validasi + hash password (bcrypt) + simpan user
               → SETELAH simpan user, copy izin default:
                  INSERT INTO user_permissions (user_id, permission_id)
                  SELECT :userId, permission_id FROM role_permissions WHERE role = :role
               + logActivity
userEdit(int $id)    → form edit user
userUpdate(int $id)  → update user
                    → Jika role berubah: hapus user_permissions lama, copy dari role_permissions baru
                    → Jika password kosong: tidak ganti password
                    → AuthHelper::clearPermissionCache() jika update izin
                    + logActivity
userDelete(int $id)  → hapus user (hard delete atau soft delete sesuai logika lama)

// Fitur profil user sendiri (tidak butuh user_manage permission):
profilIndex()        → form edit profil user yang sedang login (username + ganti password)
                     → requireAuth() saja, tanpa requirePermission()
profilUpdate()       → validasi password lama + update username/password
                     → Verifikasi password lama dengan dual-verify (bcrypt + SHA-256)
                     → Hash password baru dengan bcrypt
                     + logActivity
```

> **Hash Password:** Selalu gunakan `password_hash($password, PASSWORD_DEFAULT)` (bcrypt) untuk password baru. Tidak perlu SHA-256 untuk user baru atau ganti password.

---

### Sub-Phase 11.2 — Manajemen Izin Per-User

**File yang dibuat:**
- `app/Models/PermissionModel.php`
- `views/pages/pengaturan/izin.php`
- Tambah method ke PengaturanController

#### PermissionModel

```php
// Tabel: permissions dan user_permissions

getAllPermissions(): array
  // SELECT * FROM permissions ORDER BY grup, id

getByUser(int $userId): array
  // SELECT p.* FROM permissions p
  // JOIN user_permissions up ON up.permission_id = p.id
  // WHERE up.user_id = ?

setUserPermissions(int $userId, array $permissionIds): bool
  // DELETE FROM user_permissions WHERE user_id = ?
  // INSERT INTO user_permissions (user_id, permission_id) VALUES (?, ?) untuk setiap ID
  // Gunakan transaction PDO

hasPermission(int $userId, string $namaIzin): bool
  // Versi non-static untuk dipakai di Model context (jarang dipakai, preferensikan session check)
```

```php
izinIndex()    → tampilkan grid: semua user vs semua permission (requirePermission: izin_manage)
               → Grouped by permission.grup
izinUpdate()   → simpan set izin per user via PermissionModel::setUserPermissions()
               → AuthHelper::clearPermissionCache() setelah update
               → logActivity
```

---

### Sub-Phase 11.3 — Periode, Reset Poin, Log, Backup

**File yang dibuat:**
- `views/pages/pengaturan/periode.php`
- `views/pages/pengaturan/reset_poin.php`
- `views/pages/pengaturan/log_aktifitas.php`
- `views/pages/pengaturan/backup_restore.php`
- `app/Models/PengaturanModel.php`
- `app/Models/LogAktifitasModel.php`
- `app/Models/LogResetPoinModel.php`
- Tambah method ke PengaturanController

```php
periodeIndex()    → tampilkan periode aktif saat ini + form ubah
periodeUpdate()   → UPDATE pengaturan SET nilai = ? WHERE nama = 'periode_aktif'
                   + logActivity

resetPoinIndex()  → form cari santri (requirePermission: reset_poin_manage)
resetPoinSearch() → live search santri HTMX
resetPoinProcess()→ UPDATE santri SET poin_aktif = 0 WHERE id = ?
                   + INSERT ke log_reset_poin:
                     (id_santri, tanggal_reset, total_poin_sebelum_reset, di_reset_oleh)
                     ← GUNAKAN NAMA KOLOM YANG BENAR
                   + logActivity

logIndex()        → tampilkan log_aktifitas, filter per user/aksi/tanggal
                   (requirePermission: activity_log_manage)
logClear()        → hapus log lama (> 30 hari), logActivity pembersihan

backupIndex()     → halaman backup/restore (requirePermission: backup_restore_manage)
backupExport()    → generate file .sql + download + logActivity
backupImport()    → upload .sql + eksekusi + logActivity
```

> **Catatan Backup:** Gunakan pendekatan pure PHP: `SHOW TABLES`, `SHOW CREATE TABLE`, `SELECT *` untuk export. Untuk import: baca file SQL dan eksekusi per statement. Catat limitasi di komentar kode.

> **Reset Poin — Nama Kolom Wajib:** Tabel `log_reset_poin` menggunakan `id_log` (primary key), `id_santri` (FK), `di_reset_oleh` (FK ke users). Jangan pakai `id`, `santri_id`, atau `reset_oleh`.

---

### Sub-Phase 11.4 — Impor Data (Excel/CSV)

**File yang dibuat:**
- `views/pages/pengaturan/impor_data.php`
- Tambah method ke PengaturanController

```php
imporIndex()    → halaman impor, tampilkan tombol download template + form upload
imporTemplate() → download template Excel kosong (format: nama, kelas, kamar)
imporPreview()  → upload file + parse + tampilkan preview data (dry-run, belum simpan)
                 → Validasi: header kolom cocok, tidak ada baris kosong, kamar/kelas valid
imporProcess()  → simpan data dari preview yang sudah divalidasi ke tabel santri
                 → Loop insert + logActivity batch
```

> Fitur ini memungkinkan admin mengimpor data santri baru secara massal dari file Excel atau CSV. Template bisa diunduh dari halaman yang sama.

**Checklist Phase 11 Selesai:**
- [ ] CRUD user berfungsi, password bcrypt
- [ ] User baru otomatis dapat izin default dari role_permissions
- [ ] Profil user (ganti password sendiri) berfungsi
- [ ] Grid izin per-user bisa diubah dan tersimpan
- [ ] Permission cache ter-clear setelah update izin
- [ ] Ganti periode aktif: nilai ter-update di tabel pengaturan
- [ ] Reset poin santri: poin_aktif jadi 0, tercatat di log_reset_poin dengan nama kolom benar
- [ ] Log aktivitas tampil dan bisa difilter
- [ ] Backup database bisa diunduh sebagai .sql
- [ ] Impor data santri dari Excel berfungsi

---

## PHASE 12: PWA POLISH

### Sub-Phase 12.1 — Service Worker & Manifest

**File yang dibuat/update:**
- `public/manifest.json`
- `public/sw.js`
- `views/errors/offline.php`

#### `manifest.json`
```json
{
  "name": "AsuhTrack",
  "short_name": "AsuhTrack",
  "start_url": "/",
  "display": "standalone",
  "background_color": "#ffffff",
  "theme_color": "#sesuai-warna-primary-di-style-css",
  "icons": [
    { "src": "/assets/img/logo_aplikasi.png", "sizes": "192x192", "type": "image/png" },
    { "src": "/assets/img/logo_aplikasi.png", "sizes": "512x512", "type": "image/png" }
  ]
}
```

#### `sw.js`
- Cache First untuk: CSS, JS, gambar di `/assets/`
- Network First untuk: semua URL halaman dan API
- Fallback ke `offline.php` jika request gagal dan tidak ada cache

**Checklist Phase 12 Selesai:**
- [ ] Aplikasi bisa di-install ke Home Screen HP Android (Chrome)
- [ ] Buka offline: tampil halaman offline custom, bukan error browser
- [ ] Assets (CSS/JS) ter-cache dan load lebih cepat saat revisit

---

## SEMUA ROUTE — REFERENSI LENGKAP

### Auth
```
GET  /login   → AuthController::loginPage
POST /login   → AuthController::loginProcess
GET  /logout  → AuthController::logout
```

### Dashboard
```
GET  /        → DashboardController::index
```

### Santri
```
GET  /santri                    → SantriController::index
GET  /santri/tambah             → SantriController::create
POST /santri/tambah             → SantriController::store
GET  /santri/detail/{id}        → SantriController::detail
GET  /santri/edit/{id}          → SantriController::edit
POST /santri/edit/{id}          → SantriController::update
POST /santri/hapus/{id}         → SantriController::delete
GET  /santri/cari               → SantriController::search
GET  /santri/bulk-tambah        → SantriController::bulkCreate
POST /santri/bulk-tambah        → SantriController::bulkStore
GET  /santri/bulk-edit          → SantriController::bulkEdit
POST /santri/bulk-edit          → SantriController::bulkUpdate
POST /santri/bulk-hapus         → SantriController::bulkDelete
```

### Jenis Pelanggaran
```
GET  /jenis-pelanggaran               → JenisPelanggaranController::index
GET  /jenis-pelanggaran/tambah        → JenisPelanggaranController::create
POST /jenis-pelanggaran/tambah        → JenisPelanggaranController::store
GET  /jenis-pelanggaran/edit/{id}     → JenisPelanggaranController::edit
POST /jenis-pelanggaran/edit/{id}     → JenisPelanggaranController::update
POST /jenis-pelanggaran/hapus/{id}    → JenisPelanggaranController::delete
GET  /jenis-pelanggaran/bulk-edit     → JenisPelanggaranController::bulkEdit
POST /jenis-pelanggaran/bulk-edit     → JenisPelanggaranController::bulkUpdate
```

### Pelanggaran (5 Bidang)
```
GET  /pelanggaran                             → PelanggaranController::index
GET  /pelanggaran/{bagian}                    → PelanggaranController::rekap
GET  /pelanggaran/{bagian}/input              → PelanggaranController::create
POST /pelanggaran/{bagian}/input              → PelanggaranController::store
GET  /pelanggaran/{bagian}/detail/{santriId}  → PelanggaranController::detail
GET  /pelanggaran/edit/{id}                   → PelanggaranController::edit
POST /pelanggaran/edit/{id}                   → PelanggaranController::update
POST /pelanggaran/hapus/{id}                  → PelanggaranController::delete
GET  /pelanggaran/cari-santri                 → PelanggaranController::searchSantri
```

### Pelanggaran Kebersihan
```
GET  /kebersihan              → PelanggaranKebersihanController::index
GET  /kebersihan/input        → PelanggaranKebersihanController::create
POST /kebersihan/input        → PelanggaranKebersihanController::store
POST /kebersihan/hapus/{id}   → PelanggaranKebersihanController::delete
GET  /kebersihan/rekap        → PelanggaranKebersihanController::rekap
```

### Eksekusi Kebersihan
```
GET  /eksekusi                → EksekusiController::index
POST /eksekusi/catat/{id}     → EksekusiController::store
```

### Reward
```
GET  /reward                  → RewardController::index
GET  /reward/input            → RewardController::create
POST /reward/input            → RewardController::store
POST /reward/hapus/{id}       → RewardController::delete
GET  /reward/history          → RewardController::history
GET  /reward/cari-santri      → RewardController::searchSantri
```

### Jenis Reward
```
GET  /jenis-reward                  → JenisRewardController::index
GET  /jenis-reward/tambah           → JenisRewardController::create
POST /jenis-reward/tambah           → JenisRewardController::store
GET  /jenis-reward/edit/{id}        → JenisRewardController::edit
POST /jenis-reward/edit/{id}        → JenisRewardController::update
POST /jenis-reward/hapus/{id}       → JenisRewardController::delete
POST /jenis-reward/bulk-hapus       → JenisRewardController::bulkDelete
GET  /jenis-reward/bulk-edit        → JenisRewardController::bulkEdit
POST /jenis-reward/bulk-edit        → JenisRewardController::bulkUpdate
```

### Rekap & Analitik
```
GET  /rekap                       → RekapController::index
GET  /rekap/chart                 → RekapController::chart
GET  /rekap/pelanggaran-umum      → RekapController::pelanggaranUmum
GET  /rekap/detail-pelanggaran    → RekapController::detailPelanggaran
GET  /rekap/kebersihan            → RekapController::kebersihan
GET  /rekap/detail-kebersihan     → RekapController::detailKebersihan
GET  /rekap/keterlambatan         → RekapController::keterlambatan
GET  /rekap/tren                  → RekapController::tren
GET  /rekap/santri-teladan        → RekapController::santriTeladan
GET  /rekap/umum                  → RekapController::umum
GET  /rekap/karakter              → RekapController::karakter              [permission: rekap_detail_santri]
GET  /rekap/karakter/{santriId}   → RekapController::detailKarakter       [permission: rekap_detail_santri]
GET  /rekap/peringkat-kamar       → RekapController::peringkatKamar       [permission: rekap_kamar]
GET  /rekap/detail-kamar/{kamar}  → RekapController::detailKamar          [permission: rekap_kamar]
GET  /rekap/data-harian           → RekapController::getHarianData        [JSON, permission: rekap_view_statistik]
```

### Rapot
```
GET  /rapot                              → RapotController::index
GET  /rapot/buat/{santriId}              → RapotController::create
POST /rapot/buat/{santriId}              → RapotController::store
GET  /rapot/preview/{id}                 → RapotController::preview
GET  /rapot/pdf/{id}                     → RapotController::generatePdf    [PDF stream]
GET  /rapot/png/{id}                     → RapotController::generatePng    [file download]
POST /rapot/hapus/{id}                   → RapotController::delete
POST /rapot/bulk-buat                    → RapotController::bulkGenerate
POST /rapot/bulk-hapus                   → RapotController::bulkDelete
GET  /rapot/data-pelanggaran/{santriId}  → RapotController::getPelanggaran [JSON]
GET  /rapot/data-reward/{santriId}       → RapotController::getReward      [JSON]
GET  /rapot/catatan/{santriId}           → RapotController::generateCatatan [JSON]
```

### Arsip
```
GET  /arsip                              → ArsipController::index
GET  /arsip/buat                         → ArsipController::create
POST /arsip/buat                         → ArsipController::store
GET  /arsip/view/{id}                    → ArsipController::view            [landing satu arsip]
GET  /arsip/pelanggaran/{id}             → ArsipController::pelanggaran
GET  /arsip/kebersihan/{id}              → ArsipController::kebersihan
GET  /arsip/santri/{id}                  → ArsipController::santri          [snapshot santri]
GET  /arsip/detail/{arsipId}/{santriId}  → ArsipController::detailPelanggaran
POST /arsip/hapus/{id}                   → ArsipController::delete
GET  /arsip/export/{id}                  → ArsipController::export
```

### Export Excel
```
GET  /export         → ExportController::index
POST /export/proses  → ExportController::process
```

### Pengaturan
```
GET  /pengaturan                              → PengaturanController::index
GET  /pengaturan/users                        → PengaturanController::userIndex
GET  /pengaturan/users/tambah                 → PengaturanController::userCreate
POST /pengaturan/users/tambah                 → PengaturanController::userStore
GET  /pengaturan/users/edit/{id}              → PengaturanController::userEdit
POST /pengaturan/users/edit/{id}              → PengaturanController::userUpdate
POST /pengaturan/users/hapus/{id}             → PengaturanController::userDelete
GET  /pengaturan/profil                       → PengaturanController::profilIndex
POST /pengaturan/profil                       → PengaturanController::profilUpdate
GET  /pengaturan/izin                         → PengaturanController::izinIndex
POST /pengaturan/izin                         → PengaturanController::izinUpdate
GET  /pengaturan/periode                      → PengaturanController::periodeIndex
POST /pengaturan/periode                      → PengaturanController::periodeUpdate
GET  /pengaturan/reset-poin                   → PengaturanController::resetPoinIndex
GET  /pengaturan/reset-poin/cari              → PengaturanController::resetPoinSearch
POST /pengaturan/reset-poin                   → PengaturanController::resetPoinProcess
GET  /pengaturan/log                          → PengaturanController::logIndex
POST /pengaturan/log/bersihkan                → PengaturanController::logClear
GET  /pengaturan/backup                       → PengaturanController::backupIndex
POST /pengaturan/backup/export                → PengaturanController::backupExport
POST /pengaturan/backup/import                → PengaturanController::backupImport
GET  /pengaturan/impor-data                   → PengaturanController::imporIndex
GET  /pengaturan/impor-data/template          → PengaturanController::imporTemplate
POST /pengaturan/impor-data/preview           → PengaturanController::imporPreview
POST /pengaturan/impor-data/proses            → PengaturanController::imporProcess
```

---

## KEAMANAN

### XSS Prevention
```php
// Di View, SELALU pakai h() untuk output variabel dari DB atau user:
<?= h($santri['nama']) ?>
<?= h($user['nama_lengkap']) ?>
<?= h($jenisPelanggaran['nama_pelanggaran']) ?>  ← ingat nama kolom yang benar
```

### SQL Injection
```php
// BENAR — prepared statement PDO:
$stmt = $pdo->prepare("SELECT * FROM santri WHERE nama LIKE ?");
$stmt->execute(['%' . $keyword . '%']);

// SALAH — string concat (dilarang keras):
$pdo->query("SELECT * FROM santri WHERE nama LIKE '%" . $keyword . "%'");
```

### CSRF Token
- Generate di `Controller::generateCsrfToken()`, simpan di `$_SESSION['csrf_token']`
- Inject ke setiap form `<input type="hidden" name="csrf_token">`
- Auto-inject ke HTMX request via header di `app.js`
- Validasi di `Controller::validateCsrfToken()` di setiap POST handler

### Inactivity Timeout
- Setiap request: cek `time() - $_SESSION['login_time'] > 10800`
- Jika expired: `session_destroy()` + redirect ke `/login`
- Jika aktif: update `$_SESSION['login_time'] = time()`

---

## ROADMAP RINGKASAN

| Phase | Sub-Phase | Scope | Output Utama |
|---|---|---|---|
| 1 | 1.1 | Entry point & konfigurasi | `public/index.php`, `.htaccess`, `.env`, `composer.json` |
| 1 | 1.2 | Core classes | `Database.php`, `Model.php`, `Controller.php` |
| 1 | 1.3 | Router | `Router.php` |
| 1 | 1.4 | Helpers | `AuthHelper.php` (session-based), `FormatHelper.php` |
| 1 | 1.5 | Layout SPA + Auth | `main.php`, sidebar, `AuthController.php` (dual-verify password) |
| 1 | 1.6 | Dashboard | `DashboardController.php` |
| 2 | 2.1 | Santri CRUD | `SantriModel.php` (addPoin/reducePoin), `SantriController.php` |
| 2 | 2.2 | Santri Bulk ops | Bulk create/edit/delete |
| 3 | 3.1 | Jenis Pelanggaran CRUD | `JenisPelanggaranController.php` (kolom: nama_pelanggaran, kategori) |
| 3 | 3.2 | Bulk edit poin | Bulk update views |
| 4 | 4.1 | Model Pelanggaran | `PelanggaranModel.php` (createBulk + createBahasa) |
| 4 | 4.2 | Controller 5 bidang | `PelanggaranController.php` (bulk input, Bahasa replace & log) |
| 5 | 5.1 | Kebersihan + Eksekusi | `PelanggaranKebersihanController.php` (kamar: VARCHAR), `EksekusiController.php` |
| 6 | 6.1 | Katalog Reward | `JenisRewardController.php` (kolom: nama_reward, poin_reward) |
| 6 | 6.2 | Input Reward | `RewardController.php` (reward MENGURANGI poin_aktif) |
| 7 | 7.1 | Model Rekap | `RekapModel.php` (termasuk getRekapKarakter, getRekapPeringkatKamar) |
| 7 | 7.2 | Controller Rekap + Charts | `RekapController.php` (termasuk karakter, peringkat_kamar) |
| 8 | 8.1 | Model Rapot | `RapotModel.php` (tabel: rapot_kepengasuhan, 20 kolom aspek) |
| 8 | 8.2 | Controller Rapot + DomPDF | `RapotController.php` (handle semua kolom rapot) |
| 9 | 9.1 | Arsip | `ArsipController.php` (nama kolom benar, view + santri halaman) |
| 10 | 10.1 | Export Excel | `ExportHelper.php`, `ExportController.php` |
| 11 | 11.1 | User Management + Profil | User CRUD + role_permissions copy + profil user sendiri |
| 11 | 11.2 | Manajemen Izin Per-User | `PermissionModel.php`, grid izin UI |
| 11 | 11.3 | Periode, Reset Poin, Log, Backup | Semua sub-fitur Pengaturan (nama kolom log_reset_poin benar) |
| 11 | 11.4 | Impor Data | Import santri dari Excel/CSV |
| 12 | 12.1 | PWA Polish | `sw.js`, `manifest.json` |

---

*PRD v3.1.0 — AsuhTrack MVC Rewrite*
*Changelog dari v3.0: (1) Koreksi nama kolom kritis: `jenis_pelanggaran` pakai `nama_pelanggaran`+`kategori`, `jenis_reward` pakai `nama_reward`+`poin_reward`, `arsip_data_santri` pakai `santri_nama/santri_kelas/santri_kamar/total_poin_saat_arsip`, `arsip_data_pelanggaran_kebersihan` hapus `jumlah`+`bagian` ganti `dicatat_oleh_user_id`+`dicatat_oleh_nama`, `log_reset_poin` pakai `id_log`+`id_santri`+`di_reset_oleh`. (2) Tambah tabel `roles` dan `role_permissions` dengan logika copy izin default saat buat user. (3) Koreksi `rapot_kepengasuhan` dari 5 kolom menjadi 28 kolom lengkap. (4) Koreksi `pelanggaran_kebersihan.kamar` dari INT ke VARCHAR(50). (5) Koreksi logika bisnis: reward MENGURANGI `poin_aktif`, pelanggaran bahasa pakai Replace & Log, input pelanggaran adalah bulk (array santri_ids). (6) Koreksi password: bcrypt dual-verify dengan auto-upgrade dari SHA-256. (7) Koreksi AuthHelper: session array-based, bukan query DB per cek, admin bypass. (8) Koreksi session keys: `flash_message` (bukan `flash`), tambah `permissions` array dan `login_time`. (9) SESSION_LIFETIME dikoreksi ke 10800 (3 jam). (10) Tambah 4 halaman rekap yang hilang: karakter, detail_karakter, peringkat_kamar, detail_kamar. (11) Tambah permission ID 54 `rekap_kamar`. (12) Tambah fitur profil user dan impor data. (13) Tambah view arsip/view.php dan arsip/santri.php.*
