# Chatbot API Endpoints - Quick Reference Table

## All Endpoints in One View

| # | Endpoint | Method | Purpose | Request Body | Response |
|---|----------|--------|---------|--------------|----------|
| 1 | `/api/chatbot/message` | POST | Multi-step chat conversation for booking | `{ session_id*, message* }` | `{ message, step }` |
| 2 | `/api/chatbot/estimate` | POST | Calculate fare estimate | `{ pickup_text\|lat/lng, dropoff_text\|lat/lng, vehicle?, ac?, days? }` | `{ success, pickup, dropoff, distance_km, vehicle, ac, days, price_per_km, driving_cost, stay_cost, total_cost, message }` |
| 3 | `/api/chatbot/booking` or `/api/quick-booking` | POST | Create booking with full details | `{ vehicle*, pax*, days*, trip*, ac*, pickup*, drop*, stops?, date?, customer_name?, customer_phone?, notes? }` | `{ booking_id, vehicle, pickup, drop, stops, date, days, trip, pax, ac, distance_km, billed_km, price_per_km, driving_cost, stay_cost, total_cost, currency, status }` |

*Required field

---

## Detailed Field Reference

### Endpoint 1: POST /api/chatbot/message

#### REQUEST
```json
{
  "session_id": "string (max 100)",
  "message": "string (max 500)"
}
```

#### RESPONSE (200 OK)
```json
{
  "message": "string",
  "step": "pickup|destination|vehicle|days|pax|ac|date|confirm|done"
}
```

---

### Endpoint 2: POST /api/chatbot/estimate

#### REQUEST
```json
{
  "pickup_text": "string (max 200)",           // OR use pickup_lat/pickup_lng
  "pickup_lat": "number",
  "pickup_lng": "number",
  "dropoff_text": "string (max 200)",         // OR use dropoff_lat/dropoff_lng
  "dropoff_lat": "number",
  "dropoff_lng": "number",
  "vehicle": "string (max 100)",              // optional, default: "Sedan"
  "ac": "ac|non-ac",                          // optional, default: "ac"
  "days": "integer (1-5)"                     // optional, default: 1
}
```

#### RESPONSE (200 OK)
```json
{
  "success": true,
  "pickup": "string",
  "dropoff": "string",
  "distance_km": 37.45,
  "vehicle": "Sedan",
  "ac": "AC",
  "days": 1,
  "price_per_km": 15.50,
  "driving_cost": 580.48,
  "stay_cost": 2000.00,
  "total_cost": 2580.48,
  "message": "I assumed pickup is ... Estimated fare is Rs. 2580.48 for approximately 37.45 km."
}
```

#### RESPONSE (422 Error)
```json
{
  "success": false,
  "message": "Could not resolve one or both locations..."
}
```

---

### Endpoint 3: POST /api/chatbot/booking

#### REQUEST
```json
{
  "vehicle": "string (max 100)",              // required
  "pax": "integer (1-100)",                   // required
  "date": "YYYY-MM-DD",                       // optional, defaults to today
  "days": "integer (1-5)",                    // required
  "trip": "one-way|round-trip",               // required
  "ac": "ac|non-ac",                          // required
  "pickup": "string (max 200)",               // required
  "drop": "string (max 200)",                 // required
  "stops": ["string (max 200)"],              // optional, max 5 stops
  "customer_name": "string (max 150)",        // optional
  "customer_phone": "string (max 50)",        // optional
  "notes": "string (max 2000)"                // optional
}
```

#### RESPONSE (201 Created)
```json
{
  "booking_id": 42,
  "vehicle": "Sedan",
  "pickup": "Taj Mahal, Agra, Uttar Pradesh, India",
  "drop": "Agra Fort, Agra, Uttar Pradesh, India",
  "stops": ["Location 1", "Location 2"],
  "date": "2026-06-15",
  "days": 1,
  "trip": "one-way",
  "pax": 2,
  "ac": "ac",
  "distance_km": 6.25,
  "billed_km": 6.25,
  "price_per_km": 15.50,
  "driving_cost": 96.88,
  "stay_cost": 2000.00,
  "total_cost": 2096.88,
  "currency": "INR",
  "status": "new"
}
```

#### RESPONSE (404 Error)
```json
{
  "message": "Vehicle not found."
}
```

#### RESPONSE (422 Error)
```json
{
  "message": "Could not find pickup location: \"Invalid Place Name\"."
}
```

---

## Chatbot Conversation Steps

| Step | Input Expected | Bot Action | Next Step |
|------|----------------|-----------|-----------|
| **pickup** | Location address | Geocodes pickup, stores coordinates | destination |
| **destination** | Location address | Geocodes destination, shows vehicles | vehicle |
| **vehicle** | Vehicle number (1-N) or name | Stores vehicle choice | days |
| **days** | Number 1-5 | Validates days input | pax |
| **pax** | Number 1-100 | Validates passenger count | ac |
| **ac** | "ac" / "non-ac" / "both" | Stores preference | date |
| **date** | Date string (e.g., "25 May 2026") | Parses date, calculates fare, shows summary | confirm |
| **confirm** | "yes" / "no" / "restart" | Creates booking OR restarts | done |
| **done** | N/A | Booking completed, session cleared | N/A |

---

## HTTP Status Codes

| Code | Meaning | Endpoint(s) |
|------|---------|------------|
| 200 | OK - Request successful | /api/chatbot/message, /api/chatbot/estimate |
| 201 | Created - Booking created | /api/chatbot/booking |
| 404 | Not Found | /api/chatbot/booking (vehicle not found) |
| 422 | Unprocessable Entity | All endpoints (validation/location errors) |
| 500 | Internal Server Error | All endpoints (API/database errors) |

---

## Example cURL Commands

### 1. Send Chatbot Message
```bash
curl -X POST http://localhost:8000/api/chatbot/message \
  -H "Content-Type: application/json" \
  -d '{
    "session_id": "user_123",
    "message": "Taj Mahal, Agra"
  }'
```

### 2. Get Fare Estimate
```bash
curl -X POST http://localhost:8000/api/chatbot/estimate \
  -H "Content-Type: application/json" \
  -d '{
    "pickup_text": "Taj Mahal, Agra",
    "dropoff_text": "Agra Fort, Agra",
    "vehicle": "Sedan",
    "ac": "ac",
    "days": 1
  }'
```

### 3. Create Booking
```bash
curl -X POST http://localhost:8000/api/chatbot/booking \
  -H "Content-Type: application/json" \
  -d '{
    "vehicle": "Sedan",
    "pax": 2,
    "days": 1,
    "trip": "one-way",
    "ac": "ac",
    "pickup": "Taj Mahal, Agra",
    "drop": "Agra Fort, Agra",
    "customer_name": "John Doe",
    "customer_phone": "+91-9876543210"
  }'
```

---

## Price Calculation Formula

**Driving Cost** = Distance (km) × Price Per KM
- For round-trip: multiply distance by 2

**Stay Cost** = Vehicle's stay_price_day{N} (where N = number of days)

**Total Cost** = Driving Cost + Stay Cost

---

## Environment Setup Required

To use the chatbot endpoints, ensure the following is configured:

```env
GOOGLE_MAPS_KEY=your_google_maps_api_key
```

The endpoints use:
- **Google Geocoding API** - Convert addresses to coordinates
- **Google Distance Matrix API** - Calculate distances between locations
- **Laravel Cache (Redis)** - Store temporary chat sessions (1800s TTL)

---

**Version**: 1.0  
**Last Updated**: 2026-06-04
