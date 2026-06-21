-- =====================================================
-- LORRIES TABLE - FINAL VERSION WITH WAITING HOURS
-- Complete SQL setup with all fare calculation fields
-- =====================================================

DROP TABLE IF EXISTS `lorries`;

CREATE TABLE `lorries` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `name` varchar(255) NOT NULL UNIQUE KEY,
  `category` varchar(100) NOT NULL DEFAULT 'Lorries',
  `img` longtext,
  `img2` longtext,
  `img3` longtext,
  `img4` longtext,
  `img5` longtext,
  `seats` int unsigned NOT NULL DEFAULT 1,
  `ac_available` tinyint(1) NOT NULL DEFAULT 0,
  `non_ac_available` tinyint(1) NOT NULL DEFAULT 0,
  `rate_table` json NOT NULL COMMENT 'Windows, upDown charges, waiting charges',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `category_idx` (`category`),
  KEY `name_idx` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- INSERT SAMPLE DATA WITH ALL FIELDS
-- =====================================================

INSERT INTO `lorries`
  (`name`, `category`, `img`, `seats`, `ac_available`, `non_ac_available`, `rate_table`)
VALUES
(
  'Agra 7FT Lorry',
  'Lorries',
  '/assets/car.jpg',
  1,
  0,
  0,
  JSON_OBJECT(
    '7ft', JSON_OBJECT(
      'type', '7 FT',
      'windows', JSON_ARRAY(
        JSON_OBJECT(
          'fromKm', 0,
          'toKm', 130,
          'rate', 2500,
          'extraPerKm', 160,
          'hillExtraPerKm', 10
        )
      ),
      'upDownNonHill', 120,
      'upDownHill', 200,
      'freeWaitingHours', 2,
      'waitingChargePerHour', 500
    )
  )
),
(
  'Agra 20FT Lorry',
  'Lorries',
  '/assets/car.jpg',
  1,
  0,
  0,
  JSON_OBJECT(
    '20ft', JSON_OBJECT(
      'type', '20 FT',
      'windows', JSON_ARRAY(
        JSON_OBJECT(
          'fromKm', 0,
          'toKm', 130,
          'rate', 18000,
          'extraPerKm', 450,
          'hillExtraPerKm', 10
        )
      ),
      'upDownNonHill', 300,
      'upDownHill', 500,
      'freeWaitingHours', 2,
      'waitingChargePerHour', 800
    )
  )
),
(
  'Agra 8.5FT Lorry',
  'Lorries',
  '/assets/car.jpg',
  1,
  0,
  0,
  JSON_OBJECT(
    '8_5ft', JSON_OBJECT(
      'type', '8.5 FT',
      'windows', JSON_ARRAY(
        JSON_OBJECT(
          'fromKm', 0,
          'toKm', 130,
          'rate', 3500,
          'extraPerKm', 180,
          'hillExtraPerKm', 10
        )
      ),
      'upDownNonHill', 130,
      'upDownHill', 220,
      'freeWaitingHours', 2,
      'waitingChargePerHour', 600
    )
  )
);

-- =====================================================
-- VERIFY INSTALLATION
-- =====================================================

-- Check all lorries
SELECT `id`, `name`, `category`, `seats` FROM `lorries`;

-- View complete rate table for a lorry
SELECT `id`, `name`, `rate_table`
FROM `lorries`
WHERE `name` = 'Agra 7FT Lorry';

-- Extract specific values from JSON
SELECT
  `id`,
  `name`,
  JSON_EXTRACT(`rate_table`, '$.7ft.type') AS 'Type',
  JSON_EXTRACT(`rate_table`, '$.7ft.windows[0].rate') AS 'Base Rate',
  JSON_EXTRACT(`rate_table`, '$.7ft.windows[0].extraPerKm') AS 'Extra/KM',
  JSON_EXTRACT(`rate_table`, '$.7ft.windows[0].hillExtraPerKm') AS 'Hill Extra/KM',
  JSON_EXTRACT(`rate_table`, '$.7ft.upDownNonHill') AS 'UpDown Non-Hill',
  JSON_EXTRACT(`rate_table`, '$.7ft.upDownHill') AS 'UpDown Hill',
  JSON_EXTRACT(`rate_table`, '$.7ft.freeWaitingHours') AS 'Free Wait Hours',
  JSON_EXTRACT(`rate_table`, '$.7ft.waitingChargePerHour') AS 'Wait Charge/Hour'
FROM `lorries`;

-- =====================================================
-- FARE CALCULATION FORMULA
-- =====================================================

/*
TOTAL_FARE = BASE_FEE
           + EXTRA_FEE (if distance > window.toKm)
           + HILL_SURCHARGE (if hill location)
           + UP_DOWN_CHARGE (if round-trip)
           + WAITING_CHARGE (if waiting > free hours)

Components from rate_table:
- BASE_FEE: window.rate
- EXTRA_FEE: (distance - window.toKm) × window.extraPerKm
- HILL_SURCHARGE: distance × window.hillExtraPerKm
- UP_DOWN_CHARGE: upDownNonHill (normal) or upDownHill (hill location)
- WAITING_CHARGE: (waitingHours - freeWaitingHours) × waitingChargePerHour
                  (only if waitingHours > freeWaitingHours)

Example: 150km, round-trip, hill, 4 hours waiting
Window: [rate:2500, extra:160, hill:10]
upDownNonHill:120, upDownHill:200
freeWaitingHours:2, waitingChargePerHour:500

Base: 2500
Extra: (150-130)×160 = 3200
Hill: 150×10 = 1500
UpDown: 200 (hill round-trip)
Waiting: (4-2)×500 = 1000

TOTAL: 2500 + 3200 + 1500 + 200 + 1000 = Rs. 8,400
*/

-- =====================================================
-- UPDATE EXAMPLES
-- =====================================================

-- Update base rate
UPDATE `lorries`
SET `rate_table` = JSON_SET(
  `rate_table`,
  '$.7ft.windows[0].rate',
  3000
)
WHERE `name` = 'Agra 7FT Lorry';

-- Update extra per km
UPDATE `lorries`
SET `rate_table` = JSON_SET(
  `rate_table`,
  '$.7ft.windows[0].extraPerKm',
  170
)
WHERE `name` = 'Agra 7FT Lorry';

-- Update hill extra per km
UPDATE `lorries`
SET `rate_table` = JSON_SET(
  `rate_table`,
  '$.7ft.windows[0].hillExtraPerKm',
  12
)
WHERE `name` = 'Agra 7FT Lorry';

-- Update up/down charges
UPDATE `lorries`
SET `rate_table` = JSON_SET(
  `rate_table`,
  '$.7ft.upDownNonHill', 150,
  '$.7ft.upDownHill', 250
)
WHERE `name` = 'Agra 7FT Lorry';

-- Update waiting charges
UPDATE `lorries`
SET `rate_table` = JSON_SET(
  `rate_table`,
  '$.7ft.freeWaitingHours', 3,
  '$.7ft.waitingChargePerHour', 600
)
WHERE `name` = 'Agra 7FT Lorry';

-- Update all at once
UPDATE `lorries`
SET `rate_table` = JSON_SET(
  `rate_table`,
  '$.7ft.windows[0].rate', 2800,
  '$.7ft.windows[0].extraPerKm', 165,
  '$.7ft.windows[0].hillExtraPerKm', 11,
  '$.7ft.upDownNonHill', 130,
  '$.7ft.upDownHill', 210,
  '$.7ft.freeWaitingHours', 2,
  '$.7ft.waitingChargePerHour', 550
)
WHERE `name` = 'Agra 7FT Lorry';

-- =====================================================
-- USEFUL QUERIES
-- =====================================================

-- Check which lorries have valid rates
SELECT
  `id`,
  `name`,
  CASE
    WHEN `rate_table` IS NULL THEN 'ERROR: No rates'
    WHEN JSON_LENGTH(`rate_table`) = 0 THEN 'ERROR: Empty rates'
    WHEN JSON_SEARCH(`rate_table`, 'one', 'windows') IS NULL THEN 'ERROR: No windows'
    WHEN JSON_EXTRACT(`rate_table`, '$.*.freeWaitingHours') IS NULL THEN 'WARNING: No free waiting'
    WHEN JSON_EXTRACT(`rate_table`, '$.*.waitingChargePerHour') IS NULL THEN 'WARNING: No waiting charge'
    ELSE 'OK: Complete rates'
  END AS 'Status'
FROM `lorries`;

-- Get waiting hour configuration for all lorries
SELECT
  `id`,
  `name`,
  JSON_EXTRACT(`rate_table`, '$.*.type') AS 'Type',
  JSON_EXTRACT(`rate_table`, '$.*.freeWaitingHours') AS 'Free Hours',
  JSON_EXTRACT(`rate_table`, '$.*.waitingChargePerHour') AS 'Charge/Hour'
FROM `lorries`;

-- List all rate types available
SELECT
  `id`,
  `name`,
  JSON_KEYS(`rate_table`) AS 'Available Rates'
FROM `lorries`;

-- =====================================================
-- BACKUP & MAINTENANCE
-- =====================================================

-- View table size
SELECT
  TABLE_NAME,
  ROUND(((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024), 2) AS 'Size (MB)'
FROM INFORMATION_SCHEMA.TABLES
WHERE TABLE_NAME = 'lorries';

-- Optimize table
OPTIMIZE TABLE `lorries`;

-- Check for any corrupted data
CHECK TABLE `lorries`;

-- Repair if needed
REPAIR TABLE `lorries`;
