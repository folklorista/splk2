-- Create webhooks table for webhook system
-- Stores webhook URLs and which events they're subscribed to

DROP TABLE IF EXISTS `webhooks`;
CREATE TABLE `webhooks` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL COMMENT 'ID uživatele, který webhook vytvořil',
  `url` varchar(2048) CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci NOT NULL COMMENT 'cílová URL pro webhook',
  `events` json NOT NULL COMMENT 'JSON pole events na které se webhook subscribuje (user.created, item.updated, atd)',
  `active` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'je webhook aktivní',
  `retry_count` int NOT NULL DEFAULT '3' COMMENT 'počet pokusů o doručení',
  `timeout_seconds` int NOT NULL DEFAULT '30' COMMENT 'timeout pro webhook request',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `active` (`active`),
  CONSTRAINT `webhooks_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci COMMENT='webhook konfigurační tabulka';

-- Create webhook_logs table for audit trail and delivery status
DROP TABLE IF EXISTS `webhook_logs`;
CREATE TABLE `webhook_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `webhook_id` int NOT NULL COMMENT 'ID webhooku',
  `event` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci NOT NULL COMMENT 'typ eventu (user.created, item.updated)',
  `payload` json NOT NULL COMMENT 'odeslaná data',
  `status_code` int COMMENT 'HTTP status code odpovědi',
  `response_body` text CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci COMMENT 'tělo odpovědi nebo error message',
  `delivery_attempts` int NOT NULL DEFAULT '0' COMMENT 'počet pokusů o doručení',
  `delivered_at` timestamp NULL DEFAULT NULL COMMENT 'čas doručení',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `webhook_id` (`webhook_id`),
  KEY `delivered_at` (`delivered_at`),
  CONSTRAINT `webhook_logs_webhook_fk` FOREIGN KEY (`webhook_id`) REFERENCES `webhooks` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci COMMENT='webhook delivery audit trail';
