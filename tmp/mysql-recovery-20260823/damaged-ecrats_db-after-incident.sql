-- MySQL dump 10.13  Distrib 8.4.3, for Win64 (x86_64)
--
-- Host: 127.0.0.1    Database: ecrats_db
-- ------------------------------------------------------
-- Server version	8.4.3

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `academic_terms`
--

DROP TABLE IF EXISTS `academic_terms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `academic_terms` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `semester` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `academic_year` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `starts_at` timestamp NOT NULL,
  `ends_at` timestamp NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `academic_terms_semester_academic_year_unique` (`semester`,`academic_year`),
  KEY `academic_terms_active_timeframe_index` (`is_active`,`starts_at`,`ends_at`),
  KEY `academic_terms_starts_at_index` (`starts_at`),
  KEY `academic_terms_ends_at_index` (`ends_at`),
  KEY `academic_terms_is_active_index` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `academic_terms`
--

LOCK TABLES `academic_terms` WRITE;
/*!40000 ALTER TABLE `academic_terms` DISABLE KEYS */;
/*!40000 ALTER TABLE `academic_terms` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `application_code_sequences`
--

DROP TABLE IF EXISTS `application_code_sequences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `application_code_sequences` (
  `period` varchar(7) COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_sequence` bigint unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`period`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `application_code_sequences`
--

LOCK TABLES `application_code_sequences` WRITE;
/*!40000 ALTER TABLE `application_code_sequences` DISABLE KEYS */;
/*!40000 ALTER TABLE `application_code_sequences` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `application_documents`
--

DROP TABLE IF EXISTS `application_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `application_documents` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `research_application_id` bigint unsigned NOT NULL,
  `document_requirement_id` bigint unsigned NOT NULL,
  `uploaded_by_user_id` bigint unsigned NOT NULL,
  `original_file_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `stored_file_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime_type` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_size_bytes` bigint unsigned DEFAULT NULL,
  `document_version` smallint unsigned NOT NULL DEFAULT '1',
  `validation_status` varchar(25) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `is_current` tinyint(1) NOT NULL DEFAULT '1',
  `uploaded_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `application_documents_document_requirement_id_foreign` (`document_requirement_id`),
  KEY `application_documents_uploaded_by_user_id_foreign` (`uploaded_by_user_id`),
  KEY `application_requirement_current_index` (`research_application_id`,`document_requirement_id`,`is_current`),
  KEY `application_documents_validation_status_index` (`validation_status`),
  KEY `application_documents_is_current_index` (`is_current`),
  CONSTRAINT `application_documents_document_requirement_id_foreign` FOREIGN KEY (`document_requirement_id`) REFERENCES `document_requirements` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `application_documents_research_application_id_foreign` FOREIGN KEY (`research_application_id`) REFERENCES `research_applications` (`id`) ON DELETE CASCADE,
  CONSTRAINT `application_documents_uploaded_by_user_id_foreign` FOREIGN KEY (`uploaded_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `application_documents`
--

LOCK TABLES `application_documents` WRITE;
/*!40000 ALTER TABLE `application_documents` DISABLE KEYS */;
/*!40000 ALTER TABLE `application_documents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `application_screenings`
--

DROP TABLE IF EXISTS `application_screenings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `application_screenings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `research_application_id` bigint unsigned NOT NULL,
  `screened_by_user_id` bigint unsigned NOT NULL,
  `completeness_status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `receipt_check_status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `required_documents_verified` tinyint(1) NOT NULL,
  `receipt_status_recorded` tinyint(1) NOT NULL,
  `basic_eligibility_confirmed` tinyint(1) NOT NULL,
  `screening_notes` text COLLATE utf8mb4_unicode_ci,
  `review_type` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `classification_reason` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `classified_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `application_screenings_research_application_id_foreign` (`research_application_id`),
  KEY `application_screenings_screened_by_user_id_foreign` (`screened_by_user_id`),
  CONSTRAINT `application_screenings_research_application_id_foreign` FOREIGN KEY (`research_application_id`) REFERENCES `research_applications` (`id`) ON DELETE CASCADE,
  CONSTRAINT `application_screenings_screened_by_user_id_foreign` FOREIGN KEY (`screened_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `application_screenings`
--

LOCK TABLES `application_screenings` WRITE;
/*!40000 ALTER TABLE `application_screenings` DISABLE KEYS */;
/*!40000 ALTER TABLE `application_screenings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `audit_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `academic_term_id` bigint unsigned DEFAULT NULL,
  `actor_user_id` bigint unsigned DEFAULT NULL,
  `action` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subject_id` bigint unsigned DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `audit_logs_subject_type_subject_id_index` (`subject_type`,`subject_id`),
  KEY `audit_logs_actor_user_id_created_at_index` (`actor_user_id`,`created_at`),
  KEY `audit_logs_action_index` (`action`),
  KEY `audit_logs_academic_term_id_foreign` (`academic_term_id`),
  CONSTRAINT `audit_logs_academic_term_id_foreign` FOREIGN KEY (`academic_term_id`) REFERENCES `academic_terms` (`id`) ON DELETE SET NULL,
  CONSTRAINT `audit_logs_actor_user_id_foreign` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_logs`
--

LOCK TABLES `audit_logs` WRITE;
/*!40000 ALTER TABLE `audit_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `audit_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache`
--

LOCK TABLES `cache` WRITE;
/*!40000 ALTER TABLE `cache` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache_locks`
--

LOCK TABLES `cache_locks` WRITE;
/*!40000 ALTER TABLE `cache_locks` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache_locks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `deadline_configurations`
--

DROP TABLE IF EXISTS `deadline_configurations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `deadline_configurations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `academic_term_id` bigint unsigned DEFAULT NULL,
  `deadline_key` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `audience_role` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `semester_label` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `starts_at` timestamp NULL DEFAULT NULL,
  `due_at` timestamp NOT NULL,
  `manual_status` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `priority` tinyint unsigned NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `deadline_configurations_deadline_key_unique` (`deadline_key`),
  KEY `deadline_configurations_audience_role_index` (`audience_role`),
  KEY `deadline_configurations_due_at_index` (`due_at`),
  KEY `deadline_configurations_is_active_index` (`is_active`),
  KEY `deadline_configurations_manual_status_index` (`manual_status`),
  KEY `deadline_configurations_academic_term_id_foreign` (`academic_term_id`),
  CONSTRAINT `deadline_configurations_academic_term_id_foreign` FOREIGN KEY (`academic_term_id`) REFERENCES `academic_terms` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `deadline_configurations`
--

LOCK TABLES `deadline_configurations` WRITE;
/*!40000 ALTER TABLE `deadline_configurations` DISABLE KEYS */;
/*!40000 ALTER TABLE `deadline_configurations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `document_requirements`
--

DROP TABLE IF EXISTS `document_requirements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `document_requirements` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `is_mandatory` tinyint(1) NOT NULL DEFAULT '1',
  `research_types` json DEFAULT NULL,
  `sort_order` smallint unsigned NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `document_requirements_code_unique` (`code`),
  KEY `document_requirements_is_active_index` (`is_active`),
  KEY `document_requirements_is_mandatory_index` (`is_mandatory`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `document_requirements`
--

LOCK TABLES `document_requirements` WRITE;
/*!40000 ALTER TABLE `document_requirements` DISABLE KEYS */;
/*!40000 ALTER TABLE `document_requirements` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `endorsements`
--

DROP TABLE IF EXISTS `endorsements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `endorsements` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `research_application_id` bigint unsigned NOT NULL,
  `adviser_user_id` bigint unsigned NOT NULL,
  `endorsement_status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `return_reason` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `endorsement_remarks` text COLLATE utf8mb4_unicode_ci,
  `returned_at` timestamp NULL DEFAULT NULL,
  `endorsed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `endorsements_application_history_index` (`research_application_id`,`created_at`),
  KEY `endorsements_adviser_status_index` (`adviser_user_id`,`endorsement_status`),
  KEY `endorsements_endorsement_status_index` (`endorsement_status`),
  CONSTRAINT `endorsements_adviser_user_id_foreign` FOREIGN KEY (`adviser_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `endorsements_research_application_id_foreign` FOREIGN KEY (`research_application_id`) REFERENCES `research_applications` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `endorsements`
--

LOCK TABLES `endorsements` WRITE;
/*!40000 ALTER TABLE `endorsements` DISABLE KEYS */;
/*!40000 ALTER TABLE `endorsements` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`),
  KEY `failed_jobs_connection_queue_failed_at_index` (`connection`,`queue`,`failed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `failed_jobs`
--

LOCK TABLES `failed_jobs` WRITE;
/*!40000 ALTER TABLE `failed_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `failed_jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `job_batches`
--

DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `job_batches`
--

LOCK TABLES `job_batches` WRITE;
/*!40000 ALTER TABLE `job_batches` DISABLE KEYS */;
/*!40000 ALTER TABLE `job_batches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `jobs`
--

DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` smallint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `jobs`
--

LOCK TABLES `jobs` WRITE;
/*!40000 ALTER TABLE `jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'0001_01_01_000000_create_users_table',1),(2,'0001_01_01_000001_create_cache_table',1),(3,'0001_01_01_000002_create_jobs_table',1),(4,'2026_07_17_201500_add_login_fields_to_users_table',1),(5,'2026_07_17_230000_expand_username_length_to_30',1),(6,'2026_07_17_231000_require_usernames_on_users_table',1),(7,'2026_07_18_000000_create_research_applications_table',1),(8,'2026_07_18_000100_create_document_tracking_tables',1),(9,'2026_07_18_000200_create_reviewer_assignments_table',1),(10,'2026_07_18_000300_create_timeline_and_deadline_tables',1),(11,'2026_07_18_000400_create_notifications_table',1),(12,'2026_07_18_000500_add_applicant_type_to_users_table',1),(13,'2026_07_20_000000_add_account_management_fields_to_users_table',1),(14,'2026_07_20_000100_create_audit_logs_table',1),(15,'2026_07_21_000000_add_secure_onboarding_fields_to_users_table',1),(16,'2026_07_23_000000_create_profile_options_table',1),(17,'2026_07_23_100000_add_reviewer_classification_profile_options',1),(18,'2026_07_27_000000_complete_initial_application_submission_schema',1),(19,'2026_07_27_100000_add_semester_and_manual_status_to_deadline_configurations',1),(20,'2026_07_28_000000_create_academic_terms_and_link_records',1),(21,'2026_07_28_000100_add_revision_cycle_and_application_code_sequences',1),(22,'2026_07_28_000200_create_endorsements_table',1),(23,'2026_07_29_000000_add_expected_duration_dates_to_research_applications',1),(24,'2026_07_29_000100_create_profile_option_aliases_table',1);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notifications` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `notifiable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `notifiable_id` bigint unsigned NOT NULL,
  `data` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notifications_notifiable_type_notifiable_id_index` (`notifiable_type`,`notifiable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_reset_tokens`
--

LOCK TABLES `password_reset_tokens` WRITE;
/*!40000 ALTER TABLE `password_reset_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_reset_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `profile_option_aliases`
--

DROP TABLE IF EXISTS `profile_option_aliases`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `profile_option_aliases` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `profile_option_id` bigint unsigned NOT NULL,
  `field` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `normalized_value` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `profile_option_aliases_field_normalized_value_unique` (`field`,`normalized_value`),
  KEY `profile_option_aliases_profile_option_id_normalized_value_index` (`profile_option_id`,`normalized_value`),
  CONSTRAINT `profile_option_aliases_profile_option_id_foreign` FOREIGN KEY (`profile_option_id`) REFERENCES `profile_options` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `profile_option_aliases`
--

LOCK TABLES `profile_option_aliases` WRITE;
/*!40000 ALTER TABLE `profile_option_aliases` DISABLE KEYS */;
/*!40000 ALTER TABLE `profile_option_aliases` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `profile_options`
--

DROP TABLE IF EXISTS `profile_options`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `profile_options` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `field` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `normalized_value` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sort_order` int unsigned NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_by_user_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `profile_options_field_normalized_value_unique` (`field`,`normalized_value`),
  KEY `profile_options_created_by_user_id_foreign` (`created_by_user_id`),
  KEY `profile_options_field_is_active_sort_order_index` (`field`,`is_active`,`sort_order`),
  CONSTRAINT `profile_options_created_by_user_id_foreign` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `profile_options`
--

LOCK TABLES `profile_options` WRITE;
/*!40000 ALTER TABLE `profile_options` DISABLE KEYS */;
INSERT INTO `profile_options` VALUES (1,'year_level','First Year','first year',10,1,NULL,'2026-08-23 09:39:31','2026-08-23 09:39:31'),(2,'year_level','Second Year','second year',20,1,NULL,'2026-08-23 09:39:31','2026-08-23 09:39:31'),(3,'year_level','Third Year','third year',30,1,NULL,'2026-08-23 09:39:31','2026-08-23 09:39:31'),(4,'year_level','Fourth Year','fourth year',40,1,NULL,'2026-08-23 09:39:31','2026-08-23 09:39:31'),(5,'institution','Institute of Behavioral Sciences','institute of behavioral sciences',10,1,NULL,'2026-08-23 09:39:31','2026-08-23 09:39:31'),(6,'institution','Institute of Computing and Digital Innovation','institute of computing and digital innovation',20,1,NULL,'2026-08-23 09:39:31','2026-08-23 09:39:31'),(7,'institution','Institute of Engineering','institute of engineering',30,1,NULL,'2026-08-23 09:39:31','2026-08-23 09:39:31'),(8,'institution','Institute of Foundational Studies','institute of foundational studies',40,1,NULL,'2026-08-23 09:39:31','2026-08-23 09:39:31'),(9,'institution','Institute of Governance and Development Studies','institute of governance and development studies',50,1,NULL,'2026-08-23 09:39:31','2026-08-23 09:39:31'),(10,'institution','Institute of Medical Laboratory Science','institute of medical laboratory science',60,1,NULL,'2026-08-23 09:39:31','2026-08-23 09:39:31'),(11,'institution','Institute of Midwifery','institute of midwifery',70,1,NULL,'2026-08-23 09:39:31','2026-08-23 09:39:31'),(12,'institution','Institute of Nursing','institute of nursing',80,1,NULL,'2026-08-23 09:39:31','2026-08-23 09:39:31'),(13,'institution','Institute of Science and Mathematics','institute of science and mathematics',90,1,NULL,'2026-08-23 09:39:31','2026-08-23 09:39:31'),(14,'reviewer_classification','Expedited','expedited',10,1,NULL,'2026-08-23 09:39:35','2026-08-23 09:39:35'),(15,'reviewer_classification','Full Board','full board',20,1,NULL,'2026-08-23 09:39:35','2026-08-23 09:39:35'),(16,'reviewer_classification','Exempted','exempted',30,1,NULL,'2026-08-23 09:39:35','2026-08-23 09:39:35'),(17,'department','Computer Studies','computer studies',10,1,NULL,'2026-08-23 09:39:39','2026-08-23 09:39:39'),(18,'program','Bachelor of Science in Computer Science','bachelor of science in computer science',10,1,NULL,'2026-08-23 09:39:39','2026-08-23 09:39:39');
/*!40000 ALTER TABLE `profile_options` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `research_applications`
--

DROP TABLE IF EXISTS `research_applications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `research_applications` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `academic_term_id` bigint unsigned DEFAULT NULL,
  `application_code` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `applicant_user_id` bigint unsigned NOT NULL,
  `draft_owner_user_id` bigint unsigned DEFAULT NULL,
  `adviser_user_id` bigint unsigned DEFAULT NULL,
  `applicant_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'student',
  `research_title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `research_type` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `research_category` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `institution` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `department` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `program` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `abstract` text COLLATE utf8mb4_unicode_ci,
  `target_participants` text COLLATE utf8mb4_unicode_ci,
  `expected_duration` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expected_start_date` date DEFAULT NULL,
  `expected_end_date` date DEFAULT NULL,
  `application_type` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'new_application',
  `application_status` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `current_stage` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'application_information',
  `review_type` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `current_revision_cycle` smallint unsigned NOT NULL DEFAULT '1',
  `submitted_at` timestamp NULL DEFAULT NULL,
  `status_updated_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `research_applications_application_code_unique` (`application_code`),
  UNIQUE KEY `research_applications_draft_owner_user_id_unique` (`draft_owner_user_id`),
  KEY `research_applications_adviser_user_id_application_status_index` (`adviser_user_id`,`application_status`),
  KEY `research_applications_applicant_user_id_application_status_index` (`applicant_user_id`,`application_status`),
  KEY `research_applications_application_status_index` (`application_status`),
  KEY `research_applications_review_type_index` (`review_type`),
  KEY `research_applications_submitted_at_index` (`submitted_at`),
  KEY `research_applications_research_type_index` (`research_type`),
  KEY `research_applications_current_stage_index` (`current_stage`),
  KEY `research_applications_academic_term_id_foreign` (`academic_term_id`),
  CONSTRAINT `research_applications_academic_term_id_foreign` FOREIGN KEY (`academic_term_id`) REFERENCES `academic_terms` (`id`) ON DELETE SET NULL,
  CONSTRAINT `research_applications_adviser_user_id_foreign` FOREIGN KEY (`adviser_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `research_applications_applicant_user_id_foreign` FOREIGN KEY (`applicant_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `research_applications_draft_owner_user_id_foreign` FOREIGN KEY (`draft_owner_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `research_applications`
--

LOCK TABLES `research_applications` WRITE;
/*!40000 ALTER TABLE `research_applications` DISABLE KEYS */;
/*!40000 ALTER TABLE `research_applications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reviewer_assignments`
--

DROP TABLE IF EXISTS `reviewer_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reviewer_assignments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `research_application_id` bigint unsigned NOT NULL,
  `reviewer_user_id` bigint unsigned NOT NULL,
  `review_type` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'initial_review',
  `assignment_status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `assigned_at` timestamp NULL DEFAULT NULL,
  `review_deadline_at` timestamp NULL DEFAULT NULL,
  `submitted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `reviewer_application_type_unique` (`research_application_id`,`reviewer_user_id`,`review_type`),
  KEY `reviewer_assignments_reviewer_user_id_assignment_status_index` (`reviewer_user_id`,`assignment_status`),
  KEY `reviewer_assignments_assignment_status_index` (`assignment_status`),
  KEY `reviewer_assignments_review_deadline_at_index` (`review_deadline_at`),
  CONSTRAINT `reviewer_assignments_research_application_id_foreign` FOREIGN KEY (`research_application_id`) REFERENCES `research_applications` (`id`) ON DELETE CASCADE,
  CONSTRAINT `reviewer_assignments_reviewer_user_id_foreign` FOREIGN KEY (`reviewer_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reviewer_assignments`
--

LOCK TABLES `reviewer_assignments` WRITE;
/*!40000 ALTER TABLE `reviewer_assignments` DISABLE KEYS */;
/*!40000 ALTER TABLE `reviewer_assignments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sessions`
--

LOCK TABLES `sessions` WRITE;
/*!40000 ALTER TABLE `sessions` DISABLE KEYS */;
INSERT INTO `sessions` VALUES ('s8PiKNtyGuqVAN4ihXQcXrsJEEsTziauu7BpHAsf',NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','eyJfdG9rZW4iOiJwaVdGR0tOTGwxOXFBVnB3MWp6V1BpUG5NalU4R1p5VFRHUnFBdGtBIiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cLzEyNy4wLjAuMTo4MDAwIiwicm91dGUiOiJob21lIn0sIl9mbGFzaCI6eyJvbGQiOltdLCJuZXciOltdfX0=',1787478571);
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `timeline_calendar_events`
--

DROP TABLE IF EXISTS `timeline_calendar_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `timeline_calendar_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `academic_term_id` bigint unsigned DEFAULT NULL,
  `milestone_key` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `term_label` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `starts_at` timestamp NOT NULL,
  `ends_at` timestamp NOT NULL,
  `sort_order` smallint unsigned NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `timeline_calendar_events_milestone_key_unique` (`milestone_key`),
  KEY `timeline_calendar_events_is_active_index` (`is_active`),
  KEY `timeline_calendar_events_academic_term_id_foreign` (`academic_term_id`),
  CONSTRAINT `timeline_calendar_events_academic_term_id_foreign` FOREIGN KEY (`academic_term_id`) REFERENCES `academic_terms` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `timeline_calendar_events`
--

LOCK TABLES `timeline_calendar_events` WRITE;
/*!40000 ALTER TABLE `timeline_calendar_events` DISABLE KEYS */;
/*!40000 ALTER TABLE `timeline_calendar_events` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `first_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `middle_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `suffix` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `username` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `institutional_identifier` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone_number` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `institution` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `department` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `program` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `year_level` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `position_title` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reviewer_classification` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reviewer_capacity` smallint unsigned DEFAULT NULL,
  `role` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'student_faculty_researcher',
  `applicant_type` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `account_status` varchar(25) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_by_user_id` bigint unsigned DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password_changed_at` timestamp NULL DEFAULT NULL,
  `password_setup_completed_at` timestamp NULL DEFAULT NULL,
  `onboarding_completed_at` timestamp NULL DEFAULT NULL,
  `setup_email_status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'not_sent',
  `setup_email_sent_at` timestamp NULL DEFAULT NULL,
  `setup_email_failed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  UNIQUE KEY `users_username_unique` (`username`),
  UNIQUE KEY `users_institutional_identifier_unique` (`institutional_identifier`),
  KEY `users_created_by_user_id_foreign` (`created_by_user_id`),
  KEY `users_role_account_status_index` (`role`,`account_status`),
  KEY `users_account_status_password_setup_completed_at_index` (`account_status`,`password_setup_completed_at`),
  CONSTRAINT `users_created_by_user_id_foreign` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping events for database 'ecrats_db'
--

--
-- Dumping routines for database 'ecrats_db'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-08-23 18:32:19
