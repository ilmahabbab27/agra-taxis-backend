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
        $rateTable = $lorry->rate_table ?? [];
        $rateType  = $request->input('rate_type');

        if (!isset($rateTable[$rateType])) {
            return response()->json([
                'success' => false,
                'message' => "Rate type '{$rateType}' not found for this lorry. Available: " . implode(', ', array_keys($rateTable)),
            ], 422);
        }

        $rate = $rateTable[$rateType];

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
            'images' => array_values(array_filter([$lorry->img, $lorry->img2, $lorry->img3, $lorry->img4, $lorry->img5])),
            'rateTable' => $this->normalizeRateTable($lorry->rate_table ?? []),
        ];
    }

    private function normalizeRateTable(array $rates): array
    {
        $normalized = [];
        foreach ($rates as $key => $row) {
            if (!is_array($row)) {
                continue;
            }
            $normalized[$key] = [
                'type' => trim((string) ($row['type'] ?? $key)),
                'start' => max(0, (float) ($row['start'] ?? 0)),
                'extra' => max(0, (float) ($row['extra'] ?? 0)),
                'upDown' => max(0, (float) ($row['upDown'] ?? $row['up_down'] ?? 0)),
                'waiting' => max(0, (float) ($row['waiting'] ?? 0)),
                'waitingHour' => max(0, (float) ($row['waitingHour'] ?? $row['waiting_hour'] ?? 0)),
                'between100And130' => max(0, (float) ($row['between100And130'] ?? $row['between_100_130'] ?? 0)),
                'hillExtraPerKm' => max(0, (float) ($row['hillExtraPerKm'] ?? $row['hill_extra_per_km'] ?? 10)),
                'dropMinKm' => max(0, (float) ($row['dropMinKm'] ?? $row['drop_min_km'] ?? 10)),
                'dropMaxKm' => max(0, (float) ($row['dropMaxKm'] ?? $row['drop_max_km'] ?? 130)),
                'maxUpDownKm' => max(0, (float) ($row['maxUpDownKm'] ?? $row['max_up_down_km'] ?? 150)),
            ];
        }

        return $normalized;
    }
}
