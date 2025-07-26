-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Waktu pembuatan: 26 Jul 2025 pada 10.21
-- Versi server: 8.0.30
-- Versi PHP: 8.1.10

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `db_mukholif`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `eksekusi_kebersihan`
--

CREATE TABLE `eksekusi_kebersihan` (
  `id` int NOT NULL,
  `pelanggaran_id` int NOT NULL,
  `kamar` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `jenis_sanksi` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `catatan` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `tanggal_eksekusi` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `dicatat_oleh` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `jenis_pelanggaran`
--

CREATE TABLE `jenis_pelanggaran` (
  `id` int NOT NULL,
  `nama_pelanggaran` varchar(255) COLLATE utf8mb4_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `jenis_pelanggaran`
--

INSERT INTO `jenis_pelanggaran` (`id`, `nama_pelanggaran`) VALUES
(1, 'TELAT SHOLAT'),
(2, 'TELAT KBM'),
(3, 'KEBERSIHAN KAMAR');

-- --------------------------------------------------------

--
-- Struktur dari tabel `pelanggaran`
--

CREATE TABLE `pelanggaran` (
  `id` int NOT NULL,
  `santri_id` int NOT NULL,
  `jenis_pelanggaran_id` int NOT NULL,
  `tanggal` datetime NOT NULL,
  `dicatat_oleh` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `pelanggaran_kebersihan`
--

CREATE TABLE `pelanggaran_kebersihan` (
  `id` int NOT NULL,
  `kamar` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `tanggal` datetime NOT NULL,
  `dicatat_oleh` int DEFAULT NULL,
  `catatan` text COLLATE utf8mb4_general_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `santri`
--

CREATE TABLE `santri` (
  `id` int NOT NULL,
  `nama` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `kelas` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `kamar` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `santri`
--

INSERT INTO `santri` (`id`, `nama`, `kelas`, `kamar`) VALUES
(9, 'Abu bakar', '9', '1'),
(10, 'Aditya Ozy Pratama', '9', '1'),
(11, 'Afnan Fakhruddin', '9', '1'),
(12, 'Fudhoil', '9', '1'),
(13, 'Hafizh Sakha Fallah', '9', '1'),
(14, 'Yahya ibnu dedy', '9', '1'),
(15, 'Hashif Zulmi Alafa', '9', '1'),
(16, 'Muhammad Silmy Kaffah', '9', '1'),
(17, 'Abisha Fadhil Gantohe', '9', '1'),
(18, 'Afif Ibnu Irwansyah', '9', '1'),
(19, 'Hakiem', '9', '1'),
(20, 'Ahmad Hansam Saud', '9', '1'),
(21, 'Rayhan Putra', '9', '1'),
(22, 'Faiq Fawwas Rabbani', '9', '1'),
(23, 'Muhammad Omar Fadhilah', '12', '1'),
(24, 'Muhammad octo annafi', '11', '2'),
(25, 'Fauzan al fadli', '11', '2'),
(26, 'Ibna Fakhri Muniruzzaman', '11', '2'),
(27, 'Ibna Fikri Muniruzzaman', '11', '2'),
(28, 'Fadhil Khasyi Rizqullah', '11', '2'),
(29, 'Davian Rab Musyaffa', '11', '2'),
(30, 'Muhammad Arfan Fadhilah', '11', '2'),
(31, 'Ibrahim', '11', '2'),
(32, 'Zaid altsari', '11', '2'),
(33, 'Fatih hibban al ayubi', '11', '2'),
(34, 'Faiz ibnu irwansyah', '11', '2'),
(35, 'Adlil hakim', '11', '2'),
(36, 'Sailendra Abisya Zidni', '11', '2'),
(37, 'Omar Sholetama Putra Wibowo', '12', '2'),
(38, 'Luqman', '12', '2'),
(39, 'Ahmad khairun nurmansyah', '11', '3'),
(40, 'Ibrahim Abidin', '11', '3'),
(41, 'Rafly gymnastiar', '11', '3'),
(42, 'Azka nadhif siswadi', '11', '3'),
(43, 'Muhammad bayu pamungkas', '11', '3'),
(44, 'Ahmad Abdillah', '11', '3'),
(45, 'Muhammad Hanif Ramadhan', '11', '3'),
(46, 'Rahmahdan Arif Qisti Yosafikri', '11', '3'),
(47, 'Muhammad Ghazy', '11', '3'),
(48, 'Salman', '11', '3'),
(49, 'Damar jati arifin', '11', '3'),
(50, 'Salman ahmad zaidan', '11', '3'),
(51, 'Zenedine zaqtan', '11', '3'),
(52, 'Muhammad Ariq Ibnu Putra', '12', '3'),
(53, 'Ahmad Zaid Syabib', '12', '3'),
(54, 'Daffa Fadhil Fathurrohman Mustaqbal', '10', '4'),
(55, 'Dzulqarnain Abdurrahman El-Mahdits', '10', '4'),
(56, 'Dzaky Faiq At-Taqiy', '10', '4'),
(57, 'Dzakwan Aflah Kusmanto', '10', '4'),
(58, 'Muhamad Fakhih Ridwan', '10', '4'),
(59, 'Fuad Hazim', '10', '4'),
(60, 'Nadhif Ibnu Karim', '10', '4'),
(61, 'Muhammad Abidin', '10', '4'),
(62, 'Muhammad Daffa Syahputra', '10', '4'),
(63, 'Asyraf', '10', '4'),
(64, 'Abdurrahman Mahir Az-Zukhrufy', '10', '4'),
(65, 'Abrisam Izzudin Asman', '10', '4'),
(66, 'Abdat Ibnu Priyogo', '10', '4'),
(67, 'Raffa Egbert', '10', '4'),
(68, 'Nail Khairi Kurnia', '12', '4'),
(69, 'Rizky Ramadhan', '10', '5'),
(70, 'LA ODE LUQMAN', '10', '5'),
(71, 'Harist Ibrahim', '10', '5'),
(72, 'Rafiq Abdurrosid', '10', '5'),
(73, 'Ahmad Wildan', '10', '5'),
(74, 'Harits Abdullah', '10', '5'),
(75, 'Hijaz Riyadh Ahmad', '10', '5'),
(76, 'Ilham Yunus As-Siddiq', '10', '5'),
(77, 'Muhammad Hammam Haidar', '10', '5'),
(78, 'Muhammad Alfarizi', '10', '5'),
(79, 'Izdhar Fathul Wibisono', '10', '5'),
(80, 'Hafy Uyainah Nandatha', '10', '5'),
(81, 'Mohammad Fayyaz Munif', '10', '5'),
(82, 'Syawali Raja Setiawan', '10', '5'),
(83, 'AWA', '12', '5'),
(84, 'Alfathussolih', '9', '6'),
(85, 'Zidan Mubarok', '9', '6'),
(86, 'Ihsan Muhammad Kustiwa', '9', '6'),
(87, 'Muhammad Kiyoshi Abyan', '9', '6'),
(88, 'Agra Abhipraya', '9', '6'),
(89, 'Muhammad Hanif Hanania Ramadhan', '9', '6'),
(90, 'Muhammad Zaidan Athallah', '9', '6'),
(91, 'Muhammad Nabil Ubadah', '9', '6'),
(92, 'Muhammad Alfan Luhaidansyah', '9', '6'),
(93, 'Nino Fatih Pramudhita', '9', '6'),
(94, 'Wafi Najmi Arfan', '9', '6'),
(95, 'Wardana Candra Hidayat', '9', '6'),
(96, 'Hasan al atsari', '9', '6'),
(97, 'Bagas arkana', '9', '6'),
(98, 'Ubaidillah Faqih', '9', '6'),
(99, 'Abdurrahman Andra Al-Hanif', '12', '6'),
(100, 'Rakha Narariya', '8', '7'),
(101, 'Ibrahim Jamil Al-Katiri', '8', '7'),
(102, 'Muhammad Yahya', '8', '7'),
(103, 'Fauzan Al-Abrar', '8', '7'),
(104, 'Daffa Miqdaad', '8', '7'),
(105, 'Zibran Helendri', '8', '7'),
(106, 'Azmi Daffa', '8', '7'),
(107, 'Muhammad Syafiq Agami', '8', '7'),
(108, 'Zain Hanif Hibatallah', '8', '7'),
(109, 'Aldrin Disyami Putra', '8', '7'),
(110, 'Muhammad Uwais Al-Qarny', '8', '7'),
(111, 'Raihan Abdul Aziz', '8', '7'),
(112, 'Ahmad Sakha Al-Ghifari', '12', '7'),
(113, 'Muhammad Ferry Fadhilah', '12', '7'),
(114, 'Muhammad Fakih Pribadi', '8', '8'),
(115, 'Muhammad Nashril Azzam Utama', '8', '8'),
(116, 'Abdullah Zubair', '8', '8'),
(117, 'Hamka Hamzah', '8', '8'),
(118, 'Ilhan El-Mohammady Rumi', '8', '8'),
(119, 'Abdullah Bin Novan Teguh Setyawan', '8', '8'),
(120, 'Abdullah Bin Haris Nasution', '8', '8'),
(121, 'Muhammad Rafa Asyham Iswahyudi', '8', '8'),
(122, 'Muhammad Hudzaifah Shafiyurrahman', '8', '8'),
(123, 'Muhammad Arjuna Khalinda Putra', '8', '8'),
(124, 'Muhammad Baariq Hibrizi', '8', '8'),
(125, 'Muhammad Yahya Al-Abbad', '8', '8'),
(126, 'Muhammad Hafizh Al-Farisi', '12', '8'),
(127, 'Wahyu Muhammad Hidayat Jati', '12', '8'),
(128, 'Ahmad Hifdzi Ghazwan Miqdad', '8', '9'),
(129, 'Kainan Nur Ihsan Budi', '8', '9'),
(130, 'Khalifah Firaas Alfarisi', '8', '9'),
(131, 'Tarra Adhyla Arkaleon', '8', '9'),
(132, 'Muhammad Rayhan Adia Syatir', '8', '9'),
(133, 'Firas Habibie', '8', '9'),
(134, 'Muhammad Ghozy Al-Tamis', '8', '9'),
(135, 'Nayaka Wirayudha', '8', '9'),
(136, 'Ammar Al-Hasan', '8', '9'),
(137, 'Abdurrahman Nufail', '8', '9'),
(138, 'Rifky Almaas Muzakki', '8', '9'),
(139, 'Arya Shuhaib', '8', '9'),
(140, 'Abdurrahman Al-Hudzaify', '12', '9'),
(141, 'Labib Keysha Taat', '12', '9'),
(142, 'Zaki Naufal Ramadhan', '8', '10'),
(143, 'Ayman Ridwan Baasyin', '8', '10'),
(144, 'Gahtan Shiddiq Al-Faruq', '8', '10'),
(145, 'Naraya Yudha Permana', '8', '10'),
(146, 'Abdul Hafizh', '8', '10'),
(147, 'Muhammad Ibrahim Syarif', '8', '10'),
(148, 'Mandriva Aldebaran Ayyasa', '8', '10'),
(149, 'Azzan Munadhil Izzul Haq', '8', '10'),
(150, 'Ahmad Mahbubbillah Syamsudin', '8', '10'),
(151, 'Ghany Ilmi Mubarak', '8', '10'),
(152, 'Muhammad Nikho Ridja', '8', '10'),
(153, 'Muhammad Naufal Hakim', '8', '10'),
(154, 'Muhammad Aditya Ridza', '12', '10'),
(155, 'Al-Harist', '12', '10'),
(156, 'HUGO ABQORI KUSMANTO', '7', '11'),
(157, 'IBRAHIM ABDULBARI', '7', '11'),
(158, 'IBRAHIM FATHURRASYID ASH-SHIDDIQI', '7', '11'),
(159, 'KAFI AGHA', '7', '11'),
(160, 'KAFIE HAMIZAN AHZA', '7', '11'),
(161, 'KAHFI FATHIN RABBANI', '7', '11'),
(162, 'KAREEM ATHAR SETIADI', '7', '11'),
(163, 'KIAGUS NAWFAL FAKHRI', '7', '11'),
(164, 'LUTHFI SAKHIY ZAIDAN', '7', '11'),
(165, 'MUAZZAM SYAHIR AL FAQIH', '7', '11'),
(166, 'MUHAMMAD ALKHALIFI ZIKRI NUGROHO', '7', '11'),
(167, 'AYMAN ZAKI MUBARAK', '7', '11'),
(168, 'MUHAMMAD FARIS EL-SYAKIR', '7', '11'),
(169, 'MUHAMMAD FATHAN ABDILLAH', '7', '11'),
(170, 'MUHAMMAD HAFIDZ SYAUQILLAH', '7', '11'),
(171, 'MUHAMMAD HIBBAN AKHYAR', '7', '11'),
(172, 'MUHAMMAD HISYAM AL HAAFIZH', '7', '12'),
(173, 'MUHAMMAD MA\'RUF NURMANSYAH', '7', '12'),
(174, 'MUHAMMAD ZHAFRAN ASSYAUQI', '7', '12'),
(175, 'MUHSIN ASLAM', '7', '12'),
(176, 'MUSH\'AB', '7', '12'),
(177, 'ROFIQILANAM', '7', '12'),
(178, 'SYAMIL ZIAD AT TAMIMI', '7', '12'),
(179, 'WALDAN TSAQIF SAPUTRA', '7', '12'),
(180, 'YAHYA', '7', '12'),
(181, 'ABDAN SYAKURO', '07', '12'),
(182, 'SYADDAAD HASAN AMMAAR', '7', '12'),
(183, 'IDRAQ FATIH MUBARAK', '7', '12'),
(184, 'MIRZA ALFARIEZ', '7', '12'),
(185, 'MUHAMMAD ZEIN AL QARNI', '7', '12'),
(186, 'UKASYAH', '7', '12'),
(187, 'RASYID MOHAMMAD ALVARO', '7', '12'),
(188, 'ABDULLAH YAHYA', '7', '13'),
(189, 'ABDURRAHMAN UMAR FIRDAUS', '7', '13'),
(190, 'ARDRAZEVA PRANAJA WICAKSONO', '7', '13'),
(191, 'ASHAR NAUFAL ABDURRAHMAN', '7', '13'),
(192, 'ATHAR IBNI AZZAM', '7', '13'),
(193, 'AUFA ALFARISI', '7', '13'),
(194, 'MUHAMMAD FAIZ ARSYAD', '7', '13'),
(195, 'AYUB ABRAR', '7', '13'),
(196, 'DAFFA IFAT AS-SYARIF', '7', '13'),
(197, 'DZAKY RACHMAN', '7', '13'),
(198, 'FARIS NOER RAMADHAN', '7', '13'),
(199, 'Farras Yazid Muttaqin', '7', '13'),
(200, 'FARREL AKHTAR ATHALLAH', '7', '13'),
(201, 'FARROS KUMAIL', '7', '13'),
(202, 'FIRNAS KAMAL YAHYA', '7', '13'),
(203, 'GILANG ADHIANTA HARTANTO', '7', '13'),
(204, 'HANIF DZAKWAN AFANI', '7', '13'),
(205, 'Fudhail', '10', '4');

-- --------------------------------------------------------

--
-- Struktur dari tabel `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `username` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `role` enum('admin','user') COLLATE utf8mb4_general_ci DEFAULT 'user'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`) VALUES
(1, 'pgd', '6161b2838ffa6ce17b84db3b45b4f8437855ecf43e75de2d1ad0008eaae91aa0', 'user');

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `eksekusi_kebersihan`
--
ALTER TABLE `eksekusi_kebersihan`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_pelanggaran` (`pelanggaran_id`);

--
-- Indeks untuk tabel `jenis_pelanggaran`
--
ALTER TABLE `jenis_pelanggaran`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `pelanggaran`
--
ALTER TABLE `pelanggaran`
  ADD PRIMARY KEY (`id`),
  ADD KEY `santri_id` (`santri_id`),
  ADD KEY `jenis_pelanggaran_id` (`jenis_pelanggaran_id`);

--
-- Indeks untuk tabel `pelanggaran_kebersihan`
--
ALTER TABLE `pelanggaran_kebersihan`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `santri`
--
ALTER TABLE `santri`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `eksekusi_kebersihan`
--
ALTER TABLE `eksekusi_kebersihan`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT untuk tabel `jenis_pelanggaran`
--
ALTER TABLE `jenis_pelanggaran`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT untuk tabel `pelanggaran`
--
ALTER TABLE `pelanggaran`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=80;

--
-- AUTO_INCREMENT untuk tabel `pelanggaran_kebersihan`
--
ALTER TABLE `pelanggaran_kebersihan`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=174;

--
-- AUTO_INCREMENT untuk tabel `santri`
--
ALTER TABLE `santri`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=221;

--
-- AUTO_INCREMENT untuk tabel `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `pelanggaran`
--
ALTER TABLE `pelanggaran`
  ADD CONSTRAINT `pelanggaran_ibfk_1` FOREIGN KEY (`santri_id`) REFERENCES `santri` (`id`),
  ADD CONSTRAINT `pelanggaran_ibfk_2` FOREIGN KEY (`jenis_pelanggaran_id`) REFERENCES `jenis_pelanggaran` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
