<div align="center">
  <h1>Aplikasi Rekapitulasi Pelanggaran & Kedisiplinan Asrama</h1>
  <p><em>Sistem Informasi Manajemen Rapor Kepengasuhan dan Mutu Kedisiplinan Santri</em></p>
</div>

---

## 📖 Tentang Proyek
Aplikasi Rekapitulasi Pelanggaran & Kedisiplinan (Rekap Mukholif) adalah sebuah *Web-based System* komprehensif yang dirancang secara khusus untuk memfasilitasi pencatatan, pemantauan, dan analisis kedisiplinan serta ibadah harian para peserta didik (santri) di lingkungan asrama/pesantren.

Sistem ini digunakan oleh **Musyrif (Pembina Asrama), Dewan Guru, dan Administrator** untuk menggantikan pencatatan buku saku manual menjadi sistem digital yang terpusat. Aplikasi ini mengolah ribuan titik data harian menjadi rapor evaluasi dan papan peringkat (leaderboards) untuk memantau perilaku, pelanggaran, hingga apresiasi positif.

## ✨ Fitur Utama
- **Manajemen Poin Dinamis**: Input poin pelanggaran harian (Bahasa, Kesantrian, Tahfidz, Diniyyah, dll) dan pemberian *reward* (penghargaan).
- **Rapor Kepengasuhan Bulanan**: Generator otomatis nilai rata-rata karakter, ibadah, akhlak, dan kebersihan dalam wujud grafik radar dan PDF.
- **Papan Peringkat (Leaderboards)**: Statistik data asrama/kamar terbersih, terdisiplin, hingga grafik tren pelanggaran per bulan.
- **Role-Based Access Control (RBAC)**: Sistem otoritas granular dan terpusat (Admin, Asatidz, Musyrif) yang dilengkapi pengaman *Foreign Key ON DELETE CASCADE*.
- **Historical Data Archiving (Tutup Buku)**: Algoritma *Snapshot & Denormalisasi* cerdas untuk mem-backup rapot tiap akhir tahun ajaran dengan jaminan keutuhan sejarah (sekalipun data master dihapus).

---

## 🛠 Fundamental Kode & Arsitektur
Aplikasi ini dikembangkan dengan pendekatan kokoh, mengutamakan kecepatan eksekusi (low-overhead) dan keandalan data lintas-waktu. 

- **Stack**: PHP Native (Monolithic) + MySQL / MariaDB + Bootstrap 5 + Vanilla JS (AJAX Fetch API).
- **Desain Database**: Mengkombinasikan relasi *Strict Normalized* untuk operasional harian, dan tabel *Denormalized* untuk penyimpanan arsip rapot guna mempertahankan integritas memori sejarah.
- **Routing & Inclusion**: Menggunakan pendekatan *Absolute Pathing* (`__DIR__` base inclusion) via `bootstrap/init.php` untuk memastikan aplikasi kebal terhadap *Path Traversal* dan berjalan mulus lintas *environment* (Windows/Linux/XAMPP/Laragon).
- **Keamanan**: Dilengkapi perlindungan CSRF, validasi sesi dinamis, dan perlindungan SQL Injection via Prepared Statements.

---

## 🚀 Panduan Instalasi (Development)

Bagi pengembang (developer) yang ingin menjalankan proyek ini secara lokal:

### 1. Kloning dan Siapkan Lingkungan
Pastikan Anda menggunakan PHP versi 8.0+ dan sistem basis data MySQL/MariaDB. Buka terminal di direktori proyek dan instal pustaka (dependencies) via Composer:
```bash
composer install
```
*(Catatan: Library `vlucas/phpdotenv` digunakan secara eksklusif untuk parsing variabel `.env`)*

### 2. Konfigurasi Variabel Lingkungan (`.env`)
Buat sebuah file baru di root direktori dengan nama `.env` (diawali dengan titik). Salin parameter berikut dan sesuaikan kredensialnya dengan database Anda:

```env
DB_HOST=localhost
DB_USER=root
DB_PASSWORD=secret_password
DB_NAME=db_mukholif
```

### 3. Konfigurasi Sistem Utama
Semua pengaturan inti berada di folder `config/`. File `config/database.php` bertugas mengolah kredensial `.env` menjadi koneksi `mysqli` maupun `PDO` yang aman.

---

## 🔒 Privasi & Lisensi
Proyek ini bersifat **Internal & Tertutup (Proprietary/Confidential)**. Sistem ini menampung data riwayat santri yang sangat sensitif. Dilarang keras mempublikasikan/mendistribusikan salinan *source code* ini ke repositori publik secara utuh tanpa izin resmi dari instansi pengelola asrama terkait.

> *Dikelola dan dipelihara dengan dedikasi tinggi demi memajukan sistem pendidikan asrama yang modern, transparan, dan beradab.*