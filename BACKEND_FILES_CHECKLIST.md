# Backend Files to Update - Complete Checklist

## Admin Panel sends this to backend:
```javascript
{
  name: "Agra 7FT Lorry",
  category: "Lorries",
  img, img2, img3, img4, img5,
  seats: 1,
  acAvailable: false,
  nonAcAvailable: false,
  lorryRates: {           // ← This matches "rateTable" in backend
    "7ft": {
      type: "7 FT",
      windows: [...],
      upDownNonHill: 120,
      upDownHill: 200,
      freeWaitingHours: 2,
      waitingChargePerHour: 500
    }
  }
}
```

---

## Backend Files to Update

### 1. ✅ **LorryController.php** (DONE)
**Location:** `app/Http/Controllers/Api/LorryController.php`

**Methods to verify:**

#### a) `validateLorry()` - Lines 201-213
```php
private function validateLorry(Request $request, ?Lorry $lorry = null): array
{
    return $request->validate([
        'name' => ['required', 'string', 'max:100', 'unique:lorries,name,' . optional($lorry)->id],
        'category' => ['nullable', 'string', 'max:100'],
        'img' => ['nullable', 'string'],
        'img2' => ['nullable', 'string'],
        'img3' => ['nullable', 'string'],
        'img4' => ['nullable', 'string'],
        'img5' => ['nullable', 'string'],
        'seats' => ['nullable', 'integer', 'min:1'],
        'acAvailable' => ['nullable', 'boolean'],
        'nonAcAvailable' => ['nullable', 'boolean'],
        'rateTable' => ['nullable', 'array'],  // ← Accepts lorryRates
    ]);
}
```
✅ Status: Validates all fields including rateTable

#### b) `payload()` - Lines 215-227
```php
private function payload(array $data): array
{
    return [
        'name' => trim($data['name']),
        'category' => trim($data['category'] ?? 'Lorries'),
        'img' => $data['img'] ?? null,
        'img2' => $data['img2'] ?? null,
        'img3' => $data['img3'] ?? null,
        'img4' => $data['img4'] ?? null,
        'img5' => $data['img5'] ?? null,
        'seats' => max(1, (int) ($data['seats'] ?? 1)),
        'ac_available' => (bool) ($data['acAvailable'] ?? false),
        'non_ac_available' => (bool) ($data['nonAcAvailable'] ?? false),
        'rate_table' => $this->normalizeRateTable($data['rateTable'] ?? []),
    ];
}
```
✅ Status: Maps lorryRates → rate_table and normalizes

#### c) `normalizeRateTable()` - Lines 245-284
```php
private function normalizeRateTable(array $rates): array
{
    $normalized = [];
    foreach ($rates as $key => $row) {
        if (!is_array($row)) continue;

        $windows = [];
        if (isset($row['windows']) && is_array($row['windows'])) {
            foreach ($row['windows'] as $window) {
                if (is_array($window)) {
                    $windows[] = [
                        'fromKm' => max(0, (int) ($window['fromKm'] ?? 0)),
                        'toKm' => isset($window['toKm']) ? ((int) $window['toKm']) : null,
                        'rate' => max(0, (float) ($window['rate'] ?? 0)),
                        'extraPerKm' => max(0, (float) ($window['extraPerKm'] ?? 0)),
                        'hillExtraPerKm' => max(0, (float) ($window['hillExtraPerKm'] ?? 0)),
                    ];
                }
            }
        }

        if (empty($windows)) {
            $windows = [[
                'fromKm' => 0,
                'toKm' => 130,
                'rate' => max(0, (float) ($row['rate'] ?? $row['start'] ?? 0)),
                'extraPerKm' => max(0, (float) ($row['extraPerKm'] ?? $row['extra'] ?? 0)),
                'hillExtraPerKm' => max(0, (float) ($row['hillExtraPerKm'] ?? 0)),
            ]];
        }

        $normalized[$key] = [
            'type' => trim((string) ($row['type'] ?? $key)),
            'windows' => $windows,
            'upDownNonHill' => max(0, (float) ($row['upDownNonHill'] ?? $row['extraUpDownCharge'] ?? $row['upDown'] ?? $row['up_down'] ?? 0)),
            'upDownHill' => max(0, (float) ($row['upDownHill'] ?? 0)),
            'freeWaitingHours' => max(0, (float) ($row['freeWaitingHours'] ?? 0)),
            'waitingChargePerHour' => max(0, (float) ($row['waitingChargePerHour'] ?? 0)),
        ];
    }

    return $normalized;
}
```
⚠️ **Status: NEEDS UPDATE** - Missing freeWaitingHours and waitingChargePerHour

#### d) `format()` - Lines 229-243
```php
private function format(Lorry $lorry): array
{
    return [
        'id' => $lorry->id,
        'name' => $lorry->name,
        'category' => $lorry->category,
        'img' => $lorry->img,
        'img2' => $lorry->img2,
        'img3' => $lorry->img3,
        'img4' => $lorry->img4,
        'img5' => $lorry->img5,
        'seats' => $lorry->seats ?? 1,
        'acAvailable' => (bool) ($lorry->ac_available ?? false),
        'nonAcAvailable' => (bool) ($lorry->non_ac_available ?? false),
        'images' => array_values(array_filter([$lorry->img, $lorry->img2, $lorry->img3, $lorry->img4, $lorry->img5])),
        'lorryRates' => $this->normalizeRateTable($lorry->rate_table ?? []),
    ];
}
```
✅ Status: Maps rate_table → lorryRates for frontend

---

### 2. ✅ **LorryEstimator.php** (DONE)
**Location:** `app/Services/LorryEstimator.php`

**Uses all 5 components:**
- ✅ Base fee (window.rate)
- ✅ Extra fee (window.extraPerKm)
- ✅ Hill surcharge (window.hillExtraPerKm)
- ✅ Up/down charge (upDownNonHill / upDownHill)
- ✅ Waiting charge (waitingChargePerHour - freeWaitingHours)

---

### 3. ✅ **Lorry Model** (DONE)
**Location:** `app/Models/Lorry.php`

**Fillable array includes:**
```php
protected $fillable = [
    'name',
    'category',
    'img',
    'img2',
    'img3',
    'img4',
    'img5',
    'seats',
    'ac_available',
    'non_ac_available',
    'rate_table',
];

protected $casts = [
    'rate_table' => 'array',
];
```
✅ Status: Correctly configured

---

### 4. ✅ **Migration** (DONE)
**Location:** `database/migrations/2026_06_06_000000_create_lorries_table.php`

**Schema includes:**
```php
$table->id();
$table->string('name')->unique();
$table->string('category')->default('Lorries');
$table->string('img')->nullable();
$table->string('img2')->nullable();
$table->string('img3')->nullable();
$table->string('img4')->nullable();
$table->string('img5')->nullable();
$table->unsignedTinyInteger('seats')->default(1);
$table->boolean('ac_available')->default(false);
$table->boolean('non_ac_available')->default(false);
$table->json('rate_table')->nullable();
$table->timestamps();
$table->index('category');
```
✅ Status: Complete

---

### 5. ⚠️ **normalizeRateTable() - NEEDS UPDATE**

**Current code (lines 245-284):**
```php
'upDownNonHill' => max(0, (float) ($row['upDownNonHill'] ?? $row['extraUpDownCharge'] ?? $row['upDown'] ?? $row['up_down'] ?? 0)),
'upDownHill' => max(0, (float) ($row['upDownHill'] ?? 0)),
```

**Missing:**
```php
'freeWaitingHours' => max(0, (float) ($row['freeWaitingHours'] ?? 0)),
'waitingChargePerHour' => max(0, (float) ($row['waitingChargePerHour'] ?? 0)),
```

---

## Summary

### ✅ Ready (No changes needed):
- LorryEstimator.php (uses all 5 components)
- Lorry Model (proper fillable/casts)
- Migration (complete schema)
- LorryController format() method
- LorryController validateLorry() method
- LorryController payload() method

### ⚠️ **UPDATE NEEDED:**
- **LorryController.php → normalizeRateTable()** - Add freeWaitingHours and waitingChargePerHour

---

## Update Required

**File:** `app/Http/Controllers/Api/LorryController.php`
**Method:** `normalizeRateTable()`
**Lines:** 245-284

**Change lines 271-275 from:**
```php
'upDownNonHill' => max(0, (float) ($row['upDownNonHill'] ?? $row['extraUpDownCharge'] ?? $row['upDown'] ?? $row['up_down'] ?? 0)),
'upDownHill' => max(0, (float) ($row['upDownHill'] ?? 0)),
```

**To:**
```php
'upDownNonHill' => max(0, (float) ($row['upDownNonHill'] ?? $row['extraUpDownCharge'] ?? $row['upDown'] ?? $row['up_down'] ?? 0)),
'upDownHill' => max(0, (float) ($row['upDownHill'] ?? 0)),
'freeWaitingHours' => max(0, (float) ($row['freeWaitingHours'] ?? 0)),
'waitingChargePerHour' => max(0, (float) ($row['waitingChargePerHour'] ?? 0)),
```

---

## Testing Checklist

After updating normalizeRateTable():

- [ ] Admin saves lorry with all 5 rate fields
- [ ] Backend API accepts and normalizes all fields
- [ ] Database stores complete rate_table JSON
- [ ] Booking form receives complete rates
- [ ] Estimate calculates all 5 components
- [ ] Response includes waiting_charge breakdown
