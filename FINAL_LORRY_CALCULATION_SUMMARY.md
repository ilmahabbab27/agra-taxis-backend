# Final Lorry Fare Calculation - Complete Summary

## Formula

```
TOTAL_FARE = BASE_FEE 
           + EXTRA_FEE (if distance > window.toKm)
           + HILL_SURCHARGE (if hill location)
           + UP/DOWN_CHARGE (if round-trip)
```

---

## Components Breakdown

### 1. **Base Fee**
- From `window.rate`
- Covers distance from 0 to `window.toKm`
- Always charged

### 2. **Extra Fee**
- Only charged if `distance > window.toKm`
- Calculation: `(distance - window.toKm) × window.extraPerKm`

### 3. **Hill Surcharge** ⛰️ (NEW)
- Only charged if location is **HILL COUNTRY**
- Calculation: `distance × window.hillExtraPerKm`
- Default value: 10 per km (configurable in admin panel)

### 4. **Up/Down Charge** 🔄 (Round-Trip Only)
- Only charged if trip type is **ROUND-TRIP**
- **Different values for hill vs non-hill:**
  - Non-hill round-trip: `upDownNonHill`
  - Hill round-trip: `upDownHill` (usually higher)

---

## Rate Table Structure

```json
{
  "7ft": {
    "type": "7 FT",
    "windows": [
      {
        "fromKm": 0,
        "toKm": 130,
        "rate": 2500,              // Base fee
        "extraPerKm": 160,         // Extra km rate
        "hillExtraPerKm": 10       // Hill surcharge per km
      }
    ],
    "upDownNonHill": 120,          // Round-trip non-hill
    "upDownHill": 200              // Round-trip hill (higher!)
  }
}
```

---

## Example Calculations

### Example 1: Normal 100km, One-way
```
Distance: 100 km
Trip: One-way
Location: NORMAL
Window: [rate:2500, extra:160, hill:10]

Base Fee:        2500
Extra Fee:       0      (100 ≤ 130)
Hill Surcharge:  0      (Not hill)
Up/Down:         0      (One-way)

TOTAL: Rs. 2,500
```

### Example 2: Hill 100km, One-way
```
Distance: 100 km
Trip: One-way
Location: HILL ⛰️
Window: [rate:2500, extra:160, hill:10]

Base Fee:        2500
Extra Fee:       0      (100 ≤ 130)
Hill Surcharge:  1000   (100 × 10) ← ADDED!
Up/Down:         0      (One-way)

TOTAL: Rs. 3,500
```

### Example 3: Normal 150km, Round-trip
```
Distance: 150 km
Trip: Round-trip
Location: NORMAL
Window: [rate:2500, extra:160, hill:10]
upDownNonHill: 120, upDownHill: 200

Base Fee:        2500
Extra Fee:       3200   (20 × 160)
Hill Surcharge:  0      (Not hill)
Up/Down:         120    (Round-trip + NON-hill) ← ADDED!

TOTAL: Rs. 5,820
```

### Example 4: Hill 150km, Round-trip (MOST EXPENSIVE)
```
Distance: 150 km
Trip: Round-trip
Location: HILL ⛰️
Window: [rate:2500, extra:160, hill:10]
upDownNonHill: 120, upDownHill: 200

Base Fee:        2500
Extra Fee:       3200   (20 × 160)
Hill Surcharge:  1500   (150 × 10) ← ADDED!
Up/Down:         200    (Round-trip + HILL) ← ADDED!

TOTAL: Rs. 7,400
```

---

## Decision Tree

```
START
  │
  ├─ Find applicable window for distance
  │  └─ Get: rate, extraPerKm, hillExtraPerKm
  │
  ├─ Calculate Base Fee = window.rate
  │
  ├─ IF distance > window.toKm:
  │  └─ Add Extra Fee = (distance - toKm) × extraPerKm
  │
  ├─ IF location is HILL COUNTRY:
  │  └─ Add Hill Surcharge = distance × hillExtraPerKm
  │
  ├─ IF trip is ROUND-TRIP:
  │  ├─ IF location is HILL:
  │  │  └─ Add Up/Down = upDownHill
  │  └─ ELSE (Normal):
  │     └─ Add Up/Down = upDownNonHill
  │
  └─ TOTAL = Base + Extra + Hill + UpDown
```

---

## Admin Panel Setup

For each lorry type, admin defines:

```
Window Configuration:
├─ From KM: 0
├─ To KM: 130
├─ Base Rate: 2500
├─ Extra Per KM: 160
└─ Hill Extra Per KM: 10   ← NEW!

Up & Down Charges:
├─ Non-Hill Round-trip: 120     ← NEW!
└─ Hill Round-trip: 200         ← NEW!
```

---

## Implementation Files Updated

### Backend (Laravel)
- ✅ `LorryEstimator.php` - Calculates all 4 components
- ✅ `LorryController.php` - Normalizes rate table structure
- ✅ `Lorry.php` - Model with fillable fields
- ✅ Migration - Database schema with new columns

### Frontend (React)
- ✅ `BookingForm.tsx` - Calculates all 4 components during booking
- ✅ `vehicle-catalog.ts` - Updated type definitions
- ✅ `admin.index.tsx` - Updated default rates

---

## API Response Format

```json
{
    "success": true,
    "lorry": "7 FT Lorry",
    "rate_type": "7ft",
    "distance_km": 150,
    "is_hill_country": true,
    "trip": "round-trip",
    
    "base_fee": 2500,
    "extra_km": 20,
    "extra_fee": 3200,
    "hill_surcharge": 1500,
    "up_down_charge": 200,
    
    "total_cost": 7400
}
```

---

## Test Cases

| Distance | Trip | Location | Base | Extra | Hill | UpDown | Total |
|----------|------|----------|------|-------|------|--------|-------|
| 50 km | One-way | Normal | 2500 | 0 | 0 | 0 | **2500** |
| 50 km | One-way | Hill | 2500 | 0 | 500 | 0 | **3000** |
| 50 km | Round-trip | Normal | 2500 | 0 | 0 | 120 | **2620** |
| 50 km | Round-trip | Hill | 2500 | 0 | 500 | 200 | **3200** |
| 150 km | One-way | Normal | 2500 | 3200 | 0 | 0 | **5700** |
| 150 km | One-way | Hill | 2500 | 3200 | 1500 | 0 | **7200** |
| 150 km | Round-trip | Normal | 2500 | 3200 | 0 | 120 | **5820** |
| 150 km | Round-trip | Hill | 2500 | 3200 | 1500 | 200 | **7400** |
| 200 km | One-way | Normal | 2500 | 11200 | 0 | 0 | **13700** |
| 200 km | Round-trip | Hill | 2500 | 11200 | 2000 | 200 | **15900** |

---

## Key Points

✅ **Calculation is consistent** across frontend and backend
✅ **Hill surcharge** applies to entire distance (not just extra km)
✅ **Up/Down charges differ** by 50-100% for hill vs normal
✅ **All 4 components sum** to give total fare
✅ **Admin panel controls** all values (rate, extra, hill, upDown)
✅ **Frontend and backend** use identical logic

---

## Next Steps

1. ✅ Update database migration with new columns
2. ✅ Update backend API to use new formula
3. ✅ Update frontend booking form calculation
4. ✅ Test all combinations (normal/hill × one-way/round-trip)
5. ✅ Verify API responses match frontend calculations
6. ✅ Update admin panel to manage all 4 values
