# Using Lorry Rates from Database (Not Hardcoded)

## Architecture

```
┌─────────────────────────────────────────┐
│         Admin Panel (Frontend)          │
│   Creates/Edits Lorry Rates             │
└──────────────┬──────────────────────────┘
               │ (HTTP POST/PUT)
               ↓
┌─────────────────────────────────────────┐
│    Laravel Backend (LorryController)    │
│  Saves to Database via saveLorryToDatabase
└──────────────┬──────────────────────────┘
               │
               ↓
┌─────────────────────────────────────────┐
│         MySQL Database                  │
│  lorries table (rate_table JSON column) │
└──────────────┬──────────────────────────┘
               │
               ↓
┌─────────────────────────────────────────┐
│    Booking Form (Frontend)              │
│  Requests /api/lorries/estimate         │
└──────────────┬──────────────────────────┘
               │ (HTTP GET)
               ↓
┌─────────────────────────────────────────┐
│    LorryController@estimate             │
│  Reads rates FROM DATABASE              │
│  Validates windows & structure          │
│  Passes to LorryEstimator               │
└──────────────┬──────────────────────────┘
               │
               ↓
┌─────────────────────────────────────────┐
│    LorryEstimator Service              │
│  Calculates fare using DB rates         │
│  Returns: base + extra + hill + updown  │
└─────────────────────────────────────────┘
```

---

## Database Storage

### Table: `lorries`

```sql
CREATE TABLE lorries (
  id BIGINT PRIMARY KEY,
  name VARCHAR(255) UNIQUE,
  category VARCHAR(100),
  img LONGTEXT,
  rate_table JSON,        ← Stores all rate data here
  created_at TIMESTAMP,
  updated_at TIMESTAMP
);
```

### Example Data in `rate_table` Column

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
```

---

## API Flow

### Step 1: Admin Creates Lorry Rates

**Frontend:** Admin panel → Edit Lorry → Configure rates

**Request:**
```http
POST /api/lorries
Content-Type: application/json

{
  "name": "7FT Lorry",
  "category": "Lorries",
  "rateTable": {
    "7ft": {
      "type": "7 FT",
      "windows": [...],
      "upDownNonHill": 120,
      "upDownHill": 200
    }
  }
}
```

**Backend:** `LorryController@store`
```php
public function store(Request $request)
{
    $data = $this->validateLorry($request);
    $lorry = Lorry::create($this->payload($data));  // ← Saved to DB
    return response()->json(['data' => $this->format($lorry)], 201);
}
```

### Step 2: Booking Form Requests Estimate

**Frontend:** Booking form → Select lorry → Request fare estimate

**Request:**
```http
POST /api/lorries/estimate
Content-Type: application/json

{
  "lorry_id": 1,
  "rate_type": "7ft",
  "distance_km": 150,
  "trip": "round-trip",
  "pickup_lat": 6.9271,
  "pickup_lng": 80.7789,
  "dropoff_lat": 6.8000,
  "dropoff_lng": 80.7500
}
```

### Step 3: LorryController Fetches from Database

**Backend:** `LorryController@estimate`

```php
public function estimate(Request $request)
{
    // 1. Get lorry from DATABASE
    $lorry = Lorry::findOrFail($request->input('lorry_id'));
    
    // 2. Get rate_table from DATABASE
    $rateTable = $lorry->rate_table;
    
    // 3. Validate it exists and is not empty
    if (!is_array($rateTable) || empty($rateTable)) {
        return error("No rates configured");
    }
    
    // 4. Get specific rate type from DATABASE rates
    $rateType = $request->input('rate_type');
    $rate = $rateTable[$rateType];
    
    // 5. Calculate fare using DATABASE rates
    $fare = $this->estimator->estimate($rate, $distanceKm, $trip, $isHillCountry);
    
    return response()->json($fare);
}
```

### Step 4: Response with Calculated Fare

**Response:**
```json
{
  "success": true,
  "lorry": "7FT Lorry",
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

## Error Handling

### Scenario 1: No Lorry Found
```
GET /api/lorries/estimate?lorry_id=999
→ 404 Not Found (Lorry::findOrFail throws ModelNotFoundException)
```

### Scenario 2: No Rates Configured in Database
```
Database: Lorry 1 has rate_table = null

GET /api/lorries/estimate?lorry_id=1&rate_type=7ft
→ 422 Unprocessable Entity
   "No rate table configured for lorry 'Agra Lorry'. Please configure rates in admin panel."
```

### Scenario 3: Rate Type Not Found in Database
```
Database: Lorry 1 has rate_table = {"20ft": {...}}

GET /api/lorries/estimate?lorry_id=1&rate_type=7ft
→ 422 Unprocessable Entity
   "Rate type '7ft' not found for Agra Lorry. Available: 20ft"
```

### Scenario 4: Invalid Window Structure
```
Database: rate_table.7ft.windows = []  (empty!)

GET /api/lorries/estimate?lorry_id=1&rate_type=7ft
→ 422 Unprocessable Entity
   "Invalid rate configuration for Agra Lorry - 7ft. Windows not found."
```

---

## No Hardcoded Fallbacks

The code does **NOT** use hardcoded fallback rates. It **ALWAYS** requires valid rates in the database:

```php
// ❌ OLD (Bad - used hardcoded fallback)
$rateTable = $lorry->rate_table ?? fallbackLorryRates;  // WRONG!

// ✅ NEW (Good - requires database rates)
$rateTable = $lorry->rate_table;
if (!is_array($rateTable) || empty($rateTable)) {
    return error("No rates in database");  // Reject, don't fallback
}
```

---

## Admin Panel Sets Rates

Admin controls **all** rate values via the admin panel:

```typescript
// Frontend: admin.index.tsx
// User fills out:

Lorry Type: 7 FT
├─ Window 1 (0-130km)
│  ├─ Base Rate: 2500
│  ├─ Extra Per KM: 160
│  └─ Hill Extra: 10
├─ Up & Down (Non-Hill): 120
└─ Up & Down (Hill): 200

// Saves to database via POST /api/lorries
```

---

## Database Query Example

### View stored rates:
```sql
SELECT id, name, rate_table 
FROM lorries 
WHERE name = 'Agra Lorry';

-- Returns:
-- id: 1
-- name: Agra Lorry
-- rate_table: {
--   "7ft": {
--     "type": "7 FT",
--     "windows": [...],
--     "upDownNonHill": 120,
--     "upDownHill": 200
--   }
-- }
```

### Update rates (via API, not SQL):
```http
PUT /api/lorries/1
Content-Type: application/json

{
  "rateTable": {
    "7ft": {
      ...updated values...
    }
  }
}
```

---

## Validation Chain

```
Admin Form Input
        ↓
Validate in LorryController@validateLorry
        ↓
Normalize with normalizeRateTable()
        ↓
Save to Database (JSON column)
        ↓
Retrieve for Estimate
        ↓
Validate windows exist & not empty
        ↓
Pass to LorryEstimator
        ↓
Calculate Fare
```

---

## Key Points

✅ Rates come **FROM DATABASE**, not hardcoded
✅ Admin panel controls all rate values
✅ No fallback rates - database is required
✅ Invalid/missing rates return error (don't silently fail)
✅ Each estimate uses latest database rates
✅ Window structure is validated before calculation
✅ Frontend and backend both use database rates

---

## Testing Database Rates

### 1. Check Lorry in Database
```bash
mysql> SELECT id, name, rate_table FROM lorries;
```

### 2. Create Estimate Request
```bash
curl -X POST http://localhost/Agra%20Taxis%20Backend/public/api/lorries/estimate \
  -H "Content-Type: application/json" \
  -d '{
    "lorry_id": 1,
    "rate_type": "7ft",
    "distance_km": 150,
    "trip": "one-way"
  }'
```

### 3. Check Response Uses Database Rates
```json
{
  "success": true,
  "base_fee": 2500,        ← From database
  "extra_fee": 3200,       ← From database
  "total_cost": 5700       ← Calculated from database rates
}
```
