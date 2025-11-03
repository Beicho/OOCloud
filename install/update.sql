-- 123云盘存储新增配置
INSERT IGNORE INTO `pre_config` VALUES ('openapi123_username', '');
INSERT IGNORE INTO `pre_config` VALUES ('openapi123_password', '');
INSERT IGNORE INTO `pre_config` VALUES ('openapi123_use_client_api', '1');
INSERT IGNORE INTO `pre_config` VALUES ('openapi123_s3keyflag', '');

-- 123云盘性能优化配置
INSERT IGNORE INTO `pre_config` VALUES ('openapi123_cache_metadata', '1');
INSERT IGNORE INTO `pre_config` VALUES ('openapi123_cache_ttl', '604800');
INSERT IGNORE INTO `pre_config` VALUES ('openapi123_rate_limit', '1');
INSERT IGNORE INTO `pre_config` VALUES ('openapi123_rate_max', '60');
INSERT IGNORE INTO `pre_config` VALUES ('openapi123_circuit_breaker', '1');
INSERT IGNORE INTO `pre_config` VALUES ('openapi123_circuit_threshold', '50');
INSERT IGNORE INTO `pre_config` VALUES ('openapi123_circuit_timeout', '300');
INSERT IGNORE INTO `pre_config` VALUES ('openapi123_random_delay', '1');

-- 123云盘元数据缓存表
CREATE TABLE IF NOT EXISTS `pre_file_metadata` (
  `file_id` varchar(64) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `etag` varchar(64) NOT NULL,
  `s3keyflag` varchar(64) NOT NULL,
  `size` bigint NOT NULL,
  `cache_time` int NOT NULL,
  PRIMARY KEY (`file_id`),
  KEY `cache_time` (`cache_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 123云盘API限流状态表
CREATE TABLE IF NOT EXISTS `pre_api_rate_limit` (
  `id` int NOT NULL AUTO_INCREMENT,
  `api_type` varchar(32) NOT NULL,
  `minute_key` varchar(20) NOT NULL,
  `request_count` int NOT NULL DEFAULT '0',
  `fail_count` int NOT NULL DEFAULT '0',
  `circuit_open` tinyint NOT NULL DEFAULT '0',
  `circuit_open_time` int NOT NULL DEFAULT '0',
  `update_time` int NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `api_minute` (`api_type`,`minute_key`),
  KEY `update_time` (`update_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
