# Chatbot API Endpoints Documentation

## Table of Contents
1. [Chatbot Message Endpoint](#1-chatbot-message-endpoint)
2. [Chatbot Estimate Endpoint](#2-chatbot-estimate-endpoint)
3. [Chatbot Booking Endpoint](#3-chatbot-booking-endpoint)

---

## 1. Chatbot Message Endpoint

### Endpoint
```
POST /api/chatbot/message
```

### Description
Handles multi-step conversational flow for taxi booking. Maintains session state through Redis cache (1800s TTL).

### Request Format

| Field | Type | Required | Max Length | Description |
|-------|------|----------|-----------|-------------|
| `session_id` | string | ✓ | 100 | Unique identifier for the chat session |
| `message` | string | ✓ | 500 | User message in the current conversation step |

#### Example Request
```json
{
  "session_id": "user_123_chat_1",
  "message": "Taj Mahal, Agra"
}
```

### Response Format

| Field | Type | Description |
|-------|------|-------------|
| `message` | string | Bot's response message (supports markdown formatting with * for bold) |
| `step` | string | Current conversation step (pickup, destination, vehicle, days, pax, ac, date, confirm, done) |

#### Example Response - Success (200 OK)
```json
{
  "message": "Got it! Pickup: *Taj Mahal, Agra*\n\nWhere are you heading to?",
  "step": "destination"
}
```

#### Example Response - Completion (200 OK)
```json
{
  "message": "Your booking has been confirmed!\n\nBooking ID: *#42*\nVehicle: Sedan\nPickup: Taj Mahal, Agra\nDestination: Fatehpur Sikri, Agra\nDate: 2026-06-15\nTotal: Rs. 8500.00\n\nWe will contact you shortly. Thank you!",
  "step": "done"
}
```

### Conversation Steps

| Step | Purpose | User Input | Bot Response |
|------|---------|-----------|--------------|
| `pickup` | Collect pickup location | Any address/location name | Confirms pickup, asks for destination |
| `destination` | Collect destination | Any address/location name | Confirms destination, shows vehicle options |
| `vehicle` | Select vehicle type | Vehicle number (1-N) or name | Confirms vehicle, asks for trip duration |
| `days` | Specify trip days | Number 1-5 | Confirms days, asks for passenger count |
| `pax` | Specify passenger count | Number 1-100 | Confirms passengers, asks for AC preference |
| `ac` | AC preference | ac / non-ac / both | Confirms preference, asks for travel date |
| `date` | Specify travel date | Date string (e.g., "25 May 2026") | Shows booking summary, asks for confirmation |
| `confirm` | Confirm booking | yes/y/confirm/ok/book or no/n/cancel | Creates booking or restarts conversation |
| `done` | Booking completed | N/A | Final confirmation message |

### Special Commands
- **Restart**: Send message "restart" to clear session and start over

### Error Responses

#### 422 Unprocessable Entity
```json
{
  "message": "I couldn't find that location. Could you be more specific? (e.g. \"Taj Mahal, Agra\")"
}
```

---

## 2. Chatbot Estimate Endpoint

### Endpoint
```
POST /api/chatbot/estimate
```

### Description
Calculates fare estimate based on locations, vehicle type, and trip details. Uses Google Maps Distance Matrix API.

### Request Format

| Field | Type | Required | Max Length | Description |
|-------|------|----------|-----------|-------------|
| `pickup_text` | string | conditional* | 200 | Pickup location address (required if coordinates not provided) |
| `pickup_lat` | number | conditional* | - | Pickup latitude |
| `pickup_lng` | number | conditional* | - | Pickup longitude |
| `dropoff_text` | string | conditional* | 200 | Dropoff location address (required if coordinates not provided) |
| `dropoff_lat` | number | conditional* | - | Dropoff latitude |
| `dropoff_lng` | number | conditional* | - | Dropoff longitude |
| `vehicle` | string | ✗ | 100 | Vehicle type name (default: "Sedan") |
| `ac` | string | ✗ | - | AC preference: "ac" or "non-ac" (default: "ac") |
| `days` | integer | ✗ | - | Number of days 1-5 (default: 1) |

*Either text OR coordinates must be provided for pickup and dropoff

#### Example Request - With Text Addresses
```json
{
  "pickup_text": "Taj Mahal, Agra",
  "dropoff_text": "Fatehpur Sikri, Agra",
  "vehicle": "Sedan",
  "ac": "ac",
  "days": 1
}
```

#### Example Request - With Coordinates
```json
{
  "pickup_lat": 27.1751,
  "pickup_lng": 78.0421,
  "dropoff_lat": 27.5933,
  "dropoff_lng": 78.1442,
  "vehicle": "SUV",
  "ac": "non-ac",
  "days": 2
}
```

### Response Format - Success (200 OK)

| Field | Type | Description |
|-------|------|-------------|
| `success` | boolean | Always true on success |
| `pickup` | string | Formatted pickup address from Google Maps |
| `dropoff` | string | Formatted dropoff address from Google Maps |
| `distance_km` | number | Calculated distance in kilometers |
| `vehicle` | string | Selected vehicle name |
| `ac` | string | AC label ("AC" or "Non-AC") |
| `days` | integer | Number of days |
| `price_per_km` | number | Price per kilometer for selected vehicle and AC type |
| `driving_cost` | number | Cost for driving distance (price_per_km × distance_km) |
| `stay_cost` | number | Stay cost based on vehicle and number of days |
| `total_cost` | number | Total cost (driving_cost + stay_cost) |
| `message` | string | Human-readable estimate summary |

#### Example Response - Success (200 OK)
```json
{
  "success": true,
  "pickup": "Taj Mahal, Agra, Uttar Pradesh, India",
  "dropoff": "Fatehpur Sikri, Agra, Uttar Pradesh, India",
  "distance_km": 37.45,
  "vehicle": "Sedan",
  "ac": "AC",
  "days": 1,
  "price_per_km": 15.50,
  "driving_cost": 580.48,
  "stay_cost": 2000.00,
  "total_cost": 2580.48,
  "message": "I assumed pickup is Taj Mahal, Agra, Uttar Pradesh, India and dropoff is Fatehpur Sikri, Agra, Uttar Pradesh, India. Estimated fare is Rs. 2580.48 for approximately 37.45 km."
}
```

### Response Format - Error (422 Unprocessable Entity)

| Field | Type | Description |
|-------|------|-------------|
| `success` | boolean | Always false on error |
| `message` | string | Error message describing what went wrong |

#### Example Response - Error
```json
{
  "success": false,
  "message": "Could not resolve one or both locations. Please provide clearer pickup and dropoff information."
}
```

---

## 3. Chatbot Booking Endpoint

### Endpoint
```
POST /api/chatbot/booking
POST /api/quick-booking
```

### Description
Creates a booking with detailed location, vehicle, and passenger information. Automatically geocodes all locations and calculates total distance including stops.

### Request Format

| Field | Type | Required | Min/Max | Description |
|-------|------|----------|---------|-------------|
| `vehicle` | string | ✓ | max: 100 | Vehicle name (must exist in database) |
| `pax` | integer | ✓ | 1-100 | Number of passengers |
| `date` | date | ✗ | - | Travel date (YYYY-MM-DD format). Defaults to today if not provided |
| `days` | integer | ✓ | 1-5 | Number of days for the trip |
| `trip` | string | ✓ | - | Trip type: "one-way" or "round-trip" |
| `ac` | string | ✓ | - | AC preference: "ac" or "non-ac" |
| `pickup` | string | ✓ | max: 200 | Pickup location address |
| `drop` | string | ✓ | max: 200 | Dropoff location address |
| `stops` | array | ✗ | max: 5 items | Array of intermediate stop locations |
| `stops[*]` | string | ✗ | max: 200 | Individual stop address |
| `customer_name` | string | ✗ | max: 150 | Customer's full name |
| `customer_phone` | string | ✗ | max: 50 | Customer's phone number |
| `notes` | string | ✗ | max: 2000 | Special requests or notes |

#### Example Request - Basic
```json
{
  "vehicle": "Sedan",
  "pax": 2,
  "days": 1,
  "trip": "one-way",
  "ac": "ac",
  "pickup": "Taj Mahal, Agra",
  "drop": "Agra Fort, Agra",
  "customer_name": "John Doe",
  "customer_phone": "+91-9876543210"
}
```

#### Example Request - With Stops and Notes
```json
{
  "vehicle": "SUV",
  "pax": 4,
  "date": "2026-06-20",
  "days": 2,
  "trip": "round-trip",
  "ac": "ac",
  "pickup": "Adarsh Nagar, Agra",
  "drop": "Firozabad, Agra",
  "stops": [
    "Mathura, Uttar Pradesh",
    "Vrindavan, Uttar Pradesh"
  ],
  "customer_name": "Jane Smith",
  "customer_phone": "+91-9876543210",
  "notes": "Please arrive 15 minutes early. Child seat required for infant."
}
```

### Response Format - Success (201 Created)

| Field | Type | Description |
|-------|------|-------------|
| `booking_id` | integer | Unique booking reference number |
| `vehicle` | string | Selected vehicle name |
| `pickup` | string | Formatted pickup address |
| `drop` | string | Formatted dropoff address |
| `stops` | array | Array of stop addresses (ordered) |
| `date` | string | Travel date (YYYY-MM-DD format) |
| `days` | integer | Number of days |
| `trip` | string | Trip type ("one-way" or "round-trip") |
| `pax` | integer | Number of passengers |
| `ac` | string | AC preference ("ac" or "non-ac") |
| `distance_km` | number | Calculated route distance in km |
| `billed_km` | number | Distance billed (doubled for round-trip) |
| `price_per_km` | number | Price per kilometer |
| `driving_cost` | number | Total driving cost |
| `stay_cost` | number | Stay/parking cost based on days |
| `total_cost` | number | Total booking cost |
| `currency` | string | Currency code ("INR") |
| `status` | string | Booking status ("new") |

#### Example Response - Success (201 Created)
```json
{
  "booking_id": 42,
  "vehicle": "Sedan",
  "pickup": "Taj Mahal, Agra, Uttar Pradesh, India",
  "drop": "Agra Fort, Agra, Uttar Pradesh, India",
  "stops": [],
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

### Response Format - Error (422 or 404)

#### Vehicle Not Found (404)
```json
{
  "message": "Vehicle not found."
}
```

#### Vehicle AC/Non-AC Unavailable (422)
```json
{
  "message": "Sedan does not have an AC option."
}
```

#### Location Not Found (422)
```json
{
  "message": "Could not find pickup location: \"Invalid Place Name\"."
}
```

#### Route Calculation Failed (422)
```json
{
  "message": "Could not calculate driving distance for this route."
}
```

---

## Common Response Codes

| Code | Meaning | When Occurs |
|------|---------|------------|
| 200 | OK | Chatbot message processed successfully |
| 201 | Created | Booking successfully created |
| 422 | Unprocessable Entity | Validation failed, invalid input, or location not found |
| 404 | Not Found | Vehicle type does not exist |
| 500 | Internal Server Error | Google Maps API error or server issue |

---

## Notes

1. **Session Management**: `/chatbot/message` stores session data in Redis cache with 30-minute (1800s) TTL. Sessions are automatically cleared after booking completion.

2. **Geocoding**: All location names are converted to coordinates using Google Maps Geocoding API. Provide clear, specific addresses for better results.

3. **Distance Calculation**: Uses Google Maps Distance Matrix API for accurate driving distances and durations.

4. **Price Calculation**:
   - **Driving Cost** = Distance (km) × Price Per KM × (2 if round-trip)
   - **Stay Cost** = Vehicle's stay_price_dayX (where X = number of days)
   - **Total Cost** = Driving Cost + Stay Cost

5. **Vehicle Availability**: Check `/api/vehicles` endpoint to get available vehicle types and their pricing details.

6. **Testing**: Use session IDs like "test_session_1", "user_123", etc. for testing the message endpoint.

---

## Integration Example

```javascript
// Example: Complete chatbot flow using JavaScript

async function chatbotFlow() {
  const sessionId = 'user_' + Date.now();
  
  // Step 1: Send pickup location
  let response = await fetch('/api/chatbot/message', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      session_id: sessionId,
      message: 'Taj Mahal, Agra'
    })
  });
  
  // Step 2: Send destination
  response = await fetch('/api/chatbot/message', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      session_id: sessionId,
      message: 'Agra Fort'
    })
  });
  
  // Continue with vehicle selection, days, passengers, AC, date, and confirmation...
  
  // Final: Create booking directly (alternative to confirmation step)
  const booking = await fetch('/api/chatbot/booking', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      vehicle: 'Sedan',
      pax: 2,
      days: 1,
      trip: 'one-way',
      ac: 'ac',
      pickup: 'Taj Mahal, Agra',
      drop: 'Agra Fort, Agra',
      customer_name: 'John Doe',
      customer_phone: '+91-9876543210'
    })
  });
}
```

---

**Last Updated**: 2026-06-04
**API Version**: 1.0
