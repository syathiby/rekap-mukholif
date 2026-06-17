# Panduan Lengkap & Laporan Fitur Sinkronisasi Data Santri

Dokumen ini adalah laporan menyeluruh sekaligus panduan teknis mengenai **Fitur Sinkronisasi Data Santri** (Impor Data via Excel/CSV) yang dirancang untuk menjaga keutuhan data pondok pesantren sembari mempertahankan kepraktisan operasional.

---

## 1. Konsep Dasar: Excel sebagai *Source of Truth*
Fitur ini dibangun dengan filosofi **Source of Truth** (Sumber Kebenaran Tunggal). Artinya, alih-alih admin menginput data satu persatu di aplikasi, admin cukup mengolah satu buah file Excel induk. 

Ketika file Excel tersebut diunggah ke dalam sistem, aplikasi akan melakukan "pemindaian X-Ray" untuk membandingkan isi Excel dengan *database* saat ini, lalu secara mandiri memutuskan data mana yang harus ditambah, diperbarui, atau dihapus.

---

## 2. Mode Sinkronisasi
Sistem menyediakan dua buah mode pengoperasian yang bisa disesuaikan dengan kebutuhan Admin:

### A. Mode Aman (Update & Insert Saja)
- **Fungsi:** Menambah santri baru dan memperbarui kelas/kamar santri lama.
- **Keamanan:** Sangat aman. Jika ada santri di *database* namun namanya tidak ada di file Excel, santri tersebut **TIDAK AKAN DIHAPUS**.
- **Kapan Digunakan:** Rutin setiap bulan jika hanya ada tambahan santri baru atau perpindahan kamar biasa.

### B. Mode Penuh (+ Hapus Data)
- **Fungsi:** Menyamakan isi *database* 100% persis dengan isi file Excel.
- **Risiko Terkontrol:** Jika ada nama santri di *database* yang hilang dari Excel (misal karena lulus/pindah), sistem akan otomatis **menghapusnya**.
- **Kapan Digunakan:** Setiap pergantian semester atau tahun ajaran baru (ketika banyak santri yang lulus atau perombakan total).

---

## 3. Sistem "Pelindung Virtual" (Blacklist Protection)

Fitur sinkronisasi kita dilengkapi dengan perlindungan data cerdas (*Smart Virtual Protection*) murni berbasis algoritma aplikasi (tanpa mengubah arsitektur *database* dasar).

### Apa yang terjadi jika santri bermasalah terhapus dari Excel?
Jika Anda menggunakan **Mode Penuh (+ Hapus)**, namun santri yang akan dihapus ternyata masih memiliki **Riwayat Pelanggaran** atau **Riwayat Reward** aktif (belum di-Tutup Buku), maka:
1. Sistem akan **Memblokir** penghapusan anak tersebut secara otomatis.
2. Santri beserta seluruh riwayat hitamnya akan diamankan dan dipertahankan di dalam sistem sebagai "Daftar Hitam Permanen".
3. Admin akan mendapatkan *Notifikasi Info* berwarna biru yang merinci alasan mengapa santri tersebut tertahan (misal: *"Tertahan karena memiliki 2 Pelanggaran aktif"*).

---

## 4. Tips & Trik Penggunaan Terbaik

> [!TIP]
> **Selalu Lakukan "Tutup Buku" Sebelum Sinkronisasi Semester Baru**
> Agar database Anda tidak membengkak oleh anak-anak yang sudah lulus, pastikan Anda menekan tombol "Tutup Buku" di akhir tahun. Ini akan memindahkan data pelanggaran mereka ke arsip, sehingga sistem akhirnya "mengizinkan" nama mereka untuk dihapus pada sinkronisasi berikutnya (kecuali pelanggaran 'Sangat Berat' yang akan abadi).

> [!TIP]
> **Jangan Ubah ID Santri Secara Manual di Excel**
> ID Santri di file Excel adalah kunci nyawa sinkronisasi. Jika Anda ingin mengubah Kelas atau Kamar, ubah saja sel tersebut, tapi **JANGAN PERNAH** menyentuh angka di kolom ID.

> [!IMPORTANT]
> **Data Anak Baru? Kosongkan saja ID-nya!**
> Jika ada anak baru masuk di pertengahan bulan, cukup tambahkan namanya di baris paling bawah Excel, **biarkan kolom ID kosong**. Sistem akan mendeteksinya sebagai anak baru dan memberikan ID secara otomatis.

---

## 5. Pertanyaan yang Sering Diajukan (FAQ)

**Q: Mengapa penghapusan santri saya gagal dan malah masuk kotak "Proteksi Data Aktif Berjalan!"?**
A: Karena santri tersebut masih memiliki sisa Poin Merah (Pelanggaran) atau Poin Hijau (Reward) yang belum diselesaikan atau diarsipkan melalui fitur Tutup Buku.

**Q: Bagaimana cara saya benar-benar menghapus santri tersebut sampai ke akar-akarnya?**
A: Anda memiliki dua cara:
1. Klik tombol "Tutup Buku" di menu pengaturan (jika pelanggaran anak itu masuk kategori ringan/sedang, datanya akan diarsipkan, lalu Anda bisa menghapusnya).
2. Jika anak itu memiliki pelanggaran "Sangat Berat", Anda harus pergi ke halaman *Master Data Santri*, cari namanya, lalu klik ikon **Tong Sampah**. Di sana Anda akan diperingatkan dengan peringatan keras berwarna merah, klik "Ya, Hapus Permanen" untuk melenyapkan anak tersebut beserta riwayatnya.

**Q: Waktu saya unggah Excel, sistem bilang "Data Konflik (Fatal)", itu apa maksudnya?**
A: Itu terjadi jika Anda tidak sengaja memasukkan angka ID di Excel yang ternyata sudah dimiliki oleh santri lain di *database*. Tenang, sistem sangat cerdas: sistem tidak akan menimpa data lama Anda, melainkan akan membuatkan santri tersebut ID baru secara otomatis agar tidak terjadi kekacauan.

**Q: Boleh gak format penulisan kamarnya pakai kata-kata seperti "Kamar Abu Bakar"?**
A: Aplikasi ini dirancang untuk membaca angka (Misal: `1`, `2`, `10`). Sebaiknya di dalam file Excel, kolom Kelas dan Kamar murni diisi dengan format Angka agar pembacaan sistem (*sorting* dan *filtering*) berjalan dengan sangat ringan dan rapi.

---
*Laporan ini di-generate otomatis pasca-penyelesaian arsitektur Fitur Sinkronisasi.*
