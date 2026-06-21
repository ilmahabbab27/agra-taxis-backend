# Lorry Fare Calculation Formula

## Overview
Lorry fares are calculated using a **window-based rate table** where different km ranges have different pricing rules.

---

## Input Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `distance_km` | float | Total distance in kilometers |
| `trip` | string | Trip type: `"one-way"` or `"round-trip"` |
| `isHillCountry` | boolean | Whether route is in hill country |
| `waitingHours` | float | Hours of waiting time (optional) |

---

## Rate Table Structure

```javascript
{
  "type": "7 FT",
  "windows": [
    {
      "fromKm": 0,           // Window starts at 0km
      "toKm": 130,           // Window ends at 130km
      "rate": 2500,          // Base fare for this window
      "extraPerKm": 160,     // Cost per km beyond toKm
      "hillExtraPerKm": 10   // Hill country surcharge per km
    }
  ],
  "extraUpDownCharge": 120,     // Flat surcharge for round-trip
  "waitingChargePerHour": 600   // Hourly waiting charge
}
```

---

## Step-by-Step Calculation

### Step 1: Find Applicable Window
```
For distance_km, find the window where:
  distance_km >= window.fromKm
  
Most cases use the first (and often only) window if distance >= fromKm
```

**Example:** For 150km distance with window [0-130]:
- 150 >= 0 ✓ (applicable window found)

---

### Step 2: Calculate Base Fare

```
IF distance_km <= window.toKm:
  BASE_FARE = window.rate
  
ELSE:
  BASE_FARE = window.rate
  EXTRA_KM = distance_km - window.toKm
  EXTRA_CHARGE = EXTRA_KM * window.extraPerKm
```

**Example:** 150km with window toKm=130, rate=2500, extraPerKm=160
```
distance_km (150) > window.toKm (130)
EXTRA_KM = 150 - 130 = 20km
EXTRA_CHARGE = 20 * 160 = Rs. 3,200

BASE_FARE = 2500 + 3200 = Rs. 5,700
```

---

### Step 3: Calculate Hill Country Surcharge

```
IF isHillCountry == true:
  HILL_CHARGE = distance_km * window.hillExtraPerKm
ELSE:
  HILL_CHARGE = 0
```

**Example:** 150km hill country with hillExtraPerKm=10
```
HILL_CHARGE = 150 * 10 = Rs. 1,500
```

---

### Step 4: Calculate Waiting Charge

```
IF waitingHours > 0:
  WAITING_CHARGE = waitingHours * waitingChargePerHour
ELSE:
  WAITING_CHARGE = 0
```

**Example:** 2.5 hours waiting with waitingChargePerHour=600
```
WAITING_CHARGE = 2.5 * 600 = Rs. 1,500
```

---

### Step 5: Add Round-Trip Surcharge (if applicable)

```
IF trip == "round-trip":
  ROUND_TRIP_CHARGE = extraUpDownCharge
ELSE:
  ROUND_TRIP_CHARGE = 0
```

**Example:** Round-trip with extraUpDownCharge=120
```
ROUND_TRIP_CHARGE = 120
```

---

### Step 6: Calculate Total Fare

```
TOTAL_FARE = BASE_FARE 
           + HILL_CHARGE 
           + WAITING_CHARGE 
           + ROUND_TRIP_CHARGE
```

---

## Complete Example

### Scenario: 7 FT Lorry
- **Distance:** 150 km
- **Trip:** One-way
- **Hill Country:** Yes
- **Waiting:** 2 hours
- **Rate Window:** [0-130, rate=2500, extraPerKm=160, hillExtraPerKm=10]
- **extraUpDownCharge:** 120
- **waitingChargePerHour:** 600

### Calculation

| Component | Formula | Value |
|-----------|---------|-------|
| **Base Fare** | 2500 (window.rate) | Rs. 2,500 |
| **Extra KM** | (150 - 130) × 160 | Rs. 3,200 |
| **Subtotal** | 2500 + 3200 | **Rs. 5,700** |
| **Hill Surcharge** | 150 × 10 | Rs. 1,500 |
| **Waiting Charge** | 2 × 600 | Rs. 1,200 |
| **Round-trip** | 0 (one-way) | Rs. 0 |
| | | |
| **TOTAL FARE** | | **Rs. 8,400** |

---

## Multiple Windows Example

If a lorry has multiple windows:

```javascript
"windows": [
  { "fromKm": 0, "toKm": 100, "rate": 2000, "extraPerKm": 150 },
  { "fromKm": 100, "toKm": 250, "rate": 3500, "extraPerKm": 200 },
  { "fromKm": 250, "toKm": null, "rate": 5000, "extraPerKm": 250 }
]
```

### For 275km distance:
1. Check window 1: 275 >= 0 ✓ but 275 > 100, continue
2. Check window 2: 275 >= 100 ✓ but 275 > 250, continue
3. Check window 3: 275 >= 250 ✓ and toKm is null (unbounded)
4. **Use window 3:** base = 5000, extra = (275 - 250) × 250 = 6,250
5. **Total for distance:** 5000 + 6250 = **Rs. 11,250**

---

## Edge Cases

### Case 1: Distance Within Window
```
Distance: 80km
Window: [0-130, rate=2500, extraPerKm=160]
Result: BASE_FARE = 2500 (no extra km charge)
```

### Case 2: Distance Exactly at Boundary
```
Distance: 130km
Window: [0-130, rate=2500, extraPerKm=160]
Result: BASE_FARE = 2500 (EXTRA_KM = 0)
```

### Case 3: Very Large Distance
```
Distance: 500km
Window: [0-130, rate=2500, extraPerKm=160]
EXTRA_KM = 500 - 130 = 370
EXTRA_CHARGE = 370 * 160 = 59,200
Result: BASE_FARE = 2500 + 59,200 = 61,700
```

---

## PHP Implementation (LorryEstimator.php)

```php
public function estimate(array $rate, float $distanceKm, string $trip, bool $isHillCountry, float $waitingHours = 0): array
{
    $isRoundTrip = in_array(strtolower($trip), ['round-trip', 'round trip'], true);
    $extraUpDownCharge = (float) ($rate['extraUpDownCharge'] ?? 0);
    $waitingChargePerHour = (float) ($rate['waitingChargePerHour'] ?? 0);

    // Find applicable window and calculate base fare
    $startCharge = 0.0;
    $extraKm = 0.0;
    $extraCharge = 0.0;
    $windowHillExtra = 0.0;

    foreach ($rate['windows'] as $window) {
        $fromKm = (float) $window['fromKm'];
        $toKm = $window['toKm'] ?? null;
        
        if ($distanceKm >= $fromKm) {
            if ($toKm === null || $distanceKm <= $toKm) {
                $startCharge = $window['rate'];
                break;
            } else {
                $startCharge = $window['rate'];
                $extraKm = $distanceKm - $toKm;
                $extraCharge = $extraKm * $window['extraPerKm'];
            }
        }
        $windowHillExtra = $window['hillExtraPerKm'] ?? 0;
    }

    $baseFare = $startCharge + $extraCharge;
    $hillCharge = $isHillCountry ? $distanceKm * $windowHillExtra : 0;
    $waitingCharge = $waitingHours > 0 ? $waitingHours * $waitingChargePerHour : 0;

    if ($isRoundTrip) {
        $startCharge += $extraUpDownCharge;
        $baseFare = $startCharge + $extraCharge;
    }

    $totalFare = $baseFare + $hillCharge + $waitingCharge;

    return [
        'start_charge' => $startCharge,
        'extra_km' => $extraKm,
        'extra_charge' => $extraCharge,
        'base_fare' => $baseFare,
        'hill_charge' => $hillCharge,
        'waiting_charge' => $waitingCharge,
        'total_cost' => $totalFare,
    ];
}
```

---

## Frontend Implementation (BookingForm.tsx)

The frontend uses the **same logic** to calculate fares during booking:

```typescript
// Simplified version from BookingForm.tsx
if (totalKm && activeLorryRate?.windows?.length) {
  const windows = activeLorryRate.windows;
  
  for (const window of windows) {
    const windowStart = window.fromKm;
    const windowEnd = window.toKm ?? totalKm;
    
    if (totalKm >= windowStart) {
      if (totalKm <= windowEnd) {
        lorryStartCharge = window.rate;
        break;
      } else if (window === windows[windows.length - 1]) {
        lorryStartCharge = window.rate;
        lorryExtraKm = totalKm - windowEnd;
        lorryExtraCharge = lorryExtraKm * window.extraPerKm;
      }
    }
  }
  
  if (selectedHillCountry && totalKm) {
    lorryHillExtraPerKm = windows[0].hillExtraPerKm ?? 0;
    lorryHillCharge = totalKm * lorryHillExtraPerKm;
  }
}

const lorryBaseFare = lorryStartCharge + lorryExtraCharge + lorryHillCharge;
const lorryFare = lorryDays === 1 ? lorryBaseFare : lorryBaseFare * lorryDays;
```

---

## Testing Formula

### Test Case 1: Basic One-Way
```
Input: 100km, one-way, normal (not hill)
Window: [0-130, rate=2500, extraPerKm=160, hillExtraPerKm=10]
Expected: Rs. 2,500 (base only, no extra km)
```

### Test Case 2: One-Way with Extra KM
```
Input: 150km, one-way, normal
Window: [0-130, rate=2500, extraPerKm=160, hillExtraPerKm=10]
Expected: 2500 + (20×160) = Rs. 5,700
```

### Test Case 3: Hill Country
```
Input: 150km, one-way, hill country
Window: [0-130, rate=2500, extraPerKm=160, hillExtraPerKm=10]
Expected: 5700 + (150×10) = Rs. 7,200
```

### Test Case 4: Round-Trip
```
Input: 100km, round-trip, normal
Window: [0-130, rate=2500, extraPerKm=160]
extraUpDownCharge: 120
Expected: 2500 + 120 = Rs. 2,620
```

### Test Case 5: With Waiting
```
Input: 100km, one-way, 3 hours waiting
Window: [0-130, rate=2500, extraPerKm=160]
waitingChargePerHour: 600
Expected: 2500 + (3×600) = Rs. 4,300
```

---

## Summary Formula

```
TOTAL_FARE = 
    BASE_RATE 
    + (MAX(distance - window.toKm, 0) × extraPerKm)
    + (isHillCountry ? distance × hillExtraPerKm : 0)
    + (isRoundTrip ? extraUpDownCharge : 0)
    + (waitingHours × waitingChargePerHour)
```
