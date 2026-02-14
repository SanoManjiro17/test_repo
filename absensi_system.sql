-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 31 Jan 2026 pada 17.08
-- Versi server: 10.4.32-MariaDB
-- Versi PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `absensi_system`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `absensi`
--

CREATE TABLE `absensi` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `tanggal` date NOT NULL,
  `jam_masuk` time NOT NULL,
  `jam_keluar` time DEFAULT NULL,
  `durasi_kerja` time DEFAULT NULL,
  `tipe_absen` enum('reguler','sakit','cuti') DEFAULT 'reguler',
  `status_lembur` enum('tidak_lembur','sedang_lembur','selesai_lembur') DEFAULT 'tidak_lembur',
  `keterangan` text DEFAULT NULL,
  `bukti_izin` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `absensi`
--

INSERT INTO `absensi` (`id`, `user_id`, `tanggal`, `jam_masuk`, `jam_keluar`, `durasi_kerja`, `tipe_absen`, `status_lembur`, `keterangan`, `bukti_izin`, `created_at`, `updated_at`) VALUES
(101, 23, '2026-01-31', '22:15:25', '22:15:30', '00:00:05', 'reguler', 'tidak_lembur', NULL, NULL, '2026-01-31 15:15:25', '2026-01-31 15:15:30');

-- --------------------------------------------------------

--
-- Struktur dari tabel `absensi_lembur`
--

CREATE TABLE `absensi_lembur` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `tanggal` date NOT NULL,
  `jam_mulai` time NOT NULL,
  `jam_selesai` time DEFAULT NULL,
  `durasi_lembur` varchar(20) DEFAULT NULL,
  `keterangan` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `absensi_lembur`
--

INSERT INTO `absensi_lembur` (`id`, `user_id`, `tanggal`, `jam_mulai`, `jam_selesai`, `durasi_lembur`, `keterangan`, `created_at`, `updated_at`) VALUES
(19, 22, '2026-01-31', '21:19:37', '21:19:54', '00:00:17', NULL, '2026-01-31 14:19:37', '2026-01-31 14:19:54'),
(20, 25, '2026-01-31', '21:25:51', '21:26:05', '00:00:14', NULL, '2026-01-31 14:25:51', '2026-01-31 14:26:05');

-- --------------------------------------------------------

--
-- Struktur dari tabel `settings`
--

CREATE TABLE `settings` (
  `setting_key` varchar(50) NOT NULL,
  `setting_value` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `settings`
--

INSERT INTO `settings` (`setting_key`, `setting_value`, `updated_at`) VALUES
('shift_status', 'open', '2026-01-31 15:14:17');

-- --------------------------------------------------------

--
-- Struktur dari tabel `tasklist`
--

CREATE TABLE `tasklist` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `absensi_id` int(11) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `task_name` varchar(255) NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `status` enum('pending','progress','completed') DEFAULT 'pending',
  `deadline` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `assigned_to` int(11) DEFAULT NULL,
  `priority` enum('low','medium','high') DEFAULT 'medium',
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `is_overtime` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `tasklist`
--

INSERT INTO `tasklist` (`id`, `user_id`, `absensi_id`, `start_date`, `task_name`, `deskripsi`, `status`, `deadline`, `created_at`, `updated_at`, `assigned_to`, `priority`, `start_time`, `end_time`, `is_overtime`) VALUES
(89, 1, NULL, '2026-01-01', 'ngabagoy', '', 'completed', '2026-01-01', '2026-01-31 14:16:27', '2026-01-31 14:17:00', 23, 'low', '00:00:00', '17:00:00', 0),
(90, 1, NULL, '2026-01-02', 'ngala monyet', '', 'progress', '2026-01-02', '2026-01-31 14:22:47', '2026-01-31 14:23:30', 25, 'low', '00:00:00', '17:00:00', 0),
(91, 1, NULL, '2026-01-02', 'ngabagoy', '', 'pending', '2026-01-02', '2026-01-31 14:25:18', '2026-01-31 14:25:18', 25, 'low', NULL, NULL, 1),
(92, 1, NULL, '2026-01-31', 'Lembur Kerja', '', 'pending', '2026-01-31', '2026-01-31 14:55:06', '2026-01-31 14:55:06', 23, 'low', NULL, NULL, 1),
(93, 24, NULL, '2026-01-30', 'ngarit', '', 'completed', '2026-01-30', '2026-01-31 15:14:54', '2026-01-31 15:15:45', 23, 'high', '00:00:00', '17:00:00', 0),
(94, 24, NULL, '2026-01-30', 'ngarit', '', 'pending', '2026-01-30', '2026-01-31 15:14:54', '2026-01-31 15:14:54', 22, 'high', '00:00:00', '17:00:00', 0),
(95, 24, NULL, '2026-01-31', 'Lembur Kerja', '', 'pending', '2026-01-31', '2026-01-31 15:15:01', '2026-01-31 15:15:01', 23, 'low', NULL, NULL, 1),
(96, 1, NULL, '2026-01-03', 'ngabagoy', '', 'pending', '2026-01-03', '2026-01-31 15:39:06', '2026-01-31 15:39:06', 23, 'medium', '00:00:00', '17:00:00', 0),
(97, 1, NULL, '2026-01-31', 'mm', '', 'pending', '2026-01-31', '2026-01-31 15:39:20', '2026-01-31 15:39:20', 23, 'low', '00:00:00', '17:00:00', 0),
(98, 23, NULL, '2026-01-02', 'cuci', '', 'pending', '2026-01-02', '2026-01-31 15:40:23', '2026-01-31 15:40:23', NULL, 'low', '22:42:00', '17:00:00', 0);

-- --------------------------------------------------------

--
-- Struktur dari tabel `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `absen_id` varchar(50) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `nip` varchar(30) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('staf','manager') DEFAULT 'staf',
  `can_overtime` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `email` varchar(100) DEFAULT NULL,
  `telepon` varchar(20) DEFAULT NULL,
  `alamat` text DEFAULT NULL,
  `foto` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `users`
--

INSERT INTO `users` (`id`, `absen_id`, `nama`, `nip`, `password`, `role`, `can_overtime`, `created_at`, `email`, `telepon`, `alamat`, `foto`) VALUES
(1, 'padil', 'padil', NULL, '6f5bdb4049e8d66ccaeca0cc0a98f3aa', 'manager', 0, '2026-01-06 10:27:33', '', '', '', 'profile_1_1768137877.jpg'),
(21, 'dzikrul', 'dzikrul', '001', 'e10adc3949ba59abbe56e057f20f883e', 'staf', 1, '2026-01-31 13:56:57', 'admin@dhrcv3.com', '123456789876', 'jlll', NULL),
(22, 'hasan', 'hasan', '003', 'e10adc3949ba59abbe56e057f20f883e', 'staf', 1, '2026-01-31 13:57:25', 'admin@dhrcv3.com', '08955678912', 'afg', NULL),
(23, 'dini', 'Dini', '12345678', 'e10adc3949ba59abbe56e057f20f883e', 'staf', 1, '2026-01-31 13:57:53', 'admin@dhrcv3.com', '08955678912', 'kll', 'profile_23_1769872566.jpg'),
(24, 'fauzan', 'fauzan', '005', 'e10adc3949ba59abbe56e057f20f883e', 'manager', 1, '2026-01-31 13:58:30', 'jonisontog@gmail.com', '123456789876', 'jllllll', NULL),
(25, 'minven', 'minven', '006', 'e10adc3949ba59abbe56e057f20f883e', 'staf', 1, '2026-01-31 13:59:15', 'jonisontog@gmail.com', '08955678912', 'l', NULL),
(26, 'minven01', 'minven01', 'oii', 'e10adc3949ba59abbe56e057f20f883e', 'staf', 1, '2026-01-31 13:59:54', 'padil@dhrcv3.com', '123456789876', 'jlll', NULL),
(27, 'mas alan', 'mas alan', '00000', 'e10adc3949ba59abbe56e057f20f883e', 'staf', 1, '2026-01-31 14:40:57', 'aditlabas@gmail.com', '099867555', 'jl sinsar', NULL);

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `absensi`
--
ALTER TABLE `absensi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indeks untuk tabel `absensi_lembur`
--
ALTER TABLE `absensi_lembur`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_tanggal` (`user_id`,`tanggal`);

--
-- Indeks untuk tabel `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Indeks untuk tabel `tasklist`
--
ALTER TABLE `tasklist`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `absensi_id` (`absensi_id`),
  ADD KEY `assigned_to` (`assigned_to`);

--
-- Indeks untuk tabel `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `absen_id` (`absen_id`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `absensi`
--
ALTER TABLE `absensi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=102;

--
-- AUTO_INCREMENT untuk tabel `absensi_lembur`
--
ALTER TABLE `absensi_lembur`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT untuk tabel `tasklist`
--
ALTER TABLE `tasklist`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=99;

--
-- AUTO_INCREMENT untuk tabel `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `absensi`
--
ALTER TABLE `absensi`
  ADD CONSTRAINT `absensi_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Ketidakleluasaan untuk tabel `absensi_lembur`
--
ALTER TABLE `absensi_lembur`
  ADD CONSTRAINT `absensi_lembur_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `tasklist`
--
ALTER TABLE `tasklist`
  ADD CONSTRAINT `tasklist_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `tasklist_ibfk_2` FOREIGN KEY (`absensi_id`) REFERENCES `absensi` (`id`),
  ADD CONSTRAINT `tasklist_ibfk_3` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
