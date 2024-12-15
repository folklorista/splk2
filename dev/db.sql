-- Adminer 4.8.1 MySQL 8.0.40-0ubuntu0.20.04.1 dump

SET NAMES utf8;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

SET NAMES utf8mb4;

DROP TABLE IF EXISTS `events`;
CREATE TABLE `events` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(64) COLLATE utf8mb4_czech_ci NOT NULL COMMENT 'název',
  `description` varchar(256) COLLATE utf8mb4_czech_ci DEFAULT NULL COMMENT 'popis',
  `group_id` int DEFAULT NULL COMMENT 'ID skupiny',
  `place_id` int DEFAULT NULL COMMENT 'ID umístění',
  `starts_at` timestamp NULL DEFAULT NULL COMMENT 'datum začátku',
  `ends_at` timestamp NULL DEFAULT NULL COMMENT 'datum konce',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'datum založení',
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'datum změny',
  PRIMARY KEY (`id`),
  KEY `starts_at` (`starts_at`),
  KEY `event_group_ibfk_1` (`group_id`),
  KEY `event_place_ibfk_2` (`place_id`),
  CONSTRAINT `event_group_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `event_place_ibfk_2` FOREIGN KEY (`place_id`) REFERENCES `places` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci COMMENT='událost';


DROP TABLE IF EXISTS `groups`;
CREATE TABLE `groups` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(64) COLLATE utf8mb4_czech_ci NOT NULL COMMENT 'název',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci COMMENT 'popis',
  `group_id` int DEFAULT NULL COMMENT 'ID rodiče',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'datum založení',
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'datum změny',
  PRIMARY KEY (`id`),
  UNIQUE KEY `group_id_name` (`group_id`,`name`),
  CONSTRAINT `group_group_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci COMMENT='skupina';


DROP TABLE IF EXISTS `places`;
CREATE TABLE `places` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(64) COLLATE utf8mb4_czech_ci NOT NULL COMMENT 'název',
  `description` varchar(256) COLLATE utf8mb4_czech_ci DEFAULT NULL COMMENT 'popis',
  `gps_lat` decimal(9,7) DEFAULT NULL COMMENT 'GPS souřadnice (lat)',
  `gps_lon` decimal(9,7) DEFAULT NULL COMMENT 'GPS souřadnice (lon)',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'datum založení',
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'datum změny',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci COMMENT='umístění';


DROP TABLE IF EXISTS `user_event`;
CREATE TABLE `user_event` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `event_id` int NOT NULL,
  `attendance` enum('yes','no','other') CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `event_id` (`event_id`),
  CONSTRAINT `user_event_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_event_ibfk_2` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;


DROP TABLE IF EXISTS `user_group`;
CREATE TABLE `user_group` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `group_id` int NOT NULL,
  `is_admin` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'je administrátorem',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id_group_id_ibfk_1` (`user_id`,`group_id`),
  KEY `group_id` (`group_id`),
  CONSTRAINT `user_group_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `user_group_ibfk_2` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;


DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `email` varchar(256) COLLATE utf8mb4_czech_ci NOT NULL COMMENT 'e-mail',
  `password` varchar(256) COLLATE utf8mb4_czech_ci NOT NULL COMMENT 'přihlašovací heslo',
  `first_name` varchar(64) COLLATE utf8mb4_czech_ci NOT NULL COMMENT 'křestní jméno',
  `last_name` varchar(64) COLLATE utf8mb4_czech_ci NOT NULL COMMENT 'příjmení',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `first_name_last_name` (`first_name`,`last_name`),
  FULLTEXT KEY `first_name` (`first_name`),
  FULLTEXT KEY `last_name` (`last_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci COMMENT='uživatel';


-- 2024-12-14 09:02:00