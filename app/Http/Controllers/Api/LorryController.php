<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lorry;
use App\Services\LorryEstimator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;

class LorryController extends Controller
{
    public function __construct(private LorryEstimator $estimator) {}

    public function estimate(Request $request)
    {
        $request->validate([
            'lorry_id'       => ['required', 'integer', 'exists:lorries,id'],
            'rate_type'      => ['required', 'string'],
            'pickup_text'    => ['sometimes', 'string', 'max:200'],
            'pickup_lat'     => ['sometimes', 'numeric'],
            'pickup_lng'     => ['sometimes', 'numeric'],
            'dropoff_text'   => ['sometimes', 'string', 'max:200'],
            'dropoff_lat'    => ['sometimes', 'numeric'],
            'dropoff_lng'    => ['sometimes', 'numeric'],
            'distance_km'    => ['sometimes', 'numeric', 'min:0'],
            'trip'           => ['sometimes', Rule::in(['one-way', 'round-trip'])],
            'waiting_hours'  => ['sometimes', 'numeric', 'min:0'],
        ]);

        $lorry = Lorry::findOrFail($request->input('lorry_id'));

        // Get rates from DATABASE (not hardcoded)
        $rateTable = $lorry->rate_table;
        if (!is_array($rateTable) || empty($rateTable)) {
            return response()->json([
                'success' => false,
                'message' => "No rate table configured for lorry '{$lorry->name}'. Please configure rates in admin panel.",
            ], 422);
        }

        $rateType = $request->input('rate_type');
        if (!isset($rateTable[$rateType])) {
            return response()->json([
                'success' => false,
                'message' => "Rate type '{$rateType}' not found for {$lorry->name}. Available: " . implode(', ', array_keys($rateTable)),
            ], 422);
        }

        $rate = $rateTable[$rateType];

        // Validate rate structure
        if (!isset($rate['windows']) || !is_array($rate['windows']) || empty($rate['windows'])) {
            return response()->json([
                'success' => false,
                'message' => "Invalid rate configuration for {$lorry->name} - {$rateType}. Windows not found.",
            ], 422);
        }

        // Resolve distance
        if ($request->filled('distance_km')) {
            $distanceKm = (float) $request->input('distance_km');
            $durationText = null;
        } else {
            $pickup  = $this->resolveLocation($request, 'pickup');
            $dropoff = $this->resolveLocation($request, 'dropoff');

            if (!$pickup || !$dropoff) {
                return response()->json(['success' => false, 'message' => 'Could not resolve pickup or dropoff location.'], 422);
            }

            $distResult = $this->fetchDistance($pickup, $dropoff);
            if (!$distResult) {
                return response()->json(['success' => false, 'message' => 'Could not calculate driving distance.'], 422);
            }

            $distanceKm   = $distResult['km'];
            $durationText = $distResult['duration'];
        }

        $isHillCountry = $this->checkHillCountry(
            $request->input('pickup_text', ''),
            (float) $request->input('pickup_lat', 0),
            (float) $request->input('pickup_lng', 0),
            $request->input('dropoff_text', ''),
            (float) $request->input('dropoff_lat', 0),
            (float) $request->input('dropoff_lng', 0)
        );

        $fare = $this->estimator->estimate(
            $rate,
            $distanceKm,
            $request->input('trip', 'one-way'),
            $isHillCountry,
            (float) $request->input('waiting_hours', 0)
        );

        return response()->json(array_merge([
            'success'       => true,
            'lorry'         => $lorry->name,
            'rate_type'     => $rateType,
            'duration'      => $durationText,
        ], $fare));
    }

    private function resolveLocation(Request $request, string $type): ?array
    {
        $lat = $request->input("{$type}_lat");
        $lng = $request->input("{$type}_lng");

        if ($lat && $lng) {
            return ['lat' => (float) $lat, 'lng' => (float) $lng];
        }

        $text = $request->input("{$type}_text");
        if (!$text) return null;

        $response = Http::withoutVerifying()->get('https://maps.googleapis.com/maps/api/geocode/json', [
            'address' => $text,
            'key'     => config('services.google.maps_key'),
        ]);

        $result = $response->json('results.0');
        if (!$result) return null;

        return [
            'lat' => $result['geometry']['location']['lat'],
            'lng' => $result['geometry']['location']['lng'],
        ];
    }

    private function fetchDistance(array $origin, array $dest): ?array
    {
        $response = Http::withoutVerifying()->get('https://maps.googleapis.com/maps/api/distancematrix/json', [
            'origins'      => $origin['lat'] . ',' . $origin['lng'],
            'destinations' => $dest['lat'] . ',' . $dest['lng'],
            'mode'         => 'driving',
            'units'        => 'metric',
            'key'          => config('services.google.maps_key'),
        ]);

        $element = $response->json('rows.0.elements.0');
        if (($element['status'] ?? '') !== 'OK') return null;

        return [
            'km'       => $element['distance']['value'] / 1000,
            'duration' => $element['duration']['text'],
        ];
    }

    private function checkHillCountry(
        string $pickupText, float $pickupLat, float $pickupLng,
        string $dropoffText, float $dropoffLat, float $dropoffLng
    ): bool {
        $keywords = [
            'nuwara eliya','badulla','bandarawela','ella','haputale',
            'kandy','matale','maskeliya','hatton','diyatalawa',
            'talawakele','koslanda','gampola',
        ];

        foreach ([$pickupText, $dropoffText] as $text) {
            $lower = strtolower($text);
            foreach ($keywords as $kw) {
                if (str_contains($lower, $kw)) return true;
            }
        }

        // Central highlands bounding box
        foreach ([[$pickupLat, $pickupLng], [$dropoffLat, $dropoffLng]] as [$lat, $lng]) {
            if ($lat && $lng && $lat >= 6.7 && $lat <= 7.4 && $lng >= 80.4 && $lng <= 81.2) {
                return true;
            }
        }

        return false;
    }

    public function index()
    {
        return response()->json([
            'data' => Lorry::query()
                ->orderBy('name')
                ->get()
                ->map(fn (Lorry $lorry) => $this->format($lorry)),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateLorry($request);
        $lorry = Lorry::create($this->payload($data));

        return response()->json(['data' => $this->format($lorry)], 201);
    }

    public function show(Lorry $lorry)
    {
        return response()->json(['data' => $this->format($lorry)]);
    }

    public function update(Request $request, Lorry $lorry)
    {
        $data = $this->validateLorry($request, $lorry);
        $lorry->update($this->payload($data));

        return response()->json(['data' => $this->format($lorry->fresh())]);
    }

    public function destroy(Lorry $lorry)
    {
        $lorry->delete();

        return response()->json(['message' => 'Lorry deleted.']);
    }

    private function validateLorry(Request $request, ?Lorry $lorry = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:lorries,name,' . optional($lorry)->id],
            'category' => ['nullable', 'string', 'max:100'],
            'img' => ['nullable', 'string'],
            'img2' => ['nullable', 'string'],
            'img3' => ['nullable', 'string'],
            'img4' => ['nullable', 'string'],
            'img5' => ['nullable', 'string'],
            'seats' => ['nullable', 'integer', 'min:1'],
            'acAvailable' => ['nullable', 'boolean'],
            'nonAcAvailable' => ['nullable', 'boolean'],
            'rateTable' => ['nullable', 'array'],
        ]);
    }

    private function payload(array $data): array
    {
        return [
            'name' => trim($data['name']),
            'category' => trim($data['category'] ?? 'Lorries'),
            'img' => $data['img'] ?? null,
            'img2' => $data['img2'] ?? null,
            'img3' => $data['img3'] ?? null,
            'img4' => $data['img4'] ?? null,
            'img5' => $data['img5'] ?? null,
            'seats' => max(1, (int) ($data['seats'] ?? 1)),
            'ac_available' => (bool) ($data['acAvailable'] ?? false),
            'non_ac_available' => (bool) ($data['nonAcAvailable'] ?? false),
            'rate_table' => $this->normalizeRateTable($data['rateTable'] ?? []),
        ];
    }

    private function format(Lorry $lorry): array
    {
        return [
            'id' => $lorry->id,
            'name' => $lorry->name,
            'category' => $lorry->category,
            'img' => $lorry->img,
            'img2' => $lorry->img2,
            'img3' => $lorry->img3,
            'img4' => $lorry->img4,
            'img5' => $lorry->img5,
            'seats' => $lorry->seats ?? 1,
            'acAvailable' => (bool) ($lorry->ac_available ?? false),
            'nonAcAvailable' => (bool) ($lorry->non_ac_available ?? false),
            'images' => array_values(array_filter([$lorry->img, $lorry->img2, $lorry->img3, $lorry->img4, $lorry->img5])),
            'lorryRates' => $this->normalizeRateTable($lorry->rate_table ?? []),
        ];
    }

    private function normalizeRateTable(array $rates): array
    {
        $normalized = [];
        foreach ($rates as $key => $row) {
            if (!is_array($row)) {
                continue;
            }

            $windows = [];
            if (isset($row['windows']) && is_array($row['windows'])) {
                foreach ($row['windows'] as $window) {
                    if (is_array($window)) {
                        $windows[] = [
                            'fromKm' => max(0, (int) ($window['fromKm'] ?? 0)),
                            'toKm' => isset($window['toKm']) ? ((int) $window['toKm']) : null,
                            'rate' => max(0, (float) ($window['rate'] ?? 0)),
                            'extraPerKm' => max(0, (float) ($window['extraPerKm'] ?? 0)),
                            'hillExtraPerKm' => max(0, (float) ($window['hillExtraPerKm'] ?? 0)),
                        ];
                    }
                }
            }

            if (empty($windows)) {
                $windows = [
                    [
                        'fromKm' => 0,
                        'toKm' => 130,
                        'rate' => max(0, (float) ($row['rate'] ?? $row['start'] ?? 0)),
                        'extraPerKm' => max(0, (float) ($row['extraPerKm'] ?? $row['extra'] ?? 0)),
                        'hillExtraPerKm' => max(0, (float) ($row['hillExtraPerKm'] ?? 0)),
                    ]
                ];
            }

            $normalized[$key] = [
                'type' => trim((string) ($row['type'] ?? $key)),
                'windows' => $windows,
                'upDownNonHill' => max(0, (float) ($row['upDownNonHill'] ?? $row['upDown'] ?? $row['up_down'] ?? 0)),
                'upDownHill' => max(0, (float) ($row['upDownHill'] ?? $row['upDownHill'] ?? 0)),
                'freeWaitingHours' => max(0, (float) ($row['freeWaitingHours'] ?? 0)),
                'waitingChargePerHour' => max(0, (float) ($row['waitingChargePerHour'] ?? 0)),
            ];
        }

        return $normalized;
    }
}
