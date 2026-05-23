/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache` (
  `key` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache_locks` (
  `key` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `hub_registry_hubs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hub_registry_hubs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `hq_id` bigint(20) unsigned NOT NULL,
  `relay_hub_id` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `deployment` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `domain` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `country_code` varchar(8) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reg_code` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `prov_code` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `citymun_code` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `brgy_code` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_seen_at` timestamp NULL DEFAULT NULL,
  `last_response_ms` int(10) unsigned DEFAULT NULL,
  `deployed_at` date DEFAULT NULL,
  `has_token` tinyint(1) NOT NULL DEFAULT '0',
  `token_is_active` tinyint(1) NOT NULL DEFAULT '0',
  `token_last_used_at` timestamp NULL DEFAULT NULL,
  `token_revoked_at` timestamp NULL DEFAULT NULL,
  `token_issued_at` timestamp NULL DEFAULT NULL,
  `raw_payload_json` json DEFAULT NULL,
  `synced_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `hub_registry_hubs_hq_id_unique` (`hq_id`),
  UNIQUE KEY `hub_registry_hubs_relay_hub_id_unique` (`relay_hub_id`),
  KEY `hub_registry_hubs_code_index` (`code`),
  KEY `hub_registry_hubs_domain_index` (`domain`),
  KEY `hub_registry_hubs_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `hub_registry_links`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hub_registry_links` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `hub_relay_hub_id` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `linked_relay_hub_id` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hub_hq_id` bigint(20) unsigned NOT NULL,
  `linked_hq_id` bigint(20) unsigned DEFAULT NULL,
  `relationship_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `uplink_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `priority` int(10) unsigned DEFAULT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT '0',
  `linked_domain` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `raw_payload_json` json DEFAULT NULL,
  `synced_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `hrl_unique_link` (`hub_hq_id`,`linked_hq_id`,`relationship_type`),
  KEY `hrl_hub_relationship_idx` (`hub_hq_id`,`relationship_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `hub_relay_attachments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hub_relay_attachments` (
  `id` char(26) COLLATE utf8mb4_unicode_ci NOT NULL,
  `hub_relay_message_id` char(26) COLLATE utf8mb4_unicode_ci NOT NULL,
  `attachment_type` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Type: file, image, binary',
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Original filename',
  `mime_type` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'MIME type',
  `size_bytes` bigint(20) NOT NULL COMMENT 'File size in bytes',
  `storage_disk` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'local' COMMENT 'Disk where file is stored',
  `storage_path` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Path in storage disk',
  `checksum` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Checksum for integrity verification',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `hub_relay_attachments_hub_relay_message_id_index` (`hub_relay_message_id`),
  KEY `hub_relay_attachments_attachment_type_index` (`attachment_type`),
  CONSTRAINT `hub_relay_attachments_hub_relay_message_id_foreign` FOREIGN KEY (`hub_relay_message_id`) REFERENCES `hub_relay_messages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `hub_relay_clients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hub_relay_clients` (
  `id` char(26) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Client application name',
  `system_code` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'System identifier (e.g., sitrep.app)',
  `api_key` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'API key for authentication',
  `description` text COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Whether client is enabled',
  `last_used_at` timestamp NULL DEFAULT NULL COMMENT 'When client last made a request',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `hub_relay_clients_system_code_unique` (`system_code`),
  UNIQUE KEY `hub_relay_clients_api_key_unique` (`api_key`),
  KEY `hub_relay_clients_system_code_index` (`system_code`),
  KEY `hub_relay_clients_is_active_index` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `hub_relay_deliveries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hub_relay_deliveries` (
  `id` char(26) COLLATE utf8mb4_unicode_ci NOT NULL,
  `hub_relay_message_id` char(26) COLLATE utf8mb4_unicode_ci NOT NULL,
  `target_hub_id` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Target hub for this delivery',
  `target_hq_hub_id` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `target_system` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'queued' COMMENT 'Status: queued, sending, delivered, failed, dead',
  `attempt_count` int(11) NOT NULL DEFAULT '0' COMMENT 'Number of delivery attempts',
  `last_attempt_at` timestamp NULL DEFAULT NULL COMMENT 'When last attempt was made',
  `delivered_at` timestamp NULL DEFAULT NULL COMMENT 'When successfully delivered',
  `last_error` text COLLATE utf8mb4_unicode_ci COMMENT 'Last error message',
  `next_retry_at` timestamp NULL DEFAULT NULL COMMENT 'When to retry next',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `hub_relay_deliveries_target_hub_id_status_index` (`target_hub_id`,`status`),
  KEY `hub_relay_deliveries_status_next_retry_at_index` (`status`,`next_retry_at`),
  KEY `hub_relay_deliveries_hub_relay_message_id_index` (`hub_relay_message_id`),
  KEY `hub_relay_deliveries_target_hq_hub_id_status_index` (`target_hq_hub_id`,`status`),
  KEY `hub_relay_deliveries_target_system_index` (`target_system`),
  CONSTRAINT `hub_relay_deliveries_hub_relay_message_id_foreign` FOREIGN KEY (`hub_relay_message_id`) REFERENCES `hub_relay_messages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `hub_relay_handler_dispatches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hub_relay_handler_dispatches` (
  `id` char(26) COLLATE utf8mb4_unicode_ci NOT NULL,
  `hub_relay_handler_id` char(26) COLLATE utf8mb4_unicode_ci NOT NULL,
  `hub_relay_message_id` char(26) COLLATE utf8mb4_unicode_ci NOT NULL,
  `hub_relay_receipt_id` char(26) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'queued',
  `attempt_count` int(10) unsigned NOT NULL DEFAULT '0',
  `last_response_status` smallint(5) unsigned DEFAULT NULL,
  `last_error` text COLLATE utf8mb4_unicode_ci,
  `queued_at` timestamp NULL DEFAULT NULL,
  `last_attempt_at` timestamp NULL DEFAULT NULL,
  `next_retry_at` timestamp NULL DEFAULT NULL,
  `succeeded_at` timestamp NULL DEFAULT NULL,
  `failed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `hrhd_receipt_fk` (`hub_relay_receipt_id`),
  KEY `hrhd_handler_status_idx` (`hub_relay_handler_id`,`status`),
  KEY `hrhd_status_retry_idx` (`status`,`next_retry_at`),
  KEY `hrhd_message_receipt_idx` (`hub_relay_message_id`,`hub_relay_receipt_id`),
  CONSTRAINT `hrhd_handler_fk` FOREIGN KEY (`hub_relay_handler_id`) REFERENCES `hub_relay_handlers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `hrhd_message_fk` FOREIGN KEY (`hub_relay_message_id`) REFERENCES `hub_relay_messages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `hrhd_receipt_fk` FOREIGN KEY (`hub_relay_receipt_id`) REFERENCES `hub_relay_receipts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `hub_relay_handlers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hub_relay_handlers` (
  `id` char(26) COLLATE utf8mb4_unicode_ci NOT NULL,
  `hub_relay_client_id` char(26) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `endpoint_url` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message_type_pattern` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '*',
  `source_system` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source_hub_id` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `auth_token` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `last_dispatched_at` timestamp NULL DEFAULT NULL,
  `last_succeeded_at` timestamp NULL DEFAULT NULL,
  `last_failed_at` timestamp NULL DEFAULT NULL,
  `last_error` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `hrh_client_active_idx` (`hub_relay_client_id`,`is_active`),
  KEY `hrh_msg_active_idx` (`message_type_pattern`,`is_active`),
  KEY `hrh_src_idx` (`source_system`,`source_hub_id`),
  CONSTRAINT `hrh_client_fk` FOREIGN KEY (`hub_relay_client_id`) REFERENCES `hub_relay_clients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `hub_relay_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hub_relay_messages` (
  `id` char(26) COLLATE utf8mb4_unicode_ci NOT NULL,
  `hub_relay_client_id` char(26) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `relay_id` char(26) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Globally unique relay ID for idempotency',
  `origin_hq_hub_id` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source_hub_id` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Hub that originated the message',
  `source_system` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Local application system sending message',
  `target_hub_ids` json NOT NULL COMMENT 'Array of target hub IDs',
  `target_system` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Destination local application system code on the receiving hub',
  `targets` json DEFAULT NULL,
  `target_systems` json DEFAULT NULL,
  `hop_trace` json DEFAULT NULL,
  `message_type` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Type of message (e.g., sitrep.record)',
  `payload_format` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'json' COMMENT 'Format of payload (json, file, image, binary)',
  `payload_version` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '1.0' COMMENT 'Version of payload format',
  `reference_type` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Type of referenced entity',
  `reference_id` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'ID of referenced entity',
  `content_hash` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'SHA256 hash for deduplication',
  `payload` json NOT NULL COMMENT 'Actual message payload',
  `tags` json DEFAULT NULL COMMENT 'Optional tags for categorization',
  `priority` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'normal' COMMENT 'Message priority (normal, high, urgent)',
  `attachments_count` int(11) NOT NULL DEFAULT '0' COMMENT 'Count of attached files',
  `correlation_id` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Optional correlation ID for grouping related messages',
  `occurred_at` timestamp NOT NULL COMMENT 'When the event actually occurred',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `hub_relay_messages_relay_id_unique` (`relay_id`),
  KEY `hub_relay_messages_source_hub_id_index` (`source_hub_id`),
  KEY `hub_relay_messages_source_system_index` (`source_system`),
  KEY `hub_relay_messages_message_type_index` (`message_type`),
  KEY `hub_relay_messages_created_at_index` (`created_at`),
  KEY `hub_relay_messages_priority_index` (`priority`),
  KEY `hub_relay_messages_hub_relay_client_id_index` (`hub_relay_client_id`),
  KEY `hub_relay_messages_target_system_index` (`target_system`),
  KEY `hub_relay_messages_origin_hq_hub_id_index` (`origin_hq_hub_id`),
  CONSTRAINT `hub_relay_messages_hub_relay_client_id_foreign` FOREIGN KEY (`hub_relay_client_id`) REFERENCES `hub_relay_clients` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `hub_relay_receipts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hub_relay_receipts` (
  `id` char(26) COLLATE utf8mb4_unicode_ci NOT NULL,
  `relay_id` char(26) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Same relay_id from inbound message - ensures idempotency',
  `source_hub_id` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Hub that sent the message',
  `message_type` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Type of message received',
  `status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'received' COMMENT 'Status: received, processed, duplicate, rejected',
  `content_hash` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Hash for additional validation',
  `received_at` timestamp NOT NULL COMMENT 'When we received this message',
  `processed_at` timestamp NULL DEFAULT NULL COMMENT 'When we processed/handed off to app',
  `processing_notes` text COLLATE utf8mb4_unicode_ci COMMENT 'Any notes about processing',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `hub_relay_receipts_relay_id_unique` (`relay_id`),
  KEY `hub_relay_receipts_source_hub_id_index` (`source_hub_id`),
  KEY `hub_relay_receipts_message_type_index` (`message_type`),
  KEY `hub_relay_receipts_status_index` (`status`),
  KEY `hub_relay_receipts_received_at_index` (`received_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `hub_relay_upload_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hub_relay_upload_sessions` (
  `id` char(26) COLLATE utf8mb4_unicode_ci NOT NULL,
  `hub_relay_message_id` char(26) COLLATE utf8mb4_unicode_ci NOT NULL,
  `hub_relay_attachment_id` char(26) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `direction` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'local_outbound or hub_inbound',
  `source_hub_id` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `target_hub_id` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `attachment_name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime_type` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `size_bytes` bigint(20) NOT NULL,
  `checksum` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `chunk_size_bytes` int(11) NOT NULL,
  `total_chunks` int(11) DEFAULT NULL,
  `transferred_bytes` bigint(20) NOT NULL DEFAULT '0',
  `transfer_progress_percent` decimal(5,2) NOT NULL DEFAULT '0.00',
  `current_chunk_index` int(11) NOT NULL DEFAULT '0',
  `transfer_status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'initializing',
  `storage_disk` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'local',
  `temp_path` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `assembled_path` text COLLATE utf8mb4_unicode_ci,
  `last_activity_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `last_error` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `hrus_msg_status_idx` (`hub_relay_message_id`,`transfer_status`),
  KEY `hrus_att_status_idx` (`hub_relay_attachment_id`,`transfer_status`),
  KEY `hrus_src_tgt_idx` (`source_hub_id`,`target_hub_id`),
  CONSTRAINT `hub_relay_upload_sessions_hub_relay_attachment_id_foreign` FOREIGN KEY (`hub_relay_attachment_id`) REFERENCES `hub_relay_attachments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `hub_relay_upload_sessions_hub_relay_message_id_foreign` FOREIGN KEY (`hub_relay_message_id`) REFERENCES `hub_relay_messages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `job_batches` (
  `id` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL,
  `reserved_at` int(10) unsigned DEFAULT NULL,
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_reserved_at_available_at_index` (`queue`,`reserved_at`,`available_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `relay_node_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `relay_node_settings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `local_relay_hub_id` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `local_hq_id` bigint(20) unsigned DEFAULT NULL,
  `hq_sync_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `hq_last_sync_at` timestamp NULL DEFAULT NULL,
  `hq_last_sync_status` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hq_last_sync_error` text COLLATE utf8mb4_unicode_ci,
  `outbound_topology_mode` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual',
  `inbound_trust_mode` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sessions` (
  `id` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'operator',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `last_login_at` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  KEY `users_role_index` (`role`),
  KEY `users_is_active_index` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1,'0001_01_01_000000_create_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (2,'0001_01_01_000001_create_cache_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (3,'0001_01_01_000002_create_jobs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (4,'2026_03_13_000001_create_hub_relay_messages_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (5,'2026_03_13_000002_create_hub_relay_deliveries_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (6,'2026_03_13_000003_create_hub_relay_receipts_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (7,'2026_03_13_000004_create_hub_relay_attachments_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (8,'2026_03_13_000005_create_hub_relay_clients_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (9,'2026_03_13_000006_create_hub_relay_upload_sessions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (10,'2026_03_13_000007_create_hub_relay_handlers_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (11,'2026_03_13_000008_create_hub_relay_handler_dispatches_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (12,'2026_03_13_000009_add_relay_access_fields_to_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (13,'2026_03_20_000010_create_hub_registry_hubs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (14,'2026_03_20_000011_create_hub_registry_links_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (15,'2026_03_20_000012_create_relay_node_settings_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (16,'2026_03_26_000001_add_hub_relay_client_id_to_hub_relay_messages_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (17,'2026_03_26_000002_add_target_system_to_hub_relay_messages_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (18,'2026_03_26_000003_add_targets_to_hub_relay_messages_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (19,'2026_03_26_000004_add_explicit_targets_to_hub_relay_deliveries_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (20,'2026_03_27_000001_add_origin_and_hop_trace_to_hub_relay_messages_table',1);
