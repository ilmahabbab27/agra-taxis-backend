<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VehicleController extends Controller
{
    public function index()
    {
        return response()->json([
            'data' => Vehicle::query()
                ->orderBy('category')
                ->orderBy('name')
                ->get()
                ->map(fn (Vehicle $vehicle) => $this->format($vehicle)),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateVehicle($request);
        $vehicle = Vehicle::create($this->payload($data));

        return response()->json(['data' => $this->format($vehicle)], 201);
    }

    public function update(Request $request, Vehicle $vehicle)
    {
        $data = $this->validateVehicle($request, $vehicle);
        $vehicle->update($this->payload($data));

        return response()->json(['data' => $this->format($vehicle->fresh())]);
    }

    public function destroy(Vehicle $vehicle)
    {
        foreach ([$vehicle->img, $vehicle->img2] as $image) {
            if ($image && str_starts_with($image, '/vehicles/')) {
                $filePath = dirname(__DIR__, 4) . '/' . ltrim($image, '/');
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }
        }

        $vehicle->delete();

        return response()->json(['message' => 'Vehicle deleted.']);
    }

    public function uploadImage(Request $request)
    {
        $request->validate([
            'image' => ['required', 'file', 'image', 'max:5120'],
        ]);

        $file = $request->file('image');
        $filename = uniqid('vehicle_', true) . '.' . $file->getClientOriginalExtension();

        // Use __DIR__ to get the absolute path regardless of APP_URL subdirectory config.
        // dirname(__DIR__, 4) goes: Api -> Controllers -> Http -> app -> project root
        // On shared hosting index.php sits at project root (no public/ subfolder)
        $dest = dirname(__DIR__, 4) . '/vehicles';
        if (! is_dir($dest)) {
            mkdir($dest, 0775, true);
        }
        $file->move($dest, $filename);

        return response()->json([
            'url' => '/vehicles/' . $filename,
        ], 201);
    }

    public function categories()
    {
        $vehicleCategories = Vehicle::query()
            ->select('category as name')
            ->distinct();

        return response()->json([
            'data' => DB::table('vehicle_categories')
                ->select('name')
                ->union($vehicleCategories)
                ->orderBy('name')
                ->pluck('name')
                ->values(),
        ]);
    }

    public function storeCategory(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:vehicle_categories,name'],
        ]);

        DB::table('vehicle_categories')->insert([
            'name' => trim($data['name']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['data' => trim($data['name'])], 201);
    }

    private function validateVehicle(Request $request, ?Vehicle $vehicle = null)
    {
        $sp = $request->input('stayPrices', []);
        $perKm = $request->input('perKmPrices', []);
        $request->merge([
            'ac_price_per_km'    => $request->input('ac_price_per_km', $request->input('acPricePerKm', $perKm['ac']['oneWay']['normal'] ?? 0)),
            'non_ac_price_per_km'=> $request->input('non_ac_price_per_km', $request->input('nonAcPricePerKm', $perKm['nonAc']['oneWay']['normal'] ?? 0)),
            'ac_hill_price_per_km' => $request->input('ac_hill_price_per_km', $request->input('acHillPricePerKm', $perKm['ac']['oneWay']['hill'] ?? 0)),
            'non_ac_hill_price_per_km' => $request->input('non_ac_hill_price_per_km', $request->input('nonAcHillPricePerKm', $perKm['nonAc']['oneWay']['hill'] ?? 0)),
            'ac_available'       => $request->input('ac_available', $request->input('acAvailable')),
            'non_ac_available'   => $request->input('non_ac_available', $request->input('nonAcAvailable')),
            'stay_price_day1'    => $request->input('stay_price_day1', $sp['day1'] ?? 0),
            'stay_price_day2'    => $request->input('stay_price_day2', $sp['day2'] ?? 0),
            'stay_price_day3'    => $request->input('stay_price_day3', $sp['day3'] ?? 0),
            'stay_price_day4'    => $request->input('stay_price_day4', $sp['day4'] ?? 0),
            'stay_price_day5'    => $request->input('stay_price_day5', $sp['day5'] ?? 0),
        ]);

        return $request->validate([
            'name'               => ['required', 'string', 'max:100', 'unique:vehicles,name,' . optional($vehicle)->id],
            'category'           => ['required', 'string', 'max:100'],
            'img'                => ['nullable', 'string'],
            'img2'               => ['nullable', 'string'],
            'seats'              => ['required', 'integer', 'min:1', 'max:100'],
            'ac_price_per_km'    => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'non_ac_price_per_km'=> ['nullable', 'numeric', 'min:0', 'max:999999'],
            'ac_hill_price_per_km' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'non_ac_hill_price_per_km' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'ac_available'       => ['required', 'boolean'],
            'non_ac_available'   => ['required', 'boolean'],
            'perKmPrices'        => ['nullable', 'array'],
            'package1Prices'     => ['nullable', 'array'],
            'stay_price_day1'    => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'stay_price_day2'    => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'stay_price_day3'    => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'stay_price_day4'    => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'stay_price_day5'    => ['nullable', 'numeric', 'min:0', 'max:9999999'],
        ]);
    }

    private function payload(array $data)
    {
        $acAvailable = (bool) ($data['ac_available'] ?? false);
        $nonAcAvailable = (bool) ($data['non_ac_available'] ?? false);

        if (! $acAvailable && ! $nonAcAvailable) {
            $acAvailable = true;
        }

        return [
            'name'               => trim($data['name']),
            'category'           => trim($data['category']),
            'img'                => $data['img'] ?: '/assets/car.jpg',
            'img2'               => $data['img2'] ?? null,
            'seats'              => max(1, (int) $data['seats']),
            'ac_price_per_km'    => $acAvailable ? ($data['ac_price_per_km'] ?? 0) : 0,
            'non_ac_price_per_km'=> $nonAcAvailable ? ($data['non_ac_price_per_km'] ?? 0) : 0,
            'ac_hill_price_per_km' => $acAvailable ? ($data['ac_hill_price_per_km'] ?? 0) : 0,
            'non_ac_hill_price_per_km' => $nonAcAvailable ? ($data['non_ac_hill_price_per_km'] ?? 0) : 0,
            'ac_available'       => $acAvailable,
            'non_ac_available'   => $nonAcAvailable,
            'per_km_prices'      => $this->normalizePerKmPrices($data['perKmPrices'] ?? []),
            'package1_prices'    => $this->normalizePackagePrices($data['package1Prices'] ?? []),
            'stay_price_day1'    => max(0, (float) ($data['stay_price_day1'] ?? 0)),
            'stay_price_day2'    => max(0, (float) ($data['stay_price_day2'] ?? 0)),
            'stay_price_day3'    => max(0, (float) ($data['stay_price_day3'] ?? 0)),
            'stay_price_day4'    => max(0, (float) ($data['stay_price_day4'] ?? 0)),
            'stay_price_day5'    => max(0, (float) ($data['stay_price_day5'] ?? 0)),
        ];
    }

    private function format(Vehicle $vehicle)
    {
        return [
            'id'             => $vehicle->id,
            'name'           => $vehicle->name,
            'category'       => $vehicle->category,
            'img'            => $vehicle->img,
            'img2'           => $vehicle->img2,
            'images'         => array_values(array_filter([$vehicle->img, $vehicle->img2])),
            'seats'          => $vehicle->seats,
            'acPricePerKm'   => (float) $vehicle->ac_price_per_km,
            'nonAcPricePerKm'=> (float) $vehicle->non_ac_price_per_km,
            'acHillPricePerKm' => (float) $vehicle->ac_hill_price_per_km,
            'nonAcHillPricePerKm' => (float) $vehicle->non_ac_hill_price_per_km,
            'perKmPrices'    => $this->normalizePerKmPrices($vehicle->per_km_prices ?? []),
            'acAvailable'    => $vehicle->ac_available,
            'nonAcAvailable' => $vehicle->non_ac_available,
            'package1KmLimitPerDay' => 100,
            'package1Prices' => $this->normalizePackagePrices($vehicle->package1_prices ?? []),
            'stayPrices'     => [
                'day1' => (float) $vehicle->stay_price_day1,
                'day2' => (float) $vehicle->stay_price_day2,
                'day3' => (float) $vehicle->stay_price_day3,
                'day4' => (float) $vehicle->stay_price_day4,
                'day5' => (float) $vehicle->stay_price_day5,
            ],
        ];
    }

    private function normalizePackagePrices(array $prices): array
    {
        $normalized = [];
        $keys = array_values(array_filter(array_keys($prices), fn ($key) => preg_match('/^day\d+$/', (string) $key)));

        if (empty($keys)) {
            $keys = ['day1'];
        }

        usort($keys, fn ($a, $b) => (int) substr($a, 3) <=> (int) substr($b, 3));

        foreach ($keys as $key) {
            $row = $prices[$key] ?? [];

            $normalized[$key] = [
                'acNormal' => max(0, (float) ($row['acNormal'] ?? 0)),
                'acHill' => max(0, (float) ($row['acHill'] ?? 0)),
                'nonAcNormal' => max(0, (float) ($row['nonAcNormal'] ?? 0)),
                'nonAcHill' => max(0, (float) ($row['nonAcHill'] ?? 0)),
            ];
        }

        return $normalized;
    }

    private function normalizePerKmPrices(array $prices): array
    {
        $fallback = [
            'ac' => [
                'oneWay' => [
                    'normal' => (float) ($prices['ac']['oneWay']['normal'] ?? $prices['acNormal'] ?? 0),
                    'hill' => (float) ($prices['ac']['oneWay']['hill'] ?? $prices['acHill'] ?? 0),
                ],
                'roundTrip' => [
                    'normal' => (float) ($prices['ac']['roundTrip']['normal'] ?? $prices['acRoundTripNormal'] ?? ($prices['ac']['oneWay']['normal'] ?? 0)),
                    'hill' => (float) ($prices['ac']['roundTrip']['hill'] ?? $prices['acRoundTripHill'] ?? ($prices['ac']['oneWay']['hill'] ?? 0)),
                ],
            ],
            'nonAc' => [
                'oneWay' => [
                    'normal' => (float) ($prices['nonAc']['oneWay']['normal'] ?? $prices['nonAcNormal'] ?? 0),
                    'hill' => (float) ($prices['nonAc']['oneWay']['hill'] ?? $prices['nonAcHill'] ?? 0),
                ],
                'roundTrip' => [
                    'normal' => (float) ($prices['nonAc']['roundTrip']['normal'] ?? $prices['nonAcRoundTripNormal'] ?? ($prices['nonAc']['oneWay']['normal'] ?? 0)),
                    'hill' => (float) ($prices['nonAc']['roundTrip']['hill'] ?? $prices['nonAcRoundTripHill'] ?? ($prices['nonAc']['oneWay']['hill'] ?? 0)),
                ],
            ],
        ];

        return $fallback;
    }
}
