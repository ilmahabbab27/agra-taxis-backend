-- =====================================================
-- AGRA TAXIS - LORRIES TABLE SETUP
-- Complete SQL for lorry rates database
-- =====================================================

-- Drop table if exists (backup data first!)
DROP TABLE IF EXISTS `lorries`;

-- Create lorries table
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
  `rate_table` json NOT NULL COMMENT 'Stores lorry rate windows as JSON with upDownNonHill and upDownHill',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `category_idx` (`category`),
  KEY `name_idx` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- INSERT SAMPLE DATA
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
      'upDownHill', 200
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
      'upDownHill', 500
    )
  )
);

-- =====================================================
-- VERIFY DATA
-- =====================================================

-- View all lorries
SELECT `id`, `name`, `category`, `seats` FROM `lorries`;

-- View specific lorry with rates
SELECT `id`, `name`, `rate_table`
FROM `lorries`
WHERE `name` = 'Agra 7FT Lorry';

-- Extract rate type from JSON
SELECT
  `id`,
  `name`,
  JSON_EXTRACT(`rate_table`, '$.7ft.type') AS 'Rate Type',
  JSON_EXTRACT(`rate_table`, '$.7ft.windows[0].rate') AS 'Base Rate',
  JSON_EXTRACT(`rate_table`, '$.7ft.windows[0].extraPerKm') AS 'Extra Per KM',
  JSON_EXTRACT(`rate_table`, '$.7ft.upDownNonHill') AS 'Up/Down Non-Hill',
  JSON_EXTRACT(`rate_table`, '$.7ft.upDownHill') AS 'Up/Down Hill'
FROM `lorries`;

-- =====================================================
-- SAMPLE QUERIES
-- =====================================================

-- Get all rate types for a lorry
SELECT JSON_KEYS(`rate_table`) AS 'Available Rate Types'
FROM `lorries`
WHERE `name` = 'Agra 7FT Lorry';

-- Get specific window for a rate type
SELECT
  `id`,
  `name`,
  JSON_EXTRACT(`rate_table`, '$.7ft.windows[0]') AS 'Window Details'
FROM `lorries`
WHERE `id` = 1;

-- Check if lorry has valid rates
SELECT
  `id`,
  `name`,
  CASE
    WHEN `rate_table` IS NULL THEN 'No rates'
    WHEN JSON_LENGTH(`rate_table`) = 0 THEN 'Empty rates'
    ELSE 'Has rates'
  END AS 'Status'
FROM `lorries`;

-- =====================================================
-- UPDATE EXAMPLES
-- =====================================================

-- Update a single rate value
UPDATE `lorries`
SET `rate_table` = JSON_SET(
  `rate_table`,
  '$.7ft.windows[0].rate',
  3000
)
WHERE `name` = 'Agra 7FT Lorry';

-- Update hill surcharge
UPDATE `lorries`
SET `rate_table` = JSON_SET(
  `rate_table`,
  '$.7ft.windows[0].hillExtraPerKm',
  15
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

-- =====================================================
-- RATE TABLE JSON STRUCTURE REFERENCE
-- =====================================================

/*
Complete rate_table structure:

{
  "7ft": {
    "type": "7 FT",
    "windows": [
      {
        "fromKm": 0,
        "toKm": 130,
        "rate": 2500,
        "extraPerKm": 160,
        "hillExtraPerKm": 10
      }
    ],
    "upDownNonHill": 120,
    "upDownHill": 200
  },
  "20ft": {
    "type": "20 FT",
    "windows": [
      {
        "fromKm": 0,
        "toKm": 130,
        "rate": 18000,
        "extraPerKm": 450,
        "hillExtraPerKm": 10
      }
    ],
    "upDownNonHill": 300,
    "upDownHill": 500
  }
}

Key fields:
- type: Display name of rate type
- windows: Array of km range brackets
  - fromKm: Window starts at
  - toKm: Window ends at
  - rate: Base fee for this window
  - extraPerKm: Per-km rate beyond toKm
  - hillExtraPerKm: Hill surcharge per km
- upDownNonHill: Round-trip charge for non-hill location
- upDownHill: Round-trip charge for hill location
*/

-- =====================================================
-- LARAVEL MIGRATION (Alternative)
-- =====================================================

/*
If using Laravel migrations instead, use this:

php artisan make:migration create_lorries_table

Then in database/migrations/YYYY_MM_DD_HHMMSS_create_lorries_table.php:

<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lorries', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('category')->default('Lorries');
            $table->longText('img')->nullable();
            $table->longText('img2')->nullable();
            $table->longText('img3')->nullable();
            $table->longText('img4')->nullable();
            $table->longText('img5')->nullable();
            $table->unsignedTinyInteger('seats')->default(1);
            $table->boolean('ac_available')->default(false);
            $table->boolean('non_ac_available')->default(false);
            $table->json('rate_table')
                  ->comment('Stores lorry rate windows as JSON');
            $table->timestamps();
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lorries');
    }
};

Then run:
php artisan migrate
*/

-- =====================================================
-- USEFUL ADMIN QUERIES
-- =====================================================

-- Find lorries with invalid/missing rates
SELECT `id`, `name`
FROM `lorries`
WHERE `rate_table` IS NULL
   OR JSON_LENGTH(`rate_table`) = 0;

-- Get all available rate types across all lorries
SELECT DISTINCT
  `id`,
  `name`,
  JSON_KEYS(`rate_table`) AS 'Available Rates'
FROM `lorries`;

-- Find rate type with specific price
SELECT `name`
FROM `lorries`
WHERE JSON_CONTAINS(
  `rate_table`,
  JSON_OBJECT('rate', 2500),
  '$.7ft.windows[0]'
);

-- =====================================================
-- MAINTENANCE
-- =====================================================

-- Backup before modifications
SELECT * FROM `lorries` INTO OUTFILE '/var/lib/mysql/backup_lorries.json';

-- Check table size
SELECT
  TABLE_NAME,
  ROUND(((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024), 2) AS 'Size (MB)'
FROM INFORMATION_SCHEMA.TABLES
WHERE TABLE_NAME = 'lorries'
AND TABLE_SCHEMA = 'DATABASE_NAME';

-- Check if rate_table column can be optimized
OPTIMIZE TABLE `lorries`;

-- Rebuild indexes if needed
REPAIR TABLE `lorries`;
