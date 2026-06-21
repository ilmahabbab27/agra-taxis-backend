# Lorry Fare Calculation - With Waiting Hours

## New Formula

```
TOTAL_FARE = BASE_FEE 
           + EXTRA_FEE (if distance > window.toKm)
           + HILL_SURCHARGE (if hill location)
           + UP_DOWN_CHARGE (if round-trip)
           + WAITING_CHARGE (if waiting > free hours)
```

---

## Components

### 1-4: Base + Extra + Hill + Up/Down
(Same as before - see FINAL_LORRY_CALCULATION_SUMMARY.md)

### 5. **Waiting Charge** ⏱️ (NEW)

Only charged for waiting hours **BEYOND free waiting hours**:

```
IF waitingHours > freeWaitingHours:
  WAITING_CHARGE = (waitingHours - freeWaitingHours) × waitingChargePerHour
ELSE:
  WAITING_CHARGE = 0
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
        "hillExtraPerKm": 10
      }
    ],
    "upDownNonHill": 120,
    "upDownHill": 200,
    "freeWaitingHours": 2,        // ← NEW! Free waiting time
    "waitingChargePerHour": 500   // ← NEW! Charge after free hours
  }
}
```

---

## Examples

### Example 1: 1 Hour Waiting (Within Free Hours)
```
Waiting Hours: 1 hour
Free Waiting: 2 hours
waitingChargePerHour: 500

Chargeable Hours: MAX(1 - 2, 0) = 0
WAITING_CHARGE: 0 × 500 = Rs. 0

✓ No charge (within free period)
```

### Example 2: 3 Hours Waiting (Exceeds Free Hours)
```
Waiting Hours: 3 hours
Free Waiting: 2 hours
waitingChargePerHour: 500

Chargeable Hours: 3 - 2 = 1 hour
WAITING_CHARGE: 1 × 500 = Rs. 500

✓ Charge only for 1 hour (beyond free 2 hours)
```

### Example 3: 6 Hours Waiting
```
Waiting Hours: 6 hours
Free Waiting: 2 hours
waitingChargePerHour: 500

Chargeable Hours: 6 - 2 = 4 hours
WAITING_CHARGE: 4 × 500 = Rs. 2,000

✓ Charge for 4 hours (beyond free 2 hours)
```

### Example 4: Full Fare - 150km + 4 Hours Waiting
```
Distance: 150 km
Trip: Round-trip
Location: HILL
Waiting: 4 hours
Window: [rate:2500, extra:160, hill:10]
upDownNonHill: 120, upDownHill: 200
freeWaitingHours: 2, waitingChargePerHour: 500

Base Fee:         2500
Extra Fee:        3200   (20 × 160)
Hill Surcharge:   1500   (150 × 10)
Up/Down:          200    (Round-trip + HILL)
Waiting Charge:   1000   ((4-2) × 500) ← NEW!

TOTAL: 2500 + 3200 + 1500 + 200 + 1000 = Rs. 8,400
```

---

## API Request/Response

### Request with Waiting Hours:
```json
{
  "lorry_id": 1,
  "rate_type": "7ft",
  "distance_km": 150,
  "trip": "round-trip",
  "waiting_hours": 4,           // ← NEW! User specifies waiting
  "pickup_lat": 6.9271,
  "pickup_lng": 80.7789,
  "dropoff_lat": 6.8000,
  "dropoff_lng": 80.7500
}
```

### Response:
```json
{
  "success": true,
  "lorry": "7FT Lorry",
  "distance_km": 150,
  "is_hill_country": true,
  "trip": "round-trip",
  
  "base_fee": 2500,
  "extra_km": 20,
  "extra_fee": 3200,
  "hill_surcharge": 1500,
  "up_down_charge": 200,
  "waiting_hours": 4,
  "free_waiting_hours": 2,
  "chargeable_waiting_hours": 2,    // ← NEW!
  "waiting_charge": 1000,           // ← NEW!
  
  "total_cost": 8400
}
```

---

## Backend Implementation

### LorryEstimator.php

```php
public function estimate(
    array $rate, 
    float $distanceKm, 
    string $trip, 
    bool $isHillCountry,
    float $waitingHours = 0
): array {
    // ... existing code for base, extra, hill, upDown ...
    
    // NEW: Calculate waiting charge
    $freeWaitingHours = (float) ($rate['freeWaitingHours'] ?? 0);
    $waitingChargePerHour = (float) ($rate['waitingChargePerHour'] ?? 0);
    $chargeableWaitingHours = 0.0;
    $waitingCharge = 0.0;
    
    if ($waitingHours > $freeWaitingHours && $waitingChargePerHour > 0) {
        $chargeableWaitingHours = $waitingHours - $freeWaitingHours;
        $waitingCharge = round($chargeableWaitingHours * $waitingChargePerHour, 2);
    }
    
    $totalFare = round(
        $baseFee + $extraFee + $hillSurcharge + $upDownCharge + $waitingCharge,
        2
    );

    return [
        'trip'                      => $isRoundTrip ? 'round-trip' : 'one-way',
        'distance_km'               => round($distanceKm, 2),
        'is_hill_country'           => $isHillCountry,
        'base_fee'                  => round($baseFee, 2),
        'extra_km'                  => round($extraKm, 2),
        'extra_fee'                 => $extraFee,
        'hill_surcharge'            => $hillSurcharge,
        'up_down_charge'            => round($upDownCharge, 2),
        'waiting_hours'             => round($waitingHours, 2),        // ← NEW!
        'free_waiting_hours'        => $freeWaitingHours,             // ← NEW!
        'chargeable_waiting_hours'  => round($chargeableWaitingHours, 2), // ← NEW!
        'waiting_charge'            => $waitingCharge,                // ← NEW!
        'total_cost'                => $totalFare,
    ];
}
```

### LorryController.php

```php
public function estimate(Request $request)
{
    $request->validate([
        'lorry_id'       => ['required', 'integer', 'exists:lorries,id'],
        'rate_type'      => ['required', 'string'],
        'distance_km'    => ['sometimes', 'numeric', 'min:0'],
        'trip'           => ['sometimes', Rule::in(['one-way', 'round-trip'])],
        'waiting_hours'  => ['sometimes', 'numeric', 'min:0'],  // ← NEW!
        // ... other validations ...
    ]);

    // ... existing code ...

    $fare = $this->estimator->estimate(
        $rate,
        $distanceKm,
        $request->input('trip', 'one-way'),
        $isHillCountry,
        (float) $request->input('waiting_hours', 0)  // ← NEW!
    );

    return response()->json(array_merge([
        'success'       => true,
        'lorry'         => $lorry->name,
        'rate_type'     => $rateType,
        'duration'      => $durationText,
    ], $fare));
}
```

---

## Frontend Implementation

### BookingForm.tsx

```typescript
// Add waiting hours input to form
const [waitingHours, setWaitingHours] = useState(0);

// In pricing calculation:
const fare = $this->estimator->estimate(
  $rate,
  $distanceKm,
  $request->input('trip', 'one-way'),
  $isHillCountry,
  parseFloat(waitingHours) || 0  // ← NEW!
);

// Display waiting charge in summary
{summary.waitingCharge > 0 && (
  <div className="mt-2 p-2 bg-amber-50 border border-amber-200 rounded">
    <p className="text-sm">
      <span className="font-semibold">Waiting:</span>
      {summary.waitingHours} hours
      (Free: {summary.freeWaitingHours}h, Charged: {summary.chargeableWaitingHours}h)
      = {formatLkr(summary.waitingCharge)}
    </p>
  </div>
)}
```

---

## Admin Panel Fields

For each lorry rate type:

```
Window Configuration:
├─ From KM: 0
├─ To KM: 130
├─ Base Rate: 2500
├─ Extra Per KM: 160
└─ Hill Extra Per KM: 10

Waiting Configuration:       ← NEW!
├─ Free Waiting Hours: 2
└─ Waiting Charge Per Hour: 500

Up & Down Charges:
├─ Non-Hill: 120
└─ Hill: 200
```

---

## Complete Test Cases

| Distance | Waiting | Trip | Location | Base | Extra | Hill | UpDown | Waiting | Total |
|----------|---------|------|----------|------|-------|------|--------|---------|-------|
| 150km | 0h | R-trip | Hill | 2500 | 3200 | 1500 | 200 | 0 | **7,400** |
| 150km | 1h | R-trip | Hill | 2500 | 3200 | 1500 | 200 | 0 | **7,400** |
| 150km | 2h | R-trip | Hill | 2500 | 3200 | 1500 | 200 | 0 | **7,400** |
| 150km | 3h | R-trip | Hill | 2500 | 3200 | 1500 | 200 | 500 | **7,900** |
| 150km | 4h | R-trip | Hill | 2500 | 3200 | 1500 | 200 | 1000 | **8,400** |
| 150km | 6h | R-trip | Hill | 2500 | 3200 | 1500 | 200 | 2000 | **9,400** |
| 100km | 0h | One-way | Normal | 2500 | 0 | 0 | 0 | 0 | **2,500** |
| 100km | 2h | One-way | Normal | 2500 | 0 | 0 | 0 | 0 | **2,500** |
| 100km | 5h | One-way | Normal | 2500 | 0 | 0 | 0 | 1500 | **4,000** |

---

## SQL Updates

```sql
-- Add waiting fields to rate_table
UPDATE `lorries`
SET `rate_table` = JSON_SET(
  `rate_table`,
  '$.7ft.freeWaitingHours', 2,
  '$.7ft.waitingChargePerHour', 500
)
WHERE `name` = 'Agra 7FT Lorry';
```

---

## Summary

✅ Free waiting hours (no charge)
✅ Waiting charge per hour (after free hours)
✅ Calculated only for chargeable hours
✅ Included in total fare
✅ Admin configurable per rate type
