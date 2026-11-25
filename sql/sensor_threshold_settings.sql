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

-- --------------------------------------------------------

--
-- Gegevens worden geëxporteerd voor tabel `sensor_threshold_setting`
--
-- Standaarddrempels (overgenomen uit de eerdere sensor-instellingen):
--   geluid:          la        80 / 90 dB
--   luchtkwaliteit:  co2       1000 / 1400 ppm, humidity 30-70 / 20-80 %
--   temperatuur:     TempC_SHT 18-27 / 14-32 C
--

INSERT INTO `sensor_threshold_setting` (`device_ID`, `metric`, `warning_min`, `warning_max`, `error_min`, `error_max`, `is_enabled`) VALUES
(2, 'TempC_SHT', 18.00, 27.00, 14.00, 32.00, 1),
(3, 'la', NULL, 80.00, NULL, 90.00, 1),
(4, 'co2', NULL, 1000.00, NULL, 1400.00, 1),
(4, 'humidity', 30.00, 70.00, 20.00, 80.00, 1);

COMMIT;
