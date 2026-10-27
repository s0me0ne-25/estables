/*M!999999\- enable the sandbox mode */
-- MariaDB dump 10.20-11.8.9-MariaDB, for debian-linux-gnu (x86_64)
--
-- Host: localhost    Database: estables_demo
-- ------------------------------------------------------
-- Server version	11.8.9-MariaDB-ubu2404

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*M!100616 SET @OLD_NOTE_VERBOSITY=@@NOTE_VERBOSITY, NOTE_VERBOSITY=0 */;

--
-- Table structure for table `attribute_names`
--

DROP TABLE IF EXISTS `attribute_names`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `attribute_names` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_attribute_names` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=78 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `attribute_names`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `attribute_names` WRITE;
/*!40000 ALTER TABLE `attribute_names` DISABLE KEYS */;
INSERT INTO `attribute_names` VALUES (42,'Автор модели');
INSERT INTO `attribute_names` VALUES (46,'Встречаемость');
INSERT INTO `attribute_names` VALUES (3,'Высота');
INSERT INTO `attribute_names` VALUES (74,'Диаметр');
INSERT INTO `attribute_names` VALUES (65,'Длина');
INSERT INTO `attribute_names` VALUES (4,'Клеймо');
INSERT INTO `attribute_names` VALUES (76,'Количество');
INSERT INTO `attribute_names` VALUES (75,'Количество марок');
INSERT INTO `attribute_names` VALUES (2,'Материал');
INSERT INTO `attribute_names` VALUES (1,'Производитель');
INSERT INTO `attribute_names` VALUES (77,'Размер');
INSERT INTO `attribute_names` VALUES (60,'Страна');
/*!40000 ALTER TABLE `attribute_names` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `audit_log`
--

DROP TABLE IF EXISTS `audit_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `audit_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `created_at` datetime(3) NOT NULL,
  `user_id` int(10) unsigned DEFAULT NULL,
  `actor` varchar(100) NOT NULL,
  `ip` varchar(45) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  `action` varchar(40) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  `object_id` int(10) unsigned DEFAULT NULL,
  `subject` varchar(500) NOT NULL,
  `details` text NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_audit_created` (`created_at`),
  KEY `ix_audit_user` (`user_id`,`created_at`),
  KEY `ix_audit_action` (`action`,`created_at`),
  CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_log`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `audit_log` WRITE;
/*!40000 ALTER TABLE `audit_log` DISABLE KEYS */;
INSERT INTO `audit_log` VALUES (1,'2026-10-20 06:58:00.000',1,'admin','203.0.113.24','login',1,'admin','');
INSERT INTO `audit_log` VALUES (2,'2026-10-20 07:05:00.000',1,'admin','203.0.113.24','rubric.update',4,'Ленинградский завод','описание');
INSERT INTO `audit_log` VALUES (3,'2026-10-20 07:20:00.000',1,'admin','203.0.113.24','user.block',3,'archive','');
INSERT INTO `audit_log` VALUES (4,'2026-10-24 15:40:00.000',2,'ekovaleva','203.0.113.81','login',2,'ekovaleva','');
INSERT INTO `audit_log` VALUES (5,'2026-10-24 15:52:00.000',2,'ekovaleva','203.0.113.81','item.photo',11,'ЛФЗ-1042 Конь вздыбленный','lfz-1042-d.jpg: JPEG, 1600 × 1200, 41 КБ');
INSERT INTO `audit_log` VALUES (6,'2026-10-24 16:05:00.000',2,'ekovaleva','203.0.113.81','item.update',11,'ЛФЗ-1042 Конь вздыбленный','характеристики; подписи к фото');
INSERT INTO `audit_log` VALUES (7,'2026-10-25 19:12:00.000',1,'admin','198.51.100.7','items.unpublish',NULL,'1 предмет','ЛФЗ-1071 Конь с золочением');
INSERT INTO `audit_log` VALUES (8,'2026-10-25 19:20:00.000',1,'admin','198.51.100.7','item.order',12,'ЛФЗ-1039 Тройка, настольная композиция','в разделе «Ленинградский завод»');
INSERT INTO `audit_log` VALUES (9,'2026-10-25 19:25:00.000',1,'admin','198.51.100.7','site.maintenance-on',NULL,'Режим техработ','');
INSERT INTO `audit_log` VALUES (10,'2026-10-25 19:31:00.000',1,'admin','198.51.100.7','site.maintenance-off',NULL,'Режим техработ','');
/*!40000 ALTER TABLE `audit_log` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `images`
--

DROP TABLE IF EXISTS `images`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `images` (
  `sha256` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `ext` varchar(4) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `width` int(10) unsigned NOT NULL,
  `height` int(10) unsigned NOT NULL,
  `bytes` bigint(20) unsigned NOT NULL,
  `frames` int(10) unsigned NOT NULL DEFAULT 1,
  `source_format` varchar(16) NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`sha256`),
  KEY `ix_images_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `images`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `images` WRITE;
/*!40000 ALTER TABLE `images` DISABLE KEYS */;
INSERT INTO `images` VALUES ('006a370e59a6b23023887b7cb3c2b65f3eab5944d1872c56a5fa0c7809cd9e9c','jpg',1200,1600,42035,1,'JPEG','2026-10-27 14:58:47');
INSERT INTO `images` VALUES ('0a1732b46b5902dc14eebaf814794749fecfbd1d8700e92cb22f776fb52bf011','jpg',1200,1600,42897,1,'JPEG','2026-10-27 14:58:48');
INSERT INTO `images` VALUES ('160eea34b70cf282d4a6161c3f028093d3c233dcdafdf2b894b70a08aa3e7728','jpg',1600,1200,41015,1,'JPEG','2026-10-27 14:58:48');
INSERT INTO `images` VALUES ('20ee347a308544cb44baebab38a0340c7d0708da63137725b4062fb9be5e8217','jpg',1200,1600,35840,1,'JPEG','2026-10-27 14:58:48');
INSERT INTO `images` VALUES ('2dd24a2fe5b2440ef15dd2f7132371c19cb171909dfa93451dffa685e6d84ac7','jpg',1200,1600,41865,1,'JPEG','2026-10-27 14:58:48');
INSERT INTO `images` VALUES ('307bf2deaec53e2824c92356a50ff69e2cfcfa0f867bd68a613abc93d1f37b4a','jpg',1600,1200,37058,1,'JPEG','2026-10-27 14:58:47');
INSERT INTO `images` VALUES ('32dec55ff22211899d8ed63d60c191dc5a95b6887194a345a06ea681c166396b','jpg',1600,1200,35754,1,'JPEG','2026-10-27 14:58:48');
INSERT INTO `images` VALUES ('341cb61b5de6da378cc667d1f4c8b586d276bb76ccfb991d174e31cfd4cb7039','jpg',1600,1200,37424,1,'JPEG','2026-10-27 14:58:47');
INSERT INTO `images` VALUES ('35d50e2a8441bd45818c7d0e968da2c13bc4ecc2b58c059050b46d900dda0eab','jpg',1600,1200,41912,1,'JPEG','2026-10-27 14:58:47');
INSERT INTO `images` VALUES ('4f22dc4409d5bb5bc39a72b750cebaa4dc712160450a16fdf0b6ee51be4e58b6','jpg',1600,1200,41152,1,'JPEG','2026-10-27 14:58:48');
INSERT INTO `images` VALUES ('56d15e1158890b3923772b6f94c7b6795da33cded656225e20319e8d76a14763','jpg',1600,1200,41680,1,'JPEG','2026-10-27 14:58:47');
INSERT INTO `images` VALUES ('6271c0b3595537b19faa77b28dc472c9fefa4d95059cef517bece31a58141acd','jpg',1200,1600,37243,1,'JPEG','2026-10-27 14:58:47');
INSERT INTO `images` VALUES ('62871cd75aa6927530faa76913150563357b44b4138b0698f78897de2e23a0ff','jpg',1600,1200,40448,1,'JPEG','2026-10-27 14:58:48');
INSERT INTO `images` VALUES ('68b6b84281bb6cff0f747e1e11ad53158668ec01a9f508af5386db4f76fa9bfb','jpg',1600,1200,40922,1,'JPEG','2026-10-27 14:58:48');
INSERT INTO `images` VALUES ('700d2c2f06fcaad97b5eea7bf3d9911b2ab72ad664d5b498ac82dde74f65f6b7','jpg',1600,1200,42714,1,'JPEG','2026-10-27 14:58:47');
INSERT INTO `images` VALUES ('71238c4eb541109e4d70a328236985a65127e95f4aab131669a101ace6e7092a','jpg',1600,1200,37352,1,'JPEG','2026-10-27 14:58:47');
INSERT INTO `images` VALUES ('72eaa25c82ab92e1f37f250b742cdeebb107e57fedd5da0f82f34f144de303ec','jpg',1200,1600,41634,1,'JPEG','2026-10-27 14:58:48');
INSERT INTO `images` VALUES ('81ef76419906573c34ec3ff0fa8424f6b3dc2df288cd1d0774601dd30f87b399','jpg',1200,1600,42798,1,'JPEG','2026-10-27 14:58:47');
INSERT INTO `images` VALUES ('82ba2034d5f7902a32b066484f1c35c492383caa0cd5bf0b6a79439ac8f6c274','jpg',1600,1200,42355,1,'JPEG','2026-10-27 14:58:48');
INSERT INTO `images` VALUES ('94ce666ae1331cfe01f2990cce83433d089dbeacf4c628db2fd60f944beea59d','jpg',1200,1600,42181,1,'JPEG','2026-10-27 14:58:48');
INSERT INTO `images` VALUES ('96b88fab86ac6421a557028caa97f7ca9e352c1c2f6325f617cdf1cc6c6ebe49','jpg',1200,1600,44533,1,'JPEG','2026-10-27 14:58:47');
INSERT INTO `images` VALUES ('a419aca12a6cdc6108d50992400fc937f61e24f04f22f1569c1c1085f9b844e4','jpg',1200,1600,37146,1,'JPEG','2026-10-27 14:58:47');
INSERT INTO `images` VALUES ('a713091b0f7ef3b974fa3b48064385386aaccbf799ece918c035b81e99035cab','jpg',1600,1200,35621,1,'JPEG','2026-10-27 14:58:47');
INSERT INTO `images` VALUES ('ae3a39f9a0deb26e2a9c2692512e401e128d75272730cc5961339e88106d3761','jpg',1600,1200,44105,1,'JPEG','2026-10-27 14:58:47');
INSERT INTO `images` VALUES ('bd0516dd3589319ec0d46dc891b608e27bce08096b304798cf556d852fb237bc','jpg',1200,1600,45270,1,'JPEG','2026-10-27 14:58:47');
INSERT INTO `images` VALUES ('c0a144f2e3c6263e60b1b023bcdba727fab616d2897d5758d82bdc40f528296a','jpg',1600,1200,42869,1,'JPEG','2026-10-27 14:58:47');
INSERT INTO `images` VALUES ('c1d3266049bbd4bee7d8ee86759ffa0d35f86b55b37750647473f56b709e4ca8','jpg',1200,1600,35420,1,'JPEG','2026-10-27 14:58:47');
INSERT INTO `images` VALUES ('c37974932070a42be8ac4462c73a805f2188c2a494fa7f22210722c54e55255a','jpg',1600,1200,43306,1,'JPEG','2026-10-27 14:58:47');
INSERT INTO `images` VALUES ('d2e0dd508453b2dd6c8e3eee80d1d9d4ad59980ed315bac8bc178dde1bba1c81','jpg',1600,1200,42093,1,'JPEG','2026-10-27 14:58:47');
INSERT INTO `images` VALUES ('e09ca7800a3ebf868691f26441184105445ea1c93ebc9468c5d4b37901012d44','jpg',1200,1600,45779,1,'JPEG','2026-10-27 14:58:47');
INSERT INTO `images` VALUES ('e5f812821f03cb3a4fe8bddc6f34c6ecc41a630b7eca7628116d53f9171b40e6','jpg',1600,1200,41697,1,'JPEG','2026-10-27 14:58:48');
INSERT INTO `images` VALUES ('f4819f0bdefcb824d473fb0808c3b163fd14a6732bdd5193fdaf407656276a13','jpg',1600,1200,43966,1,'JPEG','2026-10-27 14:58:47');
INSERT INTO `images` VALUES ('fa906ef263be6bd39525bbb422bd727ab316c9433f88337e8ed14d1a78f66683','jpg',1600,1200,43624,1,'JPEG','2026-10-27 14:58:47');
INSERT INTO `images` VALUES ('fb67412202f36e6e962442be691e48dd5f37765612f56856cf5b52434df3008f','jpg',1600,1200,40533,1,'JPEG','2026-10-27 14:58:47');
INSERT INTO `images` VALUES ('fe2f1800749018c98d379f7fb7a3b45e9e894d6283a4b6830128cae7e311104e','jpg',1600,1200,41683,1,'JPEG','2026-10-27 14:58:47');
/*!40000 ALTER TABLE `images` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `item_attributes`
--

DROP TABLE IF EXISTS `item_attributes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `item_attributes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `item_id` int(10) unsigned NOT NULL,
  `name_id` int(10) unsigned NOT NULL,
  `value` varchar(500) NOT NULL,
  `position` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_item_attributes_item` (`item_id`,`position`),
  KEY `ix_item_attributes_value` (`name_id`,`value`(100)),
  CONSTRAINT `fk_item_attributes_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_item_attributes_name` FOREIGN KEY (`name_id`) REFERENCES `attribute_names` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=78 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `item_attributes`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `item_attributes` WRITE;
/*!40000 ALTER TABLE `item_attributes` DISABLE KEYS */;
INSERT INTO `item_attributes` VALUES (1,1,1,'Ленинградский фарфоровый завод',0);
INSERT INTO `item_attributes` VALUES (2,1,2,'Фарфор, роспись',1);
INSERT INTO `item_attributes` VALUES (3,1,3,'11 см',2);
INSERT INTO `item_attributes` VALUES (4,1,4,'Синее надглазурное',3);
INSERT INTO `item_attributes` VALUES (5,2,1,'Ленинградский фарфоровый завод',0);
INSERT INTO `item_attributes` VALUES (6,2,2,'Фарфор, роспись',1);
INSERT INTO `item_attributes` VALUES (7,2,3,'26 см',2);
INSERT INTO `item_attributes` VALUES (8,2,4,'Синее надглазурное',3);
INSERT INTO `item_attributes` VALUES (9,3,1,'Ленинградский фарфоровый завод',0);
INSERT INTO `item_attributes` VALUES (10,3,2,'Фарфор, надглазурное золото',1);
INSERT INTO `item_attributes` VALUES (11,3,3,'14 см',2);
INSERT INTO `item_attributes` VALUES (12,3,4,'Зелёное подглазурное',3);
INSERT INTO `item_attributes` VALUES (13,4,1,'Ленинградский фарфоровый завод',0);
INSERT INTO `item_attributes` VALUES (14,4,2,'Фарфор, бисквит',1);
INSERT INTO `item_attributes` VALUES (15,4,3,'30 см',2);
INSERT INTO `item_attributes` VALUES (16,4,4,'Зелёное подглазурное',3);
INSERT INTO `item_attributes` VALUES (17,5,1,'Ленинградский фарфоровый завод',0);
INSERT INTO `item_attributes` VALUES (18,5,2,'Фарфор, глазурь',1);
INSERT INTO `item_attributes` VALUES (19,5,3,'17 см',2);
INSERT INTO `item_attributes` VALUES (20,5,4,'Синее надглазурное',3);
INSERT INTO `item_attributes` VALUES (21,6,1,'Ленинградский фарфоровый завод',0);
INSERT INTO `item_attributes` VALUES (22,6,2,'Фарфор, роспись',1);
INSERT INTO `item_attributes` VALUES (23,6,3,'9 см',2);
INSERT INTO `item_attributes` VALUES (24,6,4,'Зелёное подглазурное',3);
INSERT INTO `item_attributes` VALUES (25,7,1,'Ленинградский фарфоровый завод',0);
INSERT INTO `item_attributes` VALUES (26,7,2,'Фарфор, роспись',1);
INSERT INTO `item_attributes` VALUES (27,7,3,'20 см',2);
INSERT INTO `item_attributes` VALUES (28,7,4,'Синее надглазурное',3);
INSERT INTO `item_attributes` VALUES (29,8,1,'Ленинградский фарфоровый завод',0);
INSERT INTO `item_attributes` VALUES (30,8,2,'Фарфор, глазурь',1);
INSERT INTO `item_attributes` VALUES (31,8,3,'19 см',2);
INSERT INTO `item_attributes` VALUES (32,8,4,'Зелёное подглазурное',3);
INSERT INTO `item_attributes` VALUES (33,9,1,'Ленинградский фарфоровый завод',0);
INSERT INTO `item_attributes` VALUES (34,9,2,'Фарфор, роспись',1);
INSERT INTO `item_attributes` VALUES (35,9,3,'22 см',2);
INSERT INTO `item_attributes` VALUES (36,9,4,'Зелёное подглазурное',3);
INSERT INTO `item_attributes` VALUES (37,10,1,'Ленинградский фарфоровый завод',0);
INSERT INTO `item_attributes` VALUES (38,10,2,'Фарфор, глазурь',1);
INSERT INTO `item_attributes` VALUES (39,10,3,'18 см',2);
INSERT INTO `item_attributes` VALUES (40,10,4,'Зелёное подглазурное',3);
INSERT INTO `item_attributes` VALUES (41,11,1,'Ленинградский фарфоровый завод',0);
INSERT INTO `item_attributes` VALUES (42,11,42,'Не установлен',1);
INSERT INTO `item_attributes` VALUES (43,11,2,'Фарфор, бисквит',2);
INSERT INTO `item_attributes` VALUES (44,11,3,'21 см',3);
INSERT INTO `item_attributes` VALUES (45,11,4,'Зелёное подглазурное, тип 3',4);
INSERT INTO `item_attributes` VALUES (46,11,46,'Редко',5);
INSERT INTO `item_attributes` VALUES (47,12,1,'Ленинградский фарфоровый завод',0);
INSERT INTO `item_attributes` VALUES (48,12,2,'Фарфор, роспись',1);
INSERT INTO `item_attributes` VALUES (49,12,3,'23 см',2);
INSERT INTO `item_attributes` VALUES (50,12,4,'Зелёное подглазурное',3);
INSERT INTO `item_attributes` VALUES (51,13,1,'Ленинградский фарфоровый завод',0);
INSERT INTO `item_attributes` VALUES (52,13,2,'Фарфор, роспись',1);
INSERT INTO `item_attributes` VALUES (53,13,3,'24 см',2);
INSERT INTO `item_attributes` VALUES (54,13,4,'Синее надглазурное',3);
INSERT INTO `item_attributes` VALUES (55,14,1,'Дулёвский фарфоровый завод',0);
INSERT INTO `item_attributes` VALUES (56,14,3,'12 см',1);
INSERT INTO `item_attributes` VALUES (57,15,1,'Дулёвский фарфоровый завод',0);
INSERT INTO `item_attributes` VALUES (58,15,3,'19 см',1);
INSERT INTO `item_attributes` VALUES (59,16,1,'Вербилковский фарфоровый завод',0);
INSERT INTO `item_attributes` VALUES (60,17,60,'Дания',0);
INSERT INTO `item_attributes` VALUES (61,17,3,'16 см',1);
INSERT INTO `item_attributes` VALUES (62,18,60,'Австрия',0);
INSERT INTO `item_attributes` VALUES (63,18,3,'22 см',1);
INSERT INTO `item_attributes` VALUES (64,19,2,'Чугун',0);
INSERT INTO `item_attributes` VALUES (65,19,65,'28 см',1);
INSERT INTO `item_attributes` VALUES (66,20,2,'Чугун',0);
INSERT INTO `item_attributes` VALUES (67,20,3,'31 см',1);
INSERT INTO `item_attributes` VALUES (68,21,2,'Бронза, мрамор',0);
INSERT INTO `item_attributes` VALUES (69,21,3,'18 см',1);
INSERT INTO `item_attributes` VALUES (70,22,2,'Пластик',0);
INSERT INTO `item_attributes` VALUES (71,23,2,'Латунь, эмаль',0);
INSERT INTO `item_attributes` VALUES (72,24,2,'Томпак',0);
INSERT INTO `item_attributes` VALUES (73,25,2,'Бронза',0);
INSERT INTO `item_attributes` VALUES (74,25,74,'65 мм',1);
INSERT INTO `item_attributes` VALUES (75,26,75,'5',0);
INSERT INTO `item_attributes` VALUES (76,29,76,'16 открыток',0);
INSERT INTO `item_attributes` VALUES (77,31,77,'60 × 90 см',0);
/*!40000 ALTER TABLE `item_attributes` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `item_photos`
--

DROP TABLE IF EXISTS `item_photos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `item_photos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `item_id` int(10) unsigned NOT NULL,
  `image_sha` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `caption` varchar(500) NOT NULL DEFAULT '',
  `position` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_item_photos` (`item_id`,`image_sha`),
  KEY `ix_item_photos_order` (`item_id`,`position`),
  KEY `ix_item_photos_image` (`image_sha`),
  CONSTRAINT `fk_item_photos_image` FOREIGN KEY (`image_sha`) REFERENCES `images` (`sha256`),
  CONSTRAINT `fk_item_photos_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=36 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `item_photos`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `item_photos` WRITE;
/*!40000 ALTER TABLE `item_photos` DISABLE KEYS */;
INSERT INTO `item_photos` VALUES (1,1,'c0a144f2e3c6263e60b1b023bcdba727fab616d2897d5758d82bdc40f528296a','',0);
INSERT INTO `item_photos` VALUES (2,2,'e09ca7800a3ebf868691f26441184105445ea1c93ebc9468c5d4b37901012d44','',0);
INSERT INTO `item_photos` VALUES (3,3,'700d2c2f06fcaad97b5eea7bf3d9911b2ab72ad664d5b498ac82dde74f65f6b7','',0);
INSERT INTO `item_photos` VALUES (4,4,'6271c0b3595537b19faa77b28dc472c9fefa4d95059cef517bece31a58141acd','',0);
INSERT INTO `item_photos` VALUES (5,5,'341cb61b5de6da378cc667d1f4c8b586d276bb76ccfb991d174e31cfd4cb7039','',0);
INSERT INTO `item_photos` VALUES (6,6,'fa906ef263be6bd39525bbb422bd727ab316c9433f88337e8ed14d1a78f66683','',0);
INSERT INTO `item_photos` VALUES (7,7,'f4819f0bdefcb824d473fb0808c3b163fd14a6732bdd5193fdaf407656276a13','',0);
INSERT INTO `item_photos` VALUES (8,8,'c37974932070a42be8ac4462c73a805f2188c2a494fa7f22210722c54e55255a','',0);
INSERT INTO `item_photos` VALUES (9,9,'ae3a39f9a0deb26e2a9c2692512e401e128d75272730cc5961339e88106d3761','',0);
INSERT INTO `item_photos` VALUES (10,10,'bd0516dd3589319ec0d46dc891b608e27bce08096b304798cf556d852fb237bc','',0);
INSERT INTO `item_photos` VALUES (11,11,'71238c4eb541109e4d70a328236985a65127e95f4aab131669a101ace6e7092a','Вид слева',0);
INSERT INTO `item_photos` VALUES (12,11,'307bf2deaec53e2824c92356a50ff69e2cfcfa0f867bd68a613abc93d1f37b4a','Вид справа',1);
INSERT INTO `item_photos` VALUES (13,11,'a419aca12a6cdc6108d50992400fc937f61e24f04f22f1569c1c1085f9b844e4','Голова крупным планом',2);
INSERT INTO `item_photos` VALUES (14,11,'d2e0dd508453b2dd6c8e3eee80d1d9d4ad59980ed315bac8bc178dde1bba1c81','Клеймо на нижней стороне постамента',3);
INSERT INTO `item_photos` VALUES (15,12,'81ef76419906573c34ec3ff0fa8424f6b3dc2df288cd1d0774601dd30f87b399','',0);
INSERT INTO `item_photos` VALUES (16,13,'96b88fab86ac6421a557028caa97f7ca9e352c1c2f6325f617cdf1cc6c6ebe49','',0);
INSERT INTO `item_photos` VALUES (17,14,'fe2f1800749018c98d379f7fb7a3b45e9e894d6283a4b6830128cae7e311104e','',0);
INSERT INTO `item_photos` VALUES (18,15,'fb67412202f36e6e962442be691e48dd5f37765612f56856cf5b52434df3008f','',0);
INSERT INTO `item_photos` VALUES (19,16,'c1d3266049bbd4bee7d8ee86759ffa0d35f86b55b37750647473f56b709e4ca8','',0);
INSERT INTO `item_photos` VALUES (20,17,'35d50e2a8441bd45818c7d0e968da2c13bc4ecc2b58c059050b46d900dda0eab','',0);
INSERT INTO `item_photos` VALUES (21,18,'006a370e59a6b23023887b7cb3c2b65f3eab5944d1872c56a5fa0c7809cd9e9c','',0);
INSERT INTO `item_photos` VALUES (22,19,'56d15e1158890b3923772b6f94c7b6795da33cded656225e20319e8d76a14763','',0);
INSERT INTO `item_photos` VALUES (23,20,'a713091b0f7ef3b974fa3b48064385386aaccbf799ece918c035b81e99035cab','',0);
INSERT INTO `item_photos` VALUES (24,21,'72eaa25c82ab92e1f37f250b742cdeebb107e57fedd5da0f82f34f144de303ec','',0);
INSERT INTO `item_photos` VALUES (25,22,'4f22dc4409d5bb5bc39a72b750cebaa4dc712160450a16fdf0b6ee51be4e58b6','',0);
INSERT INTO `item_photos` VALUES (26,23,'0a1732b46b5902dc14eebaf814794749fecfbd1d8700e92cb22f776fb52bf011','',0);
INSERT INTO `item_photos` VALUES (27,24,'e5f812821f03cb3a4fe8bddc6f34c6ecc41a630b7eca7628116d53f9171b40e6','',0);
INSERT INTO `item_photos` VALUES (28,25,'62871cd75aa6927530faa76913150563357b44b4138b0698f78897de2e23a0ff','',0);
INSERT INTO `item_photos` VALUES (29,26,'20ee347a308544cb44baebab38a0340c7d0708da63137725b4062fb9be5e8217','',0);
INSERT INTO `item_photos` VALUES (30,27,'82ba2034d5f7902a32b066484f1c35c492383caa0cd5bf0b6a79439ac8f6c274','',0);
INSERT INTO `item_photos` VALUES (31,28,'2dd24a2fe5b2440ef15dd2f7132371c19cb171909dfa93451dffa685e6d84ac7','',0);
INSERT INTO `item_photos` VALUES (32,29,'68b6b84281bb6cff0f747e1e11ad53158668ec01a9f508af5386db4f76fa9bfb','',0);
INSERT INTO `item_photos` VALUES (33,30,'32dec55ff22211899d8ed63d60c191dc5a95b6887194a345a06ea681c166396b','',0);
INSERT INTO `item_photos` VALUES (34,31,'94ce666ae1331cfe01f2990cce83433d089dbeacf4c628db2fd60f944beea59d','',0);
INSERT INTO `item_photos` VALUES (35,32,'160eea34b70cf282d4a6161c3f028093d3c233dcdafdf2b894b70a08aa3e7728','',0);
/*!40000 ALTER TABLE `item_photos` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `item_rubrics`
--

DROP TABLE IF EXISTS `item_rubrics`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `item_rubrics` (
  `item_id` int(10) unsigned NOT NULL,
  `rubric_id` int(10) unsigned NOT NULL,
  `is_main` tinyint(1) NOT NULL DEFAULT 0,
  `position` int(11) NOT NULL,
  `added_at` datetime(6) NOT NULL,
  PRIMARY KEY (`item_id`,`rubric_id`),
  KEY `ix_item_rubrics_order` (`rubric_id`,`position`),
  CONSTRAINT `fk_item_rubrics_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_item_rubrics_rubric` FOREIGN KEY (`rubric_id`) REFERENCES `rubrics` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `item_rubrics`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `item_rubrics` WRITE;
/*!40000 ALTER TABLE `item_rubrics` DISABLE KEYS */;
INSERT INTO `item_rubrics` VALUES (1,4,1,-1024,'2026-10-27 14:58:47.471614');
INSERT INTO `item_rubrics` VALUES (2,4,1,-2048,'2026-10-27 14:58:47.506567');
INSERT INTO `item_rubrics` VALUES (3,4,1,-3072,'2026-10-27 14:58:47.529911');
INSERT INTO `item_rubrics` VALUES (4,4,1,-4096,'2026-10-27 14:58:47.548594');
INSERT INTO `item_rubrics` VALUES (5,4,1,-5120,'2026-10-27 14:58:47.565788');
INSERT INTO `item_rubrics` VALUES (6,4,1,-6144,'2026-10-27 14:58:47.589059');
INSERT INTO `item_rubrics` VALUES (7,4,1,-7168,'2026-10-27 14:58:47.615667');
INSERT INTO `item_rubrics` VALUES (8,4,1,-8192,'2026-10-27 14:58:47.635833');
INSERT INTO `item_rubrics` VALUES (9,4,1,-9216,'2026-10-27 14:58:47.658676');
INSERT INTO `item_rubrics` VALUES (9,23,0,-1024,'2026-10-27 14:58:47.658677');
INSERT INTO `item_rubrics` VALUES (10,4,1,-10240,'2026-10-27 14:58:47.678450');
INSERT INTO `item_rubrics` VALUES (11,4,1,-11264,'2026-10-27 14:58:47.701622');
INSERT INTO `item_rubrics` VALUES (11,22,0,-1024,'2026-10-27 14:58:47.701623');
INSERT INTO `item_rubrics` VALUES (12,4,1,-12288,'2026-10-27 14:58:47.773938');
INSERT INTO `item_rubrics` VALUES (13,4,1,-13312,'2026-10-27 14:58:47.798350');
INSERT INTO `item_rubrics` VALUES (14,3,1,-1024,'2026-10-27 14:58:47.824180');
INSERT INTO `item_rubrics` VALUES (15,3,1,-2048,'2026-10-27 14:58:47.847572');
INSERT INTO `item_rubrics` VALUES (15,23,0,-2048,'2026-10-27 14:58:47.847573');
INSERT INTO `item_rubrics` VALUES (16,5,1,-1024,'2026-10-27 14:58:47.871037');
INSERT INTO `item_rubrics` VALUES (17,6,1,-1024,'2026-10-27 14:58:47.892184');
INSERT INTO `item_rubrics` VALUES (18,6,1,-2048,'2026-10-27 14:58:47.914359');
INSERT INTO `item_rubrics` VALUES (19,8,1,-1024,'2026-10-27 14:58:47.938352');
INSERT INTO `item_rubrics` VALUES (20,8,1,-2048,'2026-10-27 14:58:47.963164');
INSERT INTO `item_rubrics` VALUES (20,22,0,-2048,'2026-10-27 14:58:47.963165');
INSERT INTO `item_rubrics` VALUES (21,9,1,-1024,'2026-10-27 14:58:47.983938');
INSERT INTO `item_rubrics` VALUES (22,10,1,-1024,'2026-10-27 14:58:48.006758');
INSERT INTO `item_rubrics` VALUES (23,12,1,-1024,'2026-10-27 14:58:48.026587');
INSERT INTO `item_rubrics` VALUES (24,13,1,-1024,'2026-10-27 14:58:48.047049');
INSERT INTO `item_rubrics` VALUES (25,14,1,-1024,'2026-10-27 14:58:48.064562');
INSERT INTO `item_rubrics` VALUES (26,16,1,-1024,'2026-10-27 14:58:48.081099');
INSERT INTO `item_rubrics` VALUES (27,16,1,-2048,'2026-10-27 14:58:48.096880');
INSERT INTO `item_rubrics` VALUES (28,17,1,-1024,'2026-10-27 14:58:48.113431');
INSERT INTO `item_rubrics` VALUES (29,18,1,-1024,'2026-10-27 14:58:48.129512');
INSERT INTO `item_rubrics` VALUES (30,18,1,-2048,'2026-10-27 14:58:48.146173');
INSERT INTO `item_rubrics` VALUES (31,19,1,-1024,'2026-10-27 14:58:48.168131');
INSERT INTO `item_rubrics` VALUES (32,20,1,-1024,'2026-10-27 14:58:48.191656');
/*!40000 ALTER TABLE `item_rubrics` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `item_variants`
--

DROP TABLE IF EXISTS `item_variants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `item_variants` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `item_id` int(10) unsigned NOT NULL,
  `number` varchar(64) NOT NULL,
  `finish` varchar(500) NOT NULL,
  `years` varchar(64) NOT NULL,
  `position` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_item_variants_item` (`item_id`,`position`),
  CONSTRAINT `fk_item_variants_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=37 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `item_variants`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `item_variants` WRITE;
/*!40000 ALTER TABLE `item_variants` DISABLE KEYS */;
INSERT INTO `item_variants` VALUES (1,1,'1094-a','Рыжая масть','1985–1991',0);
INSERT INTO `item_variants` VALUES (2,1,'1094-b','Пегая масть','1986–1989',1);
INSERT INTO `item_variants` VALUES (3,1,'1094-c','Вороная масть','1988–1991',2);
INSERT INTO `item_variants` VALUES (4,2,'1090-a','Гнедая масть','1981–1988',0);
INSERT INTO `item_variants` VALUES (5,2,'1090-b','Караковая масть','1983–1986',1);
INSERT INTO `item_variants` VALUES (6,3,'1071-a','Сплошное золочение','1969–1974',0);
INSERT INTO `item_variants` VALUES (7,3,'1071-b','Золочение гривы и копыт','1971–1973',1);
INSERT INTO `item_variants` VALUES (8,4,'1060-a','Белый бисквит','1966',0);
INSERT INTO `item_variants` VALUES (9,5,'1085-a','Белая глазурь','1976',0);
INSERT INTO `item_variants` VALUES (10,6,'1057-a','Рыжая масть','1964–1972',0);
INSERT INTO `item_variants` VALUES (11,6,'1057-b','Буланая масть','1966–1970',1);
INSERT INTO `item_variants` VALUES (12,7,'1080-a','Рыжая масть','1974–1980',0);
INSERT INTO `item_variants` VALUES (13,7,'1080-b','Золотисто-рыжая масть','1976–1978',1);
INSERT INTO `item_variants` VALUES (14,8,'1064-a','Вороная масть','1967–1975',0);
INSERT INTO `item_variants` VALUES (15,8,'1064-b','Вороная масть, золочёные копыта','1975–1981',1);
INSERT INTO `item_variants` VALUES (16,8,'1064-c','Серая масть','1970–1972',2);
INSERT INTO `item_variants` VALUES (17,9,'1051-a','Серая в яблоках, зелёный постамент','1962–1968',0);
INSERT INTO `item_variants` VALUES (18,9,'1051-b','Серая в яблоках, белый постамент','1965–1971',1);
INSERT INTO `item_variants` VALUES (19,9,'1051-c','Вороная масть','1968–1975',2);
INSERT INTO `item_variants` VALUES (20,9,'1051-d','Гнедая масть','1970–1973',3);
INSERT INTO `item_variants` VALUES (21,10,'1043-a','Тёмно-гнедой окрас','1961–1970',0);
INSERT INTO `item_variants` VALUES (22,10,'1043-b','Белый бисквит','1961–1963',1);
INSERT INTO `item_variants` VALUES (23,11,'1042-a','Белый бисквит','1958–1965',0);
INSERT INTO `item_variants` VALUES (24,11,'1042-b','Подглазурная роспись, серая масть','1962–1974',1);
INSERT INTO `item_variants` VALUES (25,11,'1042-c','Надглазурная роспись, золочёный постамент','1970–1979',2);
INSERT INTO `item_variants` VALUES (26,12,'1039-a','Полихромная роспись','1956–1963',0);
INSERT INTO `item_variants` VALUES (27,12,'1039-b','Белый бисквит','1957–1959',1);
INSERT INTO `item_variants` VALUES (28,13,'1078-a','Красная попона','1972–1976',0);
INSERT INTO `item_variants` VALUES (29,13,'1078-b','Синяя попона','1974–1980',1);
INSERT INTO `item_variants` VALUES (30,13,'1078-c','Попона с орнаментом','1977–1983',2);
INSERT INTO `item_variants` VALUES (31,13,'1078-d','Золочёная попона','1980–1985',3);
INSERT INTO `item_variants` VALUES (32,13,'1078-e','Зелёная попона','1984–1988',4);
INSERT INTO `item_variants` VALUES (33,15,'210-a','Серая в яблоках','1958–1966',0);
INSERT INTO `item_variants` VALUES (34,15,'210-b','Белая глазурь','1960–1962',1);
INSERT INTO `item_variants` VALUES (35,20,'118-a','Чернёный чугун','1959–1985',0);
INSERT INTO `item_variants` VALUES (36,20,'118-b','Бронзированная поверхность','1972–1978',1);
/*!40000 ALTER TABLE `item_variants` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `items`
--

DROP TABLE IF EXISTS `items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `catno` varchar(64) NOT NULL,
  `catno_sort` varchar(191) NOT NULL,
  `slug` varchar(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `name` varchar(255) NOT NULL,
  `summary` text NOT NULL,
  `description` mediumtext NOT NULL,
  `year_from` smallint(5) unsigned DEFAULT NULL,
  `year_to` smallint(5) unsigned DEFAULT NULL,
  `is_published` tinyint(1) NOT NULL DEFAULT 0,
  `search_text` mediumtext NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `updated_by` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_items_catno` (`catno`),
  UNIQUE KEY `uq_items_slug` (`slug`),
  KEY `ix_items_catno_sort` (`catno_sort`),
  KEY `ix_items_updated` (`updated_at`),
  KEY `fk_items_user` (`updated_by`),
  FULLTEXT KEY `ft_items` (`catno`,`name`,`summary`,`description`,`search_text`),
  CONSTRAINT `fk_items_user` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `items`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `items` WRITE;
/*!40000 ALTER TABLE `items` DISABLE KEYS */;
INSERT INTO `items` VALUES (1,'ЛФЗ-1094','лфз-0000001094','lfz-1094','Пони','Детская серия; выпускалась в наборе с жеребёнком той же масти.','',1985,1991,1,'Ленинградский фарфоровый завод\nФарфор, роспись\n11 см\nСинее надглазурное\n1094-a Рыжая масть\n1094-b Пегая масть\n1094-c Вороная масть','2025-12-05 10:00:00','2026-08-23 05:00:00',1);
INSERT INTO `items` VALUES (2,'ЛФЗ-1090','лфз-0000001090','lfz-1090','Голова тяжеловоза','Крупная настольная скульптура; гнедая и караковая масти.','',1981,1988,1,'Ленинградский фарфоровый завод\nФарфор, роспись\n26 см\nСинее надглазурное\n1090-a Гнедая масть\n1090-b Караковая масть','2025-11-26 10:00:00','2026-08-25 06:00:00',1);
INSERT INTO `items` VALUES (3,'ЛФЗ-1071','лфз-0000001071','lfz-1071','Конь с золочением','Сувенирная модель, полностью покрытая золотом; встречается в фирменной коробке.','',1969,1974,0,'Ленинградский фарфоровый завод\nФарфор, надглазурное золото\n14 см\nЗелёное подглазурное\n1071-a Сплошное золочение\n1071-b Золочение гривы и копыт','2025-11-17 10:00:00','2026-08-27 07:00:00',1);
INSERT INTO `items` VALUES (4,'ЛФЗ-1060','лфз-0000001060','lfz-1060','Голова коня, рельеф','Настенный рельеф в белом бисквите, выпускался ограниченным тиражом.','',1966,NULL,1,'Ленинградский фарфоровый завод\nФарфор, бисквит\n30 см\nЗелёное подглазурное\n1060-a Белый бисквит','2025-11-08 10:00:00','2026-08-29 08:00:00',1);
INSERT INTO `items` VALUES (5,'ЛФЗ-1085','лфз-0000001085','lfz-1085','Белая лошадь','Лаконичная фигура под прозрачной глазурью, без росписи.','',1976,NULL,1,'Ленинградский фарфоровый завод\nФарфор, глазурь\n17 см\nСинее надглазурное\n1085-a Белая глазурь','2025-10-30 10:00:00','2026-08-31 09:00:00',1);
INSERT INTO `items` VALUES (6,'ЛФЗ-1057','лфз-0000001057','lfz-1057','Жеребёнок','Малая пластика, высота около 9 см. Встречается с рыжим и буланым окрасом.','',1964,1972,1,'Ленинградский фарфоровый завод\nФарфор, роспись\n9 см\nЗелёное подглазурное\n1057-a Рыжая масть\n1057-b Буланая масть','2025-10-21 10:00:00','2026-09-02 10:00:00',1);
INSERT INTO `items` VALUES (7,'ЛФЗ-1080','лфз-0000001080','lfz-1080','Будённовская кобыла','Модель по мотивам породного стандарта; рыжая масть с золотистым отливом.','',1974,1980,1,'Ленинградский фарфоровый завод\nФарфор, роспись\n20 см\nСинее надглазурное\n1080-a Рыжая масть\n1080-b Золотисто-рыжая масть','2025-10-12 10:00:00','2026-09-04 11:00:00',1);
INSERT INTO `items` VALUES (8,'ЛФЗ-1064','лфз-0000001064','lfz-1064','Вороной на скаку','Динамичная композиция с развевающейся гривой; поздние тиражи с золочением копыт.','',1967,1981,1,'Ленинградский фарфоровый завод\nФарфор, глазурь\n19 см\nЗелёное подглазурное\n1064-a Вороная масть\n1064-b Вороная масть, золочёные копыта\n1064-c Серая масть','2025-10-03 10:00:00','2026-09-06 12:00:00',1);
INSERT INTO `items` VALUES (9,'ЛФЗ-1051','лфз-0000001051','lfz-1051','Орловский рысак','Рысак на ходу; варианты различаются мастью и цветом постамента.','',1962,1975,1,'Ленинградский фарфоровый завод\nФарфор, роспись\n22 см\nЗелёное подглазурное\n1051-a Серая в яблоках, зелёный постамент\n1051-b Серая в яблоках, белый постамент\n1051-c Вороная масть\n1051-d Гнедая масть','2025-09-24 10:00:00','2026-09-08 13:00:00',1);
INSERT INTO `items` VALUES (10,'ЛФЗ-1043','лфз-0000001043','lfz-1043','Голова арабского скакуна','Настольная скульптура на круглом постаменте, тёмно-гнедой окрас под глазурью.','',1961,1970,1,'Ленинградский фарфоровый завод\nФарфор, глазурь\n18 см\nЗелёное подглазурное\n1043-a Тёмно-гнедой окрас\n1043-b Белый бисквит','2025-09-15 10:00:00','2026-09-10 14:00:00',1);
INSERT INTO `items` VALUES (11,'ЛФЗ-1042','лфз-0000001042','lfz-1042','Конь вздыбленный','Одна из первых послевоенных анималистических моделей завода.\nВыпускалась более двадцати лет в трёх вариантах исполнения.','Конь стоит на задних ногах на овальном постаменте, имитирующем каменистую почву. Грива и хвост проработаны отдельными прядями, что делает модель хрупкой: у большинства сохранившихся экземпляров есть реставрация ушей или пряди хвоста.\n\nРанние экземпляры в белом бисквите отличаются более чёткой проработкой мускулатуры. В поздних тиражах форма постепенно изнашивалась, детали становились мягче, а постамент получил золочёный кант.\n\nПри атрибуции стоит сверять тип клейма с годами выпуска варианта: сочетание раннего клейма с надглазурной росписью встречается на заводских переделках.',1958,1979,1,'Ленинградский фарфоровый завод\nНе установлен\nФарфор, бисквит\n21 см\nЗелёное подглазурное, тип 3\nРедко\n1042-a Белый бисквит\n1042-b Подглазурная роспись, серая масть\n1042-c Надглазурная роспись, золочёный постамент','2025-03-12 09:30:00','2026-10-24 15:12:00',1);
INSERT INTO `items` VALUES (12,'ЛФЗ-1039','лфз-0000001039','lfz-1039','Тройка, настольная композиция','Три коня в упряжке на общем постаменте; одна из самых сложных многофигурных моделей завода.','',1956,1963,1,'Ленинградский фарфоровый завод\nФарфор, роспись\n23 см\nЗелёное подглазурное\n1039-a Полихромная роспись\n1039-b Белый бисквит','2025-08-28 10:00:00','2026-09-14 16:00:00',1);
INSERT INTO `items` VALUES (13,'ЛФЗ-1078','лфз-0000001078','lfz-1078','Конь в попоне','Парадный конь в расписной попоне; рисунок попоны менялся от тиража к тиражу.','',1972,1988,1,'Ленинградский фарфоровый завод\nФарфор, роспись\n24 см\nСинее надглазурное\n1078-a Красная попона\n1078-b Синяя попона\n1078-c Попона с орнаментом\n1078-d Золочёная попона\n1078-e Зелёная попона','2025-08-19 10:00:00','2026-09-16 17:00:00',1);
INSERT INTO `items` VALUES (14,'ДФЗ-245','дфз-0000000245','dfz-245','Конь-качалка','Игрушечная лошадка на полозьях, яркая надглазурная роспись.','',1971,NULL,1,'Дулёвский фарфоровый завод\n12 см','2025-08-10 10:00:00','2026-09-18 18:00:00',1);
INSERT INTO `items` VALUES (15,'ДФЗ-210','дфз-0000000210','dfz-210','Орловский рысак в яблоках','Рысак серой в яблоках масти на прямоугольном постаменте.','',1958,1966,1,'Дулёвский фарфоровый завод\n19 см\n210-a Серая в яблоках\n210-b Белая глазурь','2025-08-01 10:00:00','2026-09-20 19:00:00',1);
INSERT INTO `items` VALUES (16,'ВФЗ-31','вфз-0000000031','vfz-31','Тройка с бубенцами','Расписная тройка с санями, сувенирная серия завода.','',1960,NULL,1,'Вербилковский фарфоровый завод','2025-07-23 10:00:00','2026-09-22 20:00:00',1);
INSERT INTO `items` VALUES (17,'ЕМ-27','ем-0000000027','em-27','Кобыла с жеребёнком','Пара на общем постаменте, подглазурная роспись в серо-голубой гамме.','',1965,1978,1,'Дания\n16 см','2025-07-14 10:00:00','2026-09-24 21:00:00',1);
INSERT INTO `items` VALUES (18,'ЕМ-12','ем-0000000012','em-12','Липицианская лошадь','Белая лошадь в позе левады, глазурованный фарфор.','',1962,NULL,1,'Австрия\n22 см','2025-07-05 10:00:00','2026-09-26 22:00:00',1);
INSERT INTO `items` VALUES (19,'КЧЗ-140','кчз-0000000140','kchz-140','Упряжка','Пара лошадей с телегой, каслинское литьё с чернением.','',1965,NULL,1,'Чугун\n28 см','2025-06-26 10:00:00','2026-09-28 23:00:00',1);
INSERT INTO `items` VALUES (20,'КЧЗ-118','кчз-0000000118','kchz-118','Конь на дыбах','Классическая модель каслинского литья по мотивам Клодта.','',1959,1985,1,'Чугун\n31 см\n118-a Чернёный чугун\n118-b Бронзированная поверхность','2025-06-17 10:00:00','2026-10-01 00:00:00',1);
INSERT INTO `items` VALUES (21,'БР-07','бр-0000000007','br-07','Скачущий конь','Камерная бронза на мраморном основании.','',1974,NULL,1,'Бронза, мрамор\n18 см','2025-06-08 10:00:00','2026-10-03 01:00:00',1);
INSERT INTO `items` VALUES (22,'ПЛ-03','пл-0000000003','pl-03','Пони-игрушка','Игрушка из мягкого пластика, несколько цветовых вариантов.','',1978,1984,1,'Пластик','2025-05-30 10:00:00','2026-10-05 02:00:00',1);
INSERT INTO `items` VALUES (23,'ЗН-501','зн-0000000501','zn-501','Знак «Мастер спорта» по конному спорту','Нагрудный знак, латунь, горячая эмаль.','',1965,NULL,1,'Латунь, эмаль','2025-05-21 10:00:00','2026-10-07 03:00:00',1);
INSERT INTO `items` VALUES (24,'ЗН-612','зн-0000000612','zn-612','Жетон Центрального московского ипподрома','Памятный жетон к юбилею ипподрома.','',1957,NULL,1,'Томпак','2025-05-12 10:00:00','2026-10-09 04:00:00',1);
INSERT INTO `items` VALUES (25,'МД-14','мд-0000000014','md-14','Настольная медаль конного завода','Медаль к юбилею завода с изображением жеребца-производителя.','',1976,NULL,1,'Бронза\n65 мм','2025-05-03 10:00:00','2026-10-11 05:00:00',1);
INSERT INTO `items` VALUES (26,'М-1964','м-0000001964','m-1964','Серия «Породы лошадей»','Серия из пяти марок: орловский рысак, ахалтекинец, донская, будённовская, тракененская.','',1964,NULL,1,'5','2025-04-24 10:00:00','2026-10-13 06:00:00',1);
INSERT INTO `items` VALUES (27,'М-1976','м-0000001976','m-1976','Марка «Конный спорт»','Марка спортивной серии, конкур.','',1976,NULL,1,'','2025-04-15 10:00:00','2026-10-15 07:00:00',1);
INSERT INTO `items` VALUES (28,'К-88','к-0000000088','k-88','Конверт «Орловский рысак»','Художественный маркированный конверт.','',1988,NULL,1,'','2025-04-06 10:00:00','2026-10-17 08:00:00',1);
INSERT INTO `items` VALUES (29,'О-415','о-0000000415','o-415','Набор открыток «Лошади»','Набор из шестнадцати открыток с фотографиями породистых лошадей.','',1972,NULL,1,'16 открыток','2025-03-28 10:00:00','2026-10-19 09:00:00',1);
INSERT INTO `items` VALUES (30,'О-301','о-0000000301','o-301','Открытка «Тройка»','Открытка с репродукцией картины.','',1955,NULL,1,'','2025-03-19 10:00:00','2026-10-21 10:00:00',1);
INSERT INTO `items` VALUES (31,'Г-12','г-0000000012','g-12','Плакат «Развивайте коневодство»','Агитационный плакат, офсетная печать.','',1954,NULL,1,'60 × 90 см','2025-03-10 10:00:00','2026-10-23 11:00:00',1);
INSERT INTO `items` VALUES (32,'КН-01','кн-0000000001','kn-01','Каталог продукции ЛФЗ','Заводской каталог с фотографиями моделей.','',1960,NULL,1,'','2025-03-01 10:00:00','2026-10-25 12:00:00',1);
/*!40000 ALTER TABLE `items` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `login_attempts`
--

DROP TABLE IF EXISTS `login_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `login_attempts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `login` varchar(64) NOT NULL,
  `ip` varchar(45) NOT NULL,
  `attempted_at` datetime NOT NULL,
  `success` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_login_attempts_login` (`login`,`attempted_at`),
  KEY `ix_login_attempts_ip` (`ip`,`attempted_at`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `login_attempts`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `login_attempts` WRITE;
/*!40000 ALTER TABLE `login_attempts` DISABLE KEYS */;
INSERT INTO `login_attempts` VALUES (1,'admin','203.0.113.24','2026-10-27 14:58:50',1);
/*!40000 ALTER TABLE `login_attempts` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `rubrics`
--

DROP TABLE IF EXISTS `rubrics`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `rubrics` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` int(10) unsigned DEFAULT NULL,
  `parent_key` int(10) unsigned GENERATED ALWAYS AS (ifnull(`parent_id`,0)) STORED,
  `path` varchar(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `slug` varchar(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `name` varchar(150) NOT NULL,
  `title` varchar(255) NOT NULL DEFAULT '',
  `description` text NOT NULL,
  `position` int(11) NOT NULL DEFAULT 0,
  `is_visible` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `cover_image` char(64) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rubrics_slug` (`parent_key`,`slug`),
  KEY `ix_rubrics_parent` (`parent_id`,`position`),
  KEY `ix_rubrics_path` (`path`),
  KEY `fk_rubrics_cover` (`cover_image`),
  CONSTRAINT `fk_rubrics_cover` FOREIGN KEY (`cover_image`) REFERENCES `images` (`sha256`) ON DELETE SET NULL,
  CONSTRAINT `fk_rubrics_parent` FOREIGN KEY (`parent_id`) REFERENCES `rubrics` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rubrics`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `rubrics` WRITE;
/*!40000 ALTER TABLE `rubrics` DISABLE KEYS */;
INSERT INTO `rubrics` VALUES (1,NULL,0,'/1/','figurki','Фигурки','','Скульптура малых форм: фарфор, художественное литьё, пластик.',0,1,'2026-10-27 14:58:47','2026-10-27 14:58:47',NULL);
INSERT INTO `rubrics` VALUES (2,1,1,'/1/2/','farfor','Фарфор','Фарфоровые фигурки','Анималистический фарфор отечественных и европейских заводов.',0,1,'2026-10-27 14:58:47','2026-10-27 14:58:47',NULL);
INSERT INTO `rubrics` VALUES (3,2,2,'/1/2/3/','dulyovskiy-zavod','Дулёвский завод','Дулёвский фарфоровый завод','',0,1,'2026-10-27 14:58:47','2026-10-27 14:58:47',NULL);
INSERT INTO `rubrics` VALUES (4,2,2,'/1/2/4/','lfz','Ленинградский завод','Ленинградский фарфоровый завод','Анималистическая скульптура завода: лошади в бисквите и с росписью, от первых послевоенных моделей до поздних тиражей. Каждая модель описана со всеми известными вариантами окраса и клейма.',1,1,'2026-10-27 14:58:47','2026-10-27 14:58:47',NULL);
INSERT INTO `rubrics` VALUES (5,2,2,'/1/2/5/','verbilki','Вербилки','Вербилковский фарфоровый завод','',2,1,'2026-10-27 14:58:47','2026-10-27 14:58:47',NULL);
INSERT INTO `rubrics` VALUES (6,2,2,'/1/2/6/','evropeyskie-manufaktury','Европейские мануфактуры','','',3,1,'2026-10-27 14:58:47','2026-10-27 14:58:47',NULL);
INSERT INTO `rubrics` VALUES (7,1,1,'/1/7/','khudozhestvennoe-lityo','Художественное литьё','','Чугунное и бронзовое литьё конной тематики.',1,1,'2026-10-27 14:58:47','2026-10-27 14:58:47',NULL);
INSERT INTO `rubrics` VALUES (8,7,7,'/1/7/8/','chugun','Чугун','Чугунное литьё','',0,1,'2026-10-27 14:58:47','2026-10-27 14:58:47',NULL);
INSERT INTO `rubrics` VALUES (9,7,7,'/1/7/9/','bronza','Бронза','','',1,1,'2026-10-27 14:58:47','2026-10-27 14:58:47',NULL);
INSERT INTO `rubrics` VALUES (10,1,1,'/1/10/','plastik-i-smola','Пластик и смола','','',2,1,'2026-10-27 14:58:47','2026-10-27 14:58:47',NULL);
INSERT INTO `rubrics` VALUES (11,NULL,0,'/11/','medali-i-znaki','Медали и знаки','','Наградные и памятные знаки, жетоны и настольные медали.',1,1,'2026-10-27 14:58:47','2026-10-27 14:58:47',NULL);
INSERT INTO `rubrics` VALUES (12,11,11,'/11/12/','konnyy-sport','Конный спорт','','',0,1,'2026-10-27 14:58:47','2026-10-27 14:58:47',NULL);
INSERT INTO `rubrics` VALUES (13,11,11,'/11/13/','ippodromy','Ипподромы','','',1,1,'2026-10-27 14:58:47','2026-10-27 14:58:47',NULL);
INSERT INTO `rubrics` VALUES (14,11,11,'/11/14/','konnye-zavody','Конные заводы','','',2,1,'2026-10-27 14:58:47','2026-10-27 14:58:47',NULL);
INSERT INTO `rubrics` VALUES (15,NULL,0,'/15/','filateliya','Филателия','','',2,1,'2026-10-27 14:58:47','2026-10-27 14:58:47',NULL);
INSERT INTO `rubrics` VALUES (16,15,15,'/15/16/','marki','Марки','','',0,1,'2026-10-27 14:58:47','2026-10-27 14:58:47',NULL);
INSERT INTO `rubrics` VALUES (17,15,15,'/15/17/','konverty-i-kartochki','Конверты и карточки','','',1,1,'2026-10-27 14:58:47','2026-10-27 14:58:47',NULL);
INSERT INTO `rubrics` VALUES (18,NULL,0,'/18/','otkrytki','Открытки','','',3,1,'2026-10-27 14:58:47','2026-10-27 14:58:47',NULL);
INSERT INTO `rubrics` VALUES (19,NULL,0,'/19/','grafika-i-plakaty','Графика и плакаты','','',4,1,'2026-10-27 14:58:47','2026-10-27 14:58:47',NULL);
INSERT INTO `rubrics` VALUES (20,NULL,0,'/20/','knigi-i-katalogi','Книги и каталоги','','Раздел готовится.',5,0,'2026-10-27 14:58:47','2026-10-27 14:58:47',NULL);
INSERT INTO `rubrics` VALUES (21,NULL,0,'/21/','tematicheskie-podborki','Тематические подборки','','Подборки предметов разных разделов на одну тему.',6,1,'2026-10-27 14:58:47','2026-10-27 14:58:47',NULL);
INSERT INTO `rubrics` VALUES (22,21,21,'/21/22/','koni-na-dybakh','Кони на дыбах','','',0,1,'2026-10-27 14:58:47','2026-10-27 14:58:47',NULL);
INSERT INTO `rubrics` VALUES (23,21,21,'/21/23/','orlovskie-rysaki','Орловские рысаки','','',1,1,'2026-10-27 14:58:47','2026-10-27 14:58:47',NULL);
/*!40000 ALTER TABLE `rubrics` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `schema_meta`
--

DROP TABLE IF EXISTS `schema_meta`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `schema_meta` (
  `name` varchar(64) NOT NULL,
  `value` varchar(255) NOT NULL,
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `schema_meta`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `schema_meta` WRITE;
/*!40000 ALTER TABLE `schema_meta` DISABLE KEYS */;
INSERT INTO `schema_meta` VALUES ('version','16');
/*!40000 ALTER TABLE `schema_meta` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id_hash` binary(32) NOT NULL,
  `previous_hash` binary(32) DEFAULT NULL,
  `previous_valid_until` datetime DEFAULT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `remember` tinyint(1) NOT NULL DEFAULT 0,
  `csrf_secret` binary(32) NOT NULL,
  `created_at` datetime NOT NULL,
  `last_seen_at` datetime NOT NULL,
  `expires_at` datetime NOT NULL,
  `rotated_at` datetime NOT NULL,
  `ip` varchar(45) NOT NULL DEFAULT '',
  `user_agent` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id_hash`),
  KEY `ix_sessions_previous` (`previous_hash`),
  KEY `ix_sessions_user` (`user_id`),
  KEY `ix_sessions_expires` (`expires_at`),
  CONSTRAINT `fk_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sessions`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `sessions` WRITE;
/*!40000 ALTER TABLE `sessions` DISABLE KEYS */;
INSERT INTO `sessions` VALUES ('H`;��^�M.\"�_w3n���+4ૣ;��K',NULL,NULL,2,0,'(Q#j���Kx��JP����v��R�\"��t','2026-10-24 15:40:00','2026-10-24 16:10:00','2026-11-23 16:10:00','2026-10-27 14:58:48','203.0.113.81','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Safari/605.1.15');
INSERT INTO `sessions` VALUES ('Hf�fL�I����q��i0�s6�����������',NULL,NULL,1,1,'Ե�~�����:�M���N��MËU�P��','2026-10-12 18:02:00','2026-10-25 19:31:00','2026-11-24 19:31:00','2026-10-27 14:58:48','198.51.100.7','Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1');
INSERT INTO `sessions` VALUES ('���\'�$�������2P��y��o���ꃖ�',NULL,NULL,1,1,'iG����1)�`�@1�~,�,5Y��UP�S~-','2026-10-20 07:47:00','2026-10-23 13:05:00','2026-11-22 13:05:00','2026-10-27 14:58:48','192.0.2.150','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36');
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `settings` (
  `name` varchar(64) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  `value` text NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `settings`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `settings` WRITE;
/*!40000 ALTER TABLE `settings` DISABLE KEYS */;
/*!40000 ALTER TABLE `settings` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `slug_history`
--

DROP TABLE IF EXISTS `slug_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `slug_history` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `kind` varchar(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `old_path` varchar(700) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `target_id` int(10) unsigned NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_slug_history_path` (`kind`,`old_path`),
  KEY `ix_slug_history_target` (`kind`,`target_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `slug_history`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `slug_history` WRITE;
/*!40000 ALTER TABLE `slug_history` DISABLE KEYS */;
/*!40000 ALTER TABLE `slug_history` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `login` varchar(64) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `display_name` varchar(128) NOT NULL DEFAULT '',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL,
  `last_login_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_login` (`login`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'admin','$argon2id$v=19$m=65536,t=4,p=1$Q09qelVmdzcycC4uM0kvMg$/eGiZ60yQV2/HvbC4dnH7cZJprbQtFwliJCSnWNGkmc','Владелец справочника',1,'2024-11-01 08:00:00','2026-10-27 14:58:50');
INSERT INTO `users` VALUES (2,'ekovaleva','$argon2id$v=19$m=65536,t=4,p=1$TEVYeHBESi96QnNrbEhENw$xcVzIsnkZe7pjv7xZWkMO2EG3xPCXbqJh+9WkhCElcc','Елена Ковалёва',1,'2025-02-20 08:00:00','2026-10-24 15:40:00');
INSERT INTO `users` VALUES (3,'archive','$argon2id$v=19$m=65536,t=4,p=1$S1g0UjRhTk92UVguVTE5Vg$jz319ThvNaq+0C1zUu65GiDIxfn7kRIviFOWew4KuL8','Архив (старый доступ)',0,'2024-11-02 08:00:00','2026-03-02 08:05:00');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*M!100616 SET NOTE_VERBOSITY=@OLD_NOTE_VERBOSITY */;

-- Dump completed
