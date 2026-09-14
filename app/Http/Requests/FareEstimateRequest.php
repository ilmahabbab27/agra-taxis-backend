<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Vehicle;

class FareEstimateRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (!$this->is('api/external/fare-estimate')) {
            return;
        }

        $value = fn (string $key, mixed $default = null) => $this->isMethod('GET')
            ? $this->query($key, $default)
            : $this->input($key, $default);
        $vehicleName = trim((string) $value('vehicle', $value('lorry')));
        $vehicle = Vehicle::whereRaw('LOWER(name) = ?', [strtolower($vehicleName)])->first();
        $vehicle ??= Vehicle::whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($vehicleName) . '%'])->first();
        $type = strtolower((string) $value('type'));
        $pickup = $value('pickup');
        $drop = $value('drop');
        $pickupCoordinates = $this->coordinatePair($pickup);
        $dropCoordinates = $this->coordinatePair($drop);

        $this->merge([
            'vehicle_id' => $vehicle?->id,
            'service_type' => $type === 'lorry' ? 'Lorry' : 'Passenger',
            'pickup_lat' => $pickupCoordinates[0] ?? null,
            'pickup_lng' => $pickupCoordinates[1] ?? null,
            'pickup_text' => $pickupCoordinates ? null : $pickup,
            'destination_lat' => $dropCoordinates[0] ?? null,
            'destination_lng' => $dropCoordinates[1] ?? null,
            'destination_text' => $dropCoordinates ? null : $drop,
            'trip' => strtolower((string) $value('trip')) === 'round-trip' ? 'Round Trip' : 'One Way',
            'days' => $value('days', 1),
            'pax' => $value('pax', 1),
            'ac' => strtolower((string) $value('ac')) === 'non-ac' ? 'Non AC' : 'AC',
            'rate_type' => $value('rate_type'),
            'distance_km' => $value('distance_km'),
            'waiting_hours' => $value('waiting_hours', 0),
        ]);
    }

    private function coordinatePair(mixed $value): ?array
    {
        $parts = array_map('trim', explode(',', (string) $value));
        if (count($parts) !== 2 || !is_numeric($parts[0]) || !is_numeric($parts[1])) {
            return null;
        }

        return [(float) $parts[0], (float) $parts[1]];
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'vehicle_id' => ['required', 'integer', 'exists:vehicles,id'],
            'service_type' => ['required', Rule::in(['Passenger', 'Lorry'])],
            'pickup_text' => ['required_without_all:pickup_lat,pickup_lng', 'string', 'max:200'],
            'pickup_lat' => ['required_without:pickup_text', 'numeric'],
            'pickup_lng' => ['required_without:pickup_text', 'numeric'],
            'destination_text' => ['required_without_all:destination_lat,destination_lng', 'string', 'max:200'],
            'destination_lat' => ['required_without:destination_text', 'numeric'],
            'destination_lng' => ['required_without:destination_text', 'numeric'],
            'trip' => ['required', Rule::in(['One Way', 'Round Trip'])],
            'days' => ['required_if:service_type,Passenger', 'nullable', 'integer', 'min:1', 'max:5'],
            'pax' => ['required_if:service_type,Passenger', 'nullable', 'integer', 'min:1', 'max:100'],
            'ac' => ['required_if:service_type,Passenger', Rule::in(['AC', 'Non AC'])],
            'rate_type' => ['required_if:service_type,Lorry', 'string', 'max:100'],
            'waiting_hours' => ['sometimes', 'numeric', 'min:0'],
            'distance_km' => ['sometimes', 'numeric', 'min:0'],
        ];
    }

    public function validatedCoordinates(string $prefix): ?array
    {
        $lat = $this->input("{$prefix}_lat");
        $lng = $this->input("{$prefix}_lng");

        if ($lat === null || $lng === null) {
            return null;
        }

        return [
            'lat' => (float) $lat,
            'lng' => (float) $lng,
        ];
    }
}
