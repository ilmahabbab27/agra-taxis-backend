# Backend & Frontend Sync - Lorry Rate Structure Update

## Summary
The frontend (BookingForm) and admin panel were using a **NEW windows-based lorry rate structure**, but the backend was still using the **OLD flat rate structure**. This has been fixed to ensure consistency across all layers.

## Changes Made

### 1. **Frontend (React) - BookingForm.tsx** ✅
- Updated `fallbackLorryRates` from flat structure to windows-based
- Rewrote lorry pricing calculation logic to use windows
- Removed undefined variables and fixed references
- Properly handles hill surcharge per window

**Structure:**
```javascript
{
  "7ft": {
    type: "7 FT",
    windows: [
      { fromKm: 0, toKm: 130, rate: 2500, extraPerKm: 160 }
    ],
    extraUpDownCharge: 120,
    waitingChargePerHour: 600
  }
}
```

### 2. **Backend (Laravel)**

#### a) **LorryController.php** ✅
- Updated `normalizeRateTable()` to accept and return windows-based structure
- Updated `format()` to include seats, acAvailable, nonAcAvailable
- Updated `payload()` to map incoming data correctly
- Updated validation rules to accept new fields

#### b) **LorryEstimator.php** ✅
- Completely rewritten to work with windows-based rates
- Calculates fare by finding correct window for distance
- Properly handles extra km charges beyond window
- Maintains hill country and waiting hour surcharges

#### c) **Lorry Model** ✅
- Added fields to fillable array:
  - `seats`
  - `ac_available`
  - `non_ac_available`

#### d) **Migration (2026_06_06_000000_create_lorries_table.php)** ✅
- Added missing columns:
  - `seats` (unsignedTinyInteger, default 1)
  - `ac_available` (boolean, default false)
  - `non_ac_available` (boolean, default false)
- Added unique constraint on `name`
- Added index on `category`
- Improved comment on `rate_table`

## How to Apply

### Step 1: Run Database Migration
```bash
cd "C:\xampp\htdocs\Agra Taxis Backend"
php artisan migrate:refresh --step=1  # If you want to be safe, just refresh the last migration

# Or if migration already exists, force it:
php artisan migrate:refresh
```

### Step 2: Seed Sample Lorry Data (Optional)
Use the SQL from `LORRIES_TABLE_MIGRATION.sql` in the frontend directory.

### Step 3: Verify Backend Controllers
Check that these files were updated:
- ✅ `app/Http/Controllers/Api/LorryController.php`
- ✅ `app/Services/LorryEstimator.php`
- ✅ `app/Models/Lorry.php`
- ✅ `database/migrations/2026_06_06_000000_create_lorries_table.php`

### Step 4: Test Endpoints
```bash
# Test lorry list
GET /api/lorries

# Test estimate (with windows-based rate)
POST /api/lorries/estimate
{
  "lorry_id": 1,
  "rate_type": "7ft",
  "distance_km": 150,
  "trip": "one-way",
  "is_hill_country": false
}

# Create/Update lorry with windows format
POST /api/lorries
{
  "name": "Test Lorry",
  "category": "Lorries",
  "seats": 1,
  "acAvailable": false,
  "nonAcAvailable": false,
  "rateTable": {
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
}
```

## Rate Table Structure

### Old Structure (❌ NO LONGER USED)
```javascript
{
  type: "7 FT",
  start: 2500,           // Base fare
  extra: 160,            // Per-km above limit
  between100And130: 2500, // Special rate for 100-130km
  upDown: 120,           // Round-trip
  waiting: 600,          // Flat wait
  waitingHour: 600,      // Hourly wait
  hillExtraPerKm: 10,    // Hill surcharge
  dropMaxKm: 130,        // Coverage limit
  maxUpDownKm: 150,      // Round-trip limit
  ...
}
```

### New Structure (✅ NOW USED)
```javascript
{
  type: "7 FT",
  windows: [
    {
      fromKm: 0,
      toKm: 130,
      rate: 2500,
      extraPerKm: 160,
      hillExtraPerKm: 10    // Per-km hill surcharge for this window
    }
  ],
  extraUpDownCharge: 120,      // Flat round-trip surcharge
  waitingChargePerHour: 600    // Hourly waiting charge
}
```

## Pricing Logic

### Fare Calculation with Windows

1. **Find Applicable Window**: Based on distance_km, find the window where `distance >= fromKm`
2. **Base Fare**: Use `window.rate` as the base
3. **Extra KM Charge**: If distance > window.toKm, charge `(distance - toKm) * extraPerKm`
4. **Hill Surcharge**: If hill country, add `distance * window.hillExtraPerKm`
5. **Round Trip**: If round-trip, add `extraUpDownCharge`
6. **Waiting**: If waiting hours > 0, add `waitingHours * waitingChargePerHour`

### Example: 150km one-way, hill country, 7FT lorry
```
Window: { fromKm: 0, toKm: 130, rate: 2500, extraPerKm: 160, hillExtraPerKm: 10 }

Base: 2500
Extra: (150 - 130) * 160 = 3200
Hill: 150 * 10 = 1500
Total: 2500 + 3200 + 1500 = 7200
```

## Files Modified

### Backend
- ✅ `app/Http/Controllers/Api/LorryController.php` - Normalization & API response
- ✅ `app/Services/LorryEstimator.php` - Fare calculation engine
- ✅ `app/Models/Lorry.php` - Model fillable fields
- ✅ `database/migrations/2026_06_06_000000_create_lorries_table.php` - Schema

### Frontend  
- ✅ `src/components/BookingForm.tsx` - Pricing calculation & fallback rates
- ✅ `src/lib/vehicle-catalog.ts` - Type definitions (unchanged, already correct)

## Testing Checklist

- [ ] Migration runs without errors
- [ ] GET /api/lorries returns lorries with windows structure
- [ ] POST /api/lorries accepts and saves windows format
- [ ] POST /api/lorries/estimate calculates fare correctly
- [ ] Frontend booking form works with returned lorry data
- [ ] Admin panel can create/edit lorries with windows
- [ ] Booking form summary shows correct fare estimates
- [ ] Hill country surcharges apply correctly
- [ ] Extra km charges apply correctly
