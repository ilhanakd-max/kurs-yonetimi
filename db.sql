-- phpMyAdmin SQL Dump
-- version 4.9.0.1
-- https://www.phpmyadmin.net/
--
-- Anamakine: sql211.infinityfree.com
-- Üretim Zamanı: 25 Ara 2025, 11:16:18
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
(1, 'Ritim Kursu (Sabah)', '#1879bf', 'Salı', '10:00-12:00', 'Çakabey Kültür Merkezi', 'Dans Stüdyosu', 1, '2025-12-01', '2026-06-30', '[]', '[]', 1),
(2, 'Halk Dansları', '#a1ee6d', 'Salı', '18:00-20:00', 'Amfi Tiyatro', 'Amfi Tiyatro Salonu', 2, '2025-12-01', '2026-06-30', '[]', '[]', 1),
(3, 'Tiyatro Kursu', '#f0b40f', 'Pazartesi', '15:30-22:30', 'Çakabey Kültür Merkezi', 'Tiyatro Salonu', 3, '2025-12-01', '2026-06-30', '[]', '[]', 1),
(4, 'Bale Kursu', '#f312ab', 'Cumartesi', '11:00-15:00', 'Çakabey Kültür Merkezi', 'Dans Stüdyosu', 5, '2025-09-01', '2026-06-30', '[]', '[]', 1),
(5, 'Bale Kursu', '#f312ab', 'Pazar', '11:00-15:00', 'Çakabey Kültür Merkezi', 'Dans Stüdyosu', 5, '2025-09-01', '2026-06-30', '[]', '[]', 1),
(6, 'Ritim Kursu (Sabah)', '#1879bf', 'Salı', '12:00-14:00', 'Çakabey Kültür Merkezi', 'Dans Stüdyosu', 1, '2025-12-01', '2026-06-30', '[]', '[]', 1),
(7, 'Piyano Kursu', '#8a4505', 'Pazartesi', '18:00-20:00', 'Çakabey Kültür Merkezi', 'Atölye 2', 4, '2025-09-01', '2026-06-30', '[]', '[]', 1),
(8, 'Piyano Kursu', '#8a4505', 'Salı', '19:00-21:00', 'Çakabey Kültür Merkezi', 'Atölye 2', 4, '2025-09-01', '2026-06-30', '[]', '[]', 1),
(9, 'Bağlama Kursu', '#e1f00a', 'Çarşamba', '18:00-20:00', 'Çakabey Kültür Merkezi', 'Dans Stüdyosu', 6, '2025-09-01', '2026-06-30', '[]', '[]', 1),
(10, 'Halk Müziği Koro', '#18af31', 'Perşembe', '19:00-23:00', 'Çakabey Kültür Merkezi', 'Dans Stüdyosu', 6, '2025-12-01', '2026-06-30', '[]', '[]', 1),
(11, 'Piyano Kursu', '#8a4505', 'Çarşamba', '18:00-21:00', 'Çakabey Kültür Merkezi', 'Atölye 2', 4, '2025-09-01', '2026-06-30', '[]', '[]', 1),
(12, 'Piyano Kursu', '#8a4505', 'Perşembe', '18:00-21:00', 'Çakabey Kültür Merkezi', 'Atölye 2', 4, '2025-09-01', '2026-06-30', '[]', '[]', 1),
(13, 'Piyano Kursu', '#8a4505', 'Cuma', '18:00-21:00', 'Çakabey Kültür Merkezi', 'Atölye 2', 4, '2025-09-01', '2026-06-30', '[]', '[]', 1);

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

INSERT INTO `course_periods` (`id`, `name`, `start_date`, `end_date`, `is_active`, `is_deleted`, `created_at`) VALUES
(1, '2025–2026 Kurs Dönemi', '2025-09-01', '2026-06-30', 1, 0, '2025-12-22 19:11:05');

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
(2, 'Ahmet', 'Yılmaz', '5551012020', 'ahmet.yilmaz@test.com', '10000000001', '2025-12-23', NULL, 'Lise', 'Mehmet Yılmaz', '5552013030'),
(3, 'Ayşe', 'Kaya', '5551022020', 'ayse.kaya@test.com', '10000000002', '2025-12-23', NULL, 'Üniversite', 'Fatma Kaya', '5552023030'),
(4, 'Mehmet', 'Demir', '5551032020', 'mehmet.demir@test.com', '10000000003', '2025-12-23', NULL, 'İlkokul', 'Ali Demir', '5552033030'),
(5, 'Fatma', 'Çelik', '5551042020', 'fatma.celik@test.com', '10000000004', '2025-12-23', NULL, 'Ortaokul', 'Hüseyin Çelik', '5552043030'),
(6, 'Mustafa', 'Şahin', '5551052020', 'mustafa.sahin@test.com', '10000000005', '2025-12-23', NULL, 'Lise', 'Emine Şahin', '5552053030'),
(7, 'Zeynep', 'Yıldız', '5551062020', 'zeynep.yildiz@test.com', '10000000006', '2025-12-23', NULL, 'Üniversite', 'Murat Yıldız', '5552063030'),
(8, 'Emre', 'Öztürk', '5551072020', 'emre.ozturk@test.com', '10000000007', '2025-12-23', NULL, 'İlkokul', 'Sibel Öztürk', '5552073030'),
(9, 'Elif', 'Aydın', '5551082020', 'elif.aydin@test.com', '10000000008', '2025-12-23', NULL, 'Ortaokul', 'Kemal Aydın', '5552083030'),
(10, 'Can', 'Arslan', '5551092020', 'can.arslan@test.com', '10000000009', '2025-12-23', NULL, 'Lise', 'Derya Arslan', '5552093030'),
(11, 'Cemre', 'Doğan', '5551102020', 'cemre.dogan@test.com', '10000000010', '2025-12-23', NULL, 'Üniversite', 'Hakan Doğan', '5552103030'),
(12, 'Burak', 'Kılıç', '5551112020', 'burak.kilic@test.com', '10000000011', '2025-12-23', NULL, 'İlkokul', 'Esra Kılıç', '5552113030'),
(13, 'Selin', 'Çetin', '5551122020', 'selin.cetin@test.com', '10000000012', '2025-12-23', NULL, 'Ortaokul', 'Okan Çetin', '5552123030'),
(14, 'Tolga', 'Kara', '5551132020', 'tolga.kara@test.com', '10000000013', '2025-12-23', NULL, 'Lise', 'Nur Kara', '5552133030'),
(15, 'Pınar', 'Koç', '5551142020', 'pinar.koc@test.com', '10000000014', '2025-12-23', NULL, 'Üniversite', 'Serkan Koç', '5552143030'),
(16, 'Volkan', 'Kurt', '5551152020', 'volkan.kurt@test.com', '10000000015', '2025-12-23', NULL, 'İlkokul', 'Gamze Kurt', '5552153030'),
(17, 'Ezgi', 'Özkan', '5551162020', 'ezgi.ozkan@test.com', '10000000016', '2025-12-23', NULL, 'Ortaokul', 'Bülent Özkan', '5552163030'),
(18, 'Onur', 'Şimşek', '5551172020', 'onur.simsek@test.com', '10000000017', '2025-12-23', NULL, 'Lise', 'Sevim Şimşek', '5552173030'),
(19, 'Gamze', 'Polat', '5551182020', 'gamze.polat@test.com', '10000000018', '2025-12-23', NULL, 'Üniversite', 'Cengiz Polat', '5552183030'),
(20, 'Mert', 'Korkmaz', '5551192020', 'mert.korkmaz@test.com', '10000000019', '2025-12-23', NULL, 'İlkokul', 'Filiz Korkmaz', '5552193030'),
(21, 'Seda', 'Bulut', '5551202020', 'seda.bulut@test.com', '10000000020', '2025-12-23', NULL, 'Ortaokul', 'Yavuz Bulut', '5552203030'),
(22, 'Kerem', 'Erdoğan', '5551212020', 'kerem.erdogan@test.com', '10000000021', '2025-12-23', NULL, 'Lise', 'Aylin Erdoğan', '5552213030'),
(23, 'Derya', 'Yavuz', '5551222020', 'derya.yavuz@test.com', '10000000022', '2025-12-23', NULL, 'Üniversite', 'Metin Yavuz', '5552223030'),
(24, 'Serkan', 'Aslan', '5551232020', 'serkan.aslan@test.com', '10000000023', '2025-12-23', NULL, 'İlkokul', 'Deniz Aslan', '5552233030'),
(25, 'Deniz', 'Ünal', '5551242020', 'deniz.unal@test.com', '10000000024', '2025-12-23', NULL, 'Ortaokul', 'Faruk Ünal', '5552243030'),
(26, 'Oğuz', 'Taş', '5551252020', 'oguz.tas@test.com', '10000000025', '2025-12-23', NULL, 'Lise', 'Gül Taş', '5552253030'),
(27, 'Gizem', 'Aksoy', '5551262020', 'gizem.aksoy@test.com', '10000000026', '2025-12-23', NULL, 'Üniversite', 'İhsan Aksoy', '5552263030'),
(28, 'Sinan', 'Coşkun', '5551272020', 'sinan.coskun@test.com', '10000000027', '2025-12-23', NULL, 'İlkokul', 'Melike Coşkun', '5552273030'),
(29, 'Buse', 'Güler', '5551282020', 'buse.guler@test.com', '10000000028', '2025-12-23', NULL, 'Ortaokul', 'Erol Güler', '5552283030'),
(30, 'Hakan', 'Uçar', '5551292020', 'hakan.ucar@test.com', '10000000029', '2025-12-23', NULL, 'Lise', 'Nermin Uçar', '5552293030'),
(31, 'Esra', 'Baş', '5551302020', 'esra.bas@test.com', '10000000030', '2025-12-23', NULL, 'Üniversite', 'Cemil Baş', '5552303030'),
(32, 'Kaan', 'Yüksel', '5551312020', 'kaan.yuksel@test.com', '10000000031', '2025-12-23', NULL, 'İlkokul', 'Dilek Yüksel', '5552313030'),
(33, 'İrem', 'Avcı', '5551322020', 'irem.avci@test.com', '10000000032', '2025-12-23', NULL, 'Ortaokul', 'Zafer Avcı', '5552323030'),
(34, 'Umut', 'Sarı', '5551332020', 'umut.sari@test.com', '10000000033', '2025-12-23', NULL, 'Lise', 'Hale Sarı', '5552333030'),
(35, 'Gözde', 'Tekin', '5551342020', 'gozde.tekin@test.com', '10000000034', '2025-12-23', NULL, 'Üniversite', 'Orhan Tekin', '5552343030'),
(36, 'Barış', 'Duman', '5551352020', 'baris.duman@test.com', '10000000035', '2025-12-23', NULL, 'İlkokul', 'Şeyma Duman', '5552353030'),
(37, 'Damla', 'Aktaş', '5551362020', 'damla.aktas@test.com', '10000000036', '2025-12-23', NULL, 'Ortaokul', 'Koray Aktaş', '5552363030'),
(38, 'Eren', 'Keskin', '5551372020', 'eren.keskin@test.com', '10000000037', '2025-12-23', NULL, 'Lise', 'Berrin Keskin', '5552373030'),
(39, 'Melis', 'Çakır', '5551382020', 'melis.cakir@test.com', '10000000038', '2025-12-23', NULL, 'Üniversite', 'Timur Çakır', '5552383030'),
(40, 'Arda', 'Yalçın', '5551392020', 'arda.yalcin@test.com', '10000000039', '2025-12-23', NULL, 'İlkokul', 'Füsun Yalçın', '5552393030'),
(41, 'Nazlı', 'Güneş', '5551402020', 'nazli.gunes@test.com', '10000000040', '2025-12-23', NULL, 'Ortaokul', 'Levent Güneş', '5552403030');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `student_courses`
--

CREATE TABLE `student_courses` (
  `student_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `period_id` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `student_courses`
  ADD COLUMN `removed_at` date DEFAULT NULL;

--
-- Tablo döküm verisi `student_courses`
--

INSERT INTO `student_courses` (`student_id`, `course_id`, `period_id`) VALUES
(2, 3, 1),
(2, 4, 1),
(2, 5, 1),
(2, 7, 1),
(2, 8, 1),
(2, 9, 1),
(2, 11, 1),
(2, 12, 1),
(2, 13, 1),
(3, 2, 1),
(3, 4, 1),
(3, 5, 1),
(3, 7, 1),
(3, 8, 1),
(3, 9, 1),
(3, 11, 1),
(3, 12, 1),
(3, 13, 1),
(4, 3, 1),
(4, 4, 1),
(4, 5, 1),
(4, 7, 1),
(4, 8, 1),
(4, 9, 1),
(4, 11, 1),
(4, 12, 1),
(4, 13, 1),
(5, 2, 1),
(5, 4, 1),
(5, 5, 1),
(5, 7, 1),
(5, 8, 1),
(5, 9, 1),
(5, 11, 1),
(5, 12, 1),
(5, 13, 1),
(6, 3, 1),
(6, 4, 1),
(6, 5, 1),
(6, 7, 1),
(6, 8, 1),
(6, 11, 1),
(6, 12, 1),
(6, 13, 1),
(7, 1, 1),
(7, 4, 1),
(7, 5, 1),
(7, 6, 1),
(7, 7, 1),
(7, 8, 1),
(7, 11, 1),
(7, 12, 1),
(7, 13, 1),
(8, 1, 1),
(8, 4, 1),
(8, 5, 1),
(8, 6, 1),
(8, 7, 1),
(8, 8, 1),
(8, 11, 1),
(8, 12, 1),
(8, 13, 1),
(9, 2, 1),
(9, 4, 1),
(9, 5, 1),
(9, 7, 1),
(9, 8, 1),
(9, 11, 1),
(9, 12, 1),
(9, 13, 1),
(10, 1, 1),
(10, 4, 1),
(10, 5, 1),
(10, 6, 1),
(10, 7, 1),
(10, 8, 1),
(10, 11, 1),
(10, 12, 1),
(10, 13, 1),
(11, 3, 1),
(11, 4, 1),
(11, 5, 1),
(11, 7, 1),
(11, 8, 1),
(11, 11, 1),
(11, 12, 1),
(11, 13, 1),
(12, 1, 1),
(12, 4, 1),
(12, 5, 1),
(12, 6, 1),
(12, 7, 1),
(12, 8, 1),
(12, 11, 1),
(12, 12, 1),
(12, 13, 1),
(13, 3, 1),
(13, 4, 1),
(13, 5, 1),
(13, 7, 1),
(13, 8, 1),
(13, 11, 1),
(13, 12, 1),
(13, 13, 1),
(14, 3, 1),
(14, 4, 1),
(14, 5, 1),
(14, 7, 1),
(14, 8, 1),
(14, 9, 1),
(14, 11, 1),
(14, 12, 1),
(14, 13, 1),
(15, 3, 1),
(15, 4, 1),
(15, 5, 1),
(15, 7, 1),
(15, 8, 1),
(15, 9, 1),
(15, 11, 1),
(15, 12, 1),
(15, 13, 1),
(16, 1, 1),
(16, 4, 1),
(16, 5, 1),
(16, 6, 1),
(16, 7, 1),
(16, 8, 1),
(16, 9, 1),
(16, 11, 1),
(16, 12, 1),
(16, 13, 1),
(17, 1, 1),
(17, 4, 1),
(17, 5, 1),
(17, 6, 1),
(17, 7, 1),
(17, 8, 1),
(17, 9, 1),
(17, 11, 1),
(17, 12, 1),
(17, 13, 1),
(18, 1, 1),
(18, 4, 1),
(18, 5, 1),
(18, 6, 1),
(18, 7, 1),
(18, 8, 1),
(18, 9, 1),
(18, 11, 1),
(18, 12, 1),
(18, 13, 1),
(19, 3, 1),
(19, 4, 1),
(19, 5, 1),
(19, 7, 1),
(19, 8, 1),
(19, 11, 1),
(19, 12, 1),
(19, 13, 1),
(20, 1, 1),
(20, 4, 1),
(20, 5, 1),
(20, 6, 1),
(20, 7, 1),
(20, 8, 1),
(20, 9, 1),
(20, 11, 1),
(20, 12, 1),
(20, 13, 1),
(21, 1, 1),
(21, 4, 1),
(21, 5, 1),
(21, 6, 1),
(21, 7, 1),
(21, 8, 1),
(21, 9, 1),
(21, 11, 1),
(21, 12, 1),
(21, 13, 1),
(22, 2, 1),
(22, 4, 1),
(22, 5, 1),
(22, 7, 1),
(22, 8, 1),
(22, 9, 1),
(22, 11, 1),
(22, 12, 1),
(22, 13, 1),
(23, 2, 1),
(23, 4, 1),
(23, 5, 1),
(23, 7, 1),
(23, 8, 1),
(23, 9, 1),
(23, 11, 1),
(23, 12, 1),
(23, 13, 1),
(24, 1, 1),
(24, 4, 1),
(24, 5, 1),
(24, 6, 1),
(24, 7, 1),
(24, 8, 1),
(24, 9, 1),
(24, 11, 1),
(24, 12, 1),
(24, 13, 1),
(25, 3, 1),
(25, 4, 1),
(25, 5, 1),
(25, 7, 1),
(25, 8, 1),
(25, 11, 1),
(25, 12, 1),
(25, 13, 1),
(26, 3, 1),
(26, 4, 1),
(26, 5, 1),
(26, 7, 1),
(26, 8, 1),
(26, 9, 1),
(26, 11, 1),
(26, 12, 1),
(26, 13, 1),
(27, 2, 1),
(27, 4, 1),
(27, 5, 1),
(27, 7, 1),
(27, 8, 1),
(27, 9, 1),
(27, 11, 1),
(27, 12, 1),
(27, 13, 1),
(28, 3, 1),
(28, 4, 1),
(28, 5, 1),
(28, 7, 1),
(28, 8, 1),
(28, 9, 1),
(28, 11, 1),
(28, 12, 1),
(28, 13, 1),
(29, 2, 1),
(29, 4, 1),
(29, 5, 1),
(29, 7, 1),
(29, 8, 1),
(29, 11, 1),
(29, 12, 1),
(29, 13, 1),
(30, 3, 1),
(30, 4, 1),
(30, 5, 1),
(30, 7, 1),
(30, 8, 1),
(30, 11, 1),
(30, 12, 1),
(30, 13, 1),
(31, 2, 1),
(31, 4, 1),
(31, 5, 1),
(31, 7, 1),
(31, 8, 1),
(31, 11, 1),
(31, 12, 1),
(31, 13, 1),
(32, 2, 1),
(32, 4, 1),
(32, 5, 1),
(32, 7, 1),
(32, 8, 1),
(32, 11, 1),
(32, 12, 1),
(32, 13, 1),
(33, 1, 1),
(33, 4, 1),
(33, 5, 1),
(33, 6, 1),
(33, 7, 1),
(33, 8, 1),
(33, 11, 1),
(33, 12, 1),
(33, 13, 1),
(34, 2, 1),
(34, 4, 1),
(34, 5, 1),
(34, 7, 1),
(34, 8, 1),
(34, 11, 1),
(34, 12, 1),
(34, 13, 1),
(35, 3, 1),
(35, 4, 1),
(35, 5, 1),
(35, 7, 1),
(35, 8, 1),
(35, 11, 1),
(35, 12, 1),
(35, 13, 1),
(36, 2, 1),
(36, 4, 1),
(36, 5, 1),
(36, 7, 1),
(36, 8, 1),
(36, 11, 1),
(36, 12, 1),
(36, 13, 1),
(37, 3, 1),
(37, 4, 1),
(37, 5, 1),
(37, 7, 1),
(37, 8, 1),
(37, 11, 1),
(37, 12, 1),
(37, 13, 1),
(38, 2, 1),
(38, 4, 1),
(38, 5, 1),
(38, 7, 1),
(38, 8, 1),
(38, 11, 1),
(38, 12, 1),
(38, 13, 1),
(39, 2, 1),
(39, 4, 1),
(39, 5, 1),
(39, 7, 1),
(39, 8, 1),
(39, 11, 1),
(39, 12, 1),
(39, 13, 1),
(40, 2, 1),
(40, 4, 1),
(40, 5, 1),
(40, 7, 1),
(40, 8, 1),
(40, 11, 1),
(40, 12, 1),
(40, 13, 1),
(41, 1, 1),
(41, 4, 1),
(41, 5, 1),
(41, 6, 1),
(41, 7, 1),
(41, 8, 1),
(41, 11, 1),
(41, 12, 1),
(41, 13, 1);

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
(2, 1, '2025-12-23'),
(3, 1, '2025-12-23'),
(4, 1, '2025-12-23'),
(5, 1, '2025-12-23'),
(6, 1, '2025-12-23'),
(7, 1, '2025-12-23'),
(8, 1, '2025-12-23'),
(9, 1, '2025-12-23'),
(10, 1, '2025-12-23'),
(11, 1, '2025-12-23'),
(12, 1, '2025-12-23'),
(13, 1, '2025-12-23'),
(14, 1, '2025-12-23'),
(15, 1, '2025-12-23'),
(16, 1, '2025-12-23'),
(17, 1, '2025-12-23'),
(18, 1, '2025-12-23'),
(19, 1, '2025-12-23'),
(20, 1, '2025-12-23'),
(21, 1, '2025-12-23'),
(22, 1, '2025-12-23'),
(23, 1, '2025-12-23'),
(24, 1, '2025-12-23'),
(25, 1, '2025-12-23'),
(26, 1, '2025-12-23'),
(27, 1, '2025-12-23'),
(28, 1, '2025-12-23'),
(29, 1, '2025-12-23'),
(30, 1, '2025-12-23'),
(31, 1, '2025-12-23'),
(32, 1, '2025-12-23'),
(33, 1, '2025-12-23'),
(34, 1, '2025-12-23'),
(35, 1, '2025-12-23'),
(36, 1, '2025-12-23'),
(37, 1, '2025-12-23'),
(38, 1, '2025-12-23'),
(39, 1, '2025-12-23'),
(40, 1, '2025-12-23'),
(41, 1, '2025-12-23');

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
(1, 'Onur Tabak', '', '', 'onur', '$2y$10$GeLbvHD7JXpOxc08FYJbsuW3eJYGTgTHTdLbtehwd5nCj.PkNevmm', 'Halk Dansları'),
(2, 'Batuhan Tabak', '5346346346346', '', 'batuhan', '$2y$10$Y/MWbRsjFbLKMVM4Wq8hX.umdBC5Y5eaFBTeZ2b41MMvpiM0VMfpS', 'Halk Dansları'),
(3, 'Aysel Güzel', '05057052602', '', 'aysel', '123456', NULL),
(4, 'Gönenç Hoca', '5346346346346', '', 'gonenc', '$2y$10$8P24JK6qnoEuJNj9EvKv9ep328iPj9paLR5BOYmd4PqBln8WU1rEO', 'Müzik Öğretmeni'),
(5, 'Svetlana Hanım', '5346346346346', '', 'test', '$2y$10$zlsxJSISQMmve15L5.qWQOT/iNmNt28n.zSu6jRP5ObFK8UtEHHNq', 'Bale Öğretmei'),
(6, 'Osman Uysal', '5346346346346', '', 'osman', '$2y$10$TRhhfJK9QZvFoHPQ.N6bHeEqBQDwyLEQs2SE1HyIxqCZHe4lXXbU2', 'Müzik Öğretmeni');

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
-- Tablo için indeksler `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`);

--
-- Tablo için indeksler `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_attendance_period` (`period_id`);

--
-- Tablo için indeksler `courses`
--
ALTER TABLE `courses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_courses_period` (`period_id`);

--
-- Tablo için indeksler `course_periods`
--
ALTER TABLE `course_periods`
  ADD PRIMARY KEY (`id`);

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
-- Tablo için indeksler `student_courses`
--
ALTER TABLE `student_courses`
  ADD PRIMARY KEY (`student_id`,`course_id`,`period_id`),
  ADD KEY `idx_student_courses_period` (`period_id`);

--
-- Tablo için indeksler `student_periods`
--
ALTER TABLE `student_periods`
  ADD PRIMARY KEY (`student_id`,`period_id`),
  ADD KEY `idx_student_periods_period` (`period_id`);

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
-- Dökümü yapılmış tablolar için AUTO_INCREMENT değeri
--

--
-- Tablo için AUTO_INCREMENT değeri `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Tablo için AUTO_INCREMENT değeri `courses`
--
ALTER TABLE `courses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- Tablo için AUTO_INCREMENT değeri `course_periods`
--
ALTER TABLE `course_periods`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Tablo için AUTO_INCREMENT değeri `holidays`
--
ALTER TABLE `holidays`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- Tablo için AUTO_INCREMENT değeri `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- Tablo için AUTO_INCREMENT değeri `teachers`
--
ALTER TABLE `teachers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Tablo için AUTO_INCREMENT değeri `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Dökümü yapılmış tablolar için kısıtlamalar
--

--
-- Tablo kısıtlamaları `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `fk_attendance_period` FOREIGN KEY (`period_id`) REFERENCES `course_periods` (`id`);

--
-- Tablo kısıtlamaları `courses`
--
ALTER TABLE `courses`
  ADD CONSTRAINT `fk_courses_period` FOREIGN KEY (`period_id`) REFERENCES `course_periods` (`id`);

--
-- Tablo kısıtlamaları `student_courses`
--
ALTER TABLE `student_courses`
  ADD CONSTRAINT `fk_student_courses_period` FOREIGN KEY (`period_id`) REFERENCES `course_periods` (`id`);

--
-- Tablo kısıtlamaları `student_periods`
--
ALTER TABLE `student_periods`
  ADD CONSTRAINT `fk_student_periods_period` FOREIGN KEY (`period_id`) REFERENCES `course_periods` (`id`),
  ADD CONSTRAINT `fk_student_periods_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
