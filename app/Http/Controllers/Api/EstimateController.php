<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lorry;
use App\Models\Vehicle;
use App\Services\LorryEstimator;
use App\Services\FareEstimator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;

class EstimateController extends Controller
{
    public function __construct(
        private FareEstimator $fareEstimator,
        private LorryEstimator $lorryEstimator
    )
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

        $isRoundTrip = in_array(strtolower((string) $request->input('trip')), ['round-trip', 'round trip'], true);

        // Build waypoints list: pickup â†’ stops â†’ drop â†’ pickup (round trip)
        $waypoints = array_merge(
            [$pickupCoords],
            $resolvedStops,
            [$dropCoords],
        );
        if ($isRoundTrip) {
            $waypoints[] = $pickupCoords;
        }

        $distanceResult = $this->calculateRouteDistance($waypoints);
        $totalDistanceKm = $distanceResult['km'];

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

        $routeSummary = $pickupCoords['formatted'] . ' to ' . $dropCoords['formatted'];
        if (! empty($resolvedStops)) {
            $routeSummary .= ' via ' . collect($resolvedStops)->pluck('formatted')->implode(', ');
        }

        $estimateSummary = $this->passengerBillingExplanation($days, (float) $totalDistanceKm, (float) $fare['included_km']);
        $estimateNote = $this->buildPassengerEstimateNote(
            $days,
            (float) $totalDistanceKm,
            (float) $fare['included_km'],
            (float) $fare['price_per_km'],
            $routeSummary,
            $estimateSummary,
            $fare['package1_estimate'] ?? null,
            $fare['package2_estimate'] ?? null
        );

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
            'pickup_map'     => $this->googleMapsUrl($pickupCoords['lat'], $pickupCoords['lng']),
            'drop'           => $dropCoords['formatted'],
            'drop_map'       => $this->googleMapsUrl($dropCoords['lat'], $dropCoords['lng']),
            'route_summary'  => $routeSummary,
            'pickup_is_hill_country' => $fare['pickup_is_hill_country'],
            'drop_is_hill_country'   => $fare['drop_is_hill_country'],
            'hill_country_rate'      => $fare['is_hill_country'],
            'stops'          => collect($resolvedStops)->pluck('formatted')->values(),
            'stop_maps'      => collect($resolvedStops)->map(fn (array $stop) => $this->googleMapsUrl($stop['lat'], $stop['lng']))->values(),
            'route_legs'     => $routeLegs,
            'distance_km'    => round($totalDistanceKm, 2),
            'distance_source' => $distanceResult['source'],
            'included_km_per_day' => 150,
            'estimate_summary' => $estimateSummary,
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
            'base_fare'       => $fare['base_fare'],
            'include_operating_costs' => $fare['include_operating_costs'],
            'operating_cost'  => $fare['operating_cost'],
            'vehicle_cost'    => $fare['vehicle_cost'],
            'driver_charge'   => $fare['driver_charge'],
            'fuel_cost'       => $fare['fuel_cost'],
            'commission_rate' => $fare['commission_rate'],
            'commission_amount' => $fare['commission_amount'],
            'driving_cost'   => $fare['driving_cost'],
            'stay_cost'      => $fare['stay_cost'],
            'total_cost'     => $fare['total_cost'],
            'currency'       => 'INR',
        ]);
    }

    public function externalCalculate(Request $request)
    {
        $request->validate([
            'type' => ['nullable', Rule::in(['vehicle', 'lorry'])],
            'vehicle' => ['nullable', 'string', 'max:100'],
            'lorry_id' => ['nullable', 'integer', 'exists:lorries,id'],
            'lorry' => ['nullable', 'string', 'max:100'],
            'rate_type' => ['nullable', 'string', 'max:100'],
            'distance_km' => ['nullable', 'numeric', 'min:0'],
            'days' => ['required', 'integer', 'min:1', 'max:30'],
            'trip' => ['required', Rule::in(['one-way', 'round-trip'])],
            'ac' => ['nullable', Rule::in(['ac', 'non-ac'])],
            'pickup' => ['nullable'],
            'drop' => ['nullable'],
            'stops' => ['nullable', 'array', 'max:10'],
            'stops.*' => ['nullable'],
        ]);

        $type = $request->input('type', $request->filled('lorry_id') || $request->filled('lorry') ? 'lorry' : 'vehicle');

        if ($type === 'lorry') {
            $rateTypeInput = (string) $request->input('rate_type', '');
            $lorry = null;
            if ($request->filled('lorry_id')) {
                $lorry = Lorry::find($request->input('lorry_id'));
            } elseif ($request->filled('lorry')) {
                $lorry = Lorry::where('name', $request->input('lorry'))->first();
            } elseif ($rateTypeInput !== '') {
                $normalizedRateType = $this->normalizeRateType($rateTypeInput);
                $lorry = Lorry::all()->first(function (Lorry $candidate) use ($normalizedRateType) {
                    $rateTable = $candidate->rate_table;
                    if (! is_array($rateTable) || empty($rateTable)) {
                        return false;
                    }

                    foreach ($rateTable as $key => $row) {
                        if ($this->normalizeRateType((string) $key) === $normalizedRateType) {
                            return true;
                        }

                        if (is_array($row) && isset($row['type']) && $this->normalizeRateType((string) $row['type']) === $normalizedRateType) {
                            return true;
                        }
                    }

                    return false;
                });
            }

            if (! $lorry) {
                return response()->json(['success' => false, 'message' => 'Lorry not found. Send lorry_id, lorry name, or a valid rate_type.'], 404);
            }

            $rateTable = $lorry->rate_table;
            if (! is_array($rateTable) || empty($rateTable)) {
                return response()->json(['success' => false, 'message' => "No rate table configured for lorry '{$lorry->name}'."], 422);
            }

            $rateType = $this->resolveRateTypeKey($rateTable, $rateTypeInput);
            if (! $rateType) {
                return response()->json(['success' => false, 'message' => 'Valid rate_type is required for lorry estimates.'], 422);
            }

            $isRoundTrip = in_array(strtolower((string) $request->input('trip')), ['round-trip', 'round trip'], true);
            $distanceResult = null;
            $pickupCoords = null;
            $dropCoords = null;
            $resolvedStops = [];
            if ($request->filled('pickup') && $request->filled('drop')) {
                [$distanceResult, $pickupCoords, $dropCoords, $resolvedStops] = $this->resolveExternalRouteDistance($request, $isRoundTrip);
            } else {
                $distanceResult = ['km' => (float) $request->input('distance_km', 0), 'source' => 'manual'];
            }

            $distanceKm = (float) ($distanceResult['km'] ?? 0);
            $isHillCountry = $this->fareEstimator->isHillCountry($pickupCoords ?? []) || $this->fareEstimator->isHillCountry($dropCoords ?? []);
            foreach ($resolvedStops as $stop) {
                if ($this->fareEstimator->isHillCountry($stop)) {
                    $isHillCountry = true;
                    break;
                }
            }

            $fare = $this->lorryEstimator->estimate(
                $rateTable[$rateType],
                $distanceKm,
                $request->input('trip'),
                $isHillCountry,
                (float) $request->input('waiting_hours', 0)
            );

            $waitingHours = (float) $request->input('waiting_hours', 0);
            $extraKmCharge = (float) ($fare['extra_fee'] ?? 0);
            $waitingCharge = (float) ($fare['waiting_charge'] ?? 0);
            $freeWaitingHours = (float) ($fare['free_waiting_hours'] ?? 0);
            $waitingChargePerHour = (float) ($fare['waiting_charge_per_hour'] ?? ($rateTable[$rateType]['waitingChargePerHour'] ?? 0));
            $chargeableWaitingHours = (float) ($fare['chargeable_waiting_hours'] ?? 0);
            $extraKmRate = $isRoundTrip
                ? ($isHillCountry ? (float) ($rateTable[$rateType]['upDownHill'] ?? 0) : (float) ($rateTable[$rateType]['upDownNonHill'] ?? 0))
                : ($isHillCountry
                    ? (float) (($rateTable[$rateType]['windows'][0]['extraPerKm'] ?? 0) + ($rateTable[$rateType]['windows'][0]['hillExtraPerKm'] ?? 0))
                    : (float) ($rateTable[$rateType]['windows'][0]['extraPerKm'] ?? 0));
            $noteParts = [
                'This is an estimate only. Final pricing may change based on route conditions, stops, waiting time, and actual trip details.',
                'Per km charge for extra kilometers: Rs. ' . number_format($extraKmRate, 0, '.', ',') . ' per km.',
                'Extra km charge for this trip: Rs. ' . number_format($extraKmCharge, 0, '.', ',') . '.',
                'Free waiting hours: ' . number_format($freeWaitingHours, 0, '.', ',') . '. After that, Rs. ' . number_format($waitingChargePerHour, 0, '.', ',') . ' per hour will be charged.',
                'Waiting charge for this trip: Rs. ' . number_format($waitingCharge, 0, '.', ',') . '.',
            ];

            $routeSummary = $pickupCoords && $dropCoords
                ? ($pickupCoords['formatted'] . ' to ' . $dropCoords['formatted'])
                : null;
            if ($routeSummary && ! empty($resolvedStops)) {
                $routeSummary .= ' via ' . collect($resolvedStops)->pluck('formatted')->implode(', ');
            }

            return response()->json([
                'success' => true,
                'message' => 'Estimated trip fare calculated successfully.',
                'data' => [
                    'title' => 'Estimated trip fare',
                    'vehicle' => $lorry->name,
                    'rate_type' => $rateType,
                    'pickup' => $pickupCoords['formatted'] ?? null,
                    'pickup_map' => $pickupCoords ? $this->googleMapsUrl($pickupCoords['lat'], $pickupCoords['lng']) : null,
                    'drop' => $dropCoords['formatted'] ?? null,
                    'drop_map' => $dropCoords ? $this->googleMapsUrl($dropCoords['lat'], $dropCoords['lng']) : null,
                    'route_summary' => $routeSummary,
                    'stops' => collect($resolvedStops)->pluck('formatted')->values(),
                    'stop_maps' => collect($resolvedStops)->map(fn (array $stop) => $this->googleMapsUrl($stop['lat'], $stop['lng']))->values(),
                    'distance_km' => round($distanceKm, 2),
                    'distance_source' => $distanceResult['source'] ?? 'manual',
                    'extra_km_rate' => round($extraKmRate),
                    'extra_km_charge' => round($extraKmCharge),
                    'waiting_hours' => round($waitingHours, 2),
                    'free_waiting_hours' => $fare['free_waiting_hours'] ?? null,
                    'chargeable_waiting_hours' => $fare['chargeable_waiting_hours'] ?? null,
                    'waiting_charge_per_hour' => $waitingChargePerHour,
                    'waiting_charge' => round($waitingCharge),
                    'amount' => round($fare['total_cost']),
                    'currency' => 'LKR',
                    'note' => implode(' ', $noteParts),
                ],
            ]);
        }

        $vehicle = null;
        if ($request->filled('vehicle')) {
            $vehicle = Vehicle::where('name', $request->input('vehicle'))->first();
        }

        if (! $vehicle) {
            return response()->json(['success' => false, 'message' => 'Vehicle not found.'], 404);
        }

        $days = (int) $request->input('days');
        $includeOperatingCosts = (bool) $vehicle->include_operating_costs && $days > 1;
        $ac = $request->input('ac');
        if (! $ac) {
            return response()->json(['success' => false, 'message' => 'AC or Non-AC is required for vehicle estimates.'], 422);
        }

        $isRoundTrip = in_array(strtolower((string) $request->input('trip')), ['round-trip', 'round trip'], true);

        $distanceResult = null;
        $pickupCoords = null;
        $dropCoords = null;
        $resolvedStops = [];

        if ($request->filled('pickup') && $request->filled('drop')) {
            [$distanceResult, $pickupCoords, $dropCoords, $resolvedStops] = $this->resolveExternalRouteDistance($request, $isRoundTrip);
        } else {
            $distanceResult = ['km' => (float) $request->input('distance_km', 0), 'source' => 'manual'];
        }
        $distanceKm = (float) ($distanceResult['km'] ?? 0);

        $fare = $this->fareEstimator->estimateExternal(
            $vehicle,
            $distanceKm,
            $days,
            $ac,
            $request->input('trip'),
            $includeOperatingCosts
        );

        $estimateSummary = $this->passengerBillingExplanation($days, (float) $distanceKm, (float) $fare['included_km']);
        $estimateNote = $this->buildPassengerEstimateNote(
            $days,
            (float) $distanceKm,
            (float) $fare['included_km'],
            (float) $fare['price_per_km'],
            null,
            $estimateSummary,
            $fare['package1_estimate'] ?? null,
            $fare['package2_estimate'] ?? null
        );

        return response()->json([
            'success' => true,
            'message' => 'Estimated trip fare calculated successfully.',
            'data' => [
                'title' => 'Estimated trip fare',
                'vehicle' => $vehicle->name,
                'distance_km' => round($distanceKm, 2),
                'distance_source' => $distanceResult['source'] ?? 'manual',
                'included_km_per_day' => 150,
                'estimate_summary' => $estimateSummary,
                'amount' => round($fare['total_cost']),
                'extra_km_rate' => $fare['price_per_km'],
                'extra_km_note' => 'Each extra kilometer will be charged at Rs. ' . number_format((float) $fare['price_per_km'], 0, '.', ',') . '.',
                'currency' => 'LKR',
                'note' => $estimateNote,
            ],
        ]);
    }

    /**
     * Resolve route distance for external calls using geocoded pickup, stops, and drop.
     *
     * @return array{0: array{km:float,source:string}, 1: array, 2: array, 3: array}
     */
    private function resolveExternalRouteDistance(Request $request, bool $isRoundTrip = false): array
    {
        $pickupCoords = $this->resolveLocationInput($request->input('pickup'));
        if (! $pickupCoords) {
            abort(response()->json(['message' => 'Could not find pickup location: "' . $request->input('pickup') . '". Please be more specific.'], 422));
        }

        $dropCoords = $this->resolveLocationInput($request->input('drop'));
        if (! $dropCoords) {
            abort(response()->json(['message' => 'Could not find drop location: "' . $request->input('drop') . '". Please be more specific.'], 422));
        }

        $resolvedStops = [];
        foreach ($request->input('stops', []) as $stop) {
            $coords = $this->resolveLocationInput($stop);
            if (! $coords) {
                abort(response()->json(['message' => 'Could not find stop location: "' . $stop . '". Please be more specific.'], 422));
            }
            $resolvedStops[] = $coords;
        }

        $waypoints = array_merge([$pickupCoords], $resolvedStops, [$dropCoords]);
        if ($isRoundTrip) {
            $waypoints[] = $pickupCoords;
        }
        $distanceResult = $this->calculateRouteDistance($waypoints);
        if ($distanceResult['km'] === null) {
            abort(response()->json(['message' => 'Could not calculate driving distance for this route.'], 422));
        }

        return [$distanceResult, $pickupCoords, $dropCoords, $resolvedStops];
    }

    /**
     * Sum driving distances across all legs: Aâ†’B, Bâ†’C, Câ†’D ...
     */
    private function calculateRouteDistance(array $waypoints): array
    {
        if (count($waypoints) < 2) {
            return ['km' => 0.0, 'source' => 'straight'];
        }

        $googleDistance = $this->getGoogleMultiPointDistanceKm($waypoints);
        if ($googleDistance !== null) {
            return ['km' => $googleDistance, 'source' => 'route'];
        }

        $osrmDistance = $this->getOsrmMultiPointDistanceKm($waypoints);
        if ($osrmDistance !== null) {
            return ['km' => $osrmDistance, 'source' => 'route'];
        }

        return ['km' => $this->haversineTotalDistance($waypoints), 'source' => 'straight'];
    }

    private function getGoogleMultiPointDistanceKm(array $points): ?float
    {
        $apiKey = config('services.google.maps_key');
        if (! $apiKey || count($points) < 2) {
            return null;
        }

        $origin = array_shift($points);
        $destination = $points[count($points) - 1];
        $intermediates = array_slice($points, 0, -1);

        $payload = [
            'origin' => [
                'location' => [
                    'latLng' => [
                        'latitude' => $origin['lat'],
                        'longitude' => $origin['lng'],
                    ],
                ],
            ],
            'destination' => [
                'location' => [
                    'latLng' => [
                        'latitude' => $destination['lat'],
                        'longitude' => $destination['lng'],
                    ],
                ],
            ],
            'travelMode' => 'DRIVE',
            'routingPreference' => 'TRAFFIC_UNAWARE',
        ];

        if (! empty($intermediates)) {
            $payload['intermediates'] = array_map(static fn ($point) => [
                'location' => [
                    'latLng' => [
                        'latitude' => $point['lat'],
                        'longitude' => $point['lng'],
                    ],
                ],
            ], $intermediates);
        }

        $response = Http::withoutVerifying()->withHeaders([
            'Content-Type' => 'application/json',
            'X-Goog-Api-Key' => $apiKey,
            'X-Goog-FieldMask' => 'routes.distanceMeters',
        ])->post('https://routes.googleapis.com/directions/v2:computeRoutes', $payload);
        if (! $response->successful()) {
            return null;
        }

        $meters = $response->json('routes.0.distanceMeters');
        return is_numeric($meters) ? round(((float) $meters) / 1000, 1) : null;
    }

    private function getOsrmMultiPointDistanceKm(array $points): ?float
    {
        if (count($points) < 2) {
            return null;
        }

        $coords = array_map(static fn ($p) => $p['lng'] . ',' . $p['lat'], $points);
        $url = 'https://router.project-osrm.org/route/v1/driving/' . implode(';', $coords) . '?overview=false';

        $response = Http::withoutVerifying()->get($url);
        if (! $response->successful()) {
            return null;
        }

        $meters = $response->json('routes.0.distance');
        return is_numeric($meters) ? round(((float) $meters) / 1000, 1) : null;
    }

    private function haversineTotalDistance(array $points): float
    {
        $total = 0.0;
        for ($i = 0; $i < count($points) - 1; $i++) {
            $total += $this->haversineDistance(
                (float) $points[$i]['lat'],
                (float) $points[$i]['lng'],
                (float) $points[$i + 1]['lat'],
                (float) $points[$i + 1]['lng']
            );
        }

        return round($total, 1);
    }

    private function haversineDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadiusKm = 6371.0;
        $latFrom = deg2rad($lat1);
        $lngFrom = deg2rad($lng1);
        $latTo = deg2rad($lat2);
        $lngTo = deg2rad($lng2);

        $latDelta = $latTo - $latFrom;
        $lngDelta = $lngTo - $lngFrom;

        $a = sin($latDelta / 2) ** 2
            + cos($latFrom) * cos($latTo) * sin($lngDelta / 2) ** 2;
        $c = 2 * asin(min(1, sqrt($a)));

        return $earthRadiusKm * $c;
    }

    private function geocode(string $address): ?array
    {
        if (preg_match('/^\s*(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)\s*$/', $address, $matches)) {
            return [
                'lat' => (float) $matches[1],
                'lng' => (float) $matches[2],
                'formatted' => trim($address),
            ];
        }

        $response = Http::withoutVerifying()->get('https://maps.googleapis.com/maps/api/geocode/json', [
            'address' => $address,
            'key'     => config('services.google.maps_key'),
        ]);

        $result = $response->json('results.0');

        if (!$result) {
            return $this->geocodeFromPrediction($address);
        }

        return [
            'lat'       => $result['geometry']['location']['lat'],
            'lng'       => $result['geometry']['location']['lng'],
            'formatted' => $result['formatted_address'],
        ];
    }

    private function geocodeFromPrediction(string $address): ?array
    {
        $apiKey = config('services.google.maps_key');
        if (! $apiKey) {
            return null;
        }

        $placeId = $this->predictPlaceId($address, $apiKey);
        if (! $placeId) {
            return null;
        }

        $detailsResponse = Http::withoutVerifying()->get('https://maps.googleapis.com/maps/api/place/details/json', [
            'place_id' => $placeId,
            'fields' => 'geometry,formatted_address',
            'key' => $apiKey,
        ]);

        if (! $detailsResponse->successful()) {
            return null;
        }

        $result = $detailsResponse->json('result');
        $location = $result['geometry']['location'] ?? null;
        if (! $location || ! isset($location['lat'], $location['lng'])) {
            return null;
        }

        return [
            'lat' => $location['lat'],
            'lng' => $location['lng'],
            'formatted' => $result['formatted_address'] ?? $address,
        ];
    }

    private function predictPlaceId(string $address, string $apiKey): ?string
    {
        $queries = array_values(array_unique([
            trim($address),
            preg_replace('/\s+/', ' ', trim($address)),
            ucfirst(strtolower(trim($address))),
        ]));

        foreach ($queries as $query) {
            if ($query === '') {
                continue;
            }

            $response = Http::withoutVerifying()->get('https://maps.googleapis.com/maps/api/place/autocomplete/json', [
                'input' => $query,
                'key' => $apiKey,
                'types' => 'geocode',
                'language' => 'en',
            ]);

            if (! $response->successful()) {
                continue;
            }

            $prediction = $response->json('predictions.0');
            $placeId = $prediction['place_id'] ?? null;
            if ($placeId) {
                return $placeId;
            }
        }

        return null;
    }

    private function resolveLocationInput(mixed $input): ?array
    {
        if (is_array($input)) {
            $lat = $input['lat'] ?? $input['latitude'] ?? null;
            $lng = $input['lng'] ?? $input['longitude'] ?? null;
            $label = $input['formatted'] ?? $input['label'] ?? $input['address'] ?? null;

            if (is_numeric($lat) && is_numeric($lng)) {
                $reverse = $this->reverseGeocode((float) $lat, (float) $lng);
                return [
                    'lat' => (float) $lat,
                    'lng' => (float) $lng,
                    'formatted' => $label ? (string) $label : ($reverse['formatted'] ?? ((string) $lat . ',' . (string) $lng)),
                ];
            }

            if (is_string($label) && $label !== '') {
                return $this->geocode($label);
            }

            return null;
        }

        if (is_string($input) && trim($input) !== '') {
            $trimmed = trim($input);

            if (preg_match('/^\s*(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)\s*$/', $trimmed, $matches)) {
                $reverse = $this->reverseGeocode((float) $matches[1], (float) $matches[2]);
                return [
                    'lat' => (float) $matches[1],
                    'lng' => (float) $matches[2],
                    'formatted' => $reverse['formatted'] ?? $trimmed,
                ];
            }

            if (filter_var($trimmed, FILTER_VALIDATE_URL)) {
                $resolved = $this->resolveGoogleMapsUrl($trimmed);
                if ($resolved) {
                    return $resolved;
                }
            }

            return $this->geocode($trimmed);
        }

        return null;
    }

    private function reverseGeocode(float $lat, float $lng): ?array
    {
        $google = Http::withoutVerifying()->get('https://maps.googleapis.com/maps/api/geocode/json', [
            'latlng' => $lat . ',' . $lng,
            'key' => config('services.google.maps_key'),
        ]);

        if ($google->successful()) {
            $result = $google->json('results.0');
            if ($result) {
                $formatted = $this->normalizePlaceLabel(
                    $result['formatted_address'] ?? null,
                    $result['address_components'] ?? []
                );

                if ($formatted) {
                    return [
                        'lat' => $lat,
                        'lng' => $lng,
                        'formatted' => $formatted,
                    ];
                }
            }
        }

        $osm = Http::withoutVerifying()->get('https://nominatim.openstreetmap.org/reverse', [
            'format' => 'jsonv2',
            'lat' => $lat,
            'lon' => $lng,
            'zoom' => 18,
            'addressdetails' => 1,
            'accept-language' => 'en',
        ]);

        if ($osm->successful()) {
            $data = $osm->json();
            if (is_array($data)) {
                $formatted = $this->normalizePlaceLabel(
                    $data['display_name'] ?? null,
                    $data['address'] ?? []
                );

                if ($formatted) {
                    return [
                        'lat' => $lat,
                        'lng' => $lng,
                        'formatted' => $formatted,
                    ];
                }
            }
        }

        return [
            'lat' => $lat,
            'lng' => $lng,
            'formatted' => $lat . ',' . $lng,
        ];
    }

    private function normalizePlaceLabel(?string $label, array $parts = []): ?string
    {
        if (is_string($label) && trim($label) !== '' && ! preg_match('/^\s*-?\d+(?:\.\d+)?\s*,\s*-?\d+(?:\.\d+)?\s*$/', trim($label))) {
            return trim($label);
        }

        $candidates = [];

        if ($parts && array_is_list($parts)) {
            foreach ($parts as $component) {
                if (! is_array($component)) {
                    continue;
                }

                $value = $component['long_name'] ?? $component['short_name'] ?? null;
                $types = $component['types'] ?? [];
                if (! is_string($value) || trim($value) === '' || ! is_array($types)) {
                    continue;
                }

                $wantedTypes = [
                    'locality',
                    'sublocality',
                    'sublocality_level_1',
                    'administrative_area_level_3',
                    'administrative_area_level_2',
                    'administrative_area_level_1',
                    'neighborhood',
                    'route',
                    'premise',
                ];

                foreach ($wantedTypes as $wantedType) {
                    if (in_array($wantedType, $types, true)) {
                        $candidates[] = trim($value);
                        break;
                    }
                }
            }
        } else {
            foreach (['city', 'town', 'village', 'suburb', 'neighbourhood', 'county', 'state', 'municipality', 'district', 'road'] as $key) {
                if (isset($parts[$key]) && is_string($parts[$key]) && trim($parts[$key]) !== '') {
                    $candidates[] = trim($parts[$key]);
                }
            }
        }

        $candidates = array_values(array_unique($candidates));
        if (! empty($candidates)) {
            return implode(', ', array_slice($candidates, 0, 3));
        }

        return null;
    }

    private function googleMapsUrl(float $lat, float $lng): string
    {
        return 'https://www.google.com/maps?q=' . $lat . ',' . $lng;
    }

    private function resolveGoogleMapsUrl(string $url): ?array
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $path = (string) parse_url($url, PHP_URL_PATH);
        $query = (string) parse_url($url, PHP_URL_QUERY);

        if (! str_contains($host, 'google') && ! str_contains($host, 'goo.gl')) {
            return null;
        }

        $response = Http::withoutVerifying()
            ->withOptions([
                'allow_redirects' => [
                    'track_redirects' => true,
                ],
                'timeout' => 15,
            ])
            ->get($url);

        $finalUrl = $this->finalRedirectUrl($response, $url);
        foreach ([$finalUrl, $url] as $candidateUrl) {
            $candidate = $this->extractGoogleMapsTarget($candidateUrl);
            if ($candidate) {
                return $candidate;
            }
        }

        if ($query !== '') {
            parse_str($query, $params);
            $text = $params['q'] ?? $params['query'] ?? $params['destination'] ?? $params['origin'] ?? $params['daddr'] ?? null;
            if (is_string($text) && trim($text) !== '') {
                return $this->geocode(rawurldecode($text));
            }
        }

        if (preg_match('#/maps/place/([^/]+)#i', $path, $matches)) {
            return $this->geocode(rawurldecode(str_replace('+', ' ', $matches[1])));
        }

        return null;
    }

    private function finalRedirectUrl($response, string $fallback): string
    {
        $effectiveUri = $response->effectiveUri();
        if ($effectiveUri) {
            $effective = (string) $effectiveUri;
            if ($effective !== '') {
                return $effective;
            }
        }

        $history = $response->header('X-Guzzle-Redirect-History');
        if (is_array($history)) {
            $history = implode(',', $history);
        }

        if (is_string($history) && trim($history) !== '') {
            $parts = array_values(array_filter(array_map('trim', explode(',', $history))));
            if ($parts) {
                return (string) end($parts);
            }
        }

        return $fallback;
    }

    private function extractGoogleMapsTarget(string $url): ?array
    {
        if (preg_match('/@(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)/', $url, $matches)) {
            return [
                'lat' => (float) $matches[1],
                'lng' => (float) $matches[2],
                'formatted' => sprintf('%s,%s', $matches[1], $matches[2]),
            ];
        }

        $query = (string) parse_url($url, PHP_URL_QUERY);
        if ($query !== '') {
            parse_str($query, $params);
            foreach (['q', 'query', 'destination', 'origin', 'daddr', 'll'] as $key) {
                if (! isset($params[$key]) || ! is_string($params[$key]) || trim($params[$key]) === '') {
                    continue;
                }

                $value = rawurldecode($params[$key]);
                if (preg_match('/^\s*(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)\s*$/', $value, $matches)) {
                    return [
                        'lat' => (float) $matches[1],
                        'lng' => (float) $matches[2],
                        'formatted' => trim($value),
                    ];
                }

                $resolved = $this->geocode($value);
                if ($resolved) {
                    return $resolved;
                }
            }
        }

        if (preg_match('#/maps/place/([^/]+)#i', $url, $matches)) {
            $place = rawurldecode(str_replace('+', ' ', $matches[1]));
            if ($place !== '') {
                return $this->geocode($place);
            }
        }

        return null;
    }

    private function resolveRateTypeKey(array $rateTable, string $rateTypeInput): ?string
    {
        $normalizedInput = $this->normalizeRateType($rateTypeInput);
        if ($normalizedInput === '') {
            return null;
        }

        foreach ($rateTable as $key => $row) {
            if ($this->normalizeRateType((string) $key) === $normalizedInput) {
                return (string) $key;
            }

            if (is_array($row) && isset($row['type']) && $this->normalizeRateType((string) $row['type']) === $normalizedInput) {
                return (string) $key;
            }
        }

        return null;
    }

    private function normalizeRateType(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '_', $value) ?? $value;

        return trim($value, '_');
    }

    private function passengerBillingExplanation(int $days, float $distanceKm, float $includedKm): string
    {
        if ($days !== 1) {
            return '';
        }

        $includedKm = $includedKm > 0 ? (int) round($includedKm) : 150;

        if ($distanceKm <= $includedKm) {
            return "This trip is billed under the 1-day package. Up to 150 km is included, and the package fare applies.";
        }

        $extraKm = round($distanceKm - $includedKm, 2);
        return "This trip is billed under the 1-day package. The first {$includedKm} km are covered by the package fare, and the remaining {$extraKm} km are billed at the selected per-km rate.";
    }

    private function buildPassengerEstimateNote(
        int $days,
        float $distanceKm,
        float $includedKm,
        float $pricePerKm,
        ?string $routeSummary = null,
        ?string $packageSummary = null,
        mixed $package1Estimate = null,
        mixed $package2Estimate = null
    ): string {
        $parts = [
            'This is an estimate only. Final pricing may change based on route conditions, stops, waiting time, the actual trip duration, and the final billing after the included allowance.',
        ];

        if (is_string($routeSummary) && trim($routeSummary) !== '') {
            $parts[] = trim($routeSummary) . '.';
        }

        $parts[] = 'Distance: ' . round($distanceKm, 2) . ' km.';

        $parts[] = 'Billing formula: the 1-day package includes up to 150 km; any extra distance is charged as extra km x selected per-km rate.';

        if (is_numeric($package1Estimate)) {
            $parts[] = 'Package 1 estimate: Rs. ' . number_format((float) $package1Estimate, 0, '.', ',') . '.';
        }

        if (is_numeric($package2Estimate)) {
            $parts[] = 'Package 2 estimate: Rs. ' . number_format((float) $package2Estimate, 0, '.', ',') . '.';
        }

        if ($days === 1) {
            if ($packageSummary) {
                $parts[] = $packageSummary;
            }

            if ($distanceKm > $includedKm) {
                $extraKm = round($distanceKm - $includedKm, 2);
                $parts[] = 'Extra distance charge: ' . $extraKm . ' km x Rs. ' . number_format($pricePerKm, 0, '.', ',') . ' per km.';
            } else {
                $parts[] = 'Up to ' . (int) round($includedKm) . ' km is included in the 1-day package.';
            }
        } elseif ($distanceKm > $includedKm) {
            $extraKm = round($distanceKm - $includedKm, 2);
            $parts[] = 'Included allowance: ' . (int) round($includedKm) . ' km for ' . $days . ' day(s).';
            $parts[] = 'Extra distance charge: ' . $extraKm . ' km x Rs. ' . number_format($pricePerKm, 0, '.', ',') . ' per km.';
        }

        return implode(' ', array_values(array_filter($parts)));
    }

}


