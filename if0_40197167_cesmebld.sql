-- phpMyAdmin SQL Dump
-- version 4.9.0.1
-- https://www.phpmyadmin.net/
--
-- Anamakine: sql211.infinityfree.com
-- Üretim Zamanı: 18 Ara 2025, 07:50:39
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
-- Veritabanı: `if0_40197167_cesmebld`
--

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `admin_users`
--

CREATE TABLE `admin_users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Tablo döküm verisi `admin_users`
--

INSERT INTO `admin_users` (`id`, `username`, `password`, `full_name`, `email`, `is_active`, `created_at`) VALUES
(4, 'djmaster', '$2y$10$UW3E437tp9dsdtnfVycfBOAc7I6xDB5nGE4qI/u2LKtlIGJhIf4oe', 'İlhan Akdeniz', 'ilhanakd@gmail.com', 1, '2025-10-14 07:22:08'),
(2, 'admin', '$2y$10$7irqARE1EwYwptYm9UFoqOoijWNHAUnyv5VVFW6fEJ1EulNsLJaym', 'Yedek Admin', 'admin@cesme.bel.tr', 1, '2025-10-13 17:51:14'),
(3, 'test', '$2y$10$eHPtUF.xVJ7drGPwb0WaweWKMl.u4A2AxRn5d2OcBGcdVwL5FFA6a', 'Test Kullanıcı', '', 1, '2025-10-13 18:01:28'),
(5, 'azizburhan', '$2y$10$X8w6BynzTSlU3cUbM57nBeLv0R5jdwm8KvVECSMgSULnbpR8R9gpm', 'Aziz Burhan', '', 1, '2025-10-30 10:42:40'),
(6, 'deryaefdal', '$2y$10$/.Uc4dLa8KCWYHDyCyHfIuHFRAGdkjIbkJ1KHLjiPbropxeEhIjNS', 'Derya Efdal', 'derya.efdal@gmail.com', 1, '2025-10-30 12:32:15'),
(7, 'pınarkaplan', '$2y$10$YashLBZeO5EOCEMHnf4jSuSC3jrY8DQ.ueuKfq48xGhyoN7mxRAdW', 'Pınar Kaplan', 'pinarkaplann@gmail.com', 1, '2025-11-02 08:48:55'),
(8, 'ezgileblebicioğlu', '$2y$10$IVdrRp6/cIt9aNc6qVsxo.8baKqOFk/V/cHGMUEIp107mNnJgzyFG', 'Ezgi Leblebicioğlu', 'ezleblebici@gmail.com', 1, '2025-11-02 08:49:48'),
(9, 'aydındogruyol', '$2y$10$lIN.bESj2kbskDt4t00ND.9zr6gOqShA.QM8bt64JcdNuuTDshujy', 'Aydın Doğruyol', '', 1, '2025-11-02 08:51:26'),
(10, 'dilekkaraoğlu', '$2y$10$OrqCfU5kMtq8n1/zAJiVbeMcoJQzZE8w3nPC3UP6Cr6cs/X.TM2Dy', 'Dilek Karaoğlu', '', 1, '2025-11-02 09:05:43'),
(11, 'kilise', '$2y$10$ueQII73LG.2a2ph45oi.HuwCFsodkNmYOu60JbXF/4Ouj.UHyEyaK', 'Nilüfer Eriş', '', 1, '2025-11-02 09:09:23'),
(12, 'çeşmeamfi', '$2y$10$wxf8KwwDSGszmDhT0vvw6u0SZdr08zfyg70uRPpd887GrhRLbDgTG', 'Çeşme Amfi Açık Hava Tiyatrosu', '', 1, '2025-11-02 09:10:31');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `announcements`
--

CREATE TABLE `announcements` (
  `id` int(11) NOT NULL,
  `content` text NOT NULL,
  `admin_user_id` int(11) NOT NULL,
  `show_author` tinyint(1) DEFAULT 0,
  `start_date` datetime DEFAULT NULL,
  `end_date` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Tablo döküm verisi `announcements`
--

INSERT INTO `announcements` (`id`, `content`, `admin_user_id`, `show_author`, `start_date`, `end_date`, `created_at`) VALUES
(7, 'İlhan beyin güncellemeleri ve Etkinliğe yönelik taleplerimiz çerçevesinde uygulama geliştirilmeye devam ediyor. Test olarak kullanıma açıldı. Aramıza yeni katılan Derya Efdal arkadaşımız, uygulama içerisine Çakabey, Kilise, Düğün Salonları etkinliklerini girmeye devam ediyor. Tüm testler bittikten sonra Aralık ayında bu program üzerinden sistemi kullanacağız. Diğer admin olacak arkadaşların kullanıcı adları alındı.\r\n\r\npınarkaplan\r\naydındoğruyol\r\nezgileblebicioğlu\r\ndilekkaraoğlu\r\n\r\n\r\n\r\nşifre: 12345', 5, 1, '2025-11-02 11:57:00', '2025-11-30 11:57:00', '2025-11-02 09:01:06'),
(8, 'Mevcut tüm program girilmiştir. Çakabey, Kilise, Düğün Salonu... Drive daki listeler aktarılmış olup, yedekli olarak tutulmaya devam edecektir. 1 Aralık itibarıyla bu uygulama aktif olarak kullanılacaktır.', 5, 1, '2025-11-06 11:09:00', '2025-11-30 11:09:00', '2025-11-06 08:10:04');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `app_settings`
--

CREATE TABLE `app_settings` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Tablo döküm verisi `app_settings`
--

INSERT INTO `app_settings` (`setting_key`, `setting_value`, `updated_at`) VALUES
('calendar_updates_color_cancelled', '#e63946', '2025-12-04 12:29:06'),
('calendar_updates_color_new', '#2a9d8f', '2025-12-04 12:29:06'),
('calendar_updates_color_updated', '#3a86ff', '2025-12-04 12:29:06'),
('calendar_updates_display_types', '[\"new\",\"updated\",\"cancelled\"]', '2025-12-04 12:29:06'),
('calendar_updates_enabled', '1', '2025-12-04 14:08:36'),
('calendar_updates_expire_hours', '24', '2025-12-04 12:29:06'),
('calendar_updates_hours', '24', '2025-12-04 13:09:39'),
('calendar_updates_limit', '5', '2025-12-04 13:09:39'),
('calendar_updates_max_visible', '10', '2025-12-04 12:29:06'),
('maintenance_mode', '0', '2025-11-10 22:59:24'),
('updates_duration', '24', '2025-12-04 12:45:07'),
('updates_duration_unit', 'hours', '2025-12-04 18:39:24'),
('updates_enabled', '1', '2025-12-15 19:15:06'),
('updates_max_count', '5', '2025-12-04 18:39:24');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `events`
--

CREATE TABLE `events` (
  `id` int(11) NOT NULL,
  `unit_id` int(11) NOT NULL,
  `event_date` date NOT NULL,
  `event_name` varchar(255) NOT NULL,
  `event_time` varchar(100) DEFAULT NULL,
  `contact_info` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('confirmed','option','cancelled','free') DEFAULT 'confirmed',
  `payment_status` enum('paid','not_paid','to_be_paid') DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Tablo döküm verisi `events`
--

INSERT INTO `events` (`id`, `unit_id`, `event_date`, `event_name`, `event_time`, `contact_info`, `notes`, `status`, `payment_status`, `created_at`, `updated_at`) VALUES
(1, 1, '2025-01-10', 'Çocuk Tiyatrosu', '14:00-16:00', 'Ahmet Yılmaz - 0532 123 4567', 'Çocuklar için eğitici tiyatro oyunu', 'confirmed', 'paid', '2025-10-13 17:39:05', '2025-10-13 17:39:05'),
(2, 11, '2025-01-15', 'Düğün Organizasyonu', '18:00-23:00', 'Ayşe Demir - 0541 234 5678', 'Mehmet & Ayşe düğün töreni', 'confirmed', 'paid', '2025-10-13 17:39:05', '2025-10-16 17:42:36'),
(3, 3, '2025-01-20', 'Yaz Konseri', '20:00-22:00', 'Kültür Müdürlüğü - 0232 123 4567', 'Yerel sanatçılar konseri', 'confirmed', 'to_be_paid', '2025-10-13 17:39:05', '2025-10-15 21:34:18'),
(5, 5, '2025-02-01', 'Resim Sergisi', '10:00-18:00', 'Sanat Galerisi - 0533 345 6789', 'Yerel ressamlar sergisi', 'option', 'to_be_paid', '2025-10-13 17:39:05', '2025-10-13 17:39:05'),
(6, 6, '2025-02-05', 'Eğitim Semineri', '13:00-17:00', 'Eğitim Derneği - 0544 456 7890', 'Kişisel gelişim semineri', 'free', NULL, '2025-10-13 17:39:05', '2025-10-16 17:42:07'),
(22, 1, '2025-10-06', 'Çeşme Belediyesi Kent Enstitüsü Tiyatro çalışması', 'Tam gün', '', 'Saat 15:00 da başlayacak', 'free', NULL, '2025-10-15 14:55:19', '2025-10-16 17:42:07'),
(21, 1, '2025-10-04', 'Alaçatı Ilıca kültür sanat derneği \'\'Masal Kabare\'\' oyunu', 'Tam gün', 'Serdar alaca tel: 5055899941', '', 'confirmed', 'paid', '2025-10-15 14:53:58', '2025-10-15 14:53:58'),
(20, 1, '2025-10-03', 'Alaçatı Ilıca kültür sanat derneği \'\'Masal Kabare oyunu', '14:00 - 17:00', 'iletişim Serdar alaca tel: 5055899941', 'Prova tüm gün', 'confirmed', 'paid', '2025-10-15 14:52:14', '2025-10-16 08:16:56'),
(23, 1, '2025-10-10', 'Dev-Sen Çeşme Şubesi kongre toplantısı', '14:00 - 17:00', 'İbrahim Tuz tel: 5326719836', '', 'free', NULL, '2025-10-15 15:37:49', '2025-10-16 17:42:07'),
(24, 1, '2025-10-13', 'Çeşme Belediyesi Kent Enstitüsü Tiyatro çalışması', 'Tam gün', '', '', 'free', NULL, '2025-10-15 15:39:23', '2025-10-16 17:42:07'),
(25, 1, '2025-10-15', 'Spor müdürlüğü toplantısı', '10:00', '', '', 'free', NULL, '2025-10-15 15:41:09', '2025-10-16 17:42:07'),
(26, 1, '2025-10-15', 'İklim Değişikliği Müdürlüğü Seminer', '14.00 - 16.30', '', '', 'free', NULL, '2025-10-15 15:42:07', '2025-10-16 17:42:07'),
(27, 1, '2025-10-17', 'afet müdürlüğü yangın eğitimi seminer kesin', 'belli değil', '', '', 'free', NULL, '2025-10-15 16:19:10', '2025-10-16 17:42:07'),
(28, 1, '2025-10-18', 'Nasrettin Hoca Müzikali opsiyon iletişim', 'Belli değil', 'Volkan Özyurt iletişim 0546 930 5556', '', 'cancelled', NULL, '2025-10-15 17:17:22', '2025-10-16 17:42:15'),
(29, 1, '2025-10-20', 'Çeşme Belediyesi Kent Enstitüsü Tiyatro çalışması', 'Tüm gün', '', '', 'free', NULL, '2025-10-15 17:28:58', '2025-10-16 17:42:07'),
(30, 3, '2025-10-01', 'Deneme etkinliği', '14:00 - 17:00', '05321234567', '', 'option', 'to_be_paid', '2025-10-15 17:34:53', '2025-10-15 17:34:53'),
(31, 11, '2025-10-01', 'Nikah Töreni', '19:00', '54544155454', 'Nikah masası ve sandalye düzeni sağlanacak.', 'confirmed', 'paid', '2025-10-16 08:00:37', '2025-10-16 10:30:32'),
(32, 11, '2025-10-02', 'deneme', '14:00 - 17:00', '4654545454', 'deneme test 123', 'option', 'to_be_paid', '2025-10-16 08:02:31', '2025-10-16 15:38:58'),
(33, 1, '2025-10-19', 'izmir Büyük Şehir Belediyesi \"Küçük Göz yaşı\" tiyatro oyunu', 'Tam gün', '', '20.00 prova  13.00 - 13.00 arası', 'cancelled', NULL, '2025-10-16 17:17:46', '2025-10-16 17:42:15'),
(34, 1, '2025-10-23', 'Kadın Aile müdürlüğü  meme kanseri farkındalık semineri', 'Belli değil', '', '', 'cancelled', NULL, '2025-10-17 06:53:18', '2025-10-23 06:35:33'),
(35, 1, '2025-10-25', 'Grand Fondo organizasyon', '16.00 - 19.00 arasında', '', '', 'cancelled', NULL, '2025-10-17 06:55:27', '2025-10-24 22:41:51'),
(36, 1, '2025-10-27', 'Çeşme Belediyesi Kent Enstitüsü Tiyatro çalışması', 'Tam gün', '', '', 'free', NULL, '2025-10-17 06:56:42', '2025-10-17 06:56:42'),
(38, 1, '2025-10-30', 'Hacı Murat Lisesi 10 Kasım anma prova', '08:30', '', '', 'free', NULL, '2025-10-17 06:57:53', '2025-10-17 07:19:14'),
(39, 1, '2025-10-31', 'Hacı Murat Lisesi 10 Kasım anma prova', '08:30', '', '', 'free', NULL, '2025-10-17 06:58:26', '2025-10-17 07:18:58'),
(40, 7, '2025-10-17', 'Nikah Töreni', '15:00', 'Test İletişim', 'Test notu.', 'confirmed', 'paid', '2025-10-17 14:20:18', '2025-10-17 14:20:18'),
(47, 1, '2025-10-29', 'Film Gösterimi: \"Bir Cumhuriyet Şarkısı\"', '17.00', '', 'Çakabey Kültür Merkezi\r\nTiyatro Salonu\r\n\r\n29 Ekim de Sinema Gösterimi yapılacaktır. \r\n\r\nFilm: \"Bir Cumhuriyet Şarkısı\"\r\nYer: Çakabey Kültür Merkezi\r\nSaat: 17.00\r\n\r\nProjeksiyon ve Laptopun hazır olması hususunda gerekli çalışmayı yapalım. \r\n\r\n@İlhan', 'free', NULL, '2025-10-21 11:11:52', '2025-10-21 11:11:52'),
(48, 1, '2025-10-26', 'Çocuk tiyatrosu', '11:00 - 18:00', '', '', 'confirmed', 'paid', '2025-10-24 22:43:27', '2025-10-24 22:43:27'),
(49, 11, '2025-11-04', 'Yoga Etkinliği', '10.00', '', 'Çeşme Kent Konseyi Nermin Ekinci', 'cancelled', NULL, '2025-10-30 12:46:28', '2025-11-04 05:28:47'),
(50, 11, '2025-11-11', 'Yoga Etkinliği', '10.00', '', 'Çeşme Kent Konseyi Nermin Ekinci', 'cancelled', NULL, '2025-10-30 12:47:10', '2025-11-04 05:28:59'),
(51, 11, '2025-11-18', 'Yoga Etkinliği', '10.00', '', 'Çeşme Kent Konseyi Nermin Ekinci', 'confirmed', '', '2025-10-30 12:47:39', '2025-10-30 13:04:26'),
(52, 11, '2025-11-25', 'Yoga Etkinliği', '10.00', '', 'Çeşme Kent Konseyi Nermin Ekinci', 'confirmed', '', '2025-10-30 12:48:11', '2025-10-30 13:04:35'),
(53, 11, '2025-11-23', 'Düğün', '20.00', 'Hilal Yağcıoğlu 05546922586', '', 'confirmed', 'paid', '2025-10-30 12:49:00', '2025-10-30 12:52:04'),
(54, 11, '2025-12-02', 'Yoga Etkinliği', '10.00', '', 'Çeşme Kent Konseyi Nermin Ekinci', 'confirmed', '', '2025-10-30 12:49:59', '2025-10-30 13:04:52'),
(55, 11, '2025-12-09', 'Yoga Etkinliği', '10.00', '', 'Çeşme Kent Konseyi Nermin Ekinci', 'confirmed', '', '2025-10-30 12:50:22', '2025-10-30 13:05:01'),
(56, 11, '2025-12-16', 'Yoga Etkinliği', '10.00', '', 'Çeşme Kent Konseyi Nermin Etkinliği', 'confirmed', '', '2025-10-30 12:50:53', '2025-10-30 13:05:07'),
(57, 11, '2025-12-23', 'Yoga Etkinliği', '10.00', '', 'Çeşme Kent Konseyi Nermin Etkinliği', 'confirmed', '', '2025-10-30 12:51:09', '2025-10-30 13:05:12'),
(58, 11, '2025-12-30', 'Yoga Etkinliği', '10.00', '', 'Çeşme Kent Konseyi Nermin Etkinliği', 'confirmed', '', '2025-10-30 12:51:27', '2025-10-30 13:05:18'),
(59, 11, '2025-12-06', 'Nişan', '20.00', 'Onur Biçer 05385137903', '', 'confirmed', 'paid', '2025-10-30 12:52:51', '2025-11-06 13:31:44'),
(60, 7, '2025-11-01', 'Kültür Yolu Festivali', '19.30', '', 'Türk Sanat Müziği Konseri', 'confirmed', '', '2025-10-30 12:58:07', '2025-10-30 12:59:02'),
(61, 7, '2025-11-02', 'Kültür Yolu Festivali', '19.30', '', 'Türk Halk Müziği Konseri', 'confirmed', '', '2025-10-30 12:58:55', '2025-10-30 12:58:55'),
(62, 7, '2025-11-03', 'Kültür Yolu Festivali Etkinlikleri', '09.00-00.00', '', 'Tüm Gün', 'confirmed', '', '2025-10-30 13:07:50', '2025-10-30 13:07:50'),
(63, 7, '2025-11-04', 'Kültür Yolu Festivali Etkinlikleri', '09.00-00.00', '', 'Tüm Gün', 'confirmed', '', '2025-10-30 13:08:11', '2025-10-30 13:08:11'),
(64, 7, '2025-11-05', 'Mehmet Culum Kitap İmza Günü', '14.30', '', '', 'confirmed', '', '2025-10-30 13:09:13', '2025-10-30 13:09:13'),
(151, 1, '2025-12-17', 'Büyükşehir Belediyesi Toprak Analizi Eğitimi', '11.30-12.30', '', '', 'confirmed', '', '2025-12-17 06:51:04', '2025-12-17 06:51:09'),
(66, 7, '2025-11-08', 'Çeşme Belediye Spor Atatürk Haftası Satranç Turnuvası', '09.00-00.00', '', '', 'confirmed', '', '2025-10-30 13:10:21', '2025-10-31 14:03:58'),
(67, 7, '2025-11-09', 'Çeşme Belediye Spor Atatürk Haftası Satranç Turnuvası', '09.00-00.00', '', '', 'confirmed', '', '2025-10-30 13:10:29', '2025-10-31 14:04:02'),
(68, 7, '2025-11-23', 'Size Şapka Çıkartıyoruz 25 Kasım Kadına Yönelik Şiddetle Mücadele Günü Şapka Sergisi Hazırlığı', '09.00-00.00', '', 'Hazırlık-Tüm Gün', 'confirmed', '', '2025-10-30 13:11:14', '2025-10-30 13:35:09'),
(69, 7, '2025-11-24', 'Size Şapka Çıkartıyoruz 25 Kasım Kadına Yönelik Şiddetle Mücadele Günü Şapka Sergisi Hazırlığı', '09.00-00.00', '', 'Tüm Gün', 'confirmed', '', '2025-10-30 13:29:48', '2025-10-30 13:34:57'),
(70, 7, '2025-11-25', 'Size Şapka Çıkartıyoruz 25 Kasım Kadına Yönelik Şiddetle Mücadele Günü Şapka Sergisi', '09.00-00.00', '', 'Açılış- Tüm Gün', 'confirmed', '', '2025-10-30 13:30:04', '2025-10-30 13:34:31'),
(71, 7, '2025-11-26', 'Size Şapka Çıkartıyoruz 25 Kasım Kadına Yönelik Şiddetle Mücadele Günü Şapka Sergisi', '09.00-00.00', '', 'Tüm Gün', 'confirmed', '', '2025-10-30 13:30:26', '2025-10-30 13:34:15'),
(72, 7, '2025-11-27', 'Size Şapka Çıkartıyoruz 25 Kasım Kadına Yönelik Şiddetle Mücadele Günü Şapka Sergisi', '09.00-00.00', '', 'Tüm Gün', 'confirmed', '', '2025-10-30 13:30:37', '2025-10-30 13:34:10'),
(73, 7, '2025-11-28', 'Size Şapka Çıkartıyoruz 25 Kasım Kadına Yönelik Şiddetle Mücadele Günü Şapka Sergisi', '09.00-00.00', '', 'Tüm Gün', 'confirmed', '', '2025-10-30 13:30:55', '2025-10-30 13:34:05'),
(74, 7, '2025-11-29', 'Size Şapka Çıkartıyoruz 25 Kasım Kadına Yönelik Şiddetle Mücadele Günü Şapka Sergisi', '09.00-00.00', '', 'Tüm Gün', 'confirmed', '', '2025-10-30 13:31:13', '2025-10-30 13:33:59'),
(75, 7, '2025-11-30', 'Size Şapka Çıkartıyoruz 25 Kasım Kadına Yönelik Şiddetle Mücadele Günü Şapka Sergisi', '09.00-00.00', '', 'Tüm Gün', 'confirmed', '', '2025-10-30 13:31:23', '2025-10-30 13:33:53'),
(76, 7, '2025-12-01', 'Size Şapka Çıkartıyoruz 25 Kasım Kadına Yönelik Şiddetle Mücadele Günü Şapka Sergisi', '09.00-00.00', '', 'Tüm Gün', 'confirmed', '', '2025-10-30 13:31:42', '2025-10-30 13:33:44'),
(77, 7, '2025-12-02', 'Size Şapka Çıkartıyoruz 25 Kasım Kadına Yönelik Şiddetle Mücadele Günü Şapka Sergisi', '09.00-00.00', '', 'Tüm Gün', 'confirmed', '', '2025-10-30 13:31:51', '2025-10-30 13:33:40'),
(78, 7, '2025-12-03', 'Size Şapka Çıkartıyoruz 25 Kasım Kadına Yönelik Şiddetle Mücadele Günü Şapka Sergisi', '09.00-00.00', '', 'Tüm Gün', 'confirmed', '', '2025-10-30 13:32:00', '2025-10-30 13:33:36'),
(79, 7, '2025-12-04', 'Size Şapka Çıkartıyoruz 25 Kasım Kadına Yönelik Şiddetle Mücadele Günü Şapka Sergisi', '09.00-00.00', '', 'Tüm Gün', 'confirmed', '', '2025-10-30 13:32:09', '2025-10-30 13:33:31'),
(80, 7, '2025-12-05', 'Size Şapka Çıkartıyoruz 25 Kasım Kadına Yönelik Şiddetle Mücadele Günü Şapka Sergisi', '09.00-00.00', '', 'Tüm Gün', 'confirmed', '', '2025-10-30 13:32:18', '2025-10-30 13:33:26'),
(81, 7, '2025-12-06', 'Size Şapka Çıkartıyoruz 25 Kasım Kadına Yönelik Şiddetle Mücadele Günü Şapka Sergisi', '09.00-00.00', '', 'Tüm Gün', 'confirmed', '', '2025-10-30 13:32:30', '2025-10-30 13:33:21'),
(82, 7, '2025-12-07', 'Size Şapka Çıkartıyoruz 25 Kasım Kadına Yönelik Şiddetle Mücadele Günü Şapka Sergisi', '09.00-00.00', '', 'Tüm Gün', 'confirmed', '', '2025-10-30 13:32:52', '2025-10-30 13:33:11'),
(83, 1, '2025-11-03', 'Hacı Murat Lisesi 10 Kasım Anma Günü Provası ve Çeşme Belediyesi Tiyatro Çalışması', '13.00-16.00', '', '13.00-15.00 Arası Hacı Murat Lisesi Çalışması\r\n\r\n15.00-22.00 Kent Enstitüsü Tiyatro Çalışması', 'confirmed', '', '2025-10-30 13:37:59', '2025-10-30 13:54:27'),
(84, 1, '2025-11-04', 'Hacı Murat Lisesi 10 Kasım Anma Günü', '09.00-00.00', '', 'Prova-Tüm Gün', 'confirmed', '', '2025-10-30 13:39:58', '2025-10-30 13:39:58'),
(85, 1, '2025-11-05', 'Hacı Murat Lisesi 10 Kasım Anma Günü', '09.00-00.00', '', 'Prova-Tüm Gün', 'confirmed', '', '2025-10-30 13:40:29', '2025-10-30 13:40:44'),
(86, 1, '2025-11-06', 'Hacı Murat Lisesi 10 Kasım Anma Günü', '09.00-00.00', '', 'Prova-Tüm Gün', 'confirmed', '', '2025-10-30 13:41:00', '2025-10-30 13:41:00'),
(87, 1, '2025-11-07', 'Hacı Murat Lisesi 10 Kasım Anma Günü', '09.00-00.00', '', 'Prova-Tüm Gün', 'confirmed', '', '2025-10-30 13:41:14', '2025-10-30 13:41:14'),
(88, 1, '2025-11-09', 'İzmir Aşkına Mübadelede Aşk Oyunu', '20.00', '', '', 'confirmed', '', '2025-10-30 13:42:01', '2025-10-30 13:42:01'),
(89, 1, '2025-11-10', 'Hacı Murat Lisesi 10 Kasım', '09.00-00.00', '', 'Anma Töreni Günü', 'confirmed', '', '2025-10-30 13:42:54', '2025-11-05 13:58:13'),
(90, 1, '2025-11-11', 'İnsan Kaynakları Müdürlüğü Semineri', '09.00-00.00', '', '', 'confirmed', '', '2025-10-30 13:43:28', '2025-10-30 13:43:28'),
(91, 1, '2025-11-12', 'Yağmuru Sevmeyen Çocuk-Sıcakken Sanat', '12.00-15.00', '', '2 temsil ve halka ücretsiz', 'confirmed', '', '2025-10-30 13:43:50', '2025-11-07 11:11:43'),
(92, 1, '2025-11-13', 'İzbb Ürkmez Çocukça Tiyatro', '13.00 ve 15.00', '', '2 seans', 'confirmed', '', '2025-10-30 13:43:59', '2025-11-04 12:31:28'),
(93, 1, '2025-11-14', 'Kamptaki Sürpriz-İzmir Sanat Etkinlikleri', '12.00-15.00', '', '2 temsil ve halka ücretsiz', 'confirmed', '', '2025-10-30 13:44:08', '2025-11-05 14:16:29'),
(94, 1, '2025-11-15', 'Özel Kalem Müdürlüğü Toplantısı', '12.00-16.00', '', 'Gürkan Bey', 'confirmed', '', '2025-10-30 13:44:16', '2025-11-05 13:01:46'),
(95, 1, '2025-11-16', 'Gerçek Oyun-Sıcakken Sanat', '12.00-15.00', '', '2 temsil ve halka ücretsiz', 'confirmed', '', '2025-10-30 13:44:25', '2025-11-05 14:17:07'),
(96, 1, '2025-11-17', 'Çeşme Belediyesi Kent Enstitüsü Tiyatro Çalışması', '09.00-00.00', '', 'Tüm Gün', 'confirmed', '', '2025-10-30 13:45:10', '2025-10-30 13:45:10'),
(97, 1, '2025-11-24', '24 Kasım Öğretmenler Günü Programı Atatürk AL', '09.00-00.00', '', 'Tüm Gün', 'confirmed', '', '2025-10-30 13:45:35', '2025-11-05 08:20:26'),
(98, 1, '2025-11-21', '24 Kasım Öğretmenler Günü Ramazan Hoca\'nın Provası Atatürk AL', '09.00-00.00', '', '', 'confirmed', '', '2025-10-30 13:45:51', '2025-11-05 08:19:31'),
(99, 1, '2025-11-22', 'Minik\'in Oyuncakları-Maske Sanat', '12.00-15.00', '', '2 temsil ve halka ücretsiz', 'confirmed', '', '2025-10-30 13:46:01', '2025-11-05 14:18:01'),
(100, 1, '2025-11-23', '24 Kasım Öğretmenler Günü Ramazan Hoca\'nın Provası Atatürk AL', '09.00-00.00', '', '', 'confirmed', '', '2025-10-30 13:46:09', '2025-11-05 08:20:06'),
(102, 1, '2025-11-29', 'Şakacı Fırfır-Maske Sanat', '12.00-15.00', '', '2 temsil ve halka ücretsiz', 'confirmed', '', '2025-10-30 13:46:28', '2025-11-05 14:19:33'),
(103, 1, '2025-11-30', 'Don Kişot-Maske Sanat', '12.00-15.00', '', '2 temsil ve halka ücretsiz', 'confirmed', '', '2025-10-30 13:46:35', '2025-11-05 14:20:07'),
(104, 1, '2025-12-01', 'Çeşme Belediyesi Kent Enstitüsü Tiyatro Çalışması', '09.00-00.00', '', 'Tüm Gün', 'confirmed', '', '2025-10-30 13:47:04', '2025-10-30 13:47:04'),
(105, 1, '2025-12-05', 'Oyun', '09.00-00.00', '', 'Dünya Kadın Hakları Günü Kapsamında Bir Oyun Sergilenecek', 'option', '', '2025-10-30 13:47:17', '2025-11-03 06:03:42'),
(106, 1, '2025-12-06', 'Minik Bezelye-Çocuk Tiyatrosu', '12.00-15.00', '', '2 seans ve halka ücretsizdir', 'confirmed', '', '2025-10-30 13:47:28', '2025-11-06 06:54:43'),
(107, 1, '2025-12-07', 'Gülmeyen Kral-Çocuk Tiyatrosu', '12.00-15.00', '', '2 seans ve halka ücretsiz', 'confirmed', '', '2025-10-30 13:47:34', '2025-12-02 12:13:39'),
(108, 1, '2025-12-08', 'Çeşme Belediyesi Kent Enstitüsü Tiyatro Çalışması', '09.00-00.00', '', 'Tüm Gün', 'confirmed', '', '2025-10-30 13:47:54', '2025-10-30 13:47:59'),
(139, 11, '2025-11-27', 'Yoga Etkinliği', '11.00-12.30', '', 'Çeşme Kent Konseyi Nermin Ekinci', 'confirmed', '', '2025-11-27 11:55:42', '2025-11-27 11:55:42'),
(113, 1, '2025-12-20', 'Nazan Kesal Tiyatro Oyunu', '20.30', '', 'Yaralarım Aşktandır', 'confirmed', '', '2025-10-30 13:49:10', '2025-11-27 07:36:29'),
(114, 1, '2025-12-21', 'Susam Sokağı Çocuk Oyunu', '12.00-15.00', '', '', 'confirmed', '', '2025-10-30 13:49:19', '2025-11-27 11:42:30'),
(115, 1, '2025-12-15', 'Çeşme Belediyesi Kent Enstitüsü Tiyatro Çalışması', '09.00-00.00', '', 'Tüm Gün', 'confirmed', '', '2025-10-30 13:49:36', '2025-10-30 13:49:36'),
(116, 1, '2025-12-22', 'Çeşme Belediyesi Kent Enstitüsü Tiyatro Çalışması', '09.00-00.00', '', 'Tüm Gün', 'confirmed', '', '2025-10-30 13:49:48', '2025-10-30 13:49:48'),
(117, 1, '2025-12-29', 'Çeşme Belediyesi Kent Enstitüsü Tiyatro Çalışması', '09.00-00.00', '', 'Tüm Gün', 'confirmed', '', '2025-10-30 13:50:06', '2025-10-30 13:50:06'),
(118, 1, '2025-12-25', 'İnsan Kaynakları ve Eğitim Müdürlüğü', '12.00-', '', 'Öğleden Sonra', 'confirmed', '', '2025-10-30 13:50:53', '2025-10-30 13:50:53'),
(119, 7, '2025-11-10', 'Atatürk Anma Günü', '09.00-00.00', '', 'Atatürk\'ün Sevdiği Şarkılar-Tüm Gün', 'confirmed', '', '2025-10-31 09:06:31', '2025-10-31 09:06:31'),
(120, 11, '2025-11-08', 'Nişan', '20.00', 'Raziye Yıldırım 05072809680', '', 'confirmed', 'paid', '2025-10-31 10:47:34', '2025-10-31 10:47:34'),
(122, 1, '2025-11-10', 'Çeşme Kent Enstitüsü Tiyatro Dersi', '13.00-22.00', '', '', 'option', '', '2025-11-03 06:06:17', '2025-11-03 06:06:17'),
(123, 1, '2026-01-11', 'Esnaf ve Sanatkarlar Odası', '09.00-00.00', '', '', 'option', '', '2025-11-03 08:52:21', '2025-11-03 08:52:21'),
(124, 1, '2025-11-08', 'Sirk Gösterisi', '11.00-19.00', 'Efe Pamukova-05516970616', '13.00-15.00-17.00-19.00 olmak üzere 4 seans olacaktır.', 'confirmed', 'paid', '2025-11-03 11:41:22', '2025-11-07 07:26:51'),
(125, 1, '2025-11-25', 'Kadına Şiddetle Mücadele Günü', '10.00', '', '', 'option', '', '2025-11-04 11:34:28', '2025-11-04 11:34:28'),
(126, 1, '2025-11-26', '2025 Aile Yılına Özel Mutlu Birey mutlu aile mutlu toplum', '10.00-20.00', 'Haydar Şentürk 05558018343', 'Semazen ve Ney Dinletisi', 'confirmed', 'paid', '2025-11-04 11:39:03', '2025-11-14 12:46:50'),
(152, 1, '2025-12-19', 'Temel Afet Bilinci', '14.00', '', '', 'confirmed', '', '2025-12-17 06:51:38', '2025-12-17 06:51:38'),
(128, 1, '2025-11-20', '24 Kasım Öğretmenler Günü Ramazan Hoca\'nın Provası Atatürk AL', '09.00-00.00', '', '', 'confirmed', '', '2025-11-05 08:19:46', '2025-11-05 08:19:53'),
(129, 7, '2025-11-25', 'İnsanca-Mavi Düşler', 'sergiyle beraber', '', 'Yetişkin Oyunu', 'confirmed', '', '2025-11-05 14:21:33', '2025-11-05 14:21:39'),
(130, 1, '2025-12-23', 'Esnaf Toplantısı', '09.00-00.00', '', '', 'confirmed', '', '2025-11-06 12:20:03', '2025-11-06 12:20:03'),
(132, 11, '2025-11-29', 'Kına', '17.00', '05374289289 Sehim Sırça', '', 'confirmed', 'paid', '2025-11-11 08:04:30', '2025-11-11 14:17:22'),
(135, 1, '2025-11-24', 'Kokteyl', '15.00', '', '24 Kasım Öğretmenler Günü', 'confirmed', '', '2025-11-18 11:41:14', '2025-11-18 11:41:28'),
(138, 1, '2025-12-11', 'CHP Toplantısı', '12.00-21.00', '', '', 'confirmed', '', '2025-11-27 11:45:02', '2025-11-27 11:45:02'),
(136, 1, '2025-11-27', 'Çeşme Belediyespor Toplantısı', '11.00', '', '', 'confirmed', '', '2025-11-27 07:35:49', '2025-11-27 07:35:49'),
(137, 1, '2025-12-21', 'İki Şehrin Hikayesi', '20.30', '', '', 'confirmed', '', '2025-11-27 11:43:24', '2025-11-27 11:43:24'),
(150, 1, '2025-12-16', 'Afet Eğitimi', '15.00-16.00', '', '', 'confirmed', '', '2025-12-17 06:50:40', '2025-12-17 06:50:40'),
(149, 1, '2025-12-13', 'Yıldız Organizasyon Çocuk Sirk Gösterisi', '12.00-17.00', 'Selim Yıldız Tel no: 0532 620 9389', '', 'confirmed', '', '2025-12-09 13:37:29', '2025-12-17 06:50:04'),
(140, 11, '2025-12-04', 'Yoga Etkinliği', '10.00', '', 'Çeşme Kent Konseyi Nermin Ekinci', 'confirmed', '', '2025-11-27 11:56:23', '2025-11-27 11:56:23'),
(141, 11, '2025-12-11', 'Yoga Etkinliği', '10.00', '', 'Çeşme Kent Konseyi Nermin Ekinci', 'confirmed', '', '2025-11-27 11:56:40', '2025-11-27 11:56:40'),
(142, 11, '2025-12-18', 'Yoga Etkinliği', '10.00', '', 'Çeşme Kent Konseyi Nermin Ekinci', 'confirmed', '', '2025-11-27 11:56:55', '2025-11-27 11:56:55'),
(143, 11, '2025-12-25', 'Yoga Etkinliği', '10.00', '', 'Çeşme Kent Konseyi Nermin Ekinci', 'confirmed', '', '2025-11-27 11:57:05', '2025-11-27 11:57:05'),
(153, 1, '2025-12-28', 'Çeşme Kent Konseyi Olağan Faaliyet Bilgilendirme Genel Kurulu Toplantısı', '14.00', '', '', 'confirmed', '', '2025-12-17 06:52:07', '2025-12-17 06:52:07'),
(154, 1, '2026-01-31', 'İZBB Çocuk Oyunu', '12.00-15.00', '', '', 'free', NULL, '2025-12-17 10:53:05', '2025-12-17 10:58:56'),
(155, 1, '2026-02-08', 'Atatürkçü Düşünce Derneği Olağan Genel Kurul Toplantısı', 'söylenmedi', 'Deniz Ahilik 05323416330', '', 'confirmed', '', '2025-12-17 10:54:03', '2025-12-17 10:54:03'),
(156, 11, '2026-01-01', 'Çeşme Kent Konseyi Nermin Ekinci Yoga Etkinliği', '10.00', '', '', 'confirmed', '', '2025-12-17 10:54:50', '2025-12-17 10:55:58'),
(205, 11, '2026-01-06', 'Kına Gecesi', '20.00', 'Lütfü Koska 05078099723', '', 'confirmed', 'paid', '2025-12-17 11:10:23', '2025-12-17 11:10:23'),
(204, 11, '2026-01-27', 'Çeşme Kent Konseyi Nermin Ekinci Yoga Etkinliği', '10.00', '', '', 'confirmed', '', '2025-12-17 11:07:36', '2025-12-17 11:07:36'),
(159, 11, '2026-01-08', 'Çeşme Kent Konseyi Nermin Ekinci Yoga Etkinliği', '10.00', '', '', 'confirmed', '', '2025-12-17 10:56:33', '2025-12-17 10:56:33'),
(203, 11, '2026-01-20', 'Çeşme Kent Konseyi Nermin Ekinci Yoga Etkinliği', '10.00', '', '', 'confirmed', '', '2025-12-17 11:07:28', '2025-12-17 11:07:28'),
(161, 11, '2026-01-15', 'Çeşme Kent Konseyi Nermin Ekinci Yoga Etkinliği', '10.00', '', '', 'confirmed', '', '2025-12-17 10:58:09', '2025-12-17 10:58:09'),
(202, 11, '2026-01-13', 'Çeşme Kent Konseyi Nermin Ekinci Yoga Etkinliği', '10.00', '', '', 'confirmed', '', '2025-12-17 11:07:22', '2025-12-17 11:07:22'),
(163, 11, '2026-01-22', 'Çeşme Kent Konseyi Nermin Ekinci Yoga Etkinliği', '10.00', '', '', 'confirmed', '', '2025-12-17 10:58:27', '2025-12-17 10:58:27'),
(201, 11, '2026-01-06', 'Çeşme Kent Konseyi Nermin Ekinci Yoga Etkinliği', '10.00', '', '', 'confirmed', '', '2025-12-17 11:07:16', '2025-12-17 11:07:16'),
(165, 11, '2026-01-29', 'Çeşme Kent Konseyi Nermin Ekinci Yoga Etkinliği', '10.00', '', '', 'confirmed', '', '2025-12-17 10:58:45', '2025-12-17 10:58:45'),
(200, 11, '2026-02-24', 'Çeşme Kent Konseyi Nermin Ekinci Yoga Etkinliği', '10.00', '', '', 'confirmed', '', '2025-12-17 11:06:39', '2025-12-17 11:06:39'),
(167, 11, '2026-02-05', 'Çeşme Kent Konseyi Nermin Ekinci Yoga Etkinliği', '10.00', '', '', 'confirmed', '', '2025-12-17 10:59:38', '2025-12-17 10:59:38'),
(199, 11, '2026-02-17', 'Çeşme Kent Konseyi Nermin Ekinci Yoga Etkinliği', '10.00', '', '', 'confirmed', '', '2025-12-17 11:06:32', '2025-12-17 11:06:32'),
(169, 11, '2026-02-12', 'Çeşme Kent Konseyi Nermin Ekinci Yoga Etkinliği', '10.00', '', '', 'confirmed', '', '2025-12-17 10:59:52', '2025-12-17 10:59:52'),
(198, 11, '2026-02-10', 'Çeşme Kent Konseyi Nermin Ekinci Yoga Etkinliği', '10.00', '', '', 'confirmed', '', '2025-12-17 11:06:26', '2025-12-17 11:06:26'),
(171, 11, '2026-02-19', 'Çeşme Kent Konseyi Nermin Ekinci Yoga Etkinliği', '10.00', '', '', 'confirmed', '', '2025-12-17 11:00:10', '2025-12-17 11:00:10'),
(197, 11, '2026-02-03', 'Çeşme Kent Konseyi Nermin Ekinci Yoga Etkinliği', '10.00', '', '', 'confirmed', '', '2025-12-17 11:06:15', '2025-12-17 11:06:15'),
(173, 11, '2026-02-26', 'Çeşme Kent Konseyi Nermin Ekinci Yoga Etkinliği', '10.00', '', '', 'confirmed', '', '2025-12-17 11:00:25', '2025-12-17 11:00:25'),
(196, 11, '2026-03-31', 'Çeşme Kent Konseyi Nermin Ekinci Yoga Etkinliği', '10.00', '', '', 'confirmed', '', '2025-12-17 11:05:51', '2025-12-17 11:05:51'),
(175, 11, '2026-03-05', 'Çeşme Kent Konseyi Nermin Ekinci Yoga Etkinliği', '10.00', '', '', 'confirmed', '', '2025-12-17 11:00:48', '2025-12-17 11:00:48'),
(176, 11, '2026-03-12', 'Çeşme Kent Konseyi Nermin Ekinci Yoga Etkinliği', '10.00', '', '', 'confirmed', '', '2025-12-17 11:00:56', '2025-12-17 11:00:56'),
(195, 11, '2026-03-24', 'Çeşme Kent Konseyi Nermin Ekinci Yoga Etkinliği', '10.00', '', '', 'confirmed', '', '2025-12-17 11:05:44', '2025-12-17 11:05:44'),
(194, 11, '2026-03-17', 'Çeşme Kent Konseyi Nermin Ekinci Yoga Etkinliği', '10.00', '', '', 'confirmed', '', '2025-12-17 11:05:36', '2025-12-17 11:05:36'),
(179, 11, '2026-03-19', 'Çeşme Kent Konseyi Nermin Ekinci Yoga Etkinliği', '10.00', '', '', 'confirmed', '', '2025-12-17 11:01:17', '2025-12-17 11:01:17'),
(193, 11, '2026-03-10', 'Çeşme Kent Konseyi Nermin Ekinci Yoga Etkinliği', '10.00', '', '', 'confirmed', '', '2025-12-17 11:05:27', '2025-12-17 11:05:27'),
(181, 11, '2026-03-26', 'Çeşme Kent Konseyi Nermin Ekinci Yoga Etkinliği', '10.00', '', '', 'confirmed', '', '2025-12-17 11:01:31', '2025-12-17 11:01:31'),
(192, 11, '2026-03-03', 'Çeşme Kent Konseyi Nermin Ekinci Yoga Etkinliği', '10.00', '', '', 'confirmed', '', '2025-12-17 11:05:20', '2025-12-17 11:05:20'),
(183, 11, '2026-04-02', 'Çeşme Kent Konseyi Nermin Ekinci Yoga Etkinliği', '10.00', '', '', 'confirmed', '', '2025-12-17 11:02:14', '2025-12-17 11:02:14'),
(186, 11, '2026-04-07', 'Çeşme Kent Konseyi Nermin Ekinci Yoga Etkinliği', '10.00', '', '', 'confirmed', '', '2025-12-17 11:03:21', '2025-12-17 11:03:21'),
(185, 11, '2026-04-09', 'Çeşme Kent Konseyi Nermin Ekinci Yoga Etkinliği', '10.00', '', '', 'confirmed', '', '2025-12-17 11:02:48', '2025-12-17 11:02:48'),
(187, 11, '2026-04-14', 'Çeşme Kent Konseyi Nermin Ekinci Yoga Etkinliği', '10.00', '', '', 'confirmed', '', '2025-12-17 11:03:55', '2025-12-17 11:03:55'),
(188, 11, '2026-04-21', 'Çeşme Kent Konseyi Nermin Ekinci Yoga Etkinliği', '10.00', '', '', 'confirmed', '', '2025-12-17 11:04:04', '2025-12-17 11:04:04'),
(189, 11, '2026-04-28', 'Çeşme Kent Konseyi Nermin Ekinci Yoga Etkinliği', '10.00', '', '', 'confirmed', '', '2025-12-17 11:04:16', '2025-12-17 11:04:16'),
(190, 11, '2026-04-16', 'Çeşme Kent Konseyi Nermin Ekinci Yoga Etkinliği', '10.00', '', '', 'confirmed', '', '2025-12-17 11:04:27', '2025-12-17 11:04:27'),
(191, 11, '2026-04-30', 'Çeşme Kent Konseyi Nermin Ekinci Yoga Etkinliği', '10.00', '', '', 'confirmed', '', '2025-12-17 11:04:45', '2025-12-17 11:04:45');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `event_statuses`
--

CREATE TABLE `event_statuses` (
  `status_key` varchar(20) NOT NULL,
  `display_name` varchar(50) NOT NULL,
  `color` varchar(7) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Tablo döküm verisi `event_statuses`
--

INSERT INTO `event_statuses` (`status_key`, `display_name`, `color`) VALUES
('confirmed', 'Onaylandı', '#14d73b'),
('option', 'Opsiyonlu', '#f1af3b'),
('cancelled', 'İptal', '#e63946'),
('free', 'Ücretsiz', '#3a86ff');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `event_updates`
--

CREATE TABLE `event_updates` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `update_type` enum('new','updated','cancelled') NOT NULL,
  `message_text` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expire_at` timestamp NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Tablo döküm verisi `event_updates`
--

INSERT INTO `event_updates` (`id`, `event_id`, `update_type`, `message_text`, `created_at`, `expire_at`) VALUES
(1, 109, 'cancelled', 'Kent Enstitüsü (12 Ara 2025 · ÇAKABEY KÜLTÜR MERKEZİ Tiyatro Salonu)', '2025-12-09 13:36:17', '2025-12-10 05:36:17'),
(2, 110, 'cancelled', 'Kent Enstitüsü Artiz Mektebi Provası (13 Ara 2025 · ÇAKABEY KÜLTÜR MERKEZİ Tiyatro Salonu)', '2025-12-09 13:36:29', '2025-12-10 05:36:29'),
(3, 111, 'cancelled', 'Kent Enstitüsü Artiz Mektebi (14 Ara 2025 · ÇAKABEY KÜLTÜR MERKEZİ Tiyatro Salonu)', '2025-12-09 13:36:36', '2025-12-10 05:36:36'),
(4, 149, 'new', 'Yıldız Organizasyon Çocuk Sirk Gösterisi (13 Ara 2025 · ÇAKABEY KÜLTÜR MERKEZİ Tiyatro Salonu)', '2025-12-09 13:37:29', '2025-12-10 05:37:29'),
(5, 149, 'updated', 'Yıldız Organizasyon Çocuk Sirk Gösterisi (13 Ara 2025 · ÇAKABEY KÜLTÜR MERKEZİ Tiyatro Salonu)', '2025-12-09 13:38:24', '2025-12-10 05:38:24'),
(6, 149, 'updated', 'Yıldız Organizasyon Çocuk Sirk Gösterisi (13 Ara 2025 · ÇAKABEY KÜLTÜR MERKEZİ Tiyatro Salonu)', '2025-12-17 06:50:04', '2025-12-17 22:50:04'),
(7, 150, 'new', 'Afet Eğitimi (16 Ara 2025 · ÇAKABEY KÜLTÜR MERKEZİ Tiyatro Salonu)', '2025-12-17 06:50:40', '2025-12-17 22:50:40'),
(8, 151, 'new', 'Büyükşehir Belediyesi Toprak Analizi Eğitimi (17 Ara 2025 · ÇAKABEY KÜLTÜR MERKEZİ Tiyatro Salonu)', '2025-12-17 06:51:04', '2025-12-17 22:51:04'),
(9, 151, 'updated', 'Büyükşehir Belediyesi Toprak Analizi Eğitimi (17 Ara 2025 · ÇAKABEY KÜLTÜR MERKEZİ Tiyatro Salonu)', '2025-12-17 06:51:09', '2025-12-17 22:51:09'),
(10, 152, 'new', 'Temel Afet Bilinci (19 Ara 2025 · ÇAKABEY KÜLTÜR MERKEZİ Tiyatro Salonu)', '2025-12-17 06:51:38', '2025-12-17 22:51:38'),
(11, 153, 'new', 'Çeşme Kent Konseyi Olağan Faaliyet Bilgilendirme Genel Kurulu Toplantısı (28 Ara 2025 · ÇAKABEY KÜLTÜR MERKEZİ Tiyatro Salonu)', '2025-12-17 06:52:07', '2025-12-17 22:52:07'),
(12, 154, 'new', 'İZBB Çocuk Oyunu (31 Oca 2026 · ÇAKABEY KÜLTÜR MERKEZİ Tiyatro Salonu)', '2025-12-17 10:53:05', '2025-12-18 02:53:05'),
(13, 155, 'new', 'Atatürkçü Düşünce Derneği Olağan Genel Kurul Toplantısı (08 Şub 2026 · ÇAKABEY KÜLTÜR MERKEZİ Tiyatro Salonu)', '2025-12-17 10:54:03', '2025-12-18 02:54:03'),
(14, 156, 'new', 'Çeşme Kent Konseyi Nermin Ekinci Yoga Etkinliği (01 Oca 2026 · ILICA Düğün Salonu)', '2025-12-17 10:54:50', '2025-12-18 02:54:50'),
(15, 157, 'new', 'Çeşme Kent Konseyi Nermin Ekinci Yoga Etkinliği (05 Oca 2026 · ILICA Düğün Salonu)', '2025-12-17 10:55:06', '2025-12-18 02:55:06'),
(16, 158, 'new', 'Kına Gecesi (05 Oca 2026 · ILICA Düğün Salonu)', '2025-12-17 10:55:48', '2025-12-18 02:55:48'),
(17, 156, 'updated', 'Çeşme Kent Konseyi Nermin Ekinci Yoga Etkinliği (01 Oca 2026 · ILICA Düğün Salonu)', '2025-12-17 10:55:53', '2025-12-18 02:55:53'),
(18, 156, 'updated', 'Çeşme Kent Konseyi Nermin Ekinci Yoga Etkinliği (01 Oca 2026 · ILICA Düğün Salonu)', '2025-12-17 10:55:58', '2025-12-18 02:55:58'),
(19, 158, 'updated', 'Kına Gecesi (05 Oca 2026 · ILICA Düğün Salonu)', '2025-12-17 10:56:03', '2025-12-18 02:56:03'),
(20, 159, 'new', 'Çeşme Kent Konseyi Nermin Ekinci Yoga Etkinliği (08 Oca 2026 · ILICA Düğün Salonu)', '2025-12-17 10:56:33', '2025-12-18 02:56:33'),
(21, 160, 'new', 'Çeşme Kent Konseyi Nermin Ekinci Yoga Etkinliği (12 Oca 2026 · ILICA Düğün Salonu)', '2025-12-17 10:56:43', '2025-12-18 02:56:43'),
(22, 161, 'new', 'Çeşme Kent Konseyi Nermin Ekinci Yoga Etkinliği (15 Oca 2026 · ILICA Düğün Salonu)', '2025-12-17 10:58:09', '2025-12-18 02:58:09'),
(23, 162, 'new', 'Çeşme Kent Konseyi Nermin Ekinci Yoga Etkinliği (19 Oca 2026 · ILICA Düğün Salonu)', '2025-12-17 10:58:19', '2025-12-18 02:58:19'),
(24, 163, 'new', 'Çeşme Kent Konseyi Nermin Ekinci Yoga Etkinliği (22 Oca 2026 · ILICA Düğün Salonu)', '2025-12-17 10:58:27', '2025-12-18 02:58:27'),
(25, 164, 'new', 'Çeşme Kent Konseyi Nermin Ekinci Yoga Etkinliği (26 Oca 2026 · ILICA Düğün Salonu)', '2025-12-17 10:58:37', '2025-12-18 02:58:37'),
(26, 165, 'new', 'Çeşme Kent Konseyi Nermin Ekinci Yoga Etkinliği (29 Oca 2026 · ILICA Düğün Salonu)', '2025-12-17 10:58:45', '2025-12-18 02:58:45'),
(27, 154, 'updated', 'İZBB Çocuk Oyunu (31 Oca 2026 · ÇAKABEY KÜLTÜR MERKEZİ Tiyatro Salonu)', '2025-12-17 10:58:56', '2025-12-18 02:58:56'),
(28, 166, 'new', 'Çeşme Kent Konseyi Nermin Ekinci Yoga Etkinliği (02 Şub 2026 · ILICA Düğün Salonu)', '2025-12-17 10:59:30', '2025-12-18 02:59:30'),
(29, 167, 'new', 'Çeşme Kent Konseyi Nermin Ekinci Yoga Etkinliği (05 Şub 2026 · ILICA Düğün Salonu)', '2025-12-17 10:59:38', '2025-12-18 02:59:38'),
(30, 168, 'new', 'Çeşme Kent Konseyi Nermin Ekinci Yoga Etkinliği (09 Şub 2026 · ILICA Düğün Salonu)', '2025-12-17 10:59:45', '2025-12-18 02:59:45'),
(31, 169, 'new', 'Çeşme Kent Konseyi Nermin Ekinci Yoga Etkinliği (12 Şub 2026 · ILICA Düğün Salonu)', '2025-12-17 10:59:52', '2025-12-18 02:59:52'),
(32, 170, 'new', 'Çeşme Kent Konseyi Nermin Ekinci Yoga Etkinliği (16 Şub 2026 · ILICA Düğün Salonu)', '2025-12-17 11:00:00', '2025-12-18 03:00:00'),
(33, 171, 'new', 'Çeşme Kent Konseyi Nermin Ekinci Yoga Etkinliği (19 Şub 2026 · ILICA Düğün Salonu)', '2025-12-17 11:00:10', '2025-12-18 03:00:10'),
(34, 172, 'new', 'Çeşme Kent Konseyi Nermin Ekinci Yoga Etkinliği (23 Şub 2026 · ILICA Düğün Salonu)', '2025-12-17 11:00:17', '2025-12-18 03:00:17'),
(35, 173, 'new', 'Çeşme Kent Konseyi Nermin Ekinci Yoga Etkinliği (26 Şub 2026 · ILICA Düğün Salonu)', '2025-12-17 11:00:25', '2025-12-18 03:00:25'),
(36, 174, 'new', 'Çeşme Kent Konseyi Nermin Ekinci Yoga Etkinliği (02 Mar 2026 · ILICA Düğün Salonu)', '2025-12-17 11:00:38', '2025-12-18 03:00:38'),
(37, 175, 'new', 'Çeşme Kent Konseyi Nermin Ekinci Yoga Etkinliği (05 Mar 2026 · ILICA Düğün Salonu)', '2025-12-17 11:00:48', '2025-12-18 03:00:48'),
(38, 176, 'new', 'Çeşme Kent Konseyi Nermin Ekinci Yoga Etkinliği (12 Mar 2026 · ILICA Düğün Salonu)', '2025-12-17 11:00:56', '2025-12-18 03:00:56'),
(39, 177, 'new', 'Çeşme Kent Konseyi Nermin Ekinci Yoga Etkinliği (09 Mar 2026 · ILICA Düğün Salonu)', '2025-12-17 11:01:02', '2025-12-18 03:01:02'),
(40, 178, 'new', 'Çeşme Kent Konseyi Nermin Ekinci Yoga Etkinliği (16 Mar 2026 · ILICA Düğün Salonu)', '2025-12-17 11:01:10', '2025-12-18 03:01:09'),
(41, 179, 'new', 'Çeşme Kent Konseyi Nermin Ekinci Yoga Etkinliği (19 Mar 2026 · ILICA Düğün Salonu)', '2025-12-17 11:01:17', '2025-12-18 03:01:16'),
(42, 180, 'new', 'Çeşme Kent Konseyi Nermin Ekinci Yoga Etkinliği (23 Mar 2026 · ILICA Düğün Salonu)', '2025-12-17 11:01:24', '2025-12-18 03:01:23'),
(43, 181, 'new', 'Çeşme Kent Konseyi Nermin Ekinci Yoga Etkinliği (26 Mar 2026 · ILICA Düğün Salonu)', '2025-12-17 11:01:31', '2025-12-18 03:01:30'),
(44, 182, 'new', 'Çeşme Kent Konseyi Nermin Ekinci Yoga Etkinliği (30 Mar 2026 · ILICA Düğün Salonu)', '2025-12-17 11:01:50', '2025-12-18 03:01:50'),
(45, 183, 'new', 'Çeşme Kent Konseyi Nermin Ekinci Yoga Etkinliği (02 Nis 2026 · ILICA Düğün Salonu)', '2025-12-17 11:02:14', '2025-12-18 03:02:13'),
(46, 184, 'new', 'Çeşme Kent Konseyi Nermin Ekinci Yoga Etkinliği (06 Nis 2026 · ILICA Düğün Salonu)', '2025-12-17 11:02:37', '2025-12-18 03:02:36'),
(47, 185, 'new', 'Çeşme Kent Konseyi Nermin Ekinci Yoga Etkinliği (09 Nis 2026 · ILICA Düğün Salonu)', '2025-12-17 11:02:48', '2025-12-18 03:02:47'),
(48, 184, 'cancelled', 'Çeşme Kent Konseyi Nermin Ekinci Yoga Etkinliği (06 Nis 2026 · ILICA Düğün Salonu)', '2025-12-17 11:03:08', '2025-12-18 03:03:07'),
(49, 186, 'new', 'Çeşme Kent Konseyi Nermin Ekinci Yoga Etkinliği (07 Nis 2026 · ILICA Düğün Salonu)', '2025-12-17 11:03:21', '2025-12-18 03:03:20'),
(50, 187, 'new', 'Çeşme Kent Konseyi Nermin Ekinci Yoga Etkinliği (14 Nis 2026 · ILICA Düğün Salonu)', '2025-12-17 11:03:55', '2025-12-18 03:03:54'),
(51, 188, 'new', 'Çeşme Kent Konseyi Nermin Ekinci Yoga Etkinliği (21 Nis 2026 · ILICA Düğün Salonu)', '2025-12-17 11:04:04', '2025-12-18 03:04:04'),
(52, 189, 'new', 'Çeşme Kent Konseyi Nermin Ekinci Yoga Etkinliği (28 Nis 2026 · ILICA Düğün Salonu)', '2025-12-17 11:04:16', '2025-12-18 03:04:15'),
(53, 190, 'new', 'Çeşme Kent Konseyi Nermin Ekinci Yoga Etkinliği (16 Nis 2026 · ILICA Düğün Salonu)', '2025-12-17 11:04:27', '2025-12-18 03:04:26'),
(54, 191, 'new', 'Çeşme Kent Konseyi Nermin Ekinci Yoga Etkinliği (30 Nis 2026 · ILICA Düğün Salonu)', '2025-12-17 11:04:45', '2025-12-18 03:04:44'),
(55, 174, 'cancelled', 'Çeşme Kent Konseyi Nermin Ekinci Yoga Etkinliği (02 Mar 2026 · ILICA Düğün Salonu)', '2025-12-17 11:04:58', '2025-12-18 03:04:57'),
(56, 177, 'cancelled', 'Çeşme Kent Konseyi Nermin Ekinci Yoga Etkinliği (09 Mar 2026 · ILICA Düğün Salonu)', '2025-12-17 11:05:01', '2025-12-18 03:05:00'),
(57, 178, 'cancelled', 'Çeşme Kent Konseyi Nermin Ekinci Yoga Etkinliği (16 Mar 2026 · ILICA Düğün Salonu)', '2025-12-17 11:05:05', '2025-12-18 03:05:04'),
(58, 180, 'cancelled', 'Çeşme Kent Konseyi Nermin Ekinci Yoga Etkinliği (23 Mar 2026 · ILICA Düğün Salonu)', '2025-12-17 11:05:08', '2025-12-18 03:05:07'),
(59, 182, 'cancelled', 'Çeşme Kent Konseyi Nermin Ekinci Yoga Etkinliği (30 Mar 2026 · ILICA Düğün Salonu)', '2025-12-17 11:05:12', '2025-12-18 03:05:11'),
(60, 192, 'new', 'Çeşme Kent Konseyi Nermin Ekinci Yoga Etkinliği (03 Mar 2026 · ILICA Düğün Salonu)', '2025-12-17 11:05:20', '2025-12-18 03:05:20'),
(61, 193, 'new', 'Çeşme Kent Konseyi Nermin Ekinci Yoga Etkinliği (10 Mar 2026 · ILICA Düğün Salonu)', '2025-12-17 11:05:27', '2025-12-18 03:05:27'),
(62, 194, 'new', 'Çeşme Kent Konseyi Nermin Ekinci Yoga Etkinliği (17 Mar 2026 · ILICA Düğün Salonu)', '2025-12-17 11:05:36', '2025-12-18 03:05:36'),
(63, 195, 'new', 'Çeşme Kent Konseyi Nermin Ekinci Yoga Etkinliği (24 Mar 2026 · ILICA Düğün Salonu)', '2025-12-17 11:05:44', '2025-12-18 03:05:43'),
(64, 196, 'new', 'Çeşme Kent Konseyi Nermin Ekinci Yoga Etkinliği (31 Mar 2026 · ILICA Düğün Salonu)', '2025-12-17 11:05:51', '2025-12-18 03:05:51'),
(65, 166, 'cancelled', 'Çeşme Kent Konseyi Nermin Ekinci Yoga Etkinliği (02 Şub 2026 · ILICA Düğün Salonu)', '2025-12-17 11:06:00', '2025-12-18 03:05:59'),
(66, 168, 'cancelled', 'Çeşme Kent Konseyi Nermin Ekinci Yoga Etkinliği (09 Şub 2026 · ILICA Düğün Salonu)', '2025-12-17 11:06:02', '2025-12-18 03:06:02'),
(67, 170, 'cancelled', 'Çeşme Kent Konseyi Nermin Ekinci Yoga Etkinliği (16 Şub 2026 · ILICA Düğün Salonu)', '2025-12-17 11:06:05', '2025-12-18 03:06:04'),
(68, 172, 'cancelled', 'Çeşme Kent Konseyi Nermin Ekinci Yoga Etkinliği (23 Şub 2026 · ILICA Düğün Salonu)', '2025-12-17 11:06:08', '2025-12-18 03:06:08'),
(69, 197, 'new', 'Çeşme Kent Konseyi Nermin Ekinci Yoga Etkinliği (03 Şub 2026 · ILICA Düğün Salonu)', '2025-12-17 11:06:15', '2025-12-18 03:06:15'),
(70, 198, 'new', 'Çeşme Kent Konseyi Nermin Ekinci Yoga Etkinliği (10 Şub 2026 · ILICA Düğün Salonu)', '2025-12-17 11:06:26', '2025-12-18 03:06:25'),
(71, 199, 'new', 'Çeşme Kent Konseyi Nermin Ekinci Yoga Etkinliği (17 Şub 2026 · ILICA Düğün Salonu)', '2025-12-17 11:06:32', '2025-12-18 03:06:32'),
(72, 200, 'new', 'Çeşme Kent Konseyi Nermin Ekinci Yoga Etkinliği (24 Şub 2026 · ILICA Düğün Salonu)', '2025-12-17 11:06:39', '2025-12-18 03:06:38'),
(73, 157, 'cancelled', 'Çeşme Kent Konseyi Nermin Ekinci Yoga Etkinliği (05 Oca 2026 · ILICA Düğün Salonu)', '2025-12-17 11:06:49', '2025-12-18 03:06:48'),
(74, 158, 'cancelled', 'Kına Gecesi (05 Oca 2026 · ILICA Düğün Salonu)', '2025-12-17 11:06:52', '2025-12-18 03:06:51'),
(75, 160, 'cancelled', 'Çeşme Kent Konseyi Nermin Ekinci Yoga Etkinliği (12 Oca 2026 · ILICA Düğün Salonu)', '2025-12-17 11:06:57', '2025-12-18 03:06:56'),
(76, 162, 'cancelled', 'Çeşme Kent Konseyi Nermin Ekinci Yoga Etkinliği (19 Oca 2026 · ILICA Düğün Salonu)', '2025-12-17 11:07:00', '2025-12-18 03:06:59'),
(77, 164, 'cancelled', 'Çeşme Kent Konseyi Nermin Ekinci Yoga Etkinliği (26 Oca 2026 · ILICA Düğün Salonu)', '2025-12-17 11:07:03', '2025-12-18 03:07:02'),
(78, 201, 'new', 'Çeşme Kent Konseyi Nermin Ekinci Yoga Etkinliği (06 Oca 2026 · ILICA Düğün Salonu)', '2025-12-17 11:07:16', '2025-12-18 03:07:15'),
(79, 202, 'new', 'Çeşme Kent Konseyi Nermin Ekinci Yoga Etkinliği (13 Oca 2026 · ILICA Düğün Salonu)', '2025-12-17 11:07:22', '2025-12-18 03:07:21'),
(80, 203, 'new', 'Çeşme Kent Konseyi Nermin Ekinci Yoga Etkinliği (20 Oca 2026 · ILICA Düğün Salonu)', '2025-12-17 11:07:28', '2025-12-18 03:07:28'),
(81, 204, 'new', 'Çeşme Kent Konseyi Nermin Ekinci Yoga Etkinliği (27 Oca 2026 · ILICA Düğün Salonu)', '2025-12-17 11:07:36', '2025-12-18 03:07:36'),
(82, 205, 'new', 'Kına Gecesi (06 Oca 2026 · ILICA Düğün Salonu)', '2025-12-17 11:10:23', '2025-12-18 03:10:23');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `holidays`
--

CREATE TABLE `holidays` (
  `id` int(11) NOT NULL,
  `holiday_date` date NOT NULL,
  `holiday_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `is_recurring` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `payment_statuses`
--

CREATE TABLE `payment_statuses` (
  `status_key` varchar(20) NOT NULL,
  `display_name` varchar(50) NOT NULL,
  `color` varchar(7) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Tablo döküm verisi `payment_statuses`
--

INSERT INTO `payment_statuses` (`status_key`, `display_name`, `color`) VALUES
('paid', 'Ödendi', '#2a970c'),
('not_paid', 'Ödenmedi', '#766f6f'),
('to_be_paid', 'Ödeme Bekleniyor', '#7c3aed');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `units`
--

CREATE TABLE `units` (
  `id` int(11) NOT NULL,
  `unit_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `color` varchar(7) DEFAULT '#3498db',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Tablo döküm verisi `units`
--

INSERT INTO `units` (`id`, `unit_name`, `description`, `color`, `is_active`, `created_at`) VALUES
(1, 'ÇAKABEY KÜLTÜR MERKEZİ Tiyatro Salonu', 'Ana kültür merkezi binası', '#db9e33', 1, '2025-10-13 17:39:05'),
(3, 'ÇEŞME Amfi Açık Hava Tiyatrosu', 'Açık hava tiyatro ve konserler', '#2e80cc', 1, '2025-10-13 17:39:05'),
(11, 'ILICA Düğün Salonu', NULL, '#ec18e5', 1, '2025-10-16 07:45:24'),
(5, 'ALAÇATI Ek Hizmet Binası Sergi Salonu', 'Sanat sergileri ve fuarlar', '#f39c12', 1, '2025-10-13 17:39:05'),
(13, 'SAHA ETKİNLİKLERİ', NULL, '#db3355', 1, '2025-10-21 11:34:27'),
(7, 'AYİOS HARALAMBOS Kilisesi', NULL, '#9b59b6', 1, '2025-10-15 17:49:52'),
(12, 'ALAÇATI Amfi Açık Hava Tiyatrosu', NULL, '#3498db', 1, '2025-10-21 10:58:35'),
(14, 'FESTİVALLER 2026 (OT,ÇEŞME,GERMİYAN,OVACIK)', NULL, '#33db4f', 1, '2025-10-21 11:34:38'),
(15, 'DANS STÜDYOSU ÇAKABEY', NULL, '#f29c07', 1, '2025-12-18 08:20:35');

--
-- Dökümü yapılmış tablolar için indeksler
--

--
-- Tablo için indeksler `admin_users`
--
ALTER TABLE `admin_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Tablo için indeksler `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `admin_user_id` (`admin_user_id`);

--
-- Tablo için indeksler `app_settings`
--
ALTER TABLE `app_settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Tablo için indeksler `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `unit_id` (`unit_id`);

--
-- Tablo için indeksler `event_statuses`
--
ALTER TABLE `event_statuses`
  ADD PRIMARY KEY (`status_key`);

--
-- Tablo için indeksler `event_updates`
--
ALTER TABLE `event_updates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `event_id` (`event_id`);

--
-- Tablo için indeksler `holidays`
--
ALTER TABLE `holidays`
  ADD PRIMARY KEY (`id`);

--
-- Tablo için indeksler `payment_statuses`
--
ALTER TABLE `payment_statuses`
  ADD PRIMARY KEY (`status_key`);

--
-- Tablo için indeksler `units`
--
ALTER TABLE `units`
  ADD PRIMARY KEY (`id`);

--
-- Dökümü yapılmış tablolar için AUTO_INCREMENT değeri
--

--
-- Tablo için AUTO_INCREMENT değeri `admin_users`
--
ALTER TABLE `admin_users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- Tablo için AUTO_INCREMENT değeri `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- Tablo için AUTO_INCREMENT değeri `events`
--
ALTER TABLE `events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=206;

--
-- Tablo için AUTO_INCREMENT değeri `event_updates`
--
ALTER TABLE `event_updates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=83;

--
-- Tablo için AUTO_INCREMENT değeri `holidays`
--
ALTER TABLE `holidays`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- Tablo için AUTO_INCREMENT değeri `units`
--
ALTER TABLE `units`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
