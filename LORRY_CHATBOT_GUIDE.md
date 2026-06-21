# WhatsApp Lorry Booking Chatbot Guide

## Overview
A WhatsApp chatbot that collects lorry booking data step-by-step and calculates fares using the `/api/lorry-estimate` endpoint.

---

## Data Collection Flow

### Step 1: Pickup Location (Location Name Only)
**User Message:**
```
Colombo
```

**Bot Response:**
```
Got it! Pickup: Colombo

Where are you heading to?
```

**Backend Action:**
- Geocode location name → Get lat/lng coordinates
- Store: `pickup_text`, `pickup_lat`, `pickup_lng`

---

### Step 2: Destination Location (Location Name Only)
**User Message:**
```
Kandy
```

**Bot Response:**
```
Destination: Kandy

What type of trip?
1. One Way
2. Round Trip

Reply with number or text.
```

**Backend Action:**
- Geocode location name → Get lat/lng coordinates
- Store: `destination_text`, `destination_lat`, `destination_lng`

---

### Step 3: Trip Type Selection
**User Message:**
```
2
```
or
```
round-trip
```

**Bot Response:**
```
Great! Round trip selected.

Available Lorry Types:
1. 7 FT   — For small loads (0-50km: Rs. 2000)
2. 8.5 FT — For medium loads (0-50km: Rs. 2800)
3. 10.5 FT — For larger loads (0-50km: Rs. 4800)
4. 12.5 FT
5. 14.5 FT
6. 16.5 FT
7. 18.5 FT
8. 20 FT
9. 24 FT

Reply with number or lorry type name.
```

**Backend Action:**
- Store: `trip_type` (one-way or round-trip)
- Fetch available lorry types from database

---

### Step 4: Lorry Type Selection
**User Message:**
```
1
```
or
```
7 FT
```

**Bot Response:**
```
7 FT Lorry selected.

What's the distance in kilometers?
(Or just tell us pickup and destination for auto-calculation)
```

**Backend Action:**
- Store: `lorry_type` = "7ft", `lorry_id` = 10

---

### Step 5: Distance Input
**User Message (Option A - Direct Distance):**
```
250
```

**User Message (Option B - Auto-Calculate):**
```
already have pickup and destination
```

**Bot Response:**
```
Calculating distance... Found: 250 km

Based on your booking:
- Pickup: Colombo
- Destination: Kandy
- Lorry: 7 FT
- Trip: Round Trip
- Distance: 250 km
- Hill Country: Yes (Kandy)

Estimated Fare: Rs. 13,000
├─ Base Fee: Rs. 3,000
├─ Extra KM (50 km × 200): Rs. 10,000
└─ Waiting Info: 2 free hours, then Rs. 500/hour

✅ Confirm booking? (yes/no)
```

**Backend Action:**
- If distance provided: use it directly
- If auto-calculate: use stored coordinates with Google Maps API
- Call `/api/lorry-estimate` endpoint

---

### Step 6: Confirmation & Booking
**User Message:**
```
yes
```

**Bot Response:**
```
Your booking confirmed!

Booking ID: #12345
Vehicle: 7 FT Lorry
Pickup: Colombo
Destination: Kandy
Trip: Round Trip
Distance: 250 km
Estimated Fare: Rs. 13,000

We will contact you shortly. Thank you!
```

**Backend Action:**
- Create booking record in database
- Send booking details to admin

---

## Data Structure for Session

```javascript
{
  "session_id": "user_phone_number or unique_id",
  "step": "pickup|destination|trip_type|lorry_type|distance|confirm|done",
  "data": {
    "pickup_text": "Colombo",
    "pickup_lat": 6.9271,
    "pickup_lng": 80.6369,
    "destination_text": "Kandy",
    "destination_lat": 7.2906,
    "destination_lng": 80.6337,
    "trip_type": "round-trip",
    "lorry_type": "7ft",
    "lorry_id": 10,
    "distance_km": 250,
    "duration_text": "5 hours",
    "is_hill_country": true,
    "fare": {
      "base_fee": 3000,
      "extra_km": 50,
      "extra_fee": 10000,
      "waiting_charge": 0,
      "total_cost": 13000
    }
  }
}
```

---

## API Endpoints Used

### 1. Location Geocoding (Google Maps)
```
GET https://maps.googleapis.com/maps/api/geocode/json
?address=Colombo
&key=YOUR_API_KEY

Response:
{
  "results": [{
    "formatted_address": "Colombo, Western Province, Sri Lanka",
    "geometry": {
      "location": { "lat": 6.9271, "lng": 80.6369 }
    }
  }]
}
```

### 2. Distance Calculation (Google Maps)
```
GET https://maps.googleapis.com/maps/api/distancematrix/json
?origins=6.9271,80.6369
&destinations=7.2906,80.6337
&mode=driving
&units=metric
&key=YOUR_API_KEY

Response:
{
  "rows": [{
    "elements": [{
      "distance": { "value": 250000, "text": "250 km" },
      "duration": { "value": 18000, "text": "5 hours" }
    }]
  }]
}
```

### 3. Lorry Types List
```bash
GET /api/lorries
```

### 4. Lorry Fare Estimation ⭐
```bash
POST /api/lorry-estimate
Content-Type: application/json

{
  "lorry_id": 10,
  "rate_type": "7ft",
  "distance_km": 250,
  "trip": "round-trip",
  "pickup_text": "Colombo",
  "pickup_lat": 6.9271,
  "pickup_lng": 80.6369,
  "dropoff_text": "Kandy",
  "dropoff_lat": 7.2906,
  "dropoff_lng": 80.6337,
  "waiting_hours": 0
}

Response:
{
  "success": true,
  "lorry": "Agra Lorries",
  "rate_type": "7ft",
  "trip": "round-trip",
  "distance_km": 250,
  "is_hill_country": true,
  "base_fee": 3000,
  "extra_km": 50,
  "extra_fee": 10000,
  "waiting_hours": 0,
  "free_waiting_hours": 2,
  "chargeable_waiting_hours": 0,
  "waiting_charge": 0,
  "total_cost": 13000,
  "duration": "5 hours"
}
```

---

## Implementation Checklist

- [ ] Create WhatsApp chatbot handler that accepts text messages
- [ ] Store session data in Cache (key: `chatbot:session_id`)
- [ ] Implement step handlers: pickup → destination → trip_type → lorry_type → distance → confirm
- [ ] Geocode location names using Google Maps API
- [ ] Fetch lorry types from `/api/lorries` endpoint
- [ ] Call `/api/lorry-estimate` when distance is available
- [ ] Create booking on confirmation
- [ ] Send confirmation message with booking ID
- [ ] Cache TTL: 1800 seconds (30 minutes) for session timeout

---

## Example WhatsApp Integration

Your WhatsApp bot receives messages like:
```
POST https://your-api.com/api/chatbot/message
{
  "session_id": "27812345678",
  "message": "Colombo"
}
```

And returns:
```json
{
  "message": "Got it! Pickup: Colombo\n\nWhere are you heading to?",
  "step": "destination"
}
```

---

## Fare Calculation Formula (for reference)

**Round Trip + Hill Country:**
```
Total = Base Fee + (Extra KM × upDownHill)
      = 3000 + (50 × 200)
      = Rs. 13,000
```

**One-Way + Hill Country:**
```
Total = Base Fee + (Extra KM × (extraPerKm + hillExtraPerKm))
      = 2000 + (100 × (180 + 15))
      = Rs. 21,500
```

**Waiting charges** are shown for information only, not added to total.

---

## Error Handling

| Scenario | Response |
|----------|----------|
| Location not found | "I couldn't find that location. Could you be more specific? (e.g. 'Taj Mahal, Agra')" |
| Invalid trip type | "Please choose 'One Way' or 'Round Trip'" |
| Invalid lorry type | "Please choose from available lorry types" |
| Invalid distance | "Please enter a number greater than 0" |
| Can't calculate distance | "I couldn't calculate the distance. Please provide the distance manually or verify locations." |
| Session timeout | New session starts, user needs to say "restart" or provide pickup location |

---

## Testing Commands

```bash
# Test location prediction
curl -X POST http://localhost:8000/api/chatbot/message \
  -H "Content-Type: application/json" \
  -d '{
    "session_id": "test_user_123",
    "message": "Colombo"
  }'

# Test estimate calculation
curl -X POST http://localhost:8000/api/lorry-estimate \
  -H "Content-Type: application/json" \
  -d '{
    "lorry_id": 10,
    "rate_type": "7ft",
    "distance_km": 250,
    "trip": "round-trip",
    "pickup_text": "Colombo",
    "pickup_lat": 6.9271,
    "pickup_lng": 80.6369,
    "dropoff_text": "Kandy",
    "dropoff_lat": 7.2906,
    "dropoff_lng": 80.6337
  }'
```
