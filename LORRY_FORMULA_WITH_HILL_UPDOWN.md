# Lorry Fare Calculation - With Hill & Up/Down

## New Formula

```
TOTAL_FARE = BASE_FEE 
           + EXTRA_FEE 
           + HILL_SURCHARGE (if hill location)
           + UP_DOWN_CHARGE (if round-trip)
```

---

## Rate Table Structure (Updated)

```json
{
  "7ft": {
    "type": "7 FT",
    "windows": [
      {
        "fromKm": 0,
        "toKm": 130,
        "rate": 2500,
        "extraPerKm": 160,
        "hillExtraPerKm": 10      // ← Hill surcharge per km
      }
    ],
    "upDownNonHill": 120,          // ← Round-trip non-hill
    "upDownHill": 200              // ← Round-trip hill (different!)
  }
}
```

---

## Components

### 1. Base Fee
- From `window.rate`
- Covers distance 0 to `window.toKm`

### 2. Extra Fee
- Only if distance > `window.toKm`
- `(distance - window.toKm) × window.extraPerKm`

### 3. Hill Surcharge (if hill location)
- `distance × window.hillExtraPerKm`
- **Only applied if location is hill country**

### 4. Up/Down Charge (if round-trip)
- Use `upDownNonHill` if NOT hill country
- Use `upDownHill` if IS hill country
- **Only applied if trip type is "round-trip"**

---

## Examples

### Example 1: Non-hill, One-way, 150km
```
Distance: 150 km
Trip: One-way
Location: Normal (NOT hill)
Window: [0-130, rate: 2500, extra: 160, hillExtra: 10]
upDownNonHill: 120
upDownHill: 200

Base Fee: 2500
Extra Fee: (150-130) × 160 = 3200
Hill Surcharge: 0 (NOT hill location)
Up/Down: 0 (One-way, not round-trip)

TOTAL: 2500 + 3200 + 0 + 0 = Rs. 5,700
```

### Example 2: Hill, Round-trip, 150km
```
Distance: 150 km
Trip: Round-trip
Location: HILL country
Window: [0-130, rate: 2500, extra: 160, hillExtra: 10]
upDownNonHill: 120
upDownHill: 200

Base Fee: 2500
Extra Fee: (150-130) × 160 = 3200
Hill Surcharge: 150 × 10 = 1500 (HILL location!)
Up/Down: 200 (Round-trip + HILL)

TOTAL: 2500 + 3200 + 1500 + 200 = Rs. 7,400
```

### Example 3: Non-hill, Round-trip, 80km
```
Distance: 80 km
Trip: Round-trip
Location: Normal (NOT hill)
Window: [0-130, rate: 2500, extra: 160, hillExtra: 10]
upDownNonHill: 120
upDownHill: 200

Base Fee: 2500
Extra Fee: 0 (80 < 130)
Hill Surcharge: 0 (NOT hill)
Up/Down: 120 (Round-trip + NON-hill)

TOTAL: 2500 + 0 + 0 + 120 = Rs. 2,620
```

### Example 4: Hill, Round-trip, 200km
```
Distance: 200 km
Trip: Round-trip
Location: HILL country
Window: [0-130, rate: 2500, extra: 160, hillExtra: 10]
upDownNonHill: 120
upDownHill: 200

Base Fee: 2500
Extra Fee: (200-130) × 160 = 11,200
Hill Surcharge: 200 × 10 = 2000 (HILL!)
Up/Down: 200 (Round-trip + HILL)

TOTAL: 2500 + 11200 + 2000 + 200 = Rs. 15,900
```

---

## Implementation

### PHP (Backend - LorryEstimator.php)

```php
public function estimate(
    array $rate, 
    float $distanceKm, 
    string $trip, 
    bool $isHillCountry
): array {
    // Step 1: Base fee + Extra fee
    $baseFee = 0.0;
    $extraKm = 0.0;
    $extraFee = 0.0;
    $hillExtraPerKm = 0.0;

    foreach ($rate['windows'] as $window) {
        $fromKm = (float) $window['fromKm'];
        $toKm = $window['toKm'] ?? null;
        
        if ($distanceKm >= $fromKm) {
            if ($toKm === null || $distanceKm <= $toKm) {
                $baseFee = (float) $window['rate'];
                $hillExtraPerKm = (float) ($window['hillExtraPerKm'] ?? 0);
                break;
            } else {
                $baseFee = (float) $window['rate'];
                $extraKm = $distanceKm - $toKm;
                $extraFee = round($extraKm * (float) $window['extraPerKm'], 2);
                $hillExtraPerKm = (float) ($window['hillExtraPerKm'] ?? 0);
            }
        }
    }

    // Step 2: Hill surcharge (if hill location)
    $hillSurcharge = 0.0;
    if ($isHillCountry && $hillExtraPerKm > 0) {
        $hillSurcharge = round($distanceKm * $hillExtraPerKm, 2);
    }

    // Step 3: Up/Down charge (if round-trip)
    $upDownCharge = 0.0;
    $isRoundTrip = in_array(strtolower($trip), ['round-trip', 'round trip'], true);
    
    if ($isRoundTrip) {
        if ($isHillCountry) {
            $upDownCharge = (float) ($rate['upDownHill'] ?? 0);
        } else {
            $upDownCharge = (float) ($rate['upDownNonHill'] ?? 0);
        }
    }

    // Total
    $totalFare = round($baseFee + $extraFee + $hillSurcharge + $upDownCharge, 2);

    return [
        'distance_km' => round($distanceKm, 2),
        'is_hill_country' => $isHillCountry,
        'trip' => $isRoundTrip ? 'round-trip' : 'one-way',
        'base_fee' => round($baseFee, 2),
        'extra_km' => round($extraKm, 2),
        'extra_fee' => $extraFee,
        'hill_surcharge' => $hillSurcharge,
        'up_down_charge' => round($upDownCharge, 2),
        'total_cost' => $totalFare,
    ];
}
```

### TypeScript (Frontend - BookingForm.tsx)

```typescript
let lorryBaseFee = 0;
let lorryExtraKm = 0;
let lorryExtraFee = 0;
let lorryHillSurcharge = 0;
let lorryUpDownCharge = 0;

if (totalKm && activeLorryRate?.windows?.length) {
  const windows = activeLorryRate.windows;
  let hillExtraPerKm = 0;
  
  for (const window of windows) {
    const windowStart = window.fromKm;
    const windowEnd = window.toKm ?? totalKm;

    if (totalKm >= windowStart) {
      if (totalKm <= windowEnd) {
        lorryBaseFee = window.rate;
        hillExtraPerKm = window.hillExtraPerKm ?? 0;
        break;
      } else {
        lorryBaseFee = window.rate;
        lorryExtraKm = totalKm - windowEnd;
        lorryExtraFee = lorryExtraKm * window.extraPerKm;
        hillExtraPerKm = window.hillExtraPerKm ?? 0;
      }
    }
  }

  // Hill surcharge (if hill location)
  if (selectedHillCountry && hillExtraPerKm > 0) {
    lorryHillSurcharge = totalKm * hillExtraPerKm;
  }

  // Up/Down charge (if round-trip)
  const isRoundTrip = form.trip === 'Round Trip';
  if (isRoundTrip) {
    if (selectedHillCountry) {
      lorryUpDownCharge = activeLorryRate.upDownHill ?? 0;
    } else {
      lorryUpDownCharge = activeLorryRate.upDownNonHill ?? 0;
    }
  }
}

const lorryFare = lorryBaseFee + lorryExtraFee + lorryHillSurcharge + lorryUpDownCharge;
```

---

## Admin Panel Fields

For each lorry type, admin enters:

```
Window 1:
  ├─ From KM: 0
  ├─ To KM: 130
  ├─ Base Rate: 2500
  ├─ Extra Per KM: 160
  └─ Hill Extra Per KM: 10    ← NEW

Charges:
  ├─ Up & Down (Non-Hill): 120   ← NEW
  └─ Up & Down (Hill): 200       ← NEW
```

---

## Test Cases

| Distance | Trip | Location | Base | Extra | Hill | UpDown | Total |
|----------|------|----------|------|-------|------|--------|-------|
| 100km | One-way | Normal | 2500 | 0 | 0 | 0 | 2500 |
| 100km | One-way | Hill | 2500 | 0 | 1000 | 0 | 3500 |
| 100km | Round-trip | Normal | 2500 | 0 | 0 | 120 | 2620 |
| 100km | Round-trip | Hill | 2500 | 0 | 1000 | 200 | 3700 |
| 150km | One-way | Normal | 2500 | 3200 | 0 | 0 | 5700 |
| 150km | One-way | Hill | 2500 | 3200 | 1500 | 0 | 7200 |
| 150km | Round-trip | Normal | 2500 | 3200 | 0 | 120 | 5820 |
| 150km | Round-trip | Hill | 2500 | 3200 | 1500 | 200 | 7400 |
