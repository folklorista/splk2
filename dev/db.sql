-- Adminer 4.8.1 MySQL 8.0.40-0ubuntu0.20.04.1 dump

SET NAMES utf8;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;

SET NAMES utf8mb4;

DROP TABLE IF EXISTS `audit_actions`;
CREATE TABLE `audit_actions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(30) COLLATE utf8mb4_czech_ci NOT NULL,
  `description` varchar(200) COLLATE utf8mb4_czech_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;


DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE `audit_logs` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT 'Primární klíč',
  `user_id` int DEFAULT NULL COMMENT 'ID uživatele, který akci provedl',
  `action_id` int NOT NULL COMMENT 'ID akce z tabulky audit_actions',
  `table_name` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci DEFAULT NULL COMMENT 'Název tabulky, pokud se akce týká dat',
  `record_id` int DEFAULT NULL COMMENT 'ID záznamu, pokud se akce týká konkrétního záznamu',
  `details` text CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci COMMENT 'Podrobnosti o akci',
  `data` json DEFAULT NULL COMMENT 'Data',
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci DEFAULT NULL COMMENT 'IP adresa uživatele',
  `user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci COMMENT 'Informace o prohlížeči nebo zařízení',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Datum a čas provedení akce',
  PRIMARY KEY (`id`),
  KEY `action_id` (`action_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `audit_logs_action_fk` FOREIGN KEY (`action_id`) REFERENCES `audit_actions` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `audit_logs_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci COMMENT='Logování akcí uživatelů a změn v systému';


DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT 'Primární klíč',
  `parent_id` int DEFAULT NULL COMMENT 'Nadřazená kategorie',
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci NOT NULL COMMENT 'Název kategorie',
  `position` int NOT NULL DEFAULT '0' COMMENT 'Pořadí v rámci nadřazené kategorie',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Datum a čas vytvoření',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Datum a čas poslední aktualizace',
  PRIMARY KEY (`id`),
  KEY `parent_id` (`parent_id`),
  CONSTRAINT `categories_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci COMMENT='kategorie';


DROP TABLE IF EXISTS `category_labels`;
CREATE TABLE `category_labels` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT 'Primární klíč',
  `category_id` int NOT NULL COMMENT 'ID kategorie',
  `label` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci NOT NULL COMMENT 'Štítek',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Datum a čas vytvoření',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Datum a čas poslední aktualizace',
  PRIMARY KEY (`id`),
  KEY `category_id` (`category_id`),
  FULLTEXT KEY `_name` (`label`),
  CONSTRAINT `category_labels_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci COMMENT='štítky';


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


DROP TABLE IF EXISTS `files`;
CREATE TABLE `files` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci NOT NULL COMMENT 'jméno',
  `filename` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci NOT NULL COMMENT 'název souboru',
  `filepath` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci NOT NULL COMMENT 'cesta k souboru',
  `size` int NOT NULL COMMENT 'velikost souboru v bajtech',
  `uploaded_by` int NOT NULL COMMENT 'ID uživatele, který nahrál soubor',
  `uploaded_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'datum nahrání',
  PRIMARY KEY (`id`),
  KEY `files_user_fk` (`uploaded_by`),
  CONSTRAINT `files_user_fk` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci COMMENT='soubory a přílohy';


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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci COMMENT='skupiny';


DROP TABLE IF EXISTS `items`;
CREATE TABLE `items` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT 'Primární klíč',
  `category_id` int NOT NULL COMMENT 'ID kategorie',
  `inventory_number` varchar(255) COLLATE utf8mb4_czech_ci NOT NULL COMMENT 'Evidenční číslo',
  `status` text COLLATE utf8mb4_czech_ci COMMENT 'Stav součástky',
  `is_retired` tinyint(1) DEFAULT '0' COMMENT 'Indikátor, zda je součástka vyřazena',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Datum a čas vytvoření',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Datum a čas poslední aktualizace',
  PRIMARY KEY (`id`),
  UNIQUE KEY `category_id_inventory_number` (`category_id`,`inventory_number`),
  FULLTEXT KEY `_name` (`inventory_number`),
  CONSTRAINT `items_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci COMMENT='součástky';


DROP TABLE IF EXISTS `items_labels`;
CREATE TABLE `items_labels` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT 'Primární klíč',
  `item_id` int NOT NULL COMMENT 'ID součástky',
  `label_id` int NOT NULL COMMENT 'ID štítku',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Datum a čas vytvoření',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Datum a čas poslední aktualizace',
  PRIMARY KEY (`id`),
  KEY `item_id` (`item_id`),
  KEY `label_id` (`label_id`),
  CONSTRAINT `items_labels_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `items_labels_ibfk_2` FOREIGN KEY (`label_id`) REFERENCES `category_labels` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci COMMENT='štítky součástek';


DROP TABLE IF EXISTS `loans`;
CREATE TABLE `loans` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT 'Primární klíč',
  `start_date` date NOT NULL COMMENT 'Datum začátku zápůjčky (od)',
  `end_date` date DEFAULT NULL COMMENT 'Datum konce zápůjčky (do, nepovinné)',
  `person_id` int NOT NULL COMMENT 'ID osoby, které byla zápůjčka poskytnuta',
  `closed_date` date DEFAULT NULL COMMENT 'Datum ukončení zápůjčky',
  `comment` text COLLATE utf8mb4_czech_ci COMMENT 'Komentář k zápůjčce',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Datum vytvoření záznamu',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Datum poslední aktualizace',
  PRIMARY KEY (`id`),
  KEY `person_id` (`person_id`),
  KEY `_name` (`id`,`start_date`),
  CONSTRAINT `loans_ibfk_1` FOREIGN KEY (`person_id`) REFERENCES `persons` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci COMMENT='zápůjčky';


DROP TABLE IF EXISTS `loans_items`;
CREATE TABLE `loans_items` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT 'Primární klíč',
  `loan_id` int NOT NULL COMMENT 'ID zápůjčky',
  `item_id` int NOT NULL COMMENT 'ID předmětu',
  `return_date` date DEFAULT NULL COMMENT 'Datum vrácení předmětu',
  `comment` text COLLATE utf8mb4_czech_ci COMMENT 'Komentář k položce zápůjčky',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Datum vytvoření záznamu',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Datum poslední aktualizace',
  PRIMARY KEY (`id`),
  KEY `loan_id` (`loan_id`),
  KEY `item_id` (`item_id`),
  CONSTRAINT `loans_items_ibfk_1` FOREIGN KEY (`loan_id`) REFERENCES `loans` (`id`) ON DELETE CASCADE,
  CONSTRAINT `loans_items_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci COMMENT='položky zápůjčky';


DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL COMMENT 'ID uživatele',
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci NOT NULL COMMENT 'text zprávy',
  `read` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'označeno jako přečtené',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'datum vytvoření',
  PRIMARY KEY (`id`),
  KEY `notifications_user_fk` (`user_id`),
  CONSTRAINT `notifications_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci COMMENT='notifikace';


DROP TABLE IF EXISTS `persons`;
CREATE TABLE `persons` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT 'Primární klíč',
  `first_name` varchar(255) COLLATE utf8mb4_czech_ci NOT NULL COMMENT 'Křestní jméno',
  `last_name` varchar(255) COLLATE utf8mb4_czech_ci NOT NULL COMMENT 'Příjmení',
  `email` varchar(255) COLLATE utf8mb4_czech_ci NOT NULL COMMENT 'E-mailová adresa',
  `phone` varchar(20) COLLATE utf8mb4_czech_ci DEFAULT NULL COMMENT 'Telefonní číslo',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Datum a čas vytvoření',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Datum a čas poslední aktualizace',
  PRIMARY KEY (`id`),
  FULLTEXT KEY `_name` (`first_name`,`last_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci COMMENT='osoby';


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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci COMMENT='místa';


DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci NOT NULL COMMENT 'název role',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci COMMENT 'popis role',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'datum vytvoření',
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'datum poslední změny',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci COMMENT='uživatelské role';


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
  KEY `_name` (`first_name`,`last_name`),
  FULLTEXT KEY `first_name` (`first_name`),
  FULLTEXT KEY `last_name` (`last_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci COMMENT='uživatelé';


DROP TABLE IF EXISTS `users_events`;
CREATE TABLE `users_events` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `event_id` int NOT NULL,
  `attendance` enum('yes','no','other') CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `event_id` (`event_id`),
  CONSTRAINT `users_events_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `users_events_ibfk_2` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci COMMENT='události uživatele';


DROP TABLE IF EXISTS `users_groups`;
CREATE TABLE `users_groups` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `group_id` int NOT NULL,
  `is_admin` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'je administrátorem',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id_group_id_ibfk_1` (`user_id`,`group_id`),
  KEY `group_id` (`group_id`),
  CONSTRAINT `users_groups_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `users_groups_ibfk_2` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci COMMENT='skupiny uživatele';


DROP TABLE IF EXISTS `users_roles`;
CREATE TABLE `users_roles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL COMMENT 'ID uživatele',
  `role_id` int NOT NULL COMMENT 'ID role',
  `assigned_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'datum přiřazení role',
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_role_unique` (`user_id`,`role_id`),
  KEY `user_role_role_fk` (`role_id`),
  CONSTRAINT `user_role_role_fk` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_role_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci COMMENT='role uživatele';


-- 2025-01-12 09:09:27