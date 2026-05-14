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
        $vehicle->delete();

        return response()->json(['message' => 'Vehicle deleted.']);
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
        $request->merge([
            'ac_price_per_km'    => $request->input('ac_price_per_km', $request->input('acPricePerKm')),
            'non_ac_price_per_km'=> $request->input('non_ac_price_per_km', $request->input('nonAcPricePerKm')),
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
            'seats'              => ['required', 'integer', 'min:1', 'max:100'],
            'ac_price_per_km'    => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'non_ac_price_per_km'=> ['nullable', 'numeric', 'min:0', 'max:999999'],
            'ac_available'       => ['required', 'boolean'],
            'non_ac_available'   => ['required', 'boolean'],
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
            'seats'              => max(1, (int) $data['seats']),
            'ac_price_per_km'    => $acAvailable ? ($data['ac_price_per_km'] ?? 0) : 0,
            'non_ac_price_per_km'=> $nonAcAvailable ? ($data['non_ac_price_per_km'] ?? 0) : 0,
            'ac_available'       => $acAvailable,
            'non_ac_available'   => $nonAcAvailable,
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
            'seats'          => $vehicle->seats,
            'acPricePerKm'   => (float) $vehicle->ac_price_per_km,
            'nonAcPricePerKm'=> (float) $vehicle->non_ac_price_per_km,
            'acAvailable'    => $vehicle->ac_available,
            'nonAcAvailable' => $vehicle->non_ac_available,
            'stayPrices'     => [
                'day1' => (float) $vehicle->stay_price_day1,
                'day2' => (float) $vehicle->stay_price_day2,
                'day3' => (float) $vehicle->stay_price_day3,
                'day4' => (float) $vehicle->stay_price_day4,
                'day5' => (float) $vehicle->stay_price_day5,
            ],
        ];
    }
}
