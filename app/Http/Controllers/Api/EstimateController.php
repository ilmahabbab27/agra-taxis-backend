<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use App\Services\FareEstimator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;

class EstimateController extends Controller
{
    public function __construct(private FareEstimator $fareEstimator)
    {
    }

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

        $isAc        = $request->input('ac') === 'ac';
        $days        = (int) $request->input('days');

        if ($isAc && !$vehicle->ac_available) {
            return response()->json(['message' => "{$vehicle->name} does not have an AC option."], 422);
        }
        if (!$isAc && !$vehicle->non_ac_available) {
            return response()->json(['message' => "{$vehicle->name} does not have a Non-AC option."], 422);
        }

        $fare = $this->fareEstimator->estimate(
            $vehicle,
            $totalDistanceKm,
            $days,
            $request->input('ac'),
            $request->input('trip'),
            $pickupCoords,
            $dropCoords
        );

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
            'pickup_is_hill_country' => $fare['pickup_is_hill_country'],
            'drop_is_hill_country'   => $fare['drop_is_hill_country'],
            'hill_country_rate'      => $fare['is_hill_country'],
            'stops'          => collect($resolvedStops)->pluck('formatted')->values(),
            'route_legs'     => $routeLegs,
            'distance_km'    => round($totalDistanceKm, 2),
            'billed_km'      => $fare['billable_km'],
            'price_per_km'   => $fare['price_per_km'],
            'effective_price_per_km' => $fare['effective_price_per_km'],
            'trip_multiplier' => $fare['trip_multiplier'],
            'included_km'    => $fare['included_km'],
            'additional_km'  => $fare['additional_km'],
            'included_distance_charge' => $fare['included_distance_charge'],
            'additional_distance_charge' => $fare['additional_distance_charge'],
            'base_package_charge' => $fare['base_package_charge'],
            'package1_estimate' => $fare['package1_estimate'],
            'package2_estimate' => $fare['package2_estimate'],
            'driving_cost'   => $fare['driving_cost'],
            'stay_cost'      => $fare['stay_cost'],
            'total_cost'     => $fare['total_cost'],
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
