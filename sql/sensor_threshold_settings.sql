-- Threshold settings per sensor metric
-- Conventions aligned with smartdatacenter_01_org.sql

START TRANSACTION;

-- --------------------------------------------------------

--
-- Tabelstructuur voor tabel `sensor_threshold_setting`
--

CREATE TABLE `sensor_threshold_setting` (
  `device_ID` int NOT NULL,
  `metric` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `warning_min` decimal(10,2) DEFAULT NULL,
  `warning_max` decimal(10,2) DEFAULT NULL,
  `error_min` decimal(10,2) DEFAULT NULL,
  `error_max` decimal(10,2) DEFAULT NULL,
  `is_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updatedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Indexen voor geëxporteerde tabellen
--

--
-- Indexen voor tabel `sensor_threshold_setting`
--
ALTER TABLE `sensor_threshold_setting`
  ADD PRIMARY KEY (`device_ID`,`metric`),
  ADD KEY `idx_sensor_threshold_device_id` (`device_ID`);

--
-- Beperkingen voor geëxporteerde tabellen
--

--
-- Beperkingen voor tabel `sensor_threshold_setting`
--
ALTER TABLE `sensor_threshold_setting`
  ADD CONSTRAINT `fk_sensor_threshold_device` FOREIGN KEY (`device_ID`) REFERENCES `device` (`ID`) ON DELETE CASCADE ON UPDATE CASCADE;

COMMIT;
