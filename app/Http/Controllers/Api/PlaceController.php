<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class PlaceController extends Controller
{
    public function predict(Request $request)
    {
        $request->validate([
            'input' => ['required', 'string', 'min:1', 'max:200'],
        ]);

        $apiKey = config('services.google.maps_key');

        if (!$apiKey) {
            return response()->json(['message' => 'Google Maps API key not configured.'], 500);
        }

        $response = Http::withoutVerifying()->get('https://maps.googleapis.com/maps/api/place/autocomplete/json', [
            'input'    => $request->input('input'),
            'key'      => $apiKey,
            'types'    => 'geocode',
            'language' => 'en',
        ]);

        if (!$response->successful()) {
            return response()->json(['message' => 'Failed to fetch predictions.'], 502);
        }

        $body = $response->json();

        if (($body['status'] ?? '') !== 'OK' && ($body['status'] ?? '') !== 'ZERO_RESULTS') {
            return response()->json([
                'message' => $body['error_message'] ?? 'Google API error: ' . ($body['status'] ?? 'UNKNOWN'),
            ], 502);
        }

        $predictions = collect($body['predictions'] ?? [])->map(fn($p) => [
            'place_id'    => $p['place_id'],
            'description' => $p['description'],
            'main_text'   => $p['structured_formatting']['main_text'] ?? null,
            'secondary_text' => $p['structured_formatting']['secondary_text'] ?? null,
        ])->values();

        return response()->json(['data' => $predictions]);
    }
}
