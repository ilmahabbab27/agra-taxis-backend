<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BookingController extends Controller
{
    public function index(Request $request)
    {
        $query = Booking::query()->latest();

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('q')) {
            $term = $request->q;
            $query->where(function ($builder) use ($term) {
                $builder->where('vehicle', 'like', "%{$term}%")
                    ->orWhere('pickup', 'like', "%{$term}%")
                    ->orWhere('destination', 'like', "%{$term}%")
                    ->orWhere('customer_name', 'like', "%{$term}%")
                    ->orWhere('customer_phone', 'like', "%{$term}%");
            });
        }

        return response()->json([
            'data' => $query->paginate((int) $request->input('per_page', 20))->through(function (Booking $booking) {
                return $this->format($booking);
            }),
        ]);
    }

    public function store(Request $request)
    {
        $request->merge([
            'pickup_lat' => $request->input('pickup_lat', $request->input('pickupLat')),
            'pickup_lng' => $request->input('pickup_lng', $request->input('pickupLng')),
            'destination_lat' => $request->input('destination_lat', $request->input('destinationLat')),
            'destination_lng' => $request->input('destination_lng', $request->input('destinationLng')),
            'distance_km' => $request->input('distance_km', $request->input('distanceKm')),
            'distance_source' => $request->input('distance_source', $request->input('distanceSource')),
            'map_url' => $request->input('map_url', $request->input('mapUrl')),
        ]);

        $data = $request->validate([
            'vehicle' => ['required', 'string', 'max:100'],
            'pickup' => ['required', 'string', 'max:150'],
            'pickup_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'pickup_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'destination' => ['required', 'string', 'max:150'],
            'destination_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'destination_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'date' => ['required', 'date'],
            'days' => ['required', 'integer', 'min:1', 'max:365'],
            'trip' => ['required', 'string', 'max:50'],
            'pax' => ['required', 'integer', 'min:1', 'max:100'],
            'ac' => ['required', 'string', 'max:50'],
            'distance_km' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'distance_source' => ['nullable', Rule::in(['route', 'straight'])],
            'map_url' => ['nullable', 'url', 'max:2000'],
            'customer_name' => ['nullable', 'string', 'max:150'],
            'customer_phone' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $booking = Booking::create([
            'vehicle' => $data['vehicle'],
            'pickup' => $data['pickup'],
            'pickup_lat' => $data['pickup_lat'] ?? null,
            'pickup_lng' => $data['pickup_lng'] ?? null,
            'destination' => $data['destination'],
            'destination_lat' => $data['destination_lat'] ?? null,
            'destination_lng' => $data['destination_lng'] ?? null,
            'travel_date' => $data['date'],
            'days' => $data['days'],
            'trip' => $data['trip'],
            'passengers' => $data['pax'],
            'ac' => $data['ac'],
            'distance_km' => $data['distance_km'] ?? null,
            'distance_source' => $data['distance_source'] ?? null,
            'map_url' => $data['map_url'] ?? null,
            'status' => 'new',
            'customer_name' => $data['customer_name'] ?? null,
            'customer_phone' => $data['customer_phone'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        return response()->json(['data' => $this->format($booking)], 201);
    }

    public function updateStatus(Request $request, Booking $booking)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(Booking::STATUSES)],
        ]);

        $booking->update(['status' => $data['status']]);

        return response()->json(['data' => $this->format($booking)]);
    }

    public function destroy(Booking $booking)
    {
        $booking->delete();

        return response()->json(['message' => 'Booking deleted.']);
    }

    private function format(Booking $booking)
    {
        return [
            'id' => (string) $booking->id,
            'createdAt' => $booking->created_at->toISOString(),
            'vehicle' => $booking->vehicle,
            'pickup' => $booking->pickup,
            'pickup_lat' => $booking->pickup_lat,
            'pickup_lng' => $booking->pickup_lng,
            'destination' => $booking->destination,
            'destination_lat' => $booking->destination_lat,
            'destination_lng' => $booking->destination_lng,
            'date' => optional($booking->travel_date)->format('Y-m-d'),
            'days' => (string) $booking->days,
            'trip' => $booking->trip,
            'pax' => (string) $booking->passengers,
            'ac' => $booking->ac,
            'distance_km' => $booking->distance_km,
            'distance_source' => $booking->distance_source,
            'map_url' => $booking->map_url,
            'status' => $booking->status,
            'customer_name' => $booking->customer_name,
            'customer_phone' => $booking->customer_phone,
            'notes' => $booking->notes,
        ];
    }
}
