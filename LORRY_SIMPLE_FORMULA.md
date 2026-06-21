# Lorry Fare Calculation - Simple Formula

## Formula

```
TOTAL_FARE = BASE_FEE + EXTRA_FEE
```

---

## Components

### 1. Base Fee
- From the applicable **window.rate**
- Covers distance from 0 km up to **window.toKm**

### 2. Extra Fee
- Only charged if distance **exceeds window.toKm**
- Calculation: `(distance_km - window.toKm) × window.extraPerKm`

---

## Examples

### Example 1: Distance Within Window
```
Distance: 100 km
Window: [fromKm: 0, toKm: 130, rate: 2500, extraPerKm: 160]

Base Fee: 2500
Extra Fee: 0 (distance 100 ≤ window 130)

TOTAL: Rs. 2,500
```

### Example 2: Distance Exceeds Window
```
Distance: 150 km
Window: [fromKm: 0, toKm: 130, rate: 2500, extraPerKm: 160]

Base Fee: 2500
Extra KM: 150 - 130 = 20 km
Extra Fee: 20 × 160 = Rs. 3,200

TOTAL: 2500 + 3200 = Rs. 5,700
```

### Example 3: Large Distance
```
Distance: 250 km
Window: [fromKm: 0, toKm: 130, rate: 2500, extraPerKm: 160]

Base Fee: 2500
Extra KM: 250 - 130 = 120 km
Extra Fee: 120 × 160 = Rs. 19,200

TOTAL: 2500 + 19200 = Rs. 21,700
```

---

## Multiple Windows

If rate table has multiple windows:

```javascript
"windows": [
  { fromKm: 0,   toKm: 100,  rate: 2000, extraPerKm: 150 },
  { fromKm: 100, toKm: 250,  rate: 3500, extraPerKm: 200 },
  { fromKm: 250, toKm: null, rate: 5000, extraPerKm: 250 }
]
```

**For 175 km distance:**
1. Check window 1: 175 >= 0 but 175 > 100 → continue
2. Check window 2: 175 >= 100 AND 175 ≤ 250 → **USE THIS WINDOW**
3. Base Fee: 3500
4. Extra KM: 0 (distance within window)
5. Extra Fee: 0

**TOTAL: Rs. 3,500**

**For 300 km distance:**
1. Check window 1: 300 >= 0 but 300 > 100 → continue
2. Check window 2: 300 >= 100 but 300 > 250 → continue
3. Check window 3: 300 >= 250 → **USE THIS WINDOW**
4. Base Fee: 5000
5. Extra KM: 300 - 250 = 50 km
6. Extra Fee: 50 × 250 = Rs. 12,500

**TOTAL: 5000 + 12500 = Rs. 17,500**

---

## Implementation

### PHP (Backend - LorryEstimator.php)
```php
$baseFee = 0.0;
$extraKm = 0.0;
$extraFee = 0.0;

foreach ($windows as $window) {
    $fromKm = $window['fromKm'];
    $toKm = $window['toKm'] ?? null;
    
    if ($distanceKm >= $fromKm) {
        if ($toKm === null || $distanceKm <= $toKm) {
            $baseFee = $window['rate'];
            break;
        } else {
            $baseFee = $window['rate'];
            $extraKm = $distanceKm - $toKm;
            $extraFee = $extraKm * $window['extraPerKm'];
        }
    }
}

$totalFare = $baseFee + $extraFee;
```

### TypeScript (Frontend - BookingForm.tsx)
```typescript
let lorryBaseFee = 0;
let lorryExtraKm = 0;
let lorryExtraFee = 0;

for (const window of windows) {
    const windowStart = window.fromKm;
    const windowEnd = window.toKm ?? totalKm;

    if (totalKm >= windowStart) {
        if (totalKm <= windowEnd) {
            lorryBaseFee = window.rate;
            break;
        } else {
            lorryBaseFee = window.rate;
            lorryExtraKm = totalKm - windowEnd;
            lorryExtraFee = lorryExtraKm * window.extraPerKm;
        }
    }
}

const lorryFare = lorryBaseFee + lorryExtraFee;
```

---

## API Response Format

```json
{
    "success": true,
    "lorry": "7 FT Lorry",
    "rate_type": "7ft",
    "distance_km": 150,
    "is_hill_country": false,
    "base_fee": 2500,
    "extra_km": 20,
    "extra_fee": 3200,
    "total_cost": 5700
}
```

---

## Rate Table Format

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
                "hillExtraPerKm": 10
            }
        ],
        "extraUpDownCharge": 120,
        "waitingChargePerHour": 600
    }
}
```

**Note:** Only `rate` and `extraPerKm` are used in fare calculation. Other fields are for reference/admin purposes.

---

## Quick Test Cases

| Distance | Window | Base Fee | Extra KM | Extra Fee | Total |
|----------|--------|----------|----------|-----------|-------|
| 50 km    | 0-130  | 2500     | 0        | 0         | 2500  |
| 130 km   | 0-130  | 2500     | 0        | 0         | 2500  |
| 150 km   | 0-130  | 2500     | 20       | 3200      | 5700  |
| 200 km   | 0-130  | 2500     | 70       | 11200     | 13700 |
| 250 km   | 0-130  | 2500     | 120      | 19200     | 21700 |
