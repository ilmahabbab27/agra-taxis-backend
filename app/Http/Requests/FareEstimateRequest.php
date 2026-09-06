<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FareEstimateRequest extends FormRequest
{
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
