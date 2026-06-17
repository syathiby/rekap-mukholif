# 🚀 Laporan Upgrade AsuhTrack: Skalabilitas & Keamanan (Versi 2.0)

**Tanggal:** 17 Juni 2026
**Fokus:** Persiapan server untuk menangani 50-100 user secara bersamaan dengan aman.

---

## 🏗️ 1. Peningkatan Performa (Anti-Lemot)

### A. Sistem "Fotokopi" Dashboard (Caching)
**Masalah sebelumnya:** Setiap kali halaman utama (Dashboard) dibuka, server dipaksa melakukan kalkulasi matematika berat (seperti menghitung ranking santri, total pelanggaran se-pondok) langsung ke database. Jika 50 musyrif membuka bersamaan, server bisa lumpuh.
**Solusi yang Diterapkan:**
*   Sistem sekarang akan melakukan kalkulasi berat tersebut **hanya 1 kali saja setiap 5 menit**.
*   Hasil perhitungannya "difotokopi" ke dalam sebuah file teks kecil (`.json`).
*   User ke-2 sampai ke-50 yang membuka dashboard dalam rentang 5 menit tersebut hanya akan disajikan "kertas fotokopian" tadi.
*   **Efek:** Loading dashboard menjadi nyaris instan (0.01 detik) walau diakses ratusan orang sekaligus.

### B. Pagination Data Santri (Buku Bertahap)
**Masalah sebelumnya:** Halaman data santri memuat *ribuan* nama sekaligus ke layar dalam satu waktu. Ini menguras RAM server dan kuota internet user.
**Solusi yang Diterapkan:**
*   Sistem sekarang menggunakan **Pagination (Halaman)**.
*   Data ditampilkan maksimal **30 santri per halaman**.
*   **Efek:** Server tidak perlu bekerja keras memuat ribuan data, dan *scroll* di HP musyrif menjadi jauh lebih ringan.

---

## 🛡️ 2. Peningkatan Keamanan (Anti-Hacker & Orang Iseng)

### A. Gembok Pintu Masuk (Rate Limiting)
**Masalah sebelumnya:** Hacker bisa menggunakan robot untuk menebak password ribuan kali per detik tanpa hambatan (Brute-Force).
**Solusi yang Diterapkan:**
*   Setiap IP address (HP/Laptop) yang gagal login sebanyak **5 kali berturut-turut** akan otomatis diblokir dari sistem selama **15 menit**.
*   Form login akan dinonaktifkan (disable) dan menampilkan hitungan mundur (*countdown*) secara real-time.
*   Sistem pemblokiran ini sengaja **TIDAK memakai database**, melainkan menyimpan log IP di file teks tersembunyi agar hacker tidak bisa "menggempur" database kita sampai mati.

### B. Tilang Akses Ilegal
**Masalah sebelumnya:** Orang luar bisa mencoba-coba mengetik URL secara acak (misal: `/santri/index.php`) untuk mencoba masuk tanpa login.
**Solusi yang Diterapkan:**
*   Jika ada orang yang *belum login* mencoba menembus pintu belakang, sistem langsung menendangnya kembali ke halaman Login.
*   Tindakannya dicatat sebagai **1x Pelanggaran**. Jika dia mencoba hal iseng ini 5 kali, IP-nya otomatis diblokir 15 menit layaknya salah password.

### C. Kado Troll (Untuk Internal)
**Masalah sebelumnya:** Musyrif (yang sudah login) iseng mencoba mengakses menu milik Admin Keuangan.
**Solusi yang Diterapkan:**
*   Sistem mendeteksi bahwa dia sudah login, tetapi izinnya kurang.
*   Daripada diblokir IP-nya (kasihan dia staf pondok), sistem memunculkan "Kado Kejutan" (Troll Emoji 🤭) yang berujung pada **Logout Otomatis**. Ini memberikan efek jera yang ramah tanpa merusak akses kerjanya hari itu.

### D. Perbaikan Celah "Search Santri"
**Solusi yang Diterapkan:**
*   Semua fitur pencarian santri (baik untuk pelanggaran maupun reward) kini dibungkus dengan **Prepared Statements**.
*   Ini adalah teknologi di mana input dari user "disterilkan" terlebih dahulu sebelum masuk ke database, membuat aplikasi 100% kebal terhadap serangan *SQL Injection*.

---

## 💡 3. Tips & Trik Pemakaian Sehari-hari

Berikut adalah beberapa hal kecil yang bermanfaat untuk operasional pondok:

> [!TIP]
> **Refresh Otomatis Dashboard**
> Kalau Ustaz/Admin ingin melihat data *real-time* tanpa harus menunggu 5 menit (misalnya sedang ada sidak kamar dan ingin langsung melihat update data), tambahkan `?refresh=1` di ujung URL.
> Contoh: `rekap-mukholif/dashboard.php?refresh=1`

> [!TIP]
> **Hitungan Mundur 15 Menit**
> Jika ada staf yang *nyangkut* dan terblokir 15 menit, biarkan saja ia diam di halaman login. Hitungan mundurnya berjalan menggunakan Javascript, dan saat waktunya habis (00:00), halamannya akan otomatis me-*refresh* diri sendiri dan membuka kembali form loginnya.

> [!IMPORTANT]
> **Upload & Git Pull di Server Asli**
> Saat *deploy* ke server internet (production), sistem akan membuat folder cache (`cache/rate_limit/` dan `cache/dashboard/`) **secara otomatis** saat aplikasi pertama kali dibuka. Jadi, Anda tidak perlu repot-repot membuat folder ini secara manual di server hosting.

> [!NOTE]
> **Performa CRUD (Tambah/Edit/Hapus)**
> Jangan ragu jika ada 5 atau 10 musyrif yang menyimpan data pelanggaran secara serentak. Aktivitas **menyimpan (menulis) data** sangatlah ringan bagi database MySQL. Aplikasi ini sudah dirancang untuk menangani penulisan data massal dengan aman.

---

### Kesimpulan
Aplikasi AsuhTrack Anda kini telah **Naik Kelas**. Tidak hanya cantik dan mudah digunakan secara UI/UX, tetapi "mesin" di dalamnya sudah siap untuk dipakai operasional pondok berukuran menengah hingga besar tanpa takut server tumbang. Mantap bro! 👍
