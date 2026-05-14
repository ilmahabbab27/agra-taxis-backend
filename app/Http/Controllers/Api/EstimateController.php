<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;

class EstimateController extends Controller
{
    public function calculate(Request $request)
    {
        $request->validate([
            'vehicle'         => ['required', 'string', 'max:100'],
            'pax'             => ['required', 'integer', 'min:1', 'max:100'],
            'date'            => ['required', 'date'],
            'days'            => ['required', 'integer', 'min:1', 'max:5'],
            'trip'            => ['required', Rule::in(['one-way', 'round-trip'])],
            'ac'              => ['required', Rule::in(['ac', 'non-ac'])],
            'pickup'          => ['required', 'string', 'max:200'],
            'drop'            => ['required', 'string', 'max:200'],
            'stops'           => ['nullable', 'array', 'max:5'],
            'stops.*'         => ['string', 'max:200'],
        ]);

        $vehicle = Vehicle::where('name', $request->input('vehicle'))->first();

        if (!$vehicle) {
            return response()->json(['message' => 'Vehicle not found.'], 404);
        }

        // Geocode pickup
        $pickupCoords = $this->geocode($request->input('pickup'));
        if (!$pickupCoords) {
            return response()->json(['message' => 'Could not find pickup location: "' . $request->input('pickup') . '". Please be more specific.'], 422);
        }

        // Geocode drop
        $dropCoords = $this->geocode($request->input('drop'));
        if (!$dropCoords) {
            return response()->json(['message' => 'Could not find drop location: "' . $request->input('drop') . '". Please be more specific.'], 422);
        }

        // Geocode stops
        $resolvedStops = [];
        foreach ($request->input('stops', []) as $stop) {
            $coords = $this->geocode($stop);
            if (!$coords) {
                return response()->json(['message' => 'Could not find stop location: "' . $stop . '". Please be more specific.'], 422);
            }
            $resolvedStops[] = $coords;
        }

        // Build waypoints list: pickup → stops → drop
        $waypoints = array_merge(
            [$pickupCoords],
            $resolvedStops,
            [$dropCoords],
        );

        $totalDistanceKm = $this->calculateRouteDistance($waypoints);

        if ($totalDistanceKm === null) {
            return response()->json(['message' => 'Could not calculate driving distance for this route.'], 422);
        }

        // For round trips double the distance
        $isRoundTrip = $request->input('trip') === 'round-trip';
        $billedKm    = $isRoundTrip ? $totalDistanceKm * 2 : $totalDistanceKm;

        $isAc        = $request->input('ac') === 'ac';
        $days        = (int) $request->input('days');

        if ($isAc && !$vehicle->ac_available) {
            return response()->json(['message' => "{$vehicle->name} does not have an AC option."], 422);
        }
        if (!$isAc && !$vehicle->non_ac_available) {
            return response()->json(['message' => "{$vehicle->name} does not have a Non-AC option."], 422);
        }

        $pricePerKm  = $isAc ? (float) $vehicle->ac_price_per_km : (float) $vehicle->non_ac_price_per_km;
        $stayField   = 'stay_price_day' . $days;
        $stayPrice   = (float) ($vehicle->$stayField ?? 0);
        $drivingCost = round($billedKm * $pricePerKm, 2);
        $totalCost   = round($drivingCost + $stayPrice, 2);

        $routeLegs = [];
        for ($i = 0; $i < count($waypoints) - 1; $i++) {
            $routeLegs[] = [
                'from' => $waypoints[$i]['formatted'],
                'to'   => $waypoints[$i + 1]['formatted'],
            ];
        }

        return response()->json([
            'vehicle'        => $vehicle->name,
            'category'       => $vehicle->category,
            'seats'          => $vehicle->seats,
            'ac'             => $isAc,
            'trip'           => $request->input('trip'),
            'date'           => $request->input('date'),
            'days'           => $days,
            'pax'            => (int) $request->input('pax'),
            'pickup'         => $pickupCoords['formatted'],
            'drop'           => $dropCoords['formatted'],
            'stops'          => collect($resolvedStops)->pluck('formatted')->values(),
            'route_legs'     => $routeLegs,
            'distance_km'    => round($totalDistanceKm, 2),
            'billed_km'      => round($billedKm, 2),
            'price_per_km'   => $pricePerKm,
            'driving_cost'   => $drivingCost,
            'stay_cost'      => $stayPrice,
            'total_cost'     => $totalCost,
            'currency'       => 'INR',
        ]);
    }

    /**
     * Sum driving distances across all legs: A→B, B→C, C→D ...
     */
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

        if (!$response->successful()) {
            return null;
        }

        $rows  = $response->json('rows') ?? [];
        $total = 0;

        foreach ($rows as $i => $row) {
            $element = $row['elements'][$i] ?? null;
            if (!$element || ($element['status'] ?? '') !== 'OK') {
                return null;
            }
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
