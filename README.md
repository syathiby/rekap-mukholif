<div align="center">

  # 🌟 AsuhTrack — Sistem Manajemen Kedisiplinan & Rapor Kepengasuhan Santri
  
  <p align="center">
    <strong>Platform Terintegrasi untuk Monitoring Kedisiplinan, Rekapitulasi Pelanggaran, Poin Apresiasi (Reward), dan Evaluasi Karakter Santri Berbasis Web</strong>
  </p>

  <p align="center">
    <img src="https://img.shields.io/badge/PHP-8.0%2B-777BB4?style=flat&logo=php&logoColor=white" height="22" alt="PHP 8.0+">
    <img src="https://img.shields.io/badge/MySQL-8.0%2B-4479A1?style=flat&logo=mysql&logoColor=white" height="22" alt="MySQL">
    <img src="https://img.shields.io/badge/Bootstrap-5.3-7952B3?style=flat&logo=bootstrap&logoColor=white" height="22" alt="Bootstrap 5">
    <img src="https://img.shields.io/badge/Chart.js-Analytics-FF6384?style=flat&logo=chartdotjs&logoColor=white" height="22" alt="Chart.js">
    <img src="https://img.shields.io/badge/Security-RBAC%20v2.0%20%2B%20CSRF%20Guard-28a745?style=flat&logo=shield" height="22" alt="Security RBAC">
  </p>

</div>

---

## 📌 Ringkasan Sistem

**AsuhTrack** adalah ekosistem digital terpadu yang dirancang khusus untuk mentransformasi manajemen kepengasuhan di lingkungan pondok pesantren dan asrama modern. Sistem ini menggantikan proses pembukuan konvensional dengan sistem pemantauan terpusat, real-time, dan akuntabel.

Platform ini memproses ribuan interaksi data kedisiplinan harian dari berbagai divisi pembinaan santri menjadi wawasan terukur, rapor kepengasuhan berkala, matriks perkembangan adab & ibadah, serta papan peringkat (*leaderboards*) kedisiplinan.

---

## 🚀 Fitur Unggulan & Modul Utama

```
                      ┌─────────────────────────────────────────┐
                      │          ASUHTRACK ECOSYSTEM            │
                      └────────────────────┬────────────────────┘
                                           │
         ┌───────────────────┬─────────────┴───────┬───────────────────┐
         ▼                   ▼                     ▼                   ▼
┌─────────────────┐ ┌─────────────────┐ ┌─────────────────┐ ┌─────────────────┐
│ 📝 Pelanggaran  │ │ 🏆 Prestasi &   │ │ 📊 Rapor &      │ │ 🔐 Granular     │
│   & Kedisiplinan│ │   Poin Reward   │ │   Analytics     │ │   RBAC v2.0     │
└─────────────────┘ └─────────────────┘ └─────────────────┘ └─────────────────┘
```

### 1. ⚖️ Manajemen Kedisiplinan Multi-Divisi
* **Kategorisasi Berlapis**: Pencatatan pelanggaran terstruktur lintas divisi (**Kesantrian**, **Bahasa**, **Tahfidz**, **Diniyyah**, dan **Pengabdian**).
* **Klasifikasi Bobot**: Penilaian otomatis kategori *Ringan*, *Sedang*, *Berat*, hingga *Sangat Berat* dengan kalkulasi penalti poin otomatis.
* **Audit Trail Pencatat**: Setiap pencatatan pelanggaran terikat langsung ke identitas staf/musyrif yang mencatat demi transparansi data.

### 2. 🌟 Sistem Reward & Apresiasi Positif
* **Penyeimbang Poin**: Mengakomodasi poin reward atas prestasi akademik, keteladanan ibadah, dan kebersihan.
* **Kalkulasi Poin Bersih**: Sistem secara dinamis menghitung *Net Points* santri (Poin Pelanggaran - Poin Prestasi) dalam periode aktif.

### 3. 📈 Analitik Visual & Tren Pelanggaran
* **Tren Harian & Mingguan**: Grafik interaktif (*Chart.js*) pemantauan frekuensi kedisiplinan secara real-time.
* **Top Pelanggaran & Komposisi Bagian**: Identifikasi jenis pelanggaran paling sering terjadi untuk mempermudah evaluasi kebijakan asrama.
* **Peringkat Kamar & Kebersihan**: Pemeringkatan kamar terbaik dan terbersih guna mendorong lingkungan asrama yang kompetitif dan positif.

### 4. 📄 Generator Rapor & Multi-Format Export
* **Rapor Bulanan & Tahunan**: Evaluasi berkala mencakup aspek Ibadah, Kedisiplinan, Kebersihan, dan Adab Santri.
* **AI-Assisted Remarks**: Pembuatan catatan deskripsi perkembangan santri yang terpersonalisasi.
* **Dokumen PDF Resmi**: Cetak dokumen rapor ber-kop surat resmi instansi (*mPDF engine*).
* **Format Gambar PNG**: Export kartu evaluasi ringkas format gambar yang siap dibagikan ke wali santri via WhatsApp.
* **Spreadsheet Reporting**: Export laporan berkala dan arsip tahunan ke Microsoft Excel (*PhpSpreadsheet*).

### 5. 🏛️ Pengarsipan Sejarah (Historical Archiving / Tutup Buku)
* **Snapshot & Denormalisasi Data**: Algoritma tutup buku tahun ajaran yang mengabadikan rekapitulasi nilai, nama pembina, dan histori santri tanpa ketergantungan relasi master aktif.
* **Perlindungan Arsip Log**: Retensi data log pelanggaran dan riwayat bahasa tahun ajaran terdahulu yang tetap dapat diakses kapan saja.

### 6. 🔐 Granular RBAC v2.0 (Role-Based Access Control)
* **Master Hak Akses**: Manajemen matriks izin bawaan untuk berbagai role (*Admin/Developer*, *Admin Kesantrian*, *Divisi Bahasa*, *Divisi Diniyyah*, *Divisi Tahfidz*, *Musyrif*, *Pelihat*, dan *Pengelola*).
* **Smart Permission Overrides**: Kemampuan memberikan izin khusus (*Allow*) atau mencabut izin tertentu (*Deny*) per individu pengguna secara spesifik.

---

## 🛠️ Arsitektur & Teknologi

| Komponen | Teknologi / Library | Keterangan |
| :--- | :--- | :--- |
| **Backend Engine** | PHP 8.0+ (Native OOP & Procedural Hybrid) | Ringan, cepat, low-overhead, dan bebas dari framework bloatware |
| **Database** | MySQL / MariaDB (InnoDB Engine) | Strict Normalization untuk operasional aktif & Denormalized Snapshot untuk arsip |
| **Frontend Styling** | Bootstrap 5.3 + FontAwesome 6 | Desain modern, responsif desktop & mobile, tema Royal Blue profesional |
| **Visualisasi Data** | Chart.js + ChartDataLabels Plugin | Diagram batang, garis tren, dan donat komposisi interaktif |
| **PDF Generation** | mPDF | Pembuatan dokumen cetak rapor ukuran standar A4/F4 berstandar percetakan |
| **Excel Generation** | PhpSpreadsheet | Pengolahan export/import data tabular berkecepatan tinggi |
| **Arsitektur File** | Absolute Pathing (`bootstrap/init.php`) | Portabel, aman dari serangan *Local File Inclusion* (LFI) & *Path Traversal* |

---

## 🛡️ Standar Keamanan Sistem

* **Perlindungan Injeksi SQL**: 100% interaksi database dinamis menggunakan *Prepared Statements* (MySQLi).
* **CSRF Shield**: Penerapan token kriptografis anti-CSRF pada setiap pengiriman formulir dan aksi batch.
* **Proteksi Sesi & Otorisasi Ketat**: Validasi hak akses (`guard()`) di setiap gerbang halaman sebelum script dirender.
* **Audit Logging**: Pencatatan aktivitas pengguna secara otomatis (login, manipulasi data, perubahan hak akses, hingga ekspor laporan).
* **Isolasi Lingkungan**: Variabel koneksi dan konfigurasi server dipisahkan secara aman menggunakan file `.env`.

---

## 🔒 Privasi & Lisensi

Proyek ini merupakan perangkat lunak berlisensi **Tertutup & Hak Milik Khusus (Proprietary/Confidential)**. Sistem ini menyimpan data catatan kepengasuhan, profil santri, dan evaluasi personal yang bersifat rahasia.

> **Pemberitahuan:** Seluruh hak cipta, kepemilikan kode sumber, dan hak kekayaan intelektual dilindungi undang-undang. Dilarang mendistribusikan, menyebarluaskan, atau mempublikasikan kembali bagian apa pun dari sistem ini tanpa persetujuan tertulis resmi dari pihak pengelola terkait.

<div align="center">
  <sub>Dibangun dan dirancang untuk kemaslahatan, kedisiplinan, dan transparansi pendidikan generasi santri.</sub>
</div>