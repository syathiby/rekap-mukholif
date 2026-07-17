<div align="center">
  <h1>Aplikasi Rekapitulasi Pelanggaran & Kedisiplinan Asrama (AsuhTrack)</h1>
  <p><em>Sistem Informasi Manajemen Rapor Kepengasuhan, Kedisiplinan Santri, dan Poin Apresiasi</em></p>
</div>

---

## 📖 Tentang Proyek
Aplikasi Rekapitulasi Pelanggaran & Kedisiplinan (AsuhTrack) adalah sebuah *Web-based System* komprehensif yang dirancang secara khusus untuk memfasilitasi pencatatan, pemantauan, dan analisis kedisiplinan serta ibadah harian para peserta didik (santri) di lingkungan asrama/pesantren.

Sistem ini digunakan oleh **Musyrif (Pembina Asrama), Dewan Guru, dan Administrator** untuk menggantikan pencatatan buku saku manual menjadi sistem digital yang terpusat. Aplikasi ini mengolah ribuan titik data harian menjadi rapor evaluasi dan papan peringkat (leaderboards) untuk memantau perilaku, pelanggaran, hingga apresiasi positif (reward).

## ✨ Fitur & Modul Utama
- **Manajemen Poin Dinamis**: Input poin pelanggaran harian (Bahasa, Kesantrian, Tahfidz, Diniyyah, Pengabdian) dan pemberian *reward* (penghargaan) secara fleksibel.
- **Rapor Kepengasuhan Terintegrasi**: Generator otomatis nilai rata-rata karakter, ibadah, akhlak, dan kebersihan. Mendukung Rapor Bulanan dan Rapor Tahunan yang dilengkapi deskripsi capaian (terintegrasi dengan AI untuk *generate* catatan).
- **Ekspor Dokumen Tingkat Lanjut**: Fitur pencetakan massal (bulk) dokumen ke format **PDF** resmi ber-kop surat, maupun gambar **PNG** praktis untuk dibagikan ke wali santri via WhatsApp, serta ekspor rekap data ke *Spreadsheet* Excel.
- **Papan Peringkat (Leaderboards)**: Analitik visual (menggunakan Chart.js) untuk melihat data asrama/kamar terbersih, terdisiplin, hingga grafik tren pelanggaran per bulan.
- **Role-Based Access Control (RBAC)**: Sistem manajemen peran (Admin, Asatidz, Musyrif) tingkat lanjut di mana setiap tombol, fitur, dan akses kamar diatur secara granular dan aman.
- **Historical Data Archiving (Tutup Buku)**: Algoritma *Snapshot & Denormalisasi* cerdas untuk mem-backup rapot tiap akhir tahun ajaran dengan jaminan keutuhan memori sejarah tanpa membebani tabel aktif.
- **Utilitas Sistem**: Fitur *Backup-Restore* database, Import massal santri via Excel cerdas, dan *Audit Log* (riwayat aktivitas) untuk memonitor setiap aksi yang dilakukan pengguna.

---

## 🛠 Fundamental Kode & Arsitektur
Aplikasi ini dibangun dengan mengutamakan performa, kemudahan maintenance, dan low-overhead.

- **Stack Teknologi**: PHP Native (Monolithic) + MySQL / MariaDB + Bootstrap 5 + Vanilla JS (AJAX Fetch API). Dependensi minimal via Composer.
- **Desain Database**: Mengkombinasikan relasi *Strict Normalized* untuk operasional harian, dan tabel *Denormalized* untuk penyimpanan arsip rapot guna mempertahankan integritas sejarah (meski akun master dihapus).
- **Routing & Pemuatan (Inclusion)**: Menggunakan arsitektur *Absolute Pathing* (`__DIR__` base inclusion) via *entry point* `bootstrap/init.php` untuk memastikan aplikasi kebal terhadap *Path Traversal* dan portabel.
- **Keamanan Lapis Ganda**: 
  - Validasi token CSRF pada setiap form (termasuk *bulk action*).
  - Isolasi direktori dan *Guard* sesi dinamis per-halaman.
  - Penolakan SQL Injection melalui penerapan *Prepared Statements* (PDO/MySQLi).

---

## 🚀 Panduan Instalasi (Development)

Bagi pengembang yang ditugaskan untuk mengelola proyek ini secara lokal:

### 1. Kloning dan Siapkan Lingkungan
Pastikan menggunakan PHP versi 8.0+ dan MySQL/MariaDB. Buka terminal di direktori proyek dan instal pustaka (dependencies) via Composer:
```bash
composer install
```
*(Catatan: Semua dependensi pihak ketiga, seperti mPDF untuk cetak rapor dan PhpSpreadsheet untuk export Excel, dikelola via Composer)*

### 2. Konfigurasi Variabel Lingkungan (`.env`)
Buat sebuah file baru di root direktori dengan nama `.env` (diawali dengan titik). Anda dapat menggunakan file konfigurasi contoh (jika ada) dan menyesuaikan kredensialnya. **PERHATIAN: Jangan pernah meng-commit file `.env` asli ke sistem kontrol versi.**

```env
DB_HOST=localhost
DB_USER=root
DB_PASSWORD=secret_password
DB_NAME=db_mukholif
```

### 3. Konfigurasi Sistem Utama
Semua pengaturan inti (konstanta aplikasi, timezone, dsb) berada di folder `config/`. File `config/database.php` bertugas membaca kredensial `.env` secara aman untuk koneksi database.

---

## 🔒 Privasi & Lisensi
Proyek ini bersifat **Internal, Tertutup, & Terbatas (Proprietary/Confidential)**. Sistem ini menampung data profil, riwayat pelanggaran, dan catatan pribadi santri yang bersifat **SANGAT SENSITIF**. 

> **Dilarang keras mempublikasikan/mendistribusikan salinan *source code*, file *dump* database, konfigurasi server asli, maupun struktur kredensial ini ke repositori publik, pihak ketiga, atau publik secara utuh tanpa izin tertulis dan resmi dari instansi pengelola asrama terkait.**

*Dikelola dan dipelihara dengan dedikasi tinggi demi memajukan sistem pendidikan asrama yang modern, transparan, dan beradab.*