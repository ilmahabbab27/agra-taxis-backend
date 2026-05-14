# Agra Taxis Backend

Laravel API backend for Agra Taxis booking inquiries.

## Local Setup

```bash
composer install
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

The local database configured in `.env` is:

- Database: `agra_taxis_backend`
- Host: `127.0.0.1`
- Port: `3307`
- Username: `root`
- Password: `amju`

Default admin:

- Email: `admin@agrataxis.lk`
- Password: `agra2026`

## API

Base URL with `php artisan serve`:

```text
http://127.0.0.1:8000/api
```

Base URL through XAMPP Apache:

```text
http://localhost/Agra%20Taxis%20Backend/public/api
```

### Public

```http
POST /api/bookings
```

Body:

```json
{
  "vehicle": "Car (Sedan)",
  "pickup": "Colombo",
  "destination": "Kandy",
  "date": "2026-05-20",
  "days": 1,
  "trip": "One Way",
  "pax": 2,
  "ac": "AC",
  "pickupLat": 6.927079,
  "pickupLng": 79.861244,
  "destinationLat": 7.290572,
  "destinationLng": 80.633728,
  "distanceKm": 115.4,
  "distanceSource": "route",
  "mapUrl": "https://www.google.com/maps/dir/?api=1&origin=6.927079,79.861244&destination=7.290572,80.633728&travelmode=driving"
}
```

```http
POST /api/admin/login
```

Body:

```json
{
  "email": "admin@agrataxis.lk",
  "password": "agra2026"
}
```

### Admin

Use the login response token as:

```http
Authorization: Bearer YOUR_TOKEN
```

Endpoints:

```http
GET /api/bookings
PATCH /api/bookings/{booking}/status
DELETE /api/bookings/{booking}
POST /api/admin/logout
```
