<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\FareEstimateRequest;
use App\Models\Vehicle;
use App\Services\FareEstimator;
use App\Services\LorryEstimator;
use Illuminate\Support\Facades\Http;

class FareEstimateController extends Controller
{
    public function __construct(private FareEstimator $fareEstimator, private LorryEstimator $lorryEstimator)
    {
    }

    public function external(FareEstimateRequest $request)
    {
        return $this->calculate($request);
    }

    public function calculate(FareEstimateRequest $request)
    {
        $vehicle = Vehicle::findOrFail($request->input('vehicle_id'));
        $serviceType = $request->input('service_type');
        $trip = $request->input('trip');
        $pickup = $this->resolveLocation($request, 'pickup');
        $destination = $this->resolveLocation($request, 'destination');
        $distanceKm = $this->resolveDistance($request, $pickup, $destination);

        if ($distanceKm === null) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'distance_unavailable',
                    'message' => 'Unable to calculate route distance from pickup to destination.',
                ],
            ], 422);
        }

        if ($serviceType === 'Passenger') {
            $fare = $this->fareEstimator->estimate(
                $vehicle,
                $distanceKm,
                (int) $request->input('days', 1),
                $request->input('ac', 'AC'),
                $trip,
                $pickup,
                $destination,
            );

            return response()->json([
                'success' => true,
                'data' => array_merge($fare, [
                    'distance_km' => round($distanceKm, 2),
                    'vehicle_name' => $vehicle->name,
                    'service_type' => $serviceType,
                    'trip' => $trip,
                    'days' => (int) $request->input('days', 1),
                    'pax' => (int) $request->input('pax', 1),
                    'ac' => $fare['ac_label'],
                ]),
            ]);
        }

        $rateType = $request->input('rate_type');
        $rateTable = $vehicle->lorry_rates ?? [];
        $rate = $rateTable[$rateType] ?? null;

        if (!is_array($rate) || empty($rate)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'rate_not_found',
                    'message' => "Lorry rate type '{$rateType}' was not found for the selected vehicle.",
                ],
            ], 422);
        }

        $fare = $this->lorryEstimator->estimate(
            $rate,
            $distanceKm,
            $trip,
            $this->isHillCountry($pickup, $destination),
            (float) $request->input('waiting_hours', 0),
        );

        return response()->json([
            'success' => true,
            'data' => array_merge($fare, [
                'distance_km' => round($distanceKm, 2),
                'lorry_name' => $vehicle->name,
                'service_type' => $serviceType,
                'trip' => $trip,
                'rate_type' => $rateType,
            ]),
        ]);
    }

    private function resolveLocation(FareEstimateRequest $request, string $type): ?array
    {
        $coords = $request->validatedCoordinates("{$type}");
        if ($coords) {
            return $coords;
        }

        $text = $request->input("{$type}_text");
        if (!$text) {
            return null;
        }

        $response = Http::withoutVerifying()->get('https://maps.googleapis.com/maps/api/geocode/json', [
            'address' => $text,
            'key' => config('services.google.maps_key'),
        ]);

        $result = $response->json('results.0');
        if (!$result) {
            return null;
        }

        return [
            'lat' => (float) $result['geometry']['location']['lat'],
            'lng' => (float) $result['geometry']['location']['lng'],
        ];
    }

    private function resolveDistance(FareEstimateRequest $request, array $pickup, array $destination): ?float
    {
        if ($request->filled('distance_km')) {
            return (float) $request->input('distance_km');
        }

        if (!$pickup || !$destination) {
            return null;
        }

        $response = Http::withoutVerifying()->get('https://maps.googleapis.com/maps/api/distancematrix/json', [
            'origins' => $pickup['lat'] . ',' . $pickup['lng'],
            'destinations' => $destination['lat'] . ',' . $destination['lng'],
            'mode' => 'driving',
            'units' => 'metric',
            'key' => config('services.google.maps_key'),
        ]);

        $element = $response->json('rows.0.elements.0');
        if (($element['status'] ?? '') !== 'OK') {
            return null;
        }

        return (float) ($element['distance']['value'] ?? 0) / 1000;
    }

    private function isHillCountry(array $pickup, array $destination): bool
    {
        $keywords = [
            'nuwara eliya',
            'badulla',
            'bandarawela',
            'ella',
            'haputale',
            'kandy',
            'matale',
            'maskeliya',
            'hatton',
            'diyatalawa',
            'talawakele',
            'koslanda',
            'gampola',
        ];

        foreach ([$pickup, $destination] as $location) {
            $lat = (float) ($location['lat'] ?? 0);
            $lng = (float) ($location['lng'] ?? 0);
            if ($lat >= 6.7 && $lat <= 7.4 && $lng >= 80.4 && $lng <= 81.2) {
                return true;
            }
        }

        return false;
    }
}
