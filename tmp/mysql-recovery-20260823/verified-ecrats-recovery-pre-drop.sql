-- MySQL dump 10.13  Distrib 8.4.3, for Win64 (x86_64)
--
-- Host: 127.0.0.1    Database: ecrats_recovery_20260823
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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `academic_terms`
--

LOCK TABLES `academic_terms` WRITE;
/*!40000 ALTER TABLE `academic_terms` DISABLE KEYS */;
INSERT INTO `academic_terms` VALUES (1,'1st Semester','2026-2027','2026-08-16 16:00:00','2026-11-30 15:59:59',1,'2026-08-17 09:06:23','2026-08-17 09:06:23');
/*!40000 ALTER TABLE `academic_terms` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `applicant_survey_responses`
--

DROP TABLE IF EXISTS `applicant_survey_responses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `applicant_survey_responses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `research_application_id` bigint unsigned NOT NULL,
  `applicant_user_id` bigint unsigned NOT NULL,
  `ratings` json NOT NULL,
  `positive_feedback` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `improvement_feedback` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `additional_comments` text COLLATE utf8mb4_unicode_ci,
  `completed_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `questionnaire_version` tinyint unsigned NOT NULL DEFAULT '1',
  `suggestions_comments` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `applicant_survey_responses_research_application_id_unique` (`research_application_id`),
  KEY `applicant_survey_responses_applicant_user_id_completed_at_index` (`applicant_user_id`,`completed_at`),
  KEY `applicant_survey_responses_completed_at_index` (`completed_at`),
  KEY `applicant_survey_responses_questionnaire_version_index` (`questionnaire_version`),
  CONSTRAINT `applicant_survey_responses_applicant_user_id_foreign` FOREIGN KEY (`applicant_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `applicant_survey_responses_research_application_id_foreign` FOREIGN KEY (`research_application_id`) REFERENCES `research_applications` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `applicant_survey_responses`
--

LOCK TABLES `applicant_survey_responses` WRITE;
/*!40000 ALTER TABLE `applicant_survey_responses` DISABLE KEYS */;
INSERT INTO `applicant_survey_responses` VALUES (1,1,2,'{\"timeliness\": \"5\", \"communication\": \"5\", \"overall_process\": \"5\", \"comments_helpfulness\": \"5\"}','test1','atest1','test1','2026-08-17 09:20:08','2026-08-17 09:20:08','2026-08-17 09:20:08',1,NULL);
/*!40000 ALTER TABLE `applicant_survey_responses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `application_certificate_recipients`
--

DROP TABLE IF EXISTS `application_certificate_recipients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `application_certificate_recipients` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `research_application_id` bigint unsigned NOT NULL,
  `recipient_name` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `normalized_name` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sort_order` smallint unsigned NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `application_certificate_recipient_name_unique` (`research_application_id`,`normalized_name`),
  KEY `application_certificate_recipient_order_index` (`research_application_id`,`sort_order`),
  CONSTRAINT `acr_application_fk` FOREIGN KEY (`research_application_id`) REFERENCES `research_applications` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `application_certificate_recipients`
--

LOCK TABLES `application_certificate_recipients` WRITE;
/*!40000 ALTER TABLE `application_certificate_recipients` DISABLE KEYS */;
INSERT INTO `application_certificate_recipients` VALUES (1,1,'Applicant Test','applicant test',1,NULL,'2026-08-21 08:35:55');
/*!40000 ALTER TABLE `application_certificate_recipients` ENABLE KEYS */;
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
INSERT INTO `application_code_sequences` VALUES ('2026-08',1,'2026-08-17 09:11:08','2026-08-17 09:11:08');
/*!40000 ALTER TABLE `application_code_sequences` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `application_decision_releases`
--

DROP TABLE IF EXISTS `application_decision_releases`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `application_decision_releases` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `research_application_id` bigint unsigned NOT NULL,
  `review_cycle` smallint unsigned NOT NULL DEFAULT '0',
  `source_review_type` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `source_review_submission_id` bigint unsigned DEFAULT NULL,
  `source_review_submission_version_id` bigint unsigned DEFAULT NULL,
  `source_review_submission_version_ids` json DEFAULT NULL,
  `decision` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `review_consensus_signature` char(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `released_feedback_snapshot` json DEFAULT NULL,
  `released_by_user_id` bigint unsigned NOT NULL,
  `released_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `application_decision_release_cycle_unique` (`research_application_id`,`review_cycle`),
  KEY `application_decision_releases_released_by_user_id_foreign` (`released_by_user_id`),
  KEY `application_decision_releases_decision_index` (`decision`),
  KEY `application_decision_releases_released_at_index` (`released_at`),
  KEY `decision_release_source_review_fk` (`source_review_submission_id`),
  KEY `decision_release_source_version_fk` (`source_review_submission_version_id`),
  CONSTRAINT `application_decision_releases_released_by_user_id_foreign` FOREIGN KEY (`released_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `application_decision_releases_research_application_id_foreign` FOREIGN KEY (`research_application_id`) REFERENCES `research_applications` (`id`) ON DELETE CASCADE,
  CONSTRAINT `decision_release_source_review_fk` FOREIGN KEY (`source_review_submission_id`) REFERENCES `review_submissions` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `decision_release_source_version_fk` FOREIGN KEY (`source_review_submission_version_id`) REFERENCES `review_submission_versions` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `application_decision_releases`
--

LOCK TABLES `application_decision_releases` WRITE;
/*!40000 ALTER TABLE `application_decision_releases` DISABLE KEYS */;
INSERT INTO `application_decision_releases` VALUES (1,1,0,'initial_review',1,1,'[1]','approved',NULL,NULL,1,'2026-08-17 09:18:04','2026-08-17 09:18:04','2026-08-17 09:18:04');
/*!40000 ALTER TABLE `application_decision_releases` ENABLE KEYS */;
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
  `file_sha256` char(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `document_version` smallint unsigned NOT NULL DEFAULT '1',
  `validation_status` varchar(25) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `is_current` tinyint(1) NOT NULL DEFAULT '1',
  `uploaded_at` timestamp NULL DEFAULT NULL,
  `formally_submitted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `application_documents_document_requirement_id_foreign` (`document_requirement_id`),
  KEY `application_documents_uploaded_by_user_id_foreign` (`uploaded_by_user_id`),
  KEY `application_requirement_current_index` (`research_application_id`,`document_requirement_id`,`is_current`),
  KEY `application_documents_validation_status_index` (`validation_status`),
  KEY `application_documents_is_current_index` (`is_current`),
  KEY `application_documents_file_sha256_index` (`file_sha256`),
  KEY `application_documents_formally_submitted_at_index` (`formally_submitted_at`),
  CONSTRAINT `application_documents_document_requirement_id_foreign` FOREIGN KEY (`document_requirement_id`) REFERENCES `document_requirements` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `application_documents_research_application_id_foreign` FOREIGN KEY (`research_application_id`) REFERENCES `research_applications` (`id`) ON DELETE CASCADE,
  CONSTRAINT `application_documents_uploaded_by_user_id_foreign` FOREIGN KEY (`uploaded_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `application_documents`
--

LOCK TABLES `application_documents` WRITE;
/*!40000 ALTER TABLE `application_documents` DISABLE KEYS */;
INSERT INTO `application_documents` VALUES (1,1,1,2,'ECRATS_Laravel_VSCode_Setup_Guide.pdf','applications/1/requirements/1/8914b15f-a85b-4e0a-ac3d-d766b9166786.pdf','application/pdf',143182,'8ef6bc0d4a373397efc4ccf7fbcf8abbbf3768f5be70758673f76477f69ce897',1,'completed',1,'2026-08-17 09:11:43','2026-08-17 09:11:43','2026-08-17 09:11:43','2026-08-17 09:11:43'),(2,1,2,2,'ECRATS_Laravel_VSCode_Setup_Guide.pdf','applications/1/requirements/2/2ec827fe-4f6a-4e70-8cb1-42590a26f972.pdf','application/pdf',143182,'8ef6bc0d4a373397efc4ccf7fbcf8abbbf3768f5be70758673f76477f69ce897',1,'completed',1,'2026-08-17 09:11:43','2026-08-17 09:11:43','2026-08-17 09:11:43','2026-08-17 09:11:43'),(3,1,3,2,'ECRATS_Laravel_VSCode_Setup_Guide.pdf','applications/1/requirements/3/8cf802b4-e370-4f46-984f-ea4ee8ef528f.pdf','application/pdf',143182,'8ef6bc0d4a373397efc4ccf7fbcf8abbbf3768f5be70758673f76477f69ce897',1,'completed',1,'2026-08-17 09:11:43','2026-08-17 09:11:43','2026-08-17 09:11:43','2026-08-17 09:11:43'),(4,1,4,2,'ECRATS_Laravel_VSCode_Setup_Guide.pdf','applications/1/requirements/4/37381c9a-6b0d-4e44-8198-6d5fa5168ebe.pdf','application/pdf',143182,'8ef6bc0d4a373397efc4ccf7fbcf8abbbf3768f5be70758673f76477f69ce897',1,'completed',1,'2026-08-17 09:11:43','2026-08-17 09:11:43','2026-08-17 09:11:43','2026-08-17 09:11:43');
/*!40000 ALTER TABLE `application_documents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `application_revision_requirements`
--

DROP TABLE IF EXISTS `application_revision_requirements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `application_revision_requirements` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `application_revision_id` bigint unsigned NOT NULL,
  `document_requirement_id` bigint unsigned NOT NULL,
  `source_application_document_id` bigint unsigned DEFAULT NULL,
  `replacement_application_document_id` bigint unsigned DEFAULT NULL,
  `is_required` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `application_revision_requirement_unique` (`application_revision_id`,`document_requirement_id`),
  KEY `arr_requirement_fk` (`document_requirement_id`),
  KEY `arr_source_document_fk` (`source_application_document_id`),
  KEY `arr_replacement_document_fk` (`replacement_application_document_id`),
  KEY `application_revision_requirements_is_required_index` (`is_required`),
  CONSTRAINT `arr_replacement_document_fk` FOREIGN KEY (`replacement_application_document_id`) REFERENCES `application_documents` (`id`) ON DELETE SET NULL,
  CONSTRAINT `arr_requirement_fk` FOREIGN KEY (`document_requirement_id`) REFERENCES `document_requirements` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `arr_revision_fk` FOREIGN KEY (`application_revision_id`) REFERENCES `application_revisions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `arr_source_document_fk` FOREIGN KEY (`source_application_document_id`) REFERENCES `application_documents` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `application_revision_requirements`
--

LOCK TABLES `application_revision_requirements` WRITE;
/*!40000 ALTER TABLE `application_revision_requirements` DISABLE KEYS */;
/*!40000 ALTER TABLE `application_revision_requirements` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `application_revisions`
--

DROP TABLE IF EXISTS `application_revisions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `application_revisions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `research_application_id` bigint unsigned NOT NULL,
  `application_decision_release_id` bigint unsigned NOT NULL,
  `revision_number` smallint unsigned NOT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `due_at` timestamp NOT NULL,
  `submitted_by_user_id` bigint unsigned DEFAULT NULL,
  `submitted_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `application_revision_number_unique` (`research_application_id`,`revision_number`),
  UNIQUE KEY `application_revisions_application_decision_release_id_unique` (`application_decision_release_id`),
  KEY `application_revisions_submitted_by_user_id_foreign` (`submitted_by_user_id`),
  KEY `application_revisions_status_index` (`status`),
  KEY `application_revisions_due_at_index` (`due_at`),
  KEY `application_revisions_submitted_at_index` (`submitted_at`),
  CONSTRAINT `application_revisions_application_decision_release_id_foreign` FOREIGN KEY (`application_decision_release_id`) REFERENCES `application_decision_releases` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `application_revisions_research_application_id_foreign` FOREIGN KEY (`research_application_id`) REFERENCES `research_applications` (`id`) ON DELETE CASCADE,
  CONSTRAINT `application_revisions_submitted_by_user_id_foreign` FOREIGN KEY (`submitted_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `application_revisions`
--

LOCK TABLES `application_revisions` WRITE;
/*!40000 ALTER TABLE `application_revisions` DISABLE KEYS */;
/*!40000 ALTER TABLE `application_revisions` ENABLE KEYS */;
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
  `review_type` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `classification_reason` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `classified_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `application_screenings_research_application_id_unique` (`research_application_id`),
  KEY `application_screenings_screened_by_user_id_classified_at_index` (`screened_by_user_id`,`classified_at`),
  KEY `application_screenings_review_type_index` (`review_type`),
  KEY `application_screenings_classified_at_index` (`classified_at`),
  CONSTRAINT `application_screenings_research_application_id_foreign` FOREIGN KEY (`research_application_id`) REFERENCES `research_applications` (`id`) ON DELETE CASCADE,
  CONSTRAINT `application_screenings_screened_by_user_id_foreign` FOREIGN KEY (`screened_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `application_screenings`
--

LOCK TABLES `application_screenings` WRITE;
/*!40000 ALTER TABLE `application_screenings` DISABLE KEYS */;
INSERT INTO `application_screenings` VALUES (1,1,1,'expedited','test1test1test1','2026-08-17 09:14:16','2026-08-17 09:14:16','2026-08-17 09:14:16');
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
) ENGINE=InnoDB AUTO_INCREMENT=40 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_logs`
--

LOCK TABLES `audit_logs` WRITE;
/*!40000 ALTER TABLE `audit_logs` DISABLE KEYS */;
INSERT INTO `audit_logs` VALUES (1,NULL,NULL,'auth.login_failed',NULL,NULL,'{\"result\": \"failed\", \"attempts\": 2, \"username_hash\": \"e0d0d88eb5c5b5c0ccbc9972983f4abfcf66cb63181687d090a26dde08f50540\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-08-17 09:01:24'),(2,NULL,NULL,'auth.login_failed',NULL,NULL,'{\"result\": \"failed\", \"attempts\": 3, \"username_hash\": \"e0d0d88eb5c5b5c0ccbc9972983f4abfcf66cb63181687d090a26dde08f50540\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-08-17 09:01:30'),(3,NULL,1,'auth.login_succeeded','App\\Models\\User',1,'{\"result\": \"succeeded\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-08-17 09:03:47'),(4,1,1,'settings.deadlines_updated','App\\Models\\User',1,'{\"result\": \"updated\", \"semester\": \"1st Semester\", \"processes\": [\"application-submission\", \"adviser-endorsement\", \"res-screening\", \"reviewer-submission\", \"revision-period\", \"reviewing-revision-period\"], \"term_ends_on\": \"2026-11-30T23:59:59+08:00\", \"academic_year\": \"2026-2027\", \"manual_states\": {\"res-screening\": \"automatic\", \"revision-period\": \"automatic\", \"adviser-endorsement\": \"automatic\", \"reviewer-submission\": \"automatic\", \"application-submission\": \"automatic\", \"reviewing-revision-period\": \"automatic\"}, \"term_starts_on\": \"2026-08-17T00:00:00+08:00\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-08-17 09:06:23'),(5,1,2,'auth.login_succeeded','App\\Models\\User',2,'{\"result\": \"succeeded\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-08-17 09:06:54'),(6,1,1,'auth.login_succeeded','App\\Models\\User',1,'{\"result\": \"succeeded\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-08-17 09:07:45'),(7,1,1,'user.profile_updated','App\\Models\\User',3,'{\"changed_fields\": [\"department\", \"position_title\"]}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-08-17 09:08:11'),(8,1,1,'user.profile_updated','App\\Models\\User',2,'{\"changed_fields\": [\"program\", \"year_level\"]}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-08-17 09:08:49'),(9,1,1,'user.profile_updated','App\\Models\\User',2,'{\"changed_fields\": [\"department\"]}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-08-17 09:09:27'),(10,1,1,'user.profile_updated','App\\Models\\User',2,'{\"changed_fields\": [\"institution\"]}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-08-17 09:10:28'),(11,1,1,'user.profile_updated','App\\Models\\User',3,'{\"changed_fields\": [\"institution\"]}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-08-17 09:10:40'),(12,1,1,'user.profile_updated','App\\Models\\User',4,'{\"changed_fields\": [\"institution\"]}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-08-17 09:11:00'),(13,1,2,'application.draft_created','App\\Models\\ResearchApplication',1,'{\"result\": \"created\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-08-17 09:11:08'),(14,1,2,'application.information_updated','App\\Models\\ResearchApplication',1,'{\"result\": \"updated\", \"updated_fields\": [\"research_title\", \"research_type\", \"research_category\", \"institution\", \"department\", \"program\", \"adviser_user_id\", \"abstract\", \"target_participants\", \"expected_start_date\", \"expected_end_date\"]}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-08-17 09:11:08'),(15,1,2,'application.requirement_uploaded','App\\Models\\ApplicationDocument',1,'{\"result\": \"uploaded\", \"mime_type\": \"application/pdf\", \"application_id\": 1, \"file_size_bytes\": 143182, \"document_version\": 1, \"requirement_code\": \"RESEARCH-PROPOSAL\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-08-17 09:11:43'),(16,1,2,'application.requirement_uploaded','App\\Models\\ApplicationDocument',2,'{\"result\": \"uploaded\", \"mime_type\": \"application/pdf\", \"application_id\": 1, \"file_size_bytes\": 143182, \"document_version\": 1, \"requirement_code\": \"KLD-RES-04-001B\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-08-17 09:11:43'),(17,1,2,'application.requirement_uploaded','App\\Models\\ApplicationDocument',3,'{\"result\": \"uploaded\", \"mime_type\": \"application/pdf\", \"application_id\": 1, \"file_size_bytes\": 143182, \"document_version\": 1, \"requirement_code\": \"KLD-RES-04-003\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-08-17 09:11:43'),(18,1,2,'application.requirement_uploaded','App\\Models\\ApplicationDocument',4,'{\"result\": \"uploaded\", \"mime_type\": \"application/pdf\", \"application_id\": 1, \"file_size_bytes\": 143182, \"document_version\": 1, \"requirement_code\": \"PAYMENT-PROOF\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-08-17 09:11:43'),(19,1,2,'application.submitted','App\\Models\\ResearchApplication',1,'{\"result\": \"submitted_to_adviser\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-08-17 09:12:11'),(20,1,2,'application.adviser_notified','App\\Models\\ResearchApplication',1,'{\"result\": \"notified\", \"adviser_user_id\": 3}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-08-17 09:12:11'),(21,1,3,'auth.login_succeeded','App\\Models\\User',3,'{\"result\": \"succeeded\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-08-17 09:12:41'),(22,1,3,'application.adviser_endorsed','App\\Models\\ResearchApplication',1,'{\"result\": \"adviser_endorsed\", \"decision\": \"endorsed\", \"return_reason\": null}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-08-17 09:13:25'),(23,1,1,'application.res_classified','App\\Models\\ResearchApplication',1,'{\"result\": \"awaiting_reviewer_assignment\", \"review_type\": \"expedited\", \"reviewer_count\": 1}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-08-17 09:14:17'),(24,1,1,'application.reviewers_assigned','App\\Models\\ResearchApplication',1,'{\"result\": \"under_expedited_review\", \"review_type\": \"expedited\", \"reviewer_count\": 1, \"superseded_count\": 0}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-08-17 09:14:28'),(25,1,1,'application.reviewers_assigned','App\\Models\\ResearchApplication',1,'{\"result\": \"under_expedited_review\", \"review_type\": \"expedited\", \"reviewer_count\": 1, \"superseded_count\": 0}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-08-17 09:14:38'),(26,1,4,'auth.login_succeeded','App\\Models\\User',4,'{\"result\": \"succeeded\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-08-17 09:14:57'),(27,1,4,'review.comment_added','App\\Models\\ResearchApplication',1,'{\"scope\": \"overall\", \"comment_id\": 1, \"assignment_id\": 1}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-08-17 09:15:36'),(28,1,4,'review.comment_added','App\\Models\\ResearchApplication',1,'{\"scope\": \"document\", \"comment_id\": 2, \"assignment_id\": 1}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-08-17 09:15:45'),(29,1,4,'review.form_completed','App\\Models\\ResearchApplication',1,'{\"form_type\": \"protocol\", \"form_status\": \"completed\", \"assignment_id\": 1}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-08-17 09:16:21'),(30,1,4,'review.form_completed','App\\Models\\ResearchApplication',1,'{\"form_type\": \"informed_consent\", \"form_status\": \"completed\", \"assignment_id\": 1}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-08-17 09:16:48'),(31,1,4,'review.decision_submitted','App\\Models\\ResearchApplication',1,'{\"result\": \"review_submitted_pending_release\", \"decision\": \"approved\", \"artifact_ids\": [1, 2], \"assignment_id\": 1, \"artifact_versions\": [1, 1], \"all_reviewers_submitted\": true}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-08-17 09:17:32'),(32,1,1,'application.review_decision_released','App\\Models\\ResearchApplication',1,'{\"result\": \"result_released_accepted\", \"decision\": \"approved\", \"review_cycle\": 0, \"decision_release_id\": 1, \"released_comment_count\": 2, \"required_document_count\": 1, \"source_review_submission_id\": 1, \"source_reviewer_assignment_id\": 1}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-08-17 09:18:04'),(33,1,1,'certificate.released','App\\Models\\ResearchApplication',1,'{\"result\": \"released\", \"file_sha256\": \"c6e6e9a60f61e6e0d062dce703ae79cf254c2bd48be80ea5542193ec8081784e\", \"background_id\": 1, \"certificate_id\": 1, \"background_version\": 1, \"certificate_number\": \"RES-2026-S-IBS-08172026-J5TVHA\", \"certificate_version\": 1, \"certificate_version_id\": 1}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-08-17 09:18:04'),(34,1,1,'release.bulk_completed',NULL,NULL,'{\"started_at\": \"2026-08-17T17:18:04+08:00\", \"completed_at\": \"2026-08-17T17:18:05+08:00\", \"failed_count\": 0, \"release_type\": \"both\", \"ineligible_count\": 0, \"release_type_label\": \"Both Certificate and Decision\", \"already_released_count\": 0, \"affected_application_ids\": {\"failed\": [], \"ineligible\": [], \"already_released\": [], \"successfully_released\": [1]}, \"failed_application_codes\": [], \"successfully_released_count\": 1}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-08-17 09:18:05'),(35,1,2,'auth.login_succeeded','App\\Models\\User',2,'{\"result\": \"succeeded\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-08-17 09:19:21'),(36,1,2,'certificate.survey_completed','App\\Models\\ResearchApplication',1,'{\"result\": \"completed\", \"certificate_id\": 1, \"survey_response_id\": 1, \"certificate_version_id\": 1}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-08-17 09:20:08'),(37,1,2,'certificate.claimed','App\\Models\\ResearchApplication',1,'{\"result\": \"claimed\", \"certificate_id\": 1, \"certificate_version\": 1, \"certificate_version_id\": 1}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-08-17 09:20:12'),(38,1,1,'auth.login_succeeded','App\\Models\\User',1,'{\"result\": \"succeeded\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-08-21 08:42:39'),(39,1,1,'auth.login_succeeded','App\\Models\\User',1,'{\"result\": \"succeeded\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-08-21 08:42:44');
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
INSERT INTO `cache` VALUES ('ecrats-cache-0c8cd3f42985020045eddebc058d3e60','i:3;',1786957887),('ecrats-cache-0c8cd3f42985020045eddebc058d3e60:timer','i:1786957887;',1786957887),('ecrats-cache-15e30c1c89eb89616e0d125b212d7742','i:1;',1786957962),('ecrats-cache-15e30c1c89eb89616e0d125b212d7742:timer','i:1786957962;',1786957962),('ecrats-cache-455f2a0f4be3a442cc6acf1e6552255c','i:3;',1786958116),('ecrats-cache-455f2a0f4be3a442cc6acf1e6552255c:timer','i:1786958116;',1786958116),('ecrats-cache-6e19daff3e3742fe6894bd7e37d8d5f4','i:1;',1786958468),('ecrats-cache-6e19daff3e3742fe6894bd7e37d8d5f4:timer','i:1786958468;',1786958468),('ecrats-cache-a879c42bb14cf9026ef8b196c8dc0dc8','i:1;',1786957991),('ecrats-cache-a879c42bb14cf9026ef8b196c8dc0dc8:timer','i:1786957991;',1786957991),('ecrats-cache-a8c967a3a8c21099262b88faa209fb4f','i:2;',1786958268),('ecrats-cache-a8c967a3a8c21099262b88faa209fb4f:timer','i:1786958268;',1786958268),('ecrats-cache-ac7b948f1be483cfc3e66df2bf75967c','i:1;',1786958472),('ecrats-cache-ac7b948f1be483cfc3e66df2bf75967c:timer','i:1786958472;',1786958472),('ecrats-cache-adviser|127.0.0.1','i:1;',1786958014),('ecrats-cache-adviser|127.0.0.1:timer','i:1786958014;',1786958014),('ecrats-cache-c3ae1fdd66e9fff01ee70fdc35aee456','i:1;',1786958344),('ecrats-cache-c3ae1fdd66e9fff01ee70fdc35aee456:timer','i:1786958344;',1786958344),('ecrats-cache-dc3ce74c5d8fc405436824ab2257b31d','i:1;',1786957928),('ecrats-cache-dc3ce74c5d8fc405436824ab2257b31d:timer','i:1786957928;',1786957928),('ecrats-cache-e4c1a29a9f99408145ec25abd0aab6f6','i:1;',1786958065),('ecrats-cache-e4c1a29a9f99408145ec25abd0aab6f6:timer','i:1786958065;',1786958065);
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
-- Table structure for table `certificate_backgrounds`
--

DROP TABLE IF EXISTS `certificate_backgrounds`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `certificate_backgrounds` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `background_type` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'certificate',
  `asset_version` int unsigned NOT NULL,
  `source_kind` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_file_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `stored_file_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime_type` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_size_bytes` bigint unsigned NOT NULL,
  `sha256` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `width_pixels` int unsigned DEFAULT NULL,
  `height_pixels` int unsigned DEFAULT NULL,
  `page_count` smallint unsigned DEFAULT NULL,
  `uploaded_by_user_id` bigint unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '0',
  `activated_at` timestamp NULL DEFAULT NULL,
  `superseded_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `certificate_background_type_version_unique` (`background_type`,`asset_version`),
  KEY `certificate_backgrounds_uploaded_by_user_id_foreign` (`uploaded_by_user_id`),
  KEY `certificate_backgrounds_source_kind_index` (`source_kind`),
  KEY `certificate_backgrounds_sha256_index` (`sha256`),
  KEY `certificate_backgrounds_is_active_index` (`is_active`),
  KEY `certificate_backgrounds_activated_at_index` (`activated_at`),
  KEY `certificate_background_type_active_index` (`background_type`,`is_active`),
  CONSTRAINT `certificate_backgrounds_uploaded_by_user_id_foreign` FOREIGN KEY (`uploaded_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `certificate_backgrounds`
--

LOCK TABLES `certificate_backgrounds` WRITE;
/*!40000 ALTER TABLE `certificate_backgrounds` DISABLE KEYS */;
INSERT INTO `certificate_backgrounds` VALUES (1,'certificate',1,'official_default','RES Certificate official background.jpeg','certificate-backgrounds/official-d7332a1bfbca1abd35434b9016008188537f137795fa01222296c103256a848f.jpeg','image/jpeg',169359,'d7332a1bfbca1abd35434b9016008188537f137795fa01222296c103256a848f',1414,1998,1,NULL,1,'2026-08-17 09:17:44',NULL,'2026-08-17 09:17:44','2026-08-17 09:17:44'),(2,'review_worksheet',1,'official_default','RES Review Worksheet official background.png','managed-backgrounds/review_worksheet/official-04a7f600af3bae57d9f11150107f97c8cbda858988c586e68c6fb0bba6925b61.png','image/png',629036,'04a7f600af3bae57d9f11150107f97c8cbda858988c586e68c6fb0bba6925b61',1414,2000,1,NULL,1,'2026-08-21 08:45:45',NULL,'2026-08-21 08:45:45','2026-08-21 08:45:45');
/*!40000 ALTER TABLE `certificate_backgrounds` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `certificate_versions`
--

DROP TABLE IF EXISTS `certificate_versions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `certificate_versions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `certificate_id` bigint unsigned NOT NULL,
  `certificate_version` int unsigned NOT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `stored_file_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_file_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime_type` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'application/pdf',
  `file_size_bytes` bigint unsigned NOT NULL,
  `sha256` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `official_template_version` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `official_template_sha256` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `certificate_background_id` bigint unsigned DEFAULT NULL,
  `background_sha256` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `generator_version` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `generated_by_user_id` bigint unsigned NOT NULL,
  `generated_at` timestamp NOT NULL,
  `issued_date` date DEFAULT NULL,
  `valid_until` date DEFAULT NULL,
  `signatory_name_snapshot` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `signature_sha256_snapshot` char(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `qr_code_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `qr_code_sha256` char(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `qr_code_width` int unsigned DEFAULT NULL,
  `qr_code_height` int unsigned DEFAULT NULL,
  `regenerated_at` timestamp NULL DEFAULT NULL,
  `regeneration_reason` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `released_by_user_id` bigint unsigned NOT NULL,
  `released_at` timestamp NOT NULL,
  `claimed_by_user_id` bigint unsigned DEFAULT NULL,
  `claimed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `certificate_version_unique` (`certificate_id`,`certificate_version`),
  KEY `certificate_versions_certificate_background_id_foreign` (`certificate_background_id`),
  KEY `certificate_versions_generated_by_user_id_foreign` (`generated_by_user_id`),
  KEY `certificate_versions_released_by_user_id_foreign` (`released_by_user_id`),
  KEY `certificate_versions_claimed_by_user_id_foreign` (`claimed_by_user_id`),
  KEY `certificate_versions_status_index` (`status`),
  KEY `certificate_versions_sha256_index` (`sha256`),
  KEY `certificate_versions_generated_at_index` (`generated_at`),
  KEY `certificate_versions_released_at_index` (`released_at`),
  KEY `certificate_versions_claimed_at_index` (`claimed_at`),
  KEY `certificate_versions_regenerated_at_index` (`regenerated_at`),
  KEY `certificate_versions_regeneration_reason_index` (`regeneration_reason`),
  KEY `certificate_versions_issued_date_index` (`issued_date`),
  KEY `certificate_versions_valid_until_index` (`valid_until`),
  CONSTRAINT `certificate_versions_certificate_background_id_foreign` FOREIGN KEY (`certificate_background_id`) REFERENCES `certificate_backgrounds` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `certificate_versions_certificate_id_foreign` FOREIGN KEY (`certificate_id`) REFERENCES `certificates` (`id`) ON DELETE CASCADE,
  CONSTRAINT `certificate_versions_claimed_by_user_id_foreign` FOREIGN KEY (`claimed_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `certificate_versions_generated_by_user_id_foreign` FOREIGN KEY (`generated_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `certificate_versions_released_by_user_id_foreign` FOREIGN KEY (`released_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `certificate_versions`
--

LOCK TABLES `certificate_versions` WRITE;
/*!40000 ALTER TABLE `certificate_versions` DISABLE KEYS */;
INSERT INTO `certificate_versions` VALUES (1,1,1,'ready','certificates/1/res-2026-s-ibs-08172026-j5tvha-v1-d9823180-3b5c-4ab4-8ef0-0ce3456d236b.pdf','res-2026-s-ibs-08172026-j5tvha-certificate-v1.pdf','application/pdf',182475,'c6e6e9a60f61e6e0d062dce703ae79cf254c2bd48be80ea5542193ec8081784e','RES-CERTIFICATE-2026-03','998e7a943c81a83afb13df162a85eb08007c4eb2aa1ea51fedfa9909cd5ff960',1,'d7332a1bfbca1abd35434b9016008188537f137795fa01222296c103256a848f','official-res-certificate-v1',1,'2026-08-17 09:18:04','2026-08-17','2027-08-17',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,'2026-08-17 09:18:04',2,'2026-08-17 09:20:12','2026-08-17 09:18:04','2026-08-17 09:20:12');
/*!40000 ALTER TABLE `certificate_versions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `certificates`
--

DROP TABLE IF EXISTS `certificates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `certificates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `research_application_id` bigint unsigned NOT NULL,
  `application_certificate_recipient_id` bigint unsigned DEFAULT NULL,
  `applicant_user_id` bigint unsigned NOT NULL,
  `recipient_name` varchar(180) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `certificate_number` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `generation_failure_code` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `current_certificate_version_id` bigint unsigned DEFAULT NULL,
  `released_by_user_id` bigint unsigned DEFAULT NULL,
  `released_at` timestamp NULL DEFAULT NULL,
  `issued_date` date DEFAULT NULL,
  `valid_until` date DEFAULT NULL,
  `claimed_by_user_id` bigint unsigned DEFAULT NULL,
  `claimed_certificate_version_id` bigint unsigned DEFAULT NULL,
  `claimed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `certificates_certificate_number_unique` (`certificate_number`),
  UNIQUE KEY `certificates_recipient_unique` (`application_certificate_recipient_id`),
  KEY `certificates_applicant_user_id_foreign` (`applicant_user_id`),
  KEY `certificates_released_by_user_id_foreign` (`released_by_user_id`),
  KEY `certificates_claimed_by_user_id_foreign` (`claimed_by_user_id`),
  KEY `certificates_status_index` (`status`),
  KEY `certificates_released_at_index` (`released_at`),
  KEY `certificates_claimed_at_index` (`claimed_at`),
  KEY `certificates_current_certificate_version_id_foreign` (`current_certificate_version_id`),
  KEY `certificates_claimed_certificate_version_id_foreign` (`claimed_certificate_version_id`),
  KEY `certificates_issued_date_index` (`issued_date`),
  KEY `certificates_valid_until_index` (`valid_until`),
  KEY `certificates_research_application_index` (`research_application_id`),
  CONSTRAINT `certificates_applicant_user_id_foreign` FOREIGN KEY (`applicant_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `certificates_claimed_by_user_id_foreign` FOREIGN KEY (`claimed_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `certificates_claimed_certificate_version_id_foreign` FOREIGN KEY (`claimed_certificate_version_id`) REFERENCES `certificate_versions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `certificates_current_certificate_version_id_foreign` FOREIGN KEY (`current_certificate_version_id`) REFERENCES `certificate_versions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `certificates_recipient_fk` FOREIGN KEY (`application_certificate_recipient_id`) REFERENCES `application_certificate_recipients` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `certificates_released_by_user_id_foreign` FOREIGN KEY (`released_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `certificates_research_application_id_foreign` FOREIGN KEY (`research_application_id`) REFERENCES `research_applications` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `certificates`
--

LOCK TABLES `certificates` WRITE;
/*!40000 ALTER TABLE `certificates` DISABLE KEYS */;
INSERT INTO `certificates` VALUES (1,1,1,2,'Applicant Test','RES-2026-S-IBS-08172026-J5TVHA','claimed',NULL,1,1,'2026-08-17 09:18:04','2026-08-17','2027-08-17',2,1,'2026-08-17 09:20:12','2026-08-17 09:18:04','2026-08-17 09:20:12');
/*!40000 ALTER TABLE `certificates` ENABLE KEYS */;
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
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `deadline_configurations`
--

LOCK TABLES `deadline_configurations` WRITE;
/*!40000 ALTER TABLE `deadline_configurations` DISABLE KEYS */;
INSERT INTO `deadline_configurations` VALUES (1,1,'term-1-application-submission','Submission of Application','student_faculty_researcher','1st Semester, A.Y. 2026-2027','2026-08-17 09:07:00','2026-08-31 09:05:00',NULL,100,1,'2026-08-17 09:06:23','2026-08-17 09:06:23'),(2,1,'term-1-adviser-endorsement','Endorsement Period','adviser','1st Semester, A.Y. 2026-2027','2026-08-17 09:07:00','2026-08-31 09:05:00',NULL,100,1,'2026-08-17 09:06:23','2026-08-17 09:06:23'),(3,1,'term-1-res-screening','RES Screening','res_lead','1st Semester, A.Y. 2026-2027','2026-08-17 09:07:00','2026-08-31 09:05:00',NULL,100,1,'2026-08-17 09:06:23','2026-08-17 09:06:23'),(4,1,'term-1-reviewer-submission','Reviewing Period','reviewer','1st Semester, A.Y. 2026-2027','2026-08-17 09:07:00','2026-08-31 09:05:00',NULL,100,1,'2026-08-17 09:06:23','2026-08-17 09:06:23'),(5,1,'term-1-revision-period','Revision Period','student_faculty_researcher','1st Semester, A.Y. 2026-2027','2026-08-17 09:07:00','2026-08-31 09:06:00',NULL,100,1,'2026-08-17 09:06:23','2026-08-17 09:06:23'),(6,1,'term-1-reviewing-revision-period','Reviewing of Revision Period','reviewer','1st Semester, A.Y. 2026-2027','2026-08-17 09:07:00','2026-08-31 09:06:00',NULL,100,1,'2026-08-17 09:06:23','2026-08-17 09:06:23');
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
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `document_requirements`
--

LOCK TABLES `document_requirements` WRITE;
/*!40000 ALTER TABLE `document_requirements` DISABLE KEYS */;
INSERT INTO `document_requirements` VALUES (1,'RESEARCH-PROPOSAL','Research Proposal','Complete research proposal for ethics review.',1,NULL,1,1,'2026-08-17 09:03:08','2026-08-17 09:03:08'),(2,'KLD-RES-04-001B','Research Ethics Compliance Agreement','Signed institutional research ethics compliance agreement.',1,NULL,2,1,'2026-08-17 09:03:08','2026-08-17 09:03:08'),(3,'KLD-RES-04-003','Informed Consent','Participant-facing informed consent document.',1,NULL,3,1,'2026-08-17 09:03:08','2026-08-17 09:03:08'),(4,'PAYMENT-PROOF','Payment Proof','Uploaded proof retained for Research Adviser verification.',1,NULL,4,1,'2026-08-17 09:03:08','2026-08-17 09:03:08');
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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `endorsements`
--

LOCK TABLES `endorsements` WRITE;
/*!40000 ALTER TABLE `endorsements` DISABLE KEYS */;
INSERT INTO `endorsements` VALUES (1,1,3,'endorsed',NULL,NULL,NULL,'2026-08-17 09:13:25','2026-08-17 09:13:25','2026-08-17 09:13:25');
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
) ENGINE=InnoDB AUTO_INCREMENT=46 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'0001_01_01_000000_create_users_table',1),(2,'0001_01_01_000001_create_cache_table',1),(3,'0001_01_01_000002_create_jobs_table',1),(4,'2026_07_17_201500_add_login_fields_to_users_table',2),(5,'2026_07_17_230000_expand_username_length_to_30',2),(6,'2026_07_17_231000_require_usernames_on_users_table',2),(7,'2026_07_18_000000_create_research_applications_table',2),(8,'2026_07_18_000100_create_document_tracking_tables',2),(9,'2026_07_18_000200_create_reviewer_assignments_table',2),(10,'2026_07_18_000300_create_timeline_and_deadline_tables',2),(11,'2026_07_18_000400_create_notifications_table',2),(12,'2026_07_18_000500_add_applicant_type_to_users_table',2),(13,'2026_07_20_000000_add_account_management_fields_to_users_table',2),(14,'2026_07_20_000100_create_audit_logs_table',2),(15,'2026_07_21_000000_add_secure_onboarding_fields_to_users_table',2),(16,'2026_07_23_000000_create_profile_options_table',2),(17,'2026_07_23_100000_add_reviewer_classification_profile_options',2),(18,'2026_07_27_000000_complete_initial_application_submission_schema',2),(19,'2026_07_27_100000_add_semester_and_manual_status_to_deadline_configurations',2),(20,'2026_07_28_000000_create_academic_terms_and_link_records',2),(21,'2026_07_28_000100_add_revision_cycle_and_application_code_sequences',2),(22,'2026_07_28_000200_create_endorsements_table',2),(23,'2026_07_29_000000_add_expected_duration_dates_to_research_applications',2),(24,'2026_07_29_000100_create_profile_option_aliases_table',2),(25,'2026_08_02_000000_create_application_screenings_table',2),(26,'2026_08_04_000000_create_reviewer_workflow_tables',2),(27,'2026_08_05_000000_retire_result_release_deadline',2),(28,'2026_08_05_001000_preserve_reviewer_assignment_history',2),(29,'2026_08_05_002000_snapshot_final_reviewer_forms',2),(30,'2026_08_05_003000_add_review_comment_resolution_history',2),(31,'2026_08_09_000000_create_review_form_artifacts',2),(32,'2026_08_11_000000_create_revision_and_certificate_workflow',2),(33,'2026_08_13_000000_align_reviewer_release_and_certificate_versions',2),(34,'2026_08_17_000000_add_reviewer_entitlement_to_users_table',3),(35,'2026_08_17_000100_create_reviewer_conflicts_table',3),(36,'2026_08_17_000200_version_reviewer_submissions_and_consensus',3),(37,'2026_08_17_000300_add_certificate_validity_dates',3),(38,'2026_08_17_000400_add_background_provenance_to_review_form_artifacts',3),(39,'2026_08_17_010000_expand_applicant_survey_responses_for_questionnaire_v2',3),(40,'2026_08_17_020000_add_role_settings_fields',3),(41,'2026_08_18_000000_scope_certificate_background_asset_versions_by_type',3),(42,'2026_08_20_000000_add_personalized_certificate_configuration',4),(43,'2026_08_20_000100_add_notification_bin',4),(44,'2026_08_21_000000_preserve_combined_release_and_worksheet_business_versions',5),(45,'2026_08_22_000000_add_submission_and_worksheet_settings',6);
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
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notifications_notifiable_type_notifiable_id_index` (`notifiable_type`,`notifiable_id`),
  KEY `notifications_bin_lookup_index` (`notifiable_type`,`notifiable_id`,`deleted_at`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
INSERT INTO `notifications` VALUES ('07aae7a7-c422-4a56-b187-2c4c033f9fb3','App\\Notifications\\DashboardUpdateNotification','App\\Models\\User',2,'{\"title\":\"Certificate released\",\"message\":\"Your generated ethics certificate is ready after you complete the required evaluation and claim it.\",\"icon\":\"award\",\"tone\":\"green\",\"route\":\"applicant.revision-certificates.index\",\"route_parameters\":{\"application\":1}}',NULL,'2026-08-17 09:18:04','2026-08-17 09:18:04',NULL),('25cf8042-abd9-4482-8318-2b904c0df870','App\\Notifications\\DashboardUpdateNotification','App\\Models\\User',2,'{\"title\":\"Application status updated\",\"message\":\"Your application moved to the next ethics review stage.\",\"icon\":\"clipboard\",\"tone\":\"blue\",\"route\":\"applicant.applications.show\",\"route_parameters\":{\"researchApplication\":1}}',NULL,'2026-08-17 09:14:38','2026-08-17 09:14:38',NULL),('499ca147-d1f2-4b35-aaa6-58ae64f19f24','App\\Notifications\\DashboardUpdateNotification','App\\Models\\User',2,'{\"title\":\"Application endorsed\",\"message\":\"Your Research Adviser endorsed the application for RES screening.\",\"icon\":\"check\",\"tone\":\"green\",\"route\":\"applicant.applications.show\",\"route_parameters\":{\"researchApplication\":1}}',NULL,'2026-08-17 09:13:25','2026-08-17 09:13:25',NULL),('508bca45-edf4-4f71-a839-60a54cc51d8c','App\\Notifications\\DashboardUpdateNotification','App\\Models\\User',4,'{\"title\":\"Ethics review assignment available\",\"message\":\"A research ethics application is ready for your review.\",\"icon\":\"file-search\",\"tone\":\"blue\",\"route\":\"reviewer.assignments.show\",\"route_parameters\":{\"reviewerAssignment\":1}}',NULL,'2026-08-17 09:14:28','2026-08-17 09:14:28',NULL),('67de0093-a352-4f6e-b417-f16601433cf0','App\\Notifications\\DashboardUpdateNotification','App\\Models\\User',1,'{\"title\":\"Application ready for RES screening\",\"message\":\"An adviser-endorsed application entered the RES screening queue.\",\"icon\":\"file-text\",\"tone\":\"orange\",\"route\":\"res.applications.show\",\"route_parameters\":{\"researchApplication\":1}}',NULL,'2026-08-17 09:13:25','2026-08-17 09:13:25',NULL),('97d4315a-45de-4bb9-9d25-e152201eb4e2','App\\Notifications\\DashboardUpdateNotification','App\\Models\\User',2,'{\"title\":\"Application status updated\",\"message\":\"Your application moved to the next ethics review stage.\",\"icon\":\"clipboard\",\"tone\":\"blue\",\"route\":\"applicant.applications.show\",\"route_parameters\":{\"researchApplication\":1}}',NULL,'2026-08-17 09:14:28','2026-08-17 09:14:28',NULL),('9acfb95c-0461-464d-b28e-daccf22b9d15','App\\Notifications\\DashboardUpdateNotification','App\\Models\\User',1,'{\"title\":\"Reviewer decisions ready for release processing\",\"message\":\"All required reviewer decisions for an application have been submitted.\",\"icon\":\"file-search\",\"tone\":\"blue\",\"route\":\"res.applications.show\",\"route_parameters\":{\"researchApplication\":1}}',NULL,'2026-08-17 09:17:32','2026-08-17 09:17:32',NULL),('a3e9ac9a-17e3-4f99-a98e-9e7d164a38c2','App\\Notifications\\DashboardUpdateNotification','App\\Models\\User',3,'{\"title\":\"New ethics application\",\"message\":\"A new ethics application has been submitted for your review.\",\"icon\":\"file-text\",\"tone\":\"orange\",\"route\":\"adviser.applications.show\",\"route_parameters\":{\"researchApplication\":1}}',NULL,'2026-08-17 09:12:11','2026-08-17 09:12:11',NULL),('cd4049cf-ac7f-4074-a14e-1349019d01d1','App\\Notifications\\DashboardUpdateNotification','App\\Models\\User',2,'{\"title\":\"Application status updated\",\"message\":\"Your application moved to the next ethics review stage.\",\"icon\":\"clipboard\",\"tone\":\"blue\",\"route\":\"applicant.applications.show\",\"route_parameters\":{\"researchApplication\":1}}',NULL,'2026-08-17 09:14:17','2026-08-17 09:14:17',NULL),('eea3d990-e1dc-408e-a77c-2bd63ea43cea','App\\Notifications\\DashboardUpdateNotification','App\\Models\\User',2,'{\"title\":\"Ethics review decision released\",\"message\":\"An authorized decision and its released comments are now available for your application.\",\"icon\":\"clipboard\",\"tone\":\"blue\",\"route\":\"applicant.revision-certificates.index\",\"route_parameters\":{\"application\":1}}',NULL,'2026-08-17 09:18:04','2026-08-17 09:18:04',NULL);
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
INSERT INTO `profile_options` VALUES (1,'year_level','First Year','first year',10,1,NULL,'2026-08-17 08:54:05','2026-08-17 08:54:05'),(2,'year_level','Second Year','second year',20,1,NULL,'2026-08-17 08:54:05','2026-08-17 08:54:05'),(3,'year_level','Third Year','third year',30,1,NULL,'2026-08-17 08:54:05','2026-08-17 08:54:05'),(4,'year_level','Fourth Year','fourth year',40,1,NULL,'2026-08-17 08:54:05','2026-08-17 08:54:05'),(5,'institution','Institute of Behavioral Sciences','institute of behavioral sciences',10,1,NULL,'2026-08-17 08:54:05','2026-08-17 08:54:05'),(6,'institution','Institute of Computing and Digital Innovation','institute of computing and digital innovation',20,1,NULL,'2026-08-17 08:54:05','2026-08-17 08:54:05'),(7,'institution','Institute of Engineering','institute of engineering',30,1,NULL,'2026-08-17 08:54:05','2026-08-17 08:54:05'),(8,'institution','Institute of Foundational Studies','institute of foundational studies',40,1,NULL,'2026-08-17 08:54:05','2026-08-17 08:54:05'),(9,'institution','Institute of Governance and Development Studies','institute of governance and development studies',50,1,NULL,'2026-08-17 08:54:05','2026-08-17 08:54:05'),(10,'institution','Institute of Medical Laboratory Science','institute of medical laboratory science',60,1,NULL,'2026-08-17 08:54:05','2026-08-17 08:54:05'),(11,'institution','Institute of Midwifery','institute of midwifery',70,1,NULL,'2026-08-17 08:54:05','2026-08-17 08:54:05'),(12,'institution','Institute of Nursing','institute of nursing',80,1,NULL,'2026-08-17 08:54:05','2026-08-17 08:54:05'),(13,'institution','Institute of Science and Mathematics','institute of science and mathematics',90,1,NULL,'2026-08-17 08:54:05','2026-08-17 08:54:05'),(14,'reviewer_classification','Expedited','expedited',10,1,NULL,'2026-08-17 08:54:06','2026-08-17 08:54:06'),(15,'reviewer_classification','Full Board','full board',20,1,NULL,'2026-08-17 08:54:06','2026-08-17 08:54:06'),(16,'reviewer_classification','Exempted','exempted',30,1,NULL,'2026-08-17 08:54:06','2026-08-17 08:54:06'),(17,'department','Computer Studies','computer studies',10,1,NULL,'2026-08-17 08:54:09','2026-08-17 08:54:09'),(18,'program','Bachelor of Science in Computer Science','bachelor of science in computer science',10,1,NULL,'2026-08-17 08:54:09','2026-08-17 08:54:09');
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
  `review_consensus_status` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `review_consensus_cycle` smallint unsigned DEFAULT NULL,
  `review_consensus_decision` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `review_consensus_signature` char(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `review_consensus_evaluated_at` timestamp NULL DEFAULT NULL,
  `review_conflicted_at` timestamp NULL DEFAULT NULL,
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
  KEY `research_applications_review_consensus_status_index` (`review_consensus_status`),
  CONSTRAINT `research_applications_academic_term_id_foreign` FOREIGN KEY (`academic_term_id`) REFERENCES `academic_terms` (`id`) ON DELETE SET NULL,
  CONSTRAINT `research_applications_adviser_user_id_foreign` FOREIGN KEY (`adviser_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `research_applications_applicant_user_id_foreign` FOREIGN KEY (`applicant_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `research_applications_draft_owner_user_id_foreign` FOREIGN KEY (`draft_owner_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `research_applications`
--

LOCK TABLES `research_applications` WRITE;
/*!40000 ALTER TABLE `research_applications` DISABLE KEYS */;
INSERT INTO `research_applications` VALUES (1,1,'RES-2026-S-IBS-08172026-J5TVHA',2,NULL,3,'student','test1','thesis','test1','Institute of Behavioral Sciences','Computer Studies','Bachelor of Science in Computer Science','test1','test1',NULL,'2026-08-17','2026-08-31','new_application','certificate_released','completed','expedited',1,NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-17 09:12:11','2026-08-17 09:18:04','2026-08-17 09:11:08','2026-08-17 09:18:04');
/*!40000 ALTER TABLE `research_applications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `review_comment_status_changes`
--

DROP TABLE IF EXISTS `review_comment_status_changes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `review_comment_status_changes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `review_comment_id` bigint unsigned NOT NULL,
  `actor_user_id` bigint unsigned NOT NULL,
  `from_status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `to_status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `changed_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `review_comment_status_changes_actor_user_id_foreign` (`actor_user_id`),
  KEY `review_comment_status_history_index` (`review_comment_id`,`changed_at`),
  CONSTRAINT `review_comment_status_changes_actor_user_id_foreign` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `review_comment_status_changes_review_comment_id_foreign` FOREIGN KEY (`review_comment_id`) REFERENCES `review_comments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `review_comment_status_changes`
--

LOCK TABLES `review_comment_status_changes` WRITE;
/*!40000 ALTER TABLE `review_comment_status_changes` DISABLE KEYS */;
/*!40000 ALTER TABLE `review_comment_status_changes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `review_comments`
--

DROP TABLE IF EXISTS `review_comments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `review_comments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `reviewer_assignment_id` bigint unsigned NOT NULL,
  `application_document_id` bigint unsigned DEFAULT NULL,
  `scope` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `page_number` int unsigned DEFAULT NULL,
  `body` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `resolved_at` timestamp NULL DEFAULT NULL,
  `resolved_by_user_id` bigint unsigned DEFAULT NULL,
  `application_decision_release_id` bigint unsigned DEFAULT NULL,
  `released_at` timestamp NULL DEFAULT NULL,
  `released_by_user_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `review_comments_application_document_id_foreign` (`application_document_id`),
  KEY `review_comments_reviewer_assignment_id_created_at_index` (`reviewer_assignment_id`,`created_at`),
  KEY `review_comments_scope_index` (`scope`),
  KEY `review_comments_released_at_index` (`released_at`),
  KEY `review_comments_resolved_by_user_id_foreign` (`resolved_by_user_id`),
  KEY `review_comments_status_index` (`status`),
  KEY `review_comments_application_decision_release_id_foreign` (`application_decision_release_id`),
  KEY `review_comments_released_by_user_id_foreign` (`released_by_user_id`),
  CONSTRAINT `review_comments_application_decision_release_id_foreign` FOREIGN KEY (`application_decision_release_id`) REFERENCES `application_decision_releases` (`id`) ON DELETE SET NULL,
  CONSTRAINT `review_comments_application_document_id_foreign` FOREIGN KEY (`application_document_id`) REFERENCES `application_documents` (`id`) ON DELETE SET NULL,
  CONSTRAINT `review_comments_released_by_user_id_foreign` FOREIGN KEY (`released_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `review_comments_resolved_by_user_id_foreign` FOREIGN KEY (`resolved_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `review_comments_reviewer_assignment_id_foreign` FOREIGN KEY (`reviewer_assignment_id`) REFERENCES `reviewer_assignments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `review_comments`
--

LOCK TABLES `review_comments` WRITE;
/*!40000 ALTER TABLE `review_comments` DISABLE KEYS */;
INSERT INTO `review_comments` VALUES (1,1,NULL,'overall','general',NULL,'test1','open',NULL,NULL,1,'2026-08-17 09:18:04',1,'2026-08-17 09:15:36','2026-08-17 09:18:04',NULL),(2,1,2,'document','required_revision',NULL,'test1','open',NULL,NULL,1,'2026-08-17 09:18:04',1,'2026-08-17 09:15:45','2026-08-17 09:18:04',NULL);
/*!40000 ALTER TABLE `review_comments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `review_form_artifacts`
--

DROP TABLE IF EXISTS `review_form_artifacts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `review_form_artifacts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `review_form_submission_id` bigint unsigned NOT NULL,
  `review_submission_version_id` bigint unsigned DEFAULT NULL,
  `certificate_background_id` bigint unsigned DEFAULT NULL,
  `background_sha256` char(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `artifact_version` int unsigned NOT NULL,
  `business_version` smallint unsigned DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ready',
  `stored_file_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_file_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime_type` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'application/pdf',
  `file_size_bytes` bigint unsigned NOT NULL,
  `sha256` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `template_code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `template_version` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `template_sha256` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `generator_version` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `generated_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `review_form_artifact_version_unique` (`review_form_submission_id`,`artifact_version`),
  UNIQUE KEY `review_form_artifacts_stored_file_path_unique` (`stored_file_path`),
  KEY `review_form_artifacts_status_index` (`status`),
  KEY `review_form_artifacts_sha256_index` (`sha256`),
  KEY `review_form_artifacts_review_submission_version_id_index` (`review_submission_version_id`),
  KEY `review_form_artifacts_certificate_background_id_foreign` (`certificate_background_id`),
  KEY `review_form_artifact_business_version_index` (`review_form_submission_id`,`business_version`),
  CONSTRAINT `review_artifact_submission_version_fk` FOREIGN KEY (`review_submission_version_id`) REFERENCES `review_submission_versions` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `review_form_artifacts_certificate_background_id_foreign` FOREIGN KEY (`certificate_background_id`) REFERENCES `certificate_backgrounds` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `review_form_artifacts_review_form_submission_id_foreign` FOREIGN KEY (`review_form_submission_id`) REFERENCES `review_form_submissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `review_form_artifacts`
--

LOCK TABLES `review_form_artifacts` WRITE;
/*!40000 ALTER TABLE `review_form_artifacts` DISABLE KEYS */;
INSERT INTO `review_form_artifacts` VALUES (1,2,1,NULL,NULL,1,1,'ready','review-form-artifacts/1/e90b45c6-5c5d-4cfc-9637-4e12e7e0358d.pdf','KLD-RES-04-002-RES-2026-S-IBS-08172026-J5TVHA-v1.pdf','application/pdf',1872189,'14850e449e2c4f0399f7a6119e37176b1960900d6ac9a79d83e44edb9c433972','KLD-RES-04-002','rems-review-forms-7231e839-v1','7231e839ed75dc8d3977f84c35d62c552b5b1919b4065efa412dbd122c960f16','ecrats-fpdi-2-final-review','2026-08-17 09:17:30','2026-08-17 09:17:31','2026-08-17 09:17:31'),(2,1,1,NULL,NULL,1,1,'ready','review-form-artifacts/1/f71ac0f1-5760-48aa-96e5-3e2e235a8ea8.pdf','KLD-RES-04-001-RES-2026-S-IBS-08172026-J5TVHA-v1.pdf','application/pdf',1883900,'517ea3285dadf531153e69ea38bb8eeeb536371b64c1848f467c3ebd44a20d0c','KLD-RES-04-001','rems-review-forms-7231e839-v1','7231e839ed75dc8d3977f84c35d62c552b5b1919b4065efa412dbd122c960f16','ecrats-fpdi-2-final-review','2026-08-17 09:17:30','2026-08-17 09:17:32','2026-08-17 09:17:32');
/*!40000 ALTER TABLE `review_form_artifacts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `review_form_submissions`
--

DROP TABLE IF EXISTS `review_form_submissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `review_form_submissions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `reviewer_assignment_id` bigint unsigned NOT NULL,
  `form_type` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `catalog_version` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `catalog_snapshot` json DEFAULT NULL,
  `finalized_payload_snapshot` json DEFAULT NULL,
  `finalized_context_snapshot` json DEFAULT NULL,
  `responses` json DEFAULT NULL,
  `consent_required` tinyint(1) DEFAULT NULL,
  `consent_not_required_explanation` text COLLATE utf8mb4_unicode_ci,
  `recommendation` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `recommendation_comments` text COLLATE utf8mb4_unicode_ci,
  `review_date` date DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `finalized_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `reviewer_assignment_form_unique` (`reviewer_assignment_id`,`form_type`),
  KEY `review_form_submissions_status_index` (`status`),
  KEY `review_form_submissions_completed_at_index` (`completed_at`),
  CONSTRAINT `review_form_submissions_reviewer_assignment_id_foreign` FOREIGN KEY (`reviewer_assignment_id`) REFERENCES `reviewer_assignments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `review_form_submissions`
--

LOCK TABLES `review_form_submissions` WRITE;
/*!40000 ALTER TABLE `review_form_submissions` DISABLE KEYS */;
INSERT INTO `review_form_submissions` VALUES (1,1,'protocol','final','rems-review-forms-7231e839-v1','{\"items\": {\"protocol_01\": {\"key\": \"protocol_01\", \"text\": \"Does study have social value? (e.g. scientific value, relevance to national /community needs)\", \"answer_y_mm\": 122, \"source_page\": 1, \"comment_y_mm\": 132, \"printed_number\": 1, \"comment_source_page\": 1}, \"protocol_02\": {\"key\": \"protocol_02\", \"text\": \"Is the study background adequate?\", \"answer_y_mm\": 138.3, \"source_page\": 1, \"comment_y_mm\": 148, \"printed_number\": 2, \"comment_source_page\": 1}, \"protocol_03\": {\"key\": \"protocol_03\", \"text\": \"Are the research questions supported by the Review of Literature?\", \"answer_y_mm\": 155.2, \"source_page\": 1, \"comment_y_mm\": 169.5, \"printed_number\": 3, \"comment_source_page\": 1}, \"protocol_04\": {\"key\": \"protocol_04\", \"text\": \"(For pure Qualitative) Are the study objectives having Credibility, Transferability, Dependability, Feasibility, Flexibility, Confirmability? (For pure Quantitative, systematic literature reviews (SLRs), and others) Are the study objectives Specific, Measurable, Attainable, Realistic, Time-bound?\", \"answer_y_mm\": 176.7, \"source_page\": 1, \"comment_y_mm\": 223, \"printed_number\": 4, \"comment_source_page\": 1}, \"protocol_05\": {\"key\": \"protocol_05\", \"text\": \"Is the research design appropriate? Is the population identified and defined? Is the selection of study participants described? Is the sample size justified? Is the plan for data analysis described? Are there dummy tables?\", \"answer_y_mm\": 230, \"source_page\": 1, \"comment_y_mm\": 76.8, \"printed_number\": 5, \"comment_source_page\": 2}, \"protocol_06\": {\"key\": \"protocol_06\", \"text\": \"Does the research need to be carried out with human participants?\", \"answer_y_mm\": 83.7, \"source_page\": 2, \"comment_y_mm\": 98.2, \"printed_number\": 6, \"comment_source_page\": 2}, \"protocol_07\": {\"key\": \"protocol_07\", \"text\": \"Does the study have a vulnerability issue?\", \"answer_y_mm\": 104.9, \"source_page\": 2, \"comment_y_mm\": 114.5, \"printed_number\": 7, \"comment_source_page\": 2}, \"protocol_08\": {\"key\": \"protocol_08\", \"text\": \"Are appropriate mechanisms/interventions in place to address the vulnerability issue/s?\", \"answer_y_mm\": 121.1, \"source_page\": 2, \"comment_y_mm\": 135.2, \"printed_number\": 8, \"comment_source_page\": 2}, \"protocol_09\": {\"key\": \"protocol_09\", \"text\": \"Are there risks/probable harms to the human participants in the study?\", \"answer_y_mm\": 141.9, \"source_page\": 2, \"comment_y_mm\": 156.7, \"printed_number\": 9, \"comment_source_page\": 2}, \"protocol_10\": {\"key\": \"protocol_10\", \"text\": \"Are there measures to mitigate the risks?\", \"answer_y_mm\": 163.4, \"source_page\": 2, \"comment_y_mm\": 173, \"printed_number\": 10, \"comment_source_page\": 2}, \"protocol_11\": {\"key\": \"protocol_11\", \"text\": \"Is the informed consent procedure/ form and culturally appropriate?\", \"answer_y_mm\": 179.6, \"source_page\": 2, \"comment_y_mm\": 194.4, \"printed_number\": 11, \"comment_source_page\": 2}, \"protocol_12\": {\"key\": \"protocol_12\", \"text\": \"Is/are the investigator/s adequately trained and do they have sufficient experience to undertake the study?\", \"answer_y_mm\": 201, \"source_page\": 2, \"comment_y_mm\": 215.8, \"printed_number\": 12, \"comment_source_page\": 2}, \"protocol_13\": {\"key\": \"protocol_13\", \"text\": \"Is there a disclosure of conflict of interest?\", \"answer_y_mm\": 222.5, \"source_page\": 2, \"comment_y_mm\": 232, \"printed_number\": 13, \"comment_source_page\": 2}, \"protocol_14\": {\"key\": \"protocol_14\", \"text\": \"Are the research facilities adequate?\", \"answer_y_mm\": 238.7, \"source_page\": 2, \"comment_y_mm\": 248.2, \"printed_number\": 14, \"comment_source_page\": 2}, \"protocol_15\": {\"key\": \"protocol_15\", \"text\": \"Are there any other concerns in the study?\", \"answer_y_mm\": 74, \"source_page\": 3, \"comment_y_mm\": 83.5, \"printed_number\": 15, \"comment_source_page\": 3}}, \"answers\": {\"no\": \"No\", \"yes\": \"Yes\", \"unable_to_assess\": \"Unable to Assess\"}, \"template\": {\"sha256\": \"7231e839ed75dc8d3977f84c35d62c552b5b1919b4065efa412dbd122c960f16\", \"version\": \"rems-review-forms-7231e839-v1\", \"source_pages\": [1, 2, 3], \"generator_version\": \"ecrats-fpdi-2-final-review\"}, \"form_code\": \"KLD-RES-04-001\", \"form_type\": \"protocol\", \"questions\": {\"protocol_01\": \"Does study have social value? (e.g. scientific value, relevance to national /community needs)\", \"protocol_02\": \"Is the study background adequate?\", \"protocol_03\": \"Are the research questions supported by the Review of Literature?\", \"protocol_04\": \"(For pure Qualitative) Are the study objectives having Credibility, Transferability, Dependability, Feasibility, Flexibility, Confirmability? (For pure Quantitative, systematic literature reviews (SLRs), and others) Are the study objectives Specific, Measurable, Attainable, Realistic, Time-bound?\", \"protocol_05\": \"Is the research design appropriate? Is the population identified and defined? Is the selection of study participants described? Is the sample size justified? Is the plan for data analysis described? Are there dummy tables?\", \"protocol_06\": \"Does the research need to be carried out with human participants?\", \"protocol_07\": \"Does the study have a vulnerability issue?\", \"protocol_08\": \"Are appropriate mechanisms/interventions in place to address the vulnerability issue/s?\", \"protocol_09\": \"Are there risks/probable harms to the human participants in the study?\", \"protocol_10\": \"Are there measures to mitigate the risks?\", \"protocol_11\": \"Is the informed consent procedure/ form and culturally appropriate?\", \"protocol_12\": \"Is/are the investigator/s adequately trained and do they have sufficient experience to undertake the study?\", \"protocol_13\": \"Is there a disclosure of conflict of interest?\", \"protocol_14\": \"Are the research facilities adequate?\", \"protocol_15\": \"Are there any other concerns in the study?\"}, \"form_label\": \"Protocol Review Worksheet\"}','{\"responses\": {\"protocol_01\": {\"answer\": \"no\", \"comment\": null}, \"protocol_02\": {\"answer\": \"no\", \"comment\": null}, \"protocol_03\": {\"answer\": \"no\", \"comment\": null}, \"protocol_04\": {\"answer\": \"no\", \"comment\": null}, \"protocol_05\": {\"answer\": \"no\", \"comment\": null}, \"protocol_06\": {\"answer\": \"no\", \"comment\": null}, \"protocol_07\": {\"answer\": \"no\", \"comment\": null}, \"protocol_08\": {\"answer\": \"no\", \"comment\": null}, \"protocol_09\": {\"answer\": \"no\", \"comment\": null}, \"protocol_10\": {\"answer\": \"no\", \"comment\": null}, \"protocol_11\": {\"answer\": \"no\", \"comment\": null}, \"protocol_12\": {\"answer\": \"no\", \"comment\": null}, \"protocol_13\": {\"answer\": \"no\", \"comment\": null}, \"protocol_14\": {\"answer\": \"no\", \"comment\": null}, \"protocol_15\": {\"answer\": \"no\", \"comment\": null}}, \"recommendation\": \"approved\", \"consent_required\": null, \"recommendation_comments\": \"test1test1test1test1test1test1\", \"consent_not_required_explanation\": null}','{\"attestation\": {\"method\": \"authenticated_electronic_attestation\", \"version\": 1, \"statement\": \"The authenticated reviewer finalized this response as their official review record.\", \"actor_name\": \"Reviewer Test\", \"attested_at\": \"2026-08-17T17:17:30+08:00\", \"actor_user_id\": 4}, \"institution\": \"Institute of Behavioral Sciences\", \"received_at\": \"2026-08-17T17:14:28+08:00\", \"review_date\": \"08/17/26\", \"finalized_at\": \"2026-08-17T17:17:30+08:00\", \"received_date\": \"08/17/26\", \"reviewer_name\": \"Reviewer Test\", \"application_id\": 1, \"research_title\": \"test1\", \"proponent_label\": \"WITHHELD - BLIND REVIEW\", \"application_code\": \"RES-2026-S-IBS-08172026-J5TVHA\", \"reviewer_user_id\": 4, \"assignment_sequence\": 1, \"review_classification\": \"Expedited\", \"primary_reviewer_label\": \"Not designated in ECRATS\", \"reviewer_assignment_id\": 1}','{\"protocol_01\": {\"answer\": \"no\", \"comment\": null}, \"protocol_02\": {\"answer\": \"no\", \"comment\": null}, \"protocol_03\": {\"answer\": \"no\", \"comment\": null}, \"protocol_04\": {\"answer\": \"no\", \"comment\": null}, \"protocol_05\": {\"answer\": \"no\", \"comment\": null}, \"protocol_06\": {\"answer\": \"no\", \"comment\": null}, \"protocol_07\": {\"answer\": \"no\", \"comment\": null}, \"protocol_08\": {\"answer\": \"no\", \"comment\": null}, \"protocol_09\": {\"answer\": \"no\", \"comment\": null}, \"protocol_10\": {\"answer\": \"no\", \"comment\": null}, \"protocol_11\": {\"answer\": \"no\", \"comment\": null}, \"protocol_12\": {\"answer\": \"no\", \"comment\": null}, \"protocol_13\": {\"answer\": \"no\", \"comment\": null}, \"protocol_14\": {\"answer\": \"no\", \"comment\": null}, \"protocol_15\": {\"answer\": \"no\", \"comment\": null}}',NULL,NULL,'approved','test1test1test1test1test1test1','2026-08-17','2026-08-17 09:16:21','2026-08-17 09:17:30','2026-08-17 09:16:21','2026-08-17 09:17:30'),(2,1,'informed_consent','final','rems-review-forms-7231e839-v1','{\"items\": {\"consent_01\": {\"key\": \"consent_01\", \"text\": \"Purpose of the study?\", \"answer_y_mm\": 157.6, \"source_page\": 7}, \"consent_02\": {\"key\": \"consent_02\", \"text\": \"Expected duration of participation?\", \"answer_y_mm\": 163.1, \"source_page\": 7}, \"consent_03\": {\"key\": \"consent_03\", \"text\": \"Procedures to be carried out?\", \"answer_y_mm\": 169.8, \"source_page\": 7}, \"consent_04\": {\"key\": \"consent_04\", \"text\": \"Discomforts and inconveniences?\", \"answer_y_mm\": 175.3, \"source_page\": 7}, \"consent_05\": {\"key\": \"consent_05\", \"text\": \"Risks (including possible discrimination)?\", \"answer_y_mm\": 180.8, \"source_page\": 7}, \"consent_06\": {\"key\": \"consent_06\", \"text\": \"Random assignment to the trial treatments?\", \"answer_y_mm\": 186.3, \"source_page\": 7}, \"consent_07\": {\"key\": \"consent_07\", \"text\": \"Benefits to the participants?\", \"answer_y_mm\": 191.8, \"source_page\": 7}, \"consent_08\": {\"key\": \"consent_08\", \"text\": \"Alternative treatments/ procedures?\", \"answer_y_mm\": 198, \"source_page\": 7}, \"consent_09\": {\"key\": \"consent_09\", \"text\": \"Compensation and/or medical treatments in case of injury?\", \"answer_y_mm\": 203.5, \"source_page\": 7}, \"consent_10\": {\"key\": \"consent_10\", \"text\": \"Who to contact for pertinent questions and/ or for assistance in a research- related injury?\", \"answer_y_mm\": 210.2, \"source_page\": 7}, \"consent_11\": {\"key\": \"consent_11\", \"text\": \"Refusal to participate or discontinuance at any time will involve penalty or loss of benefits to which the subject is entitled?\", \"answer_y_mm\": 221, \"source_page\": 7}, \"consent_12\": {\"key\": \"consent_12\", \"text\": \"Extent of confidentiality?\", \"answer_y_mm\": 236.7, \"source_page\": 7}, \"consent_13\": {\"key\": \"consent_13\", \"text\": \"Is the informed consent written or presented in simple language that participants can understand?\", \"answer_y_mm\": 71.7, \"source_page\": 8}, \"consent_14\": {\"key\": \"consent_14\", \"text\": \"Does the protocol include an adequate process for ensuring that consent is voluntary?\", \"answer_y_mm\": 82.4, \"source_page\": 8}, \"consent_15\": {\"key\": \"consent_15\", \"text\": \"Do you have any other concerns?\", \"answer_y_mm\": 95.4, \"source_page\": 8}}, \"answers\": {\"no\": \"No\", \"yes\": \"Yes\"}, \"template\": {\"sha256\": \"7231e839ed75dc8d3977f84c35d62c552b5b1919b4065efa412dbd122c960f16\", \"version\": \"rems-review-forms-7231e839-v1\", \"source_pages\": [7, 8], \"generator_version\": \"ecrats-fpdi-2-final-review\"}, \"form_code\": \"KLD-RES-04-002\", \"form_type\": \"informed_consent\", \"questions\": {\"consent_01\": \"Purpose of the study?\", \"consent_02\": \"Expected duration of participation?\", \"consent_03\": \"Procedures to be carried out?\", \"consent_04\": \"Discomforts and inconveniences?\", \"consent_05\": \"Risks (including possible discrimination)?\", \"consent_06\": \"Random assignment to the trial treatments?\", \"consent_07\": \"Benefits to the participants?\", \"consent_08\": \"Alternative treatments/ procedures?\", \"consent_09\": \"Compensation and/or medical treatments in case of injury?\", \"consent_10\": \"Who to contact for pertinent questions and/ or for assistance in a research- related injury?\", \"consent_11\": \"Refusal to participate or discontinuance at any time will involve penalty or loss of benefits to which the subject is entitled?\", \"consent_12\": \"Extent of confidentiality?\", \"consent_13\": \"Is the informed consent written or presented in simple language that participants can understand?\", \"consent_14\": \"Does the protocol include an adequate process for ensuring that consent is voluntary?\", \"consent_15\": \"Do you have any other concerns?\"}, \"form_label\": \"Informed Consent Checklist\"}','{\"responses\": null, \"recommendation\": \"minor_revision\", \"consent_required\": false, \"recommendation_comments\": \"test1test1test1test1\", \"consent_not_required_explanation\": \"test1test1test1\"}','{\"attestation\": {\"method\": \"authenticated_electronic_attestation\", \"version\": 1, \"statement\": \"The authenticated reviewer finalized this response as their official review record.\", \"actor_name\": \"Reviewer Test\", \"attested_at\": \"2026-08-17T17:17:30+08:00\", \"actor_user_id\": 4}, \"institution\": \"Institute of Behavioral Sciences\", \"received_at\": \"2026-08-17T17:14:28+08:00\", \"review_date\": \"08/17/26\", \"finalized_at\": \"2026-08-17T17:17:30+08:00\", \"received_date\": \"08/17/26\", \"reviewer_name\": \"Reviewer Test\", \"application_id\": 1, \"research_title\": \"test1\", \"proponent_label\": \"WITHHELD - BLIND REVIEW\", \"application_code\": \"RES-2026-S-IBS-08172026-J5TVHA\", \"reviewer_user_id\": 4, \"assignment_sequence\": 1, \"review_classification\": \"Expedited\", \"primary_reviewer_label\": \"Not designated in ECRATS\", \"reviewer_assignment_id\": 1}',NULL,0,'test1test1test1','minor_revision','test1test1test1test1','2026-08-17','2026-08-17 09:16:48','2026-08-17 09:17:30','2026-08-17 09:16:48','2026-08-17 09:17:30');
/*!40000 ALTER TABLE `review_form_submissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `review_submission_versions`
--

DROP TABLE IF EXISTS `review_submission_versions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `review_submission_versions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `review_submission_id` bigint unsigned NOT NULL,
  `reviewer_assignment_id` bigint unsigned NOT NULL,
  `version_number` int unsigned NOT NULL,
  `submission_token` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `decision` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `decision_comment` text COLLATE utf8mb4_unicode_ci,
  `snapshot_schema_version` smallint unsigned NOT NULL DEFAULT '1',
  `payload_snapshot` json NOT NULL,
  `payload_sha256` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `request_sha256` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `submitted_by_user_id` bigint unsigned DEFAULT NULL,
  `submitted_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `rsv_submission_version_unique` (`review_submission_id`,`version_number`),
  UNIQUE KEY `rsv_submission_token_unique` (`review_submission_id`,`submission_token`),
  KEY `rsv_submitter_fk` (`submitted_by_user_id`),
  KEY `rsv_assignment_submitted_idx` (`reviewer_assignment_id`,`submitted_at`),
  KEY `review_submission_versions_submitted_at_index` (`submitted_at`),
  CONSTRAINT `rsv_assignment_fk` FOREIGN KEY (`reviewer_assignment_id`) REFERENCES `reviewer_assignments` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `rsv_submission_fk` FOREIGN KEY (`review_submission_id`) REFERENCES `review_submissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `rsv_submitter_fk` FOREIGN KEY (`submitted_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `review_submission_versions`
--

LOCK TABLES `review_submission_versions` WRITE;
/*!40000 ALTER TABLE `review_submission_versions` DISABLE KEYS */;
INSERT INTO `review_submission_versions` VALUES (1,1,1,1,'4b486d65-9133-438d-a8c0-f7e1b8e7a77e','approved','test1test1test1test1',1,'{\"forms\": [{\"id\": 1, \"context\": {\"attestation\": {\"method\": \"authenticated_electronic_attestation\", \"version\": 1, \"statement\": \"The authenticated reviewer finalized this response as their official review record.\", \"actor_name\": \"Reviewer Test\", \"attested_at\": \"2026-08-17T17:17:30+08:00\", \"actor_user_id\": 4}, \"institution\": \"Institute of Behavioral Sciences\", \"received_at\": \"2026-08-17T17:14:28+08:00\", \"review_date\": \"08/17/26\", \"finalized_at\": \"2026-08-17T17:17:30+08:00\", \"received_date\": \"08/17/26\", \"reviewer_name\": \"Reviewer Test\", \"application_id\": 1, \"research_title\": \"test1\", \"proponent_label\": \"WITHHELD - BLIND REVIEW\", \"application_code\": \"RES-2026-S-IBS-08172026-J5TVHA\", \"reviewer_user_id\": 4, \"assignment_sequence\": 1, \"review_classification\": \"Expedited\", \"primary_reviewer_label\": \"Not designated in ECRATS\", \"reviewer_assignment_id\": 1}, \"payload\": {\"responses\": {\"protocol_01\": {\"answer\": \"no\", \"comment\": null}, \"protocol_02\": {\"answer\": \"no\", \"comment\": null}, \"protocol_03\": {\"answer\": \"no\", \"comment\": null}, \"protocol_04\": {\"answer\": \"no\", \"comment\": null}, \"protocol_05\": {\"answer\": \"no\", \"comment\": null}, \"protocol_06\": {\"answer\": \"no\", \"comment\": null}, \"protocol_07\": {\"answer\": \"no\", \"comment\": null}, \"protocol_08\": {\"answer\": \"no\", \"comment\": null}, \"protocol_09\": {\"answer\": \"no\", \"comment\": null}, \"protocol_10\": {\"answer\": \"no\", \"comment\": null}, \"protocol_11\": {\"answer\": \"no\", \"comment\": null}, \"protocol_12\": {\"answer\": \"no\", \"comment\": null}, \"protocol_13\": {\"answer\": \"no\", \"comment\": null}, \"protocol_14\": {\"answer\": \"no\", \"comment\": null}, \"protocol_15\": {\"answer\": \"no\", \"comment\": null}}, \"recommendation\": \"approved\", \"consent_required\": null, \"recommendation_comments\": \"test1test1test1test1test1test1\", \"consent_not_required_explanation\": null}, \"form_type\": \"protocol\", \"catalog_version\": \"rems-review-forms-7231e839-v1\", \"catalog_snapshot\": {\"items\": {\"protocol_01\": {\"key\": \"protocol_01\", \"text\": \"Does study have social value? (e.g. scientific value, relevance to national /community needs)\", \"answer_y_mm\": 122, \"source_page\": 1, \"comment_y_mm\": 132, \"printed_number\": 1, \"comment_source_page\": 1}, \"protocol_02\": {\"key\": \"protocol_02\", \"text\": \"Is the study background adequate?\", \"answer_y_mm\": 138.3, \"source_page\": 1, \"comment_y_mm\": 148, \"printed_number\": 2, \"comment_source_page\": 1}, \"protocol_03\": {\"key\": \"protocol_03\", \"text\": \"Are the research questions supported by the Review of Literature?\", \"answer_y_mm\": 155.2, \"source_page\": 1, \"comment_y_mm\": 169.5, \"printed_number\": 3, \"comment_source_page\": 1}, \"protocol_04\": {\"key\": \"protocol_04\", \"text\": \"(For pure Qualitative) Are the study objectives having Credibility, Transferability, Dependability, Feasibility, Flexibility, Confirmability? (For pure Quantitative, systematic literature reviews (SLRs), and others) Are the study objectives Specific, Measurable, Attainable, Realistic, Time-bound?\", \"answer_y_mm\": 176.7, \"source_page\": 1, \"comment_y_mm\": 223, \"printed_number\": 4, \"comment_source_page\": 1}, \"protocol_05\": {\"key\": \"protocol_05\", \"text\": \"Is the research design appropriate? Is the population identified and defined? Is the selection of study participants described? Is the sample size justified? Is the plan for data analysis described? Are there dummy tables?\", \"answer_y_mm\": 230, \"source_page\": 1, \"comment_y_mm\": 76.8, \"printed_number\": 5, \"comment_source_page\": 2}, \"protocol_06\": {\"key\": \"protocol_06\", \"text\": \"Does the research need to be carried out with human participants?\", \"answer_y_mm\": 83.7, \"source_page\": 2, \"comment_y_mm\": 98.2, \"printed_number\": 6, \"comment_source_page\": 2}, \"protocol_07\": {\"key\": \"protocol_07\", \"text\": \"Does the study have a vulnerability issue?\", \"answer_y_mm\": 104.9, \"source_page\": 2, \"comment_y_mm\": 114.5, \"printed_number\": 7, \"comment_source_page\": 2}, \"protocol_08\": {\"key\": \"protocol_08\", \"text\": \"Are appropriate mechanisms/interventions in place to address the vulnerability issue/s?\", \"answer_y_mm\": 121.1, \"source_page\": 2, \"comment_y_mm\": 135.2, \"printed_number\": 8, \"comment_source_page\": 2}, \"protocol_09\": {\"key\": \"protocol_09\", \"text\": \"Are there risks/probable harms to the human participants in the study?\", \"answer_y_mm\": 141.9, \"source_page\": 2, \"comment_y_mm\": 156.7, \"printed_number\": 9, \"comment_source_page\": 2}, \"protocol_10\": {\"key\": \"protocol_10\", \"text\": \"Are there measures to mitigate the risks?\", \"answer_y_mm\": 163.4, \"source_page\": 2, \"comment_y_mm\": 173, \"printed_number\": 10, \"comment_source_page\": 2}, \"protocol_11\": {\"key\": \"protocol_11\", \"text\": \"Is the informed consent procedure/ form and culturally appropriate?\", \"answer_y_mm\": 179.6, \"source_page\": 2, \"comment_y_mm\": 194.4, \"printed_number\": 11, \"comment_source_page\": 2}, \"protocol_12\": {\"key\": \"protocol_12\", \"text\": \"Is/are the investigator/s adequately trained and do they have sufficient experience to undertake the study?\", \"answer_y_mm\": 201, \"source_page\": 2, \"comment_y_mm\": 215.8, \"printed_number\": 12, \"comment_source_page\": 2}, \"protocol_13\": {\"key\": \"protocol_13\", \"text\": \"Is there a disclosure of conflict of interest?\", \"answer_y_mm\": 222.5, \"source_page\": 2, \"comment_y_mm\": 232, \"printed_number\": 13, \"comment_source_page\": 2}, \"protocol_14\": {\"key\": \"protocol_14\", \"text\": \"Are the research facilities adequate?\", \"answer_y_mm\": 238.7, \"source_page\": 2, \"comment_y_mm\": 248.2, \"printed_number\": 14, \"comment_source_page\": 2}, \"protocol_15\": {\"key\": \"protocol_15\", \"text\": \"Are there any other concerns in the study?\", \"answer_y_mm\": 74, \"source_page\": 3, \"comment_y_mm\": 83.5, \"printed_number\": 15, \"comment_source_page\": 3}}, \"answers\": {\"no\": \"No\", \"yes\": \"Yes\", \"unable_to_assess\": \"Unable to Assess\"}, \"template\": {\"sha256\": \"7231e839ed75dc8d3977f84c35d62c552b5b1919b4065efa412dbd122c960f16\", \"version\": \"rems-review-forms-7231e839-v1\", \"source_pages\": [1, 2, 3], \"generator_version\": \"ecrats-fpdi-2-final-review\"}, \"form_code\": \"KLD-RES-04-001\", \"form_type\": \"protocol\", \"questions\": {\"protocol_01\": \"Does study have social value? (e.g. scientific value, relevance to national /community needs)\", \"protocol_02\": \"Is the study background adequate?\", \"protocol_03\": \"Are the research questions supported by the Review of Literature?\", \"protocol_04\": \"(For pure Qualitative) Are the study objectives having Credibility, Transferability, Dependability, Feasibility, Flexibility, Confirmability? (For pure Quantitative, systematic literature reviews (SLRs), and others) Are the study objectives Specific, Measurable, Attainable, Realistic, Time-bound?\", \"protocol_05\": \"Is the research design appropriate? Is the population identified and defined? Is the selection of study participants described? Is the sample size justified? Is the plan for data analysis described? Are there dummy tables?\", \"protocol_06\": \"Does the research need to be carried out with human participants?\", \"protocol_07\": \"Does the study have a vulnerability issue?\", \"protocol_08\": \"Are appropriate mechanisms/interventions in place to address the vulnerability issue/s?\", \"protocol_09\": \"Are there risks/probable harms to the human participants in the study?\", \"protocol_10\": \"Are there measures to mitigate the risks?\", \"protocol_11\": \"Is the informed consent procedure/ form and culturally appropriate?\", \"protocol_12\": \"Is/are the investigator/s adequately trained and do they have sufficient experience to undertake the study?\", \"protocol_13\": \"Is there a disclosure of conflict of interest?\", \"protocol_14\": \"Are the research facilities adequate?\", \"protocol_15\": \"Are there any other concerns in the study?\"}, \"form_label\": \"Protocol Review Worksheet\"}}, {\"id\": 2, \"context\": {\"attestation\": {\"method\": \"authenticated_electronic_attestation\", \"version\": 1, \"statement\": \"The authenticated reviewer finalized this response as their official review record.\", \"actor_name\": \"Reviewer Test\", \"attested_at\": \"2026-08-17T17:17:30+08:00\", \"actor_user_id\": 4}, \"institution\": \"Institute of Behavioral Sciences\", \"received_at\": \"2026-08-17T17:14:28+08:00\", \"review_date\": \"08/17/26\", \"finalized_at\": \"2026-08-17T17:17:30+08:00\", \"received_date\": \"08/17/26\", \"reviewer_name\": \"Reviewer Test\", \"application_id\": 1, \"research_title\": \"test1\", \"proponent_label\": \"WITHHELD - BLIND REVIEW\", \"application_code\": \"RES-2026-S-IBS-08172026-J5TVHA\", \"reviewer_user_id\": 4, \"assignment_sequence\": 1, \"review_classification\": \"Expedited\", \"primary_reviewer_label\": \"Not designated in ECRATS\", \"reviewer_assignment_id\": 1}, \"payload\": {\"responses\": null, \"recommendation\": \"minor_revision\", \"consent_required\": false, \"recommendation_comments\": \"test1test1test1test1\", \"consent_not_required_explanation\": \"test1test1test1\"}, \"form_type\": \"informed_consent\", \"catalog_version\": \"rems-review-forms-7231e839-v1\", \"catalog_snapshot\": {\"items\": {\"consent_01\": {\"key\": \"consent_01\", \"text\": \"Purpose of the study?\", \"answer_y_mm\": 157.6, \"source_page\": 7}, \"consent_02\": {\"key\": \"consent_02\", \"text\": \"Expected duration of participation?\", \"answer_y_mm\": 163.1, \"source_page\": 7}, \"consent_03\": {\"key\": \"consent_03\", \"text\": \"Procedures to be carried out?\", \"answer_y_mm\": 169.8, \"source_page\": 7}, \"consent_04\": {\"key\": \"consent_04\", \"text\": \"Discomforts and inconveniences?\", \"answer_y_mm\": 175.3, \"source_page\": 7}, \"consent_05\": {\"key\": \"consent_05\", \"text\": \"Risks (including possible discrimination)?\", \"answer_y_mm\": 180.8, \"source_page\": 7}, \"consent_06\": {\"key\": \"consent_06\", \"text\": \"Random assignment to the trial treatments?\", \"answer_y_mm\": 186.3, \"source_page\": 7}, \"consent_07\": {\"key\": \"consent_07\", \"text\": \"Benefits to the participants?\", \"answer_y_mm\": 191.8, \"source_page\": 7}, \"consent_08\": {\"key\": \"consent_08\", \"text\": \"Alternative treatments/ procedures?\", \"answer_y_mm\": 198, \"source_page\": 7}, \"consent_09\": {\"key\": \"consent_09\", \"text\": \"Compensation and/or medical treatments in case of injury?\", \"answer_y_mm\": 203.5, \"source_page\": 7}, \"consent_10\": {\"key\": \"consent_10\", \"text\": \"Who to contact for pertinent questions and/ or for assistance in a research- related injury?\", \"answer_y_mm\": 210.2, \"source_page\": 7}, \"consent_11\": {\"key\": \"consent_11\", \"text\": \"Refusal to participate or discontinuance at any time will involve penalty or loss of benefits to which the subject is entitled?\", \"answer_y_mm\": 221, \"source_page\": 7}, \"consent_12\": {\"key\": \"consent_12\", \"text\": \"Extent of confidentiality?\", \"answer_y_mm\": 236.7, \"source_page\": 7}, \"consent_13\": {\"key\": \"consent_13\", \"text\": \"Is the informed consent written or presented in simple language that participants can understand?\", \"answer_y_mm\": 71.7, \"source_page\": 8}, \"consent_14\": {\"key\": \"consent_14\", \"text\": \"Does the protocol include an adequate process for ensuring that consent is voluntary?\", \"answer_y_mm\": 82.4, \"source_page\": 8}, \"consent_15\": {\"key\": \"consent_15\", \"text\": \"Do you have any other concerns?\", \"answer_y_mm\": 95.4, \"source_page\": 8}}, \"answers\": {\"no\": \"No\", \"yes\": \"Yes\"}, \"template\": {\"sha256\": \"7231e839ed75dc8d3977f84c35d62c552b5b1919b4065efa412dbd122c960f16\", \"version\": \"rems-review-forms-7231e839-v1\", \"source_pages\": [7, 8], \"generator_version\": \"ecrats-fpdi-2-final-review\"}, \"form_code\": \"KLD-RES-04-002\", \"form_type\": \"informed_consent\", \"questions\": {\"consent_01\": \"Purpose of the study?\", \"consent_02\": \"Expected duration of participation?\", \"consent_03\": \"Procedures to be carried out?\", \"consent_04\": \"Discomforts and inconveniences?\", \"consent_05\": \"Risks (including possible discrimination)?\", \"consent_06\": \"Random assignment to the trial treatments?\", \"consent_07\": \"Benefits to the participants?\", \"consent_08\": \"Alternative treatments/ procedures?\", \"consent_09\": \"Compensation and/or medical treatments in case of injury?\", \"consent_10\": \"Who to contact for pertinent questions and/ or for assistance in a research- related injury?\", \"consent_11\": \"Refusal to participate or discontinuance at any time will involve penalty or loss of benefits to which the subject is entitled?\", \"consent_12\": \"Extent of confidentiality?\", \"consent_13\": \"Is the informed consent written or presented in simple language that participants can understand?\", \"consent_14\": \"Does the protocol include an adequate process for ensuring that consent is voluntary?\", \"consent_15\": \"Do you have any other concerns?\"}, \"form_label\": \"Informed Consent Checklist\"}}], \"comments\": [{\"id\": 1, \"body\": \"test1\", \"scope\": \"overall\", \"status\": \"open\", \"category\": \"general\", \"created_at\": \"2026-08-17 17:15:36\", \"updated_at\": \"2026-08-17 17:18:04\", \"page_number\": null, \"application_document_id\": null}, {\"id\": 2, \"body\": \"test1\", \"scope\": \"document\", \"status\": \"open\", \"category\": \"required_revision\", \"created_at\": \"2026-08-17 17:15:45\", \"updated_at\": \"2026-08-17 17:18:04\", \"page_number\": null, \"application_document_id\": 2}], \"decision\": \"approved\", \"artifacts\": [{\"id\": 1, \"sha256\": \"14850e449e2c4f0399f7a6119e37176b1960900d6ac9a79d83e44edb9c433972\", \"artifact_version\": 1}, {\"id\": 2, \"sha256\": \"517ea3285dadf531153e69ea38bb8eeeb536371b64c1848f467c3ebd44a20d0c\", \"artifact_version\": 1}], \"review_type\": \"initial_review\", \"review_cycle\": 0, \"assignment_id\": 1, \"schema_version\": 1, \"decision_comment\": \"test1test1test1test1\"}','400d38c0a5982ed52e897d8e4d8f5898b0efc91e9d72055bf0d3c9d615debe7c','400d38c0a5982ed52e897d8e4d8f5898b0efc91e9d72055bf0d3c9d615debe7c',4,'2026-08-17 09:17:30','2026-08-17 09:17:30','2026-08-17 09:17:30');
/*!40000 ALTER TABLE `review_submission_versions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `review_submissions`
--

DROP TABLE IF EXISTS `review_submissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `review_submissions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `reviewer_assignment_id` bigint unsigned NOT NULL,
  `current_version_id` bigint unsigned DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `decision` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `decision_comment` text COLLATE utf8mb4_unicode_ci,
  `draft_decision` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `draft_decision_comment` text COLLATE utf8mb4_unicode_ci,
  `has_unsubmitted_changes` tinyint(1) NOT NULL DEFAULT '0',
  `submitted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `review_submissions_reviewer_assignment_id_unique` (`reviewer_assignment_id`),
  KEY `review_submissions_status_index` (`status`),
  KEY `review_submissions_submitted_at_index` (`submitted_at`),
  KEY `review_submissions_has_unsubmitted_changes_index` (`has_unsubmitted_changes`),
  KEY `review_submission_current_version_fk` (`current_version_id`),
  CONSTRAINT `review_submission_current_version_fk` FOREIGN KEY (`current_version_id`) REFERENCES `review_submission_versions` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `review_submissions_reviewer_assignment_id_foreign` FOREIGN KEY (`reviewer_assignment_id`) REFERENCES `reviewer_assignments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `review_submissions`
--

LOCK TABLES `review_submissions` WRITE;
/*!40000 ALTER TABLE `review_submissions` DISABLE KEYS */;
INSERT INTO `review_submissions` VALUES (1,1,1,'submitted','approved','test1test1test1test1','approved','test1test1test1test1',0,'2026-08-17 09:17:30','2026-08-17 09:17:30','2026-08-17 09:17:30');
/*!40000 ALTER TABLE `review_submissions` ENABLE KEYS */;
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
  `review_cycle` smallint unsigned NOT NULL DEFAULT '0',
  `assignment_sequence` int unsigned NOT NULL DEFAULT '1',
  `replaces_assignment_id` bigint unsigned DEFAULT NULL,
  `assignment_status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `assigned_at` timestamp NULL DEFAULT NULL,
  `review_deadline_at` timestamp NULL DEFAULT NULL,
  `submitted_at` timestamp NULL DEFAULT NULL,
  `superseded_at` timestamp NULL DEFAULT NULL,
  `superseded_by_user_id` bigint unsigned DEFAULT NULL,
  `supersession_reason` text COLLATE utf8mb4_unicode_ci,
  `superseded_from_status` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `reviewer_application_type_sequence_unique` (`research_application_id`,`reviewer_user_id`,`review_type`,`assignment_sequence`),
  KEY `reviewer_assignments_reviewer_user_id_assignment_status_index` (`reviewer_user_id`,`assignment_status`),
  KEY `reviewer_assignments_assignment_status_index` (`assignment_status`),
  KEY `reviewer_assignments_review_deadline_at_index` (`review_deadline_at`),
  KEY `reviewer_assignments_application_fk_index` (`research_application_id`),
  KEY `reviewer_assignments_replaces_assignment_id_foreign` (`replaces_assignment_id`),
  KEY `reviewer_assignments_superseded_at_index` (`superseded_at`),
  KEY `reviewer_assignments_superseded_by_user_id_foreign` (`superseded_by_user_id`),
  KEY `reviewer_assignment_current_set_index` (`research_application_id`,`review_type`,`superseded_at`),
  KEY `reviewer_assignment_cycle_index` (`research_application_id`,`review_cycle`,`assignment_status`),
  CONSTRAINT `reviewer_assignments_replaces_assignment_id_foreign` FOREIGN KEY (`replaces_assignment_id`) REFERENCES `reviewer_assignments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `reviewer_assignments_research_application_id_foreign` FOREIGN KEY (`research_application_id`) REFERENCES `research_applications` (`id`) ON DELETE CASCADE,
  CONSTRAINT `reviewer_assignments_reviewer_user_id_foreign` FOREIGN KEY (`reviewer_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `reviewer_assignments_superseded_by_user_id_foreign` FOREIGN KEY (`superseded_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reviewer_assignments`
--

LOCK TABLES `reviewer_assignments` WRITE;
/*!40000 ALTER TABLE `reviewer_assignments` DISABLE KEYS */;
INSERT INTO `reviewer_assignments` VALUES (1,1,4,'initial_review',0,1,NULL,'decision_submitted','2026-08-17 09:14:28','2026-08-31 09:05:00','2026-08-17 09:17:30',NULL,NULL,NULL,NULL,'2026-08-17 09:14:28','2026-08-17 09:17:32');
/*!40000 ALTER TABLE `reviewer_assignments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reviewer_conflicts`
--

DROP TABLE IF EXISTS `reviewer_conflicts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reviewer_conflicts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `research_application_id` bigint unsigned NOT NULL,
  `reviewer_user_id` bigint unsigned NOT NULL,
  `declared_by_user_id` bigint unsigned DEFAULT NULL,
  `reason` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `declared_at` timestamp NOT NULL,
  `cleared_by_user_id` bigint unsigned DEFAULT NULL,
  `cleared_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `reviewer_conflicts_application_reviewer_unique` (`research_application_id`,`reviewer_user_id`),
  KEY `reviewer_conflicts_declared_by_user_id_foreign` (`declared_by_user_id`),
  KEY `reviewer_conflicts_cleared_by_user_id_foreign` (`cleared_by_user_id`),
  KEY `reviewer_conflicts_reviewer_active_index` (`reviewer_user_id`,`cleared_at`),
  CONSTRAINT `reviewer_conflicts_cleared_by_user_id_foreign` FOREIGN KEY (`cleared_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `reviewer_conflicts_declared_by_user_id_foreign` FOREIGN KEY (`declared_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `reviewer_conflicts_research_application_id_foreign` FOREIGN KEY (`research_application_id`) REFERENCES `research_applications` (`id`) ON DELETE CASCADE,
  CONSTRAINT `reviewer_conflicts_reviewer_user_id_foreign` FOREIGN KEY (`reviewer_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reviewer_conflicts`
--

LOCK TABLES `reviewer_conflicts` WRITE;
/*!40000 ALTER TABLE `reviewer_conflicts` DISABLE KEYS */;
/*!40000 ALTER TABLE `reviewer_conflicts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reviewer_identity_reconciliations`
--

DROP TABLE IF EXISTS `reviewer_identity_reconciliations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reviewer_identity_reconciliations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `source_user_id` bigint unsigned NOT NULL,
  `target_adviser_user_id` bigint unsigned NOT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `matched_fields` json NOT NULL,
  `reason` text COLLATE utf8mb4_unicode_ci,
  `resolution_notes` text COLLATE utf8mb4_unicode_ci,
  `resolved_by_user_id` bigint unsigned DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `reviewer_reconciliation_pair_unique` (`source_user_id`,`target_adviser_user_id`),
  KEY `reviewer_reconciliation_target_fk` (`target_adviser_user_id`),
  KEY `reviewer_reconciliation_resolver_fk` (`resolved_by_user_id`),
  KEY `reviewer_identity_reconciliations_status_index` (`status`),
  CONSTRAINT `reviewer_reconciliation_resolver_fk` FOREIGN KEY (`resolved_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `reviewer_reconciliation_source_fk` FOREIGN KEY (`source_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `reviewer_reconciliation_target_fk` FOREIGN KEY (`target_adviser_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reviewer_identity_reconciliations`
--

LOCK TABLES `reviewer_identity_reconciliations` WRITE;
/*!40000 ALTER TABLE `reviewer_identity_reconciliations` DISABLE KEYS */;
/*!40000 ALTER TABLE `reviewer_identity_reconciliations` ENABLE KEYS */;
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
INSERT INTO `sessions` VALUES ('s8PiKNtyGuqVAN4ihXQcXrsJEEsTziauu7BpHAsf',NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','eyJfdG9rZW4iOiJhOU1IYzhlelZsNzVuVDhNUlZ0WXl2VUJxSzBrSWk0QjA1Z3B5dVJZIiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cLzEyNy4wLjAuMTo4MDAwIiwicm91dGUiOiJob21lIn0sIl9mbGFzaCI6eyJvbGQiOltdLCJuZXciOltdfX0=',1787477714);
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
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `timeline_calendar_events`
--

LOCK TABLES `timeline_calendar_events` WRITE;
/*!40000 ALTER TABLE `timeline_calendar_events` DISABLE KEYS */;
INSERT INTO `timeline_calendar_events` VALUES (1,1,'term-1-submission','Submission of Application','1st Semester, A.Y. 2026-2027','2026-08-17 09:07:00','2026-08-31 09:05:00',0,1,'2026-08-17 09:06:23','2026-08-17 09:06:23'),(2,1,'term-1-endorsement','Endorsement Period','1st Semester, A.Y. 2026-2027','2026-08-17 09:07:00','2026-08-31 09:05:00',1,1,'2026-08-17 09:06:23','2026-08-17 09:06:23'),(3,1,'term-1-res-screening','RES Screening','1st Semester, A.Y. 2026-2027','2026-08-17 09:07:00','2026-08-31 09:05:00',2,1,'2026-08-17 09:06:23','2026-08-17 09:06:23'),(4,1,'term-1-reviewing','Reviewing Period','1st Semester, A.Y. 2026-2027','2026-08-17 09:07:00','2026-08-31 09:05:00',3,1,'2026-08-17 09:06:23','2026-08-17 09:06:23'),(5,1,'term-1-revision','Revision Period','1st Semester, A.Y. 2026-2027','2026-08-17 09:07:00','2026-08-31 09:06:00',4,1,'2026-08-17 09:06:23','2026-08-17 09:06:23'),(6,1,'term-1-reviewing-revision','Reviewing of Revision Period','1st Semester, A.Y. 2026-2027','2026-08-17 09:07:00','2026-08-31 09:06:00',5,1,'2026-08-17 09:06:23','2026-08-17 09:06:23');
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
  `expected_endorsement_count` int unsigned DEFAULT NULL,
  `certificate_signatory_name` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `certificate_valid_until` date DEFAULT NULL,
  `certificate_signature_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `certificate_signature_sha256` char(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `certificate_signature_width` int unsigned DEFAULT NULL,
  `certificate_signature_height` int unsigned DEFAULT NULL,
  `certificate_signature_uploaded_at` timestamp NULL DEFAULT NULL,
  `certificate_qr_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `certificate_qr_sha256` char(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `certificate_qr_width` int unsigned DEFAULT NULL,
  `certificate_qr_height` int unsigned DEFAULT NULL,
  `certificate_qr_uploaded_at` timestamp NULL DEFAULT NULL,
  `worksheet_signatory_name` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `worksheet_signature_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `worksheet_signature_sha256` char(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `worksheet_signature_width` int unsigned DEFAULT NULL,
  `worksheet_signature_height` int unsigned DEFAULT NULL,
  `worksheet_signature_uploaded_at` timestamp NULL DEFAULT NULL,
  `reviewer_classification` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reviewer_classifications` json DEFAULT NULL,
  `reviewer_capacity` smallint unsigned DEFAULT NULL,
  `role` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'student_faculty_researcher',
  `applicant_type` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `account_status` varchar(25) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `reviewer_enabled` tinyint(1) NOT NULL DEFAULT '0',
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
  KEY `users_reviewer_entitlement_index` (`role`,`account_status`,`reviewer_enabled`),
  CONSTRAINT `users_created_by_user_id_foreign` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'RES Lead','RES',NULL,'Lead',NULL,'reslead','reslead@ecrats.test','KLD-RES-001',NULL,'Kolehiyo ng Lungsod ng Dasmarinas','Research Ethics Section',NULL,NULL,'RES Lead',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'res_lead',NULL,'active',0,NULL,NULL,'$2y$12$XEYDxNzNn8rbNOo8DgYu.u2vcQssJXNQ2YvnO1bTyOLonmukDMhkC',NULL,'2026-08-22 07:05:59','2026-08-22 07:05:59','2026-08-22 07:05:59','not_required',NULL,NULL,'2026-08-17 09:03:08','2026-08-22 07:06:01',NULL),(2,'Applicant Test','Applicant',NULL,'Test',NULL,'applicanttest','applicanttest@ecrats.test','KLD-STU-001',NULL,'Kolehiyo ng Lungsod ng Dasmarinas',NULL,'Bachelor of Science in Computer Science','First Year',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'student_faculty_researcher','student','active',0,NULL,NULL,'$2y$12$PbJi10wy2.bIjVvNdu9PGOaWs8bG1tYoPuXpHXd3VdQLrlGMGMlje',NULL,'2026-08-22 07:06:01','2026-08-22 07:06:01','2026-08-22 07:06:01','not_required',NULL,NULL,'2026-08-17 09:03:08','2026-08-22 07:06:01',NULL),(3,'Adviser Test','Adviser',NULL,'Test',NULL,'advisertest','advisertest@ecrats.test','KLD-EMP-001',NULL,'Kolehiyo ng Lungsod ng Dasmarinas',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'adviser',NULL,'active',0,NULL,NULL,'$2y$12$AxSbhxCtBtFMDECIGtcHyex1RG7DHYFBcPSZttJhuCB6l8ScKp5dG',NULL,'2026-08-22 07:06:02','2026-08-22 07:06:02','2026-08-22 07:06:02','not_required',NULL,NULL,'2026-08-17 09:03:09','2026-08-22 07:06:02',NULL),(4,'Reviewer Test','Reviewer',NULL,'Test',NULL,'reviewertest','reviewertest@ecrats.test','KLD-EMP-002',NULL,'Kolehiyo ng Lungsod ng Dasmarinas','Computer Studies',NULL,NULL,'Ethics Reviewer',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Expedited','[\"Expedited\"]',6,'adviser',NULL,'active',1,NULL,NULL,'$2y$12$fDzerOAJWI5FXR4op0T7/.u1oStfP9CTIva//ZZuQfrS52.hDhLmW',NULL,'2026-08-22 07:06:02','2026-08-22 07:06:02','2026-08-22 07:06:02','not_required',NULL,NULL,'2026-08-17 09:03:09','2026-08-22 07:06:02',NULL);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `workflow_drafts`
--

DROP TABLE IF EXISTS `workflow_drafts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `workflow_drafts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `research_application_id` bigint unsigned NOT NULL,
  `workflow` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` json NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `workflow_drafts_owner_unique` (`user_id`,`research_application_id`,`workflow`),
  KEY `workflow_drafts_application_index` (`research_application_id`,`workflow`),
  CONSTRAINT `workflow_drafts_research_application_id_foreign` FOREIGN KEY (`research_application_id`) REFERENCES `research_applications` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `workflow_drafts_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `workflow_drafts`
--

LOCK TABLES `workflow_drafts` WRITE;
/*!40000 ALTER TABLE `workflow_drafts` DISABLE KEYS */;
/*!40000 ALTER TABLE `workflow_drafts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping events for database 'ecrats_recovery_20260823'
--

--
-- Dumping routines for database 'ecrats_recovery_20260823'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-08-23 18:32:20
