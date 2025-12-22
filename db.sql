-- phpMyAdmin SQL Dump
-- version 4.9.0.1
-- https://www.phpmyadmin.net/
--
-- Anamakine: sql211.infinityfree.com
-- Üretim Zamanı: 21 Ara 2025, 02:36:26
-- Sunucu sürümü: 11.4.7-MariaDB
-- PHP Sürümü: 7.2.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Veritabanı: `if0_40197167_test`
--

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `course_periods`
--

CREATE TABLE `course_periods` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 0,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `course_periods`
--

INSERT INTO `course_periods` (`id`, `name`, `start_date`, `end_date`, `is_active`, `is_deleted`) VALUES
(1, '2025–2026 Kurs Dönemi', '2025-09-01', '2026-06-30', 1, 0);

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `announcements`
--

CREATE TABLE `announcements` (
  `id` int(11) NOT NULL,
  `title` varchar(150) NOT NULL,
  `message` text NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `attendance`
--

CREATE TABLE `attendance` (
  `id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `status` varchar(20) NOT NULL,
  `period_id` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `attendance`
--

INSERT INTO `attendance` (`id`, `course_id`, `student_id`, `date`, `status`, `period_id`) VALUES
(1, 1, 1, '2025-12-15', 'present', 1),
(2, 2, 1, '2025-12-16', 'present', 1),
(3, 2, 1, '2025-12-02', 'present', 1),
(4, 3, 1, '2025-12-15', 'present', 1);

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `courses`
--

CREATE TABLE `courses` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `color` varchar(20) DEFAULT '#e3f2fd',
  `day` varchar(20) NOT NULL,
  `time` varchar(50) NOT NULL,
  `building` varchar(100) NOT NULL,
  `classroom` varchar(100) NOT NULL,
  `teacher_id` int(11) DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `cancelled_dates` text DEFAULT NULL,
  `modifications` text DEFAULT NULL,
  `period_id` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `courses`
--

INSERT INTO `courses` (`id`, `name`, `color`, `day`, `time`, `building`, `classroom`, `teacher_id`, `start_date`, `end_date`, `cancelled_dates`, `modifications`, `period_id`) VALUES
(1, 'Ritim Kursu (Sabah)', '#1879bf', 'Pazartesi', '10:00-12:00', 'Çakabey Kültür Merkezi', 'Dans Stüdyosu', 1, '2025-12-01', '2026-06-30', '[]', '[]', 1),
(2, 'Halk Dansları', '#a1ee6d', 'Salı', '18:00-20:00', 'Amfi Tiyatro', 'Amfi Tiyatro Salonu', 2, '2025-12-01', '2026-06-30', '[]', '[]', 1),
(3, 'Tiyatro Kursu', '#f0b40f', 'Pazartesi', '15:30-22:30', 'Çakabey Kültür Merkezi', 'Tiyatro Salonu', 3, '2025-12-01', '2026-06-30', '[]', '[]', 1);

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `holidays`
--

CREATE TABLE `holidays` (
  `id` int(11) NOT NULL,
  `date` date NOT NULL,
  `name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `holidays`
--

INSERT INTO `holidays` (`id`, `date`, `name`) VALUES
(1, '2025-03-30', 'Ramazan Bayramı 1. Gün'),
(2, '2025-03-31', 'Ramazan Bayramı 2. Gün'),
(3, '2025-04-01', 'Ramazan Bayramı 3. Gün'),
(4, '2025-06-06', 'Kurban Bayramı 1. Gün'),
(5, '2025-06-07', 'Kurban Bayramı 2. Gün'),
(6, '2025-06-08', 'Kurban Bayramı 3. Gün'),
(7, '2025-06-09', 'Kurban Bayramı 4. Gün'),
(8, '2026-03-20', 'Ramazan Bayramı 1. Gün'),
(9, '2026-03-21', 'Ramazan Bayramı 2. Gün'),
(10, '2026-03-22', 'Ramazan Bayramı 3. Gün'),
(11, '2026-05-27', 'Kurban Bayramı 1. Gün'),
(12, '2026-05-28', 'Kurban Bayramı 2. Gün'),
(13, '2026-05-29', 'Kurban Bayramı 3. Gün'),
(14, '2026-05-30', 'Kurban Bayramı 4. Gün'),
(15, '2027-03-09', 'Ramazan Bayramı 1. Gün'),
(16, '2027-03-10', 'Ramazan Bayramı 2. Gün'),
(17, '2027-03-11', 'Ramazan Bayramı 3. Gün'),
(18, '2027-05-16', 'Kurban Bayramı 1. Gün'),
(19, '2027-05-17', 'Kurban Bayramı 2. Gün'),
(20, '2027-05-18', 'Kurban Bayramı 3. Gün'),
(21, '2027-05-19', 'Kurban Bayramı 4. Gün'),
(22, '2028-02-27', 'Ramazan Bayramı 1. Gün'),
(23, '2028-02-28', 'Ramazan Bayramı 2. Gün'),
(24, '2028-02-29', 'Ramazan Bayramı 3. Gün'),
(25, '2028-05-05', 'Kurban Bayramı 1. Gün'),
(26, '2028-05-06', 'Kurban Bayramı 2. Gün'),
(27, '2028-05-07', 'Kurban Bayramı 3. Gün'),
(28, '2028-05-08', 'Kurban Bayramı 4. Gün'),
(29, '2029-02-15', 'Ramazan Bayramı 1. Gün'),
(30, '2029-02-16', 'Ramazan Bayramı 2. Gün'),
(31, '2029-02-17', 'Ramazan Bayramı 3. Gün'),
(32, '2029-04-24', 'Kurban Bayramı 1. Gün'),
(33, '2029-04-25', 'Kurban Bayramı 2. Gün'),
(34, '2029-04-26', 'Kurban Bayramı 3. Gün'),
(35, '2029-04-27', 'Kurban Bayramı 4. Gün'),
(36, '2030-02-04', 'Ramazan Bayramı 1. Gün'),
(37, '2030-02-05', 'Ramazan Bayramı 2. Gün'),
(38, '2030-02-06', 'Ramazan Bayramı 3. Gün'),
(39, '2030-04-13', 'Kurban Bayramı 1. Gün'),
(40, '2030-04-14', 'Kurban Bayramı 2. Gün'),
(41, '2030-04-15', 'Kurban Bayramı 3. Gün'),
(42, '2030-04-16', 'Kurban Bayramı 4. Gün');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `meta_data`
--

CREATE TABLE `meta_data` (
  `item_key` varchar(50) NOT NULL,
  `item_value` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `meta_data`
--

INSERT INTO `meta_data` (`item_key`, `item_value`) VALUES
('buildings', '[\"Sanat Evi\",\"\\u00c7akabey K\\u00fclt\\u00fcr Merkezi\",\"Amfi Tiyatro\"]'),
('classes', '[\"At\\u00f6lye 1\",\"At\\u00f6lye 2\",\"Dans St\\u00fcdyosu\",\"Tiyatro Salonu\",\"Amfi Salon\"]'),
('title', 'Çeşme Belediyesi Kültür Müdürlüğü');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `students`
--

CREATE TABLE `students` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `surname` varchar(50) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `tc` varchar(11) DEFAULT NULL,
  `reg_date` date DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `education` varchar(100) DEFAULT NULL,
  `parent_name` varchar(100) DEFAULT NULL,
  `parent_phone` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `students`
--

INSERT INTO `students` (`id`, `name`, `surname`, `phone`, `email`, `tc`, `reg_date`, `date_of_birth`, `education`, `parent_name`, `parent_phone`) VALUES
(1, 'İlhan', 'Akdeniz', '5512208104', 'ilhanakd@gmail.com', '23423435345', '2025-12-19', NULL, '', 'İlyas Akdeniz', '5325127831');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `student_periods`
--

CREATE TABLE `student_periods` (
  `student_id` int(11) NOT NULL,
  `period_id` int(11) NOT NULL,
  `reg_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `student_periods`
--

INSERT INTO `student_periods` (`student_id`, `period_id`, `reg_date`) VALUES
(1, 1, '2025-12-19');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `student_courses`
--

CREATE TABLE `student_courses` (
  `student_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `period_id` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `student_courses`
--

INSERT INTO `student_courses` (`student_id`, `course_id`, `period_id`) VALUES
(1, 1, 1),
(1, 2, 1),
(1, 3, 1);

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `teachers`
--

CREATE TABLE `teachers` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `branch` varchar(150) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `teachers`
--

INSERT INTO `teachers` (`id`, `name`, `phone`, `email`, `username`, `password`, `branch`) VALUES
(1, 'Onur Tabak', '', '', 'onur', '123456', NULL),
(2, 'Batuhan Tabak', '5346346346346', '', 'batuhan', '123456', NULL),
(3, 'Aysel Güzel', '05057052602', '', 'aysel', '123456', NULL);

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(20) NOT NULL DEFAULT 'admin'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `users`
--

INSERT INTO `users` (`id`, `name`, `username`, `password`, `role`) VALUES
(3, 'İlhan Akdeniz', 'djmaster', '$2y$10$DNCBWzvYLahJxPueuZA13usiTDbVXcy7ZMEenXNW4R9pjeKOmzwkm', 'admin'),
(4, 'admin', 'admin', '$2y$10$RCihHhrXSuYSR5ToDAtR5eVpp014A4mlXXi8E3c2XiqgIQTAEmG.2', 'admin'),
(5, 'Aziz Burhan', 'aziz', '$2y$10$S6lEMfgXtMVmMZNdB6Vc9.k8RJm4V1o/kA4nLfuO2fdK4xEKrs266', 'admin');

--
-- Dökümü yapılmış tablolar için indeksler
--

--
-- Tablo için indeksler `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_attendance_period` (`period_id`);

--
-- Tablo için indeksler `course_periods`
--
ALTER TABLE `course_periods`
  ADD PRIMARY KEY (`id`);

--
-- Tablo için indeksler `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`);

--
-- Tablo için indeksler `courses`
--
ALTER TABLE `courses`
  ADD PRIMARY KEY (`id`);

--
-- Tablo için indeksler `courses` (dönem)
--
ALTER TABLE `courses`
  ADD KEY `idx_courses_period` (`period_id`);

--
-- Tablo için indeksler `holidays`
--
ALTER TABLE `holidays`
  ADD PRIMARY KEY (`id`);

--
-- Tablo için indeksler `meta_data`
--
ALTER TABLE `meta_data`
  ADD PRIMARY KEY (`item_key`);

--
-- Tablo için indeksler `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`);

--
-- Tablo için indeksler `student_periods`
--
ALTER TABLE `student_periods`
  ADD PRIMARY KEY (`student_id`,`period_id`),
  ADD KEY `idx_student_periods_period` (`period_id`);

--
-- Tablo için indeksler `student_courses`
--
ALTER TABLE `student_courses`
  ADD PRIMARY KEY (`student_id`,`course_id`,`period_id`),
  ADD KEY `idx_student_courses_period` (`period_id`);

--
-- Tablo için indeksler `teachers`
--
ALTER TABLE `teachers`
  ADD PRIMARY KEY (`id`);

--
-- Tablo için indeksler `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`);

--
-- Tablo için kısıtlamalar
--
ALTER TABLE `courses`
  ADD CONSTRAINT `fk_courses_period` FOREIGN KEY (`period_id`) REFERENCES `course_periods` (`id`) ON DELETE RESTRICT;

ALTER TABLE `attendance`
  ADD CONSTRAINT `fk_attendance_period` FOREIGN KEY (`period_id`) REFERENCES `course_periods` (`id`) ON DELETE RESTRICT;

ALTER TABLE `student_courses`
  ADD CONSTRAINT `fk_student_courses_period` FOREIGN KEY (`period_id`) REFERENCES `course_periods` (`id`) ON DELETE RESTRICT;

ALTER TABLE `student_periods`
  ADD CONSTRAINT `fk_student_periods_period` FOREIGN KEY (`period_id`) REFERENCES `course_periods` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_student_periods_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Dökümü yapılmış tablolar için AUTO_INCREMENT değeri
--

--
-- Tablo için AUTO_INCREMENT değeri `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Tablo için AUTO_INCREMENT değeri `course_periods`
--
ALTER TABLE `course_periods`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Tablo için AUTO_INCREMENT değeri `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `courses`
--
ALTER TABLE `courses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Tablo için AUTO_INCREMENT değeri `holidays`
--
ALTER TABLE `holidays`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- Tablo için AUTO_INCREMENT değeri `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Tablo için AUTO_INCREMENT değeri `teachers`
--
ALTER TABLE `teachers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Tablo için AUTO_INCREMENT değeri `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
