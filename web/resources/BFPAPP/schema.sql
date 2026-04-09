CREATE TABLE `device` (
  `device_ID` int(11) NOT NULL AUTO_INCREMENT,
  `FK_user_ID` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `location` varchar(100) DEFAULT NULL,
  `esp_id` varchar(50) NOT NULL,
  `status` varchar(50) DEFAULT 'Offline',
  `fire_status` varchar(50) DEFAULT 'SAFE',
  `battery` varchar(10) DEFAULT '100%',
  `last_check` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`device_ID`),
  UNIQUE KEY `esp_id` (`esp_id`),
  KEY `FK_user_ID` (`FK_user_ID`),
  CONSTRAINT `device_ibfk_1` FOREIGN KEY (`FK_user_ID`) REFERENCES `users` (`user_ID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
