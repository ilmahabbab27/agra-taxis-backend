# WhatsApp Lorry Chatbot - Complete Implementation Guide

## Quick Start

### Endpoint
```
POST /api/lorry-chatbot/message
```

### Request Format
```json
{
  "session_id": "user_phone_number_or_unique_id",
  "message": "user text input"
}
```

### Response Format
```json
{
  "message": "Bot response message",
  "step": "current_step_name"
}
```

---

## Complete Flow Example

### Conversation 1: One-Way Trip

**User 1: Pickup**
```json
Request:
{
  "session_id": "27812345678",
  "message": "Colombo"
}

Response:
{
  "message": "Got it! Pickup: *Colombo, Western Province, Sri Lanka*\n\nWhere are you heading to?",
  "step": "destination"
}
```

**User 2: Destination**
```json
Request:
{
  "session_id": "27812345678",
  "message": "Kandy"
}

Response:
{
  "message": "Destination: *Kandy, Central Province, Sri Lanka*\n\nWhat type of trip?\n1. *One Way*\n2. *Round Trip*\n\nReply with number or text.",
  "step": "trip_type"
}
```

**User 3: Trip Type**
```json
Request:
{
  "session_id": "27812345678",
  "message": "1"
}

Response:
{
  "message": "Great! *one-way* selected.\n\nAvailable Lorry Types:\n\n1. *7 FT* — Rs. 2000 for 0-50 km\n2. *8.5 FT* — Rs. 2800 for 0-50 km\n3. *10.5 FT* — Rs. 4800 for 0-50 km\n4. *12.5 FT* — Rs. 6000 for 0-50 km\n5. *14.5 FT* — Rs. 8000 for 0-50 km\n6. *16.5 FT* — Rs. 8800 for 0-50 km\n7. *18.5 FT* — Rs. 12800 for 0-50 km\n8. *20 FT* — Rs. 14400 for 0-50 km\n9. *24 FT* — Rs. 18000 for 0-50 km\n\nReply with number or lorry type name.",
  "step": "lorry_type"
}
```

**User 4: Lorry Type**
```json
Request:
{
  "session_id": "27812345678",
  "message": "7 FT"
}

Response:
{
  "message": "Great! *7 FT* selected.\n\nWhat's the distance in kilometers? (e.g. 250)",
  "step": "distance"
}
```

**User 5: Distance**
```json
Request:
{
  "session_id": "27812345678",
  "message": "115"
}

Response:
{
  "message": "📋 *Booking Summary*\n\nPickup: Colombo, Western Province, Sri Lanka\nDestination: Kandy, Central Province, Sri Lanka\nDistance: 115 km (Not calculated)\nLorry: 7 FT\nTrip: one-way\nRoute: 🏔️ Hill Country\n\n💰 *Fare Breakdown*\nBase Fee: Rs. 2500\nExtra KM (65 km): Rs. 10400\n\n💵 *Total Fare: Rs. 12900*\n\nReply *yes* to confirm or *no* to start over.",
  "step": "confirm"
}
```

**User 6: Confirmation**
```json
Request:
{
  "session_id": "27812345678",
  "message": "yes"
}

Response:
{
  "message": "✅ Your booking has been confirmed!\n\nBooking ID: *#12345*\nLorry Type: 7 FT\nPickup: Colombo, Western Province, Sri Lanka\nDestination: Kandy, Central Province, Sri Lanka\nDistance: 115 km\nTrip: one-way\nEstimated Fare: *Rs. 12900*\n\nWe will contact you shortly. Thank you!",
  "step": "done"
}
```

---

## Distance Calculation Options

### Option A: User Provides Distance
```json
{
  "session_id": "27812345678",
  "message": "250"
}
```
Bot calculates immediately without API calls.

### Option B: Auto-Calculate from Coordinates
```json
{
  "session_id": "27812345678",
  "message": "auto"
}
```
Bot uses stored pickup/destination coordinates with Google Maps API.

---

## Data Stored per Session

```javascript
{
  "pickup_text": "Colombo, Western Province, Sri Lanka",
  "pickup_lat": 6.9271,
  "pickup_lng": 80.6369,
  "destination_text": "Kandy, Central Province, Sri Lanka",
  "destination_lat": 7.2906,
  "destination_lng": 80.6337,
  "trip_type": "round-trip",
  "lorry_type": "7 FT",
  "lorry_id": 10,
  "rate_type": "7ft",
  "distance_km": 250,
  "duration_text": "5 hours",
  "is_hill_country": true,
  "total_cost": 13000,
  "base_fee": 3000,
  "extra_fee": 10000,
  "extra_km": 50,
  "waiting_charge": 0
}
```

---

## Booking Creation

When user confirms (`step: confirm` + `message: yes`), the system creates:

```javascript
{
  "vehicle": "7 FT",           // lorry_type
  "pickup": "Colombo, ...",    // pickup_text
  "pickup_lat": 6.9271,
  "pickup_lng": 80.6369,
  "destination": "Kandy, ...",  // destination_text
  "destination_lat": 7.2906,
  "destination_lng": 80.6337,
  "travel_date": "2026-06-17",  // tomorrow by default
  "days": 1,
  "trip": "round-trip",         // from trip_type
  "passengers": 1,              // default for lorry
  "distance_km": 250,
  "distance_source": "chatbot",
  "total_cost": 13000,
  "status": "new"               // awaiting approval
}
```

---

## Fare Calculation Logic

### Formula Applied
```javascript
// Determine which distance window applies
if (distance >= 100 && distance <= 200) {
  baseFee = 3000;
  extraPerKm = 180;
  hillExtraPerKm = 15;
}

// Calculate extra km beyond window
extraKm = 250 - 200 = 50;

// Apply formula based on trip type
if (trip === "round-trip" && isHillCountry) {
  extraFee = 50 * 200 = 10,000;  // 200 is upDownHill
  // (not 180 + 15, but the upDownHill value)
}

totalFare = baseFee + extraFee = 3000 + 10000 = 13,000
```

---

## Error Handling

| Scenario | Response | Next Step |
|----------|----------|-----------|
| Invalid location | "I couldn't find that location. Could you be more specific?" | Retry same step |
| Invalid trip choice | "Please reply with: 1. One Way or 2. Round Trip" | Retry same step |
| Invalid lorry choice | "Please choose a valid lorry by number or name" | Retry with valid options |
| Invalid distance | "Please enter a distance greater than 0 km" | Retry same step |
| Distance calculation failed | "I couldn't calculate the distance. Please enter it manually" | Retry with manual entry |
| Confirmation error | "Please reply yes to confirm or no to start over" | Retry confirmation |
| Session timeout | User starts new session | New `session_id` required |

---

## WhatsApp Integration Steps

### 1. Choose WhatsApp Business API Provider
- Twilio
- Meta (Facebook) Business API
- MessageBird
- Others...

### 2. Configure Webhook
Point WhatsApp provider to your endpoint:
```
https://your-domain.com/api/lorry-chatbot/message
```

### 3. Map Incoming Message to Session
```javascript
// WhatsApp message received
{
  "from": "27812345678",        // User phone (becomes session_id)
  "text": "Colombo"              // User message
}

// Transform to our format
{
  "session_id": "27812345678",
  "message": "Colombo"
}

// Send to backend
POST /api/lorry-chatbot/message
```

### 4. Send Response Back to WhatsApp
```javascript
// Backend response
{
  "message": "Got it! Pickup: Colombo\n\n...",
  "step": "destination"
}

// Transform for WhatsApp
{
  "to": "27812345678",
  "text": "Got it! Pickup: Colombo\n\n..."
}

// Send via WhatsApp provider API
```

---

## Testing

### Using cURL
```bash
# Step 1: Pickup
curl -X POST http://localhost:8000/api/lorry-chatbot/message \
  -H "Content-Type: application/json" \
  -d '{
    "session_id": "test_123",
    "message": "Colombo"
  }'

# Step 2: Destination
curl -X POST http://localhost:8000/api/lorry-chatbot/message \
  -H "Content-Type: application/json" \
  -d '{
    "session_id": "test_123",
    "message": "Kandy"
  }'

# Step 3: Trip Type
curl -X POST http://localhost:8000/api/lorry-chatbot/message \
  -H "Content-Type: application/json" \
  -d '{
    "session_id": "test_123",
    "message": "2"
  }'

# Step 4: Lorry Type
curl -X POST http://localhost:8000/api/lorry-chatbot/message \
  -H "Content-Type: application/json" \
  -d '{
    "session_id": "test_123",
    "message": "1"
  }'

# Step 5: Distance
curl -X POST http://localhost:8000/api/lorry-chatbot/message \
  -H "Content-Type: application/json" \
  -d '{
    "session_id": "test_123",
    "message": "250"
  }'

# Step 6: Confirm
curl -X POST http://localhost:8000/api/lorry-chatbot/message \
  -H "Content-Type: application/json" \
  -d '{
    "session_id": "test_123",
    "message": "yes"
  }'
```

### Using Postman
1. Set method to `POST`
2. URL: `http://localhost:8000/api/lorry-chatbot/message`
3. Headers: `Content-Type: application/json`
4. Body (raw):
```json
{
  "session_id": "test_user_123",
  "message": "Colombo"
}
```
5. Click Send and follow the conversation steps

---

## Session Management

### Cache Storage
- **Key Format**: `lorry_chatbot:{session_id}`
- **TTL**: 1800 seconds (30 minutes)
- **Data**: Step name + collected data so far

### Session Restart
User can type `restart` at any time:
```json
{
  "session_id": "27812345678",
  "message": "restart"
}
```

Response:
```json
{
  "message": "Sure! Let's start over. Where would you like to be picked up from?",
  "step": "pickup"
}
```

---

## Integration Checklist

- [ ] Add `LorryChatbotController.php` to `app/Http/Controllers/Api/`
- [ ] Register route in `routes/api.php`
- [ ] Test endpoints with cURL or Postman
- [ ] Configure WhatsApp Business API webhook
- [ ] Map WhatsApp messages to our endpoint format
- [ ] Map our responses back to WhatsApp format
- [ ] Test full conversation flow
- [ ] Verify bookings are created in database
- [ ] Set up admin notifications for new bookings
- [ ] Monitor session cache and cleanup

---

## Advanced Features (Optional)

1. **Multi-language support** - Add language selection at start
2. **Payment integration** - Collect payment after confirmation
3. **Driver assignment** - Auto-assign nearest available driver
4. **Real-time tracking** - Send tracking link after booking
5. **Ratings** - Ask for feedback after trip
6. **Recurring bookings** - Offer repeat same route button
7. **Promo codes** - Validate discounts before booking

---

## Performance Notes

- **Google Maps API calls**: 2 per booking (geocode pickup + destination)
- **Optional**: 1 more for distance calculation if user selects "auto"
- **Database queries**: Minimal - only on confirmation
- **Session cache**: Cleared after 30 minutes of inactivity
- **No frontend needed** - Pure API, WhatsApp handles UI

