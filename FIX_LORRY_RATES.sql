-- Fix Lorry Rates with Single 0-130 km Window
UPDATE `lorries`
SET `rate_table` = JSON_OBJECT(
  '7ft', JSON_OBJECT(
    'type', '7 FT',
    'windows', JSON_ARRAY(
      JSON_OBJECT('fromKm', 0, 'toKm', 130, 'rate', 2500, 'extraPerKm', 160, 'hillExtraPerKm', 10)
    ),
    'upDownNonHill', 120,
    'upDownHill', 200,
    'freeWaitingHours', 2,
    'waitingChargePerHour', 500
  ),
  '8_5ft', JSON_OBJECT(
    'type', '8.5 FT',
    'windows', JSON_ARRAY(
      JSON_OBJECT('fromKm', 0, 'toKm', 130, 'rate', 3500, 'extraPerKm', 180, 'hillExtraPerKm', 10)
    ),
    'upDownNonHill', 130,
    'upDownHill', 220,
    'freeWaitingHours', 2,
    'waitingChargePerHour', 600
  ),
  '10_5ft', JSON_OBJECT(
    'type', '10.5 FT',
    'windows', JSON_ARRAY(
      JSON_OBJECT('fromKm', 0, 'toKm', 130, 'rate', 6000, 'extraPerKm', 230, 'hillExtraPerKm', 10)
    ),
    'upDownNonHill', 170,
    'upDownHill', 280,
    'freeWaitingHours', 2,
    'waitingChargePerHour', 700
  ),
  '12_5ft', JSON_OBJECT(
    'type', '12.5 FT',
    'windows', JSON_ARRAY(
      JSON_OBJECT('fromKm', 0, 'toKm', 130, 'rate', 7500, 'extraPerKm', 250, 'hillExtraPerKm', 10)
    ),
    'upDownNonHill', 180,
    'upDownHill', 300,
    'freeWaitingHours', 2,
    'waitingChargePerHour', 750
  ),
  '14_5ft', JSON_OBJECT(
    'type', '14.5 FT',
    'windows', JSON_ARRAY(
      JSON_OBJECT('fromKm', 0, 'toKm', 130, 'rate', 10000, 'extraPerKm', 320, 'hillExtraPerKm', 10)
    ),
    'upDownNonHill', 210,
    'upDownHill', 350,
    'freeWaitingHours', 2,
    'waitingChargePerHour', 800
  ),
  '16_5ft', JSON_OBJECT(
    'type', '16.5 FT',
    'windows', JSON_ARRAY(
      JSON_OBJECT('fromKm', 0, 'toKm', 130, 'rate', 11000, 'extraPerKm', 330, 'hillExtraPerKm', 10)
    ),
    'upDownNonHill', 220,
    'upDownHill', 360,
    'freeWaitingHours', 2,
    'waitingChargePerHour', 850
  ),
  '18_5ft', JSON_OBJECT(
    'type', '18.5 FT',
    'windows', JSON_ARRAY(
      JSON_OBJECT('fromKm', 0, 'toKm', 130, 'rate', 15000, 'extraPerKm', 380, 'hillExtraPerKm', 10)
    ),
    'upDownNonHill', 270,
    'upDownHill', 450,
    'freeWaitingHours', 2,
    'waitingChargePerHour', 900
  ),
  '20ft', JSON_OBJECT(
    'type', '20 FT',
    'windows', JSON_ARRAY(
      JSON_OBJECT('fromKm', 0, 'toKm', 130, 'rate', 18000, 'extraPerKm', 450, 'hillExtraPerKm', 10)
    ),
    'upDownNonHill', 300,
    'upDownHill', 500,
    'freeWaitingHours', 2,
    'waitingChargePerHour', 800
  )
)
WHERE `name` = 'Agra Lorries';

-- Verify
SELECT `id`, `name`, JSON_KEYS(`rate_table`) AS 'Lorry Types'
FROM `lorries`
WHERE `name` = 'Agra Lorries';
