<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;

class QuickBookingController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'vehicle'        => ['required', 'string', 'max:100'],
            'pax'            => ['required', 'integer', 'min:1', 'max:100'],
            'date'           => ['nullable', 'date'],
            'days'           => ['required', 'integer', 'min:1', 'max:5'],
            'trip'           => ['required', Rule::in(['one-way', 'round-trip'])],
            'ac'             => ['required', Rule::in(['ac', 'non-ac'])],
            'pickup'         => ['required', 'string', 'max:200'],
            'drop'           => ['required', 'string', 'max:200'],
            'stops'          => ['nullable', 'array', 'max:5'],
            'stops.*'        => ['string', 'max:200'],
            'customer_name'  => ['nullable', 'string', 'max:150'],
            'customer_phone' => ['nullable', 'string', 'max:50'],
            'notes'          => ['nullable', 'string', 'max:2000'],
        ]);

        $vehicle = Vehicle::where('name', $request->input('vehicle'))->first();

        if (!$vehicle) {
            return response()->json(['message' => 'Vehicle not found.'], 404);
        }

        $isAc = $request->input('ac') === 'ac';

        if ($isAc && !$vehicle->ac_available) {
            return response()->json(['message' => "{$vehicle->name} does not have an AC option."], 422);
        }
        if (!$isAc && !$vehicle->non_ac_available) {
            return response()->json(['message' => "{$vehicle->name} does not have a Non-AC option."], 422);
        }

        // Geocode pickup
        $pickupCoords = $this->geocode($request->input('pickup'));
        if (!$pickupCoords) {
            return response()->json(['message' => 'Could not find pickup location: "' . $request->input('pickup') . '".'], 422);
        }

        // Geocode drop
        $dropCoords = $this->geocode($request->input('drop'));
        if (!$dropCoords) {
            return response()->json(['message' => 'Could not find drop location: "' . $request->input('drop') . '".'], 422);
        }

        // Geocode stops
        $resolvedStops = [];
        foreach ($request->input('stops', []) as $stop) {
            $coords = $this->geocode($stop);
            if (!$coords) {
                return response()->json(['message' => 'Could not find stop location: "' . $stop . '".'], 422);
            }
            $resolvedStops[] = $coords;
        }

        // Build waypoints and calculate total driving distance
        $waypoints       = array_merge([$pickupCoords], $resolvedStops, [$dropCoords]);
        $totalDistanceKm = $this->calculateRouteDistance($waypoints);

        if ($totalDistanceKm === null) {
            return response()->json(['message' => 'Could not calculate driving distance for this route.'], 422);
        }

        $billedKm    = $request->input('trip') === 'round-trip' ? $totalDistanceKm * 2 : $totalDistanceKm;
        $days        = (int) $request->input('days');
        $pricePerKm  = $isAc ? (float) $vehicle->ac_price_per_km : (float) $vehicle->non_ac_price_per_km;
        $stayField   = 'stay_price_day' . $days;
        $stayPrice   = (float) ($vehicle->$stayField ?? 0);
        $drivingCost = round($billedKm * $pricePerKm, 2);
        $totalCost   = round($drivingCost + $stayPrice, 2);

        $booking = Booking::create([
            'vehicle'         => $vehicle->name,
            'pickup'          => $pickupCoords['formatted'],
            'pickup_lat'      => $pickupCoords['lat'],
            'pickup_lng'      => $pickupCoords['lng'],
            'destination'     => $dropCoords['formatted'],
            'destination_lat' => $dropCoords['lat'],
            'destination_lng' => $dropCoords['lng'],
            'stops'           => collect($resolvedStops)->map(fn($s) => [
                'address' => $s['formatted'],
                'lat'     => $s['lat'],
                'lng'     => $s['lng'],
            ])->toArray(),
            'travel_date'     => $request->input('date') ?? now()->format('Y-m-d'),
            'days'            => $days,
            'trip'            => $request->input('trip'),
            'passengers'      => (int) $request->input('pax'),
            'ac'              => $request->input('ac'),
            'distance_km'     => round($totalDistanceKm, 2),
            'distance_source' => 'route',
            'price_per_km'    => $pricePerKm,
            'driving_cost'    => $drivingCost,
            'stay_cost'       => $stayPrice,
            'total_cost'      => $totalCost,
            'status'          => 'new',
            'customer_name'   => $request->input('customer_name'),
            'customer_phone'  => $request->input('customer_phone'),
            'notes'           => $request->input('notes'),
        ]);

        return response()->json([
            'booking_id'     => $booking->id,
            'vehicle'        => $booking->vehicle,
            'pickup'         => $booking->pickup,
            'drop'           => $booking->destination,
            'stops'          => collect($booking->stops)->pluck('address')->values(),
            'date'           => $booking->travel_date->format('Y-m-d'),
            'days'           => $booking->days,
            'trip'           => $booking->trip,
            'pax'            => $booking->passengers,
            'ac'             => $booking->ac,
            'distance_km'    => (float) $booking->distance_km,
            'billed_km'      => round($billedKm, 2),
            'price_per_km'   => (float) $booking->price_per_km,
            'driving_cost'   => (float) $booking->driving_cost,
            'stay_cost'      => (float) $booking->stay_cost,
            'total_cost'     => (float) $booking->total_cost,
            'currency'       => 'INR',
            'status'         => $booking->status,
        ], 201);
    }

    private function calculateRouteDistance(array $waypoints): ?float
    {
        $origins      = [];
        $destinations = [];

        for ($i = 0; $i < count($waypoints) - 1; $i++) {
            $origins[]      = $waypoints[$i]['lat'] . ',' . $waypoints[$i]['lng'];
            $destinations[] = $waypoints[$i + 1]['lat'] . ',' . $waypoints[$i + 1]['lng'];
        }

        $response = Http::withoutVerifying()->get('https://maps.googleapis.com/maps/api/distancematrix/json', [
            'origins'      => implode('|', $origins),
            'destinations' => implode('|', $destinations),
            'mode'         => 'driving',
            'units'        => 'metric',
            'key'          => config('services.google.maps_key'),
        ]);

        if (!$response->successful()) return null;

        $rows  = $response->json('rows') ?? [];
        $total = 0;

        foreach ($rows as $i => $row) {
            $element = $row['elements'][$i] ?? null;
            if (!$element || ($element['status'] ?? '') !== 'OK') return null;
            $total += $element['distance']['value'];
        }

        return $total / 1000;
    }

    private function geocode(string $address): ?array
    {
        $response = Http::withoutVerifying()->get('https://maps.googleapis.com/maps/api/geocode/json', [
            'address' => $address,
            'key'     => config('services.google.maps_key'),
        ]);

        $result = $response->json('results.0');

        if (!$result) return null;

        return [
            'lat'       => $result['geometry']['location']['lat'],
            'lng'       => $result['geometry']['location']['lng'],
            'formatted' => $result['formatted_address'],
        ];
    }
}
