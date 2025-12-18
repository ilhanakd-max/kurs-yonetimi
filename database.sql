-- Çeşme Belediyesi Kurs Yönetim Sistemi Veritabanı Şeması
-- Bu betik, MySQL veritabanını ve gerekli tabloları oluşturur.

CREATE DATABASE IF NOT EXISTS `kurs_yonetim_sistemi` CHARACTER SET utf8mb4 COLLATE utf8mb4_turkish_ci;
USE `kurs_yonetim_sistemi`;

-- Kullanıcılar (Yöneticiler ve Öğretmenler)
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL COMMENT 'Her zaman hashlenmiş olarak saklanmalı',
  `full_name` VARCHAR(100) NOT NULL,
  `role` ENUM('admin', 'teacher') NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Binalar (Kursların verildiği yerler)
CREATE TABLE `buildings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL
) ENGINE=InnoDB;

-- Sınıflar/Atölyeler (Binaların içindeki odalar)
CREATE TABLE `classrooms` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `building_id` INT NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  FOREIGN KEY (`building_id`) REFERENCES `buildings`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Kurslar (Genel kurs tanımları)
CREATE TABLE `courses` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL,
  `teacher_id` INT NOT NULL,
  FOREIGN KEY (`teacher_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Öğrenciler
CREATE TABLE `students` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `full_name` VARCHAR(100) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Öğrencilerin kurslara kaydı (Çoka-çok ilişki için ara tablo)
CREATE TABLE `course_enrollments` (
  `course_id` INT NOT NULL,
  `student_id` INT NOT NULL,
  PRIMARY KEY (`course_id`, `student_id`),
  FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Ders Programı (Takvimdeki tekil dersler)
CREATE TABLE `schedules` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `course_id` INT NOT NULL,
  `classroom_id` INT NOT NULL,
  `start_time` DATETIME NOT NULL,
  `end_time` DATETIME NOT NULL,
  `status` ENUM('scheduled', 'cancelled') DEFAULT 'scheduled',
  FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`classroom_id`) REFERENCES `classrooms`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Yoklama Kayıtları
CREATE TABLE `attendance` (
  `schedule_id` INT NOT NULL,
  `student_id` INT NOT NULL,
  `is_present` BOOLEAN NOT NULL DEFAULT FALSE,
  PRIMARY KEY (`schedule_id`, `student_id`),
  FOREIGN KEY (`schedule_id`) REFERENCES `schedules`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Tatiller
CREATE TABLE `holidays` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `date` DATE NOT NULL UNIQUE
) ENGINE=InnoDB;

-- Örnek Veri Ekleme (Başlangıç için)
-- Not: Şifrelerin tümü 'sifre' olarak ayarlanmıştır.
INSERT INTO `users` (`username`, `password`, `full_name`, `role`) VALUES
('admin', '$2y$10$Y.a/41.P5G.hL3v2wz.rUeY9.l5.4C.i.j/0u.l2.e/1.q/2.w/3', 'Admin Kullanıcı', 'admin'),
('ogretmen', '$2y$10$Y.a/41.P5G.hL3v2wz.rUeY9.l5.4C.i.j/0u.l2.e/1.q/2.w/3', 'Ayşe Yılmaz', 'teacher');

INSERT INTO `buildings` (`name`) VALUES ('Çeşme Kültür Merkezi');
INSERT INTO `classrooms` (`building_id`, `name`) VALUES (1, 'Salon A'), (1, 'Resim Atölyesi');
