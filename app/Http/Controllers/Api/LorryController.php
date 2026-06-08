<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lorry;
use Illuminate\Http\Request;

class LorryController extends Controller
{
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
