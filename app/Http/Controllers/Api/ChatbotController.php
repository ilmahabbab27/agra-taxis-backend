<?php

namespace App\Http\Controllers\Api;

use App\Models\Booking;
use App\Models\Vehicle;
use App\Http\Controllers\Controller;
use App\Services\FareEstimator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;

class ChatbotController extends Controller
{
    private const TTL = 1800;

    public function __construct(private FareEstimator $fareEstimator)
    {
    }

    public function message(Request $request)
    {
        $request->validate([
            'session_id' => ['required', 'string', 'max:100'],
            'message'    => ['required', 'string', 'max:500'],
        ]);

        $sessionId = $request->input('session_id');
        $message   = trim($request->input('message'));

        $session = Cache::get("chatbot:{$sessionId}", [
            'step' => 'pickup',
            'data' => [],
        ]);

        if (strtolower($message) === 'restart') {
            Cache::forget("chatbot:{$sessionId}");
            return $this->reply('Sure! Let\'s start over. Where would you like to be picked up from?', 'pickup');
        }

        $step = $session['step'];
        $data = $session['data'];

        [$next, $reply, $data] = $this->handleStep($step, $message, $data, $sessionId);

        if ($next !== 'done') {
            Cache::put("chatbot:{$sessionId}", ['step' => $next, 'data' => $data], self::TTL);
        } else {
            Cache::forget("chatbot:{$sessionId}");
        }

        return $this->reply($reply, $next);
    }

    public function estimate(Request $request)
    {
        $request->validate([
            'pickup_text' => ['sometimes', 'string', 'max:200'],
            'pickup_lat' => ['sometimes', 'numeric'],
            'pickup_lng' => ['sometimes', 'numeric'],
            'dropoff_text' => ['sometimes', 'string', 'max:200'],
            'dropoff_lat' => ['sometimes', 'numeric'],
            'dropoff_lng' => ['sometimes', 'numeric'],
            'vehicle' => ['sometimes', 'string', 'max:100'],
            'ac' => ['sometimes', Rule::in(['ac', 'non-ac'])],
            'days' => ['sometimes', 'integer', 'min:1', 'max:5'],
            'trip' => ['sometimes', Rule::in(['one-way', 'round-trip'])],
        ]);

        $pickup = $this->resolveLocation($request->only(['pickup_text', 'pickup_lat', 'pickup_lng']), 'pickup');
        $dropoff = $this->resolveLocation($request->only(['dropoff_text', 'dropoff_lat', 'dropoff_lng']), 'dropoff');

        if (!$pickup || !$dropoff) {
            return response()->json([
                'success' => false,
                'message' => 'Could not resolve one or both locations. Please provide clearer pickup and dropoff information.',
            ], 422);
        }

        $distanceResult = $this->fetchDistance(
            $pickup['lat'] . ',' . $pickup['lng'], 
            $dropoff['lat'] . ',' . $dropoff['lng']
        );

        if (!$distanceResult) {
            return response()->json([
                'success' => false,
                'message' => 'Could not calculate the driving distance for this route.',
            ], 422);
        }

        $vehicleName = $request->input('vehicle', 'Sedan');
        $vehicle = Vehicle::where('name', $vehicleName)->first();
        if (!$vehicle) {
            $vehicle = Vehicle::where('name', 'Sedan')->first();
        }

        if (!$vehicle) {
            return response()->json([
                'success' => false,
                'message' => 'No vehicle is available for estimate.',
            ], 422);
        }

        $days = $request->input('days', 1);
        $ac = $request->input('ac', 'ac');

        $trip = $request->input('trip', 'one-way');
        $fare = $this->fareEstimator->estimate($vehicle, $distanceResult['km'], (int) $days, $ac, $trip, $pickup, $dropoff);

        return response()->json([
            'success' => true,
            'pickup' => $pickup['formatted'],
            'dropoff' => $dropoff['formatted'],
            'distance_km' => round($distanceResult['km'], 2),
            'vehicle' => $vehicle->name,
            'ac' => $fare['ac_label'],
            'days' => $days,
            'price_per_km' => $fare['price_per_km'],
            'effective_price_per_km' => $fare['effective_price_per_km'],
            'trip_multiplier' => $fare['trip_multiplier'],
            'included_km' => $fare['included_km'],
            'additional_km' => $fare['additional_km'],
            'billable_km' => $fare['billable_km'],
            'included_distance_charge' => $fare['included_distance_charge'],
            'additional_distance_charge' => $fare['additional_distance_charge'],
            'base_package_charge' => $fare['base_package_charge'],
            'package1_estimate' => $fare['package1_estimate'],
            'package2_estimate' => $fare['package2_estimate'],
            'pickup_is_hill_country' => $fare['pickup_is_hill_country'],
            'drop_is_hill_country' => $fare['drop_is_hill_country'],
            'hill_country_rate' => $fare['is_hill_country'],
            'driving_cost' => $fare['driving_cost'],
            'stay_cost' => $fare['stay_cost'],
            'total_cost' => $fare['total_cost'],
            'message' => sprintf(
                'I assumed pickup is %s and dropoff is %s. Estimated fare is Rs. %s for approximately %s km.',
                $pickup['formatted'],
                $dropoff['formatted'],
                number_format($fare['total_cost'], 2),
                number_format($distanceResult['km'], 2)
            ),
        ]);
    }

    private function handleStep(string $step, string $message, array $data, string $sessionId): array
    {
        switch ($step) {

            // ── Step 1: Pickup ────────────────────────────────────────────────
            case 'pickup':
                $resolved = $this->geocode($message);
                if (!$resolved) {
                    return ['pickup', "I couldn't find that location. Could you be more specific? (e.g. \"Taj Mahal, Agra\")", $data];
                }
                $data['pickup']     = $resolved['formatted'];
                $data['pickup_lat'] = $resolved['lat'];
                $data['pickup_lng'] = $resolved['lng'];

                return ['destination', "Got it! Pickup: *{$resolved['formatted']}*\n\nWhere are you heading to?", $data];

            // ── Step 2: Destination ───────────────────────────────────────────
            case 'destination':
                $resolved = $this->geocode($message);
                if (!$resolved) {
                    return ['destination', "I couldn't find that location. Could you be more specific?", $data];
                }
                $data['destination']     = $resolved['formatted'];
                $data['destination_lat'] = $resolved['lat'];
                $data['destination_lng'] = $resolved['lng'];

                // Build and show vehicle list at step 3
                $vehicleList = $this->buildVehicleList();
                $data['vehicle_list'] = $vehicleList;

                $lines   = [];
                $lines[] = "Destination: *{$resolved['formatted']}*";
                $lines[] = "";
                $lines[] = "Please choose your vehicle:";
                $lines[] = "";
                foreach ($vehicleList as $i => $v) {
                    $lines[] = ($i + 1) . ". *{$v['name']}* — {$v['seats']} seats";
                }
                $lines[] = "";
                $lines[] = "Reply with the number or name of your preferred vehicle.";

                return ['vehicle', implode("\n", $lines), $data];

            // ── Step 3: Vehicle selection ─────────────────────────────────────
            case 'vehicle':
                $choice  = trim($message);
                $options = collect($data['vehicle_list'] ?? []);

                if (is_numeric($choice)) {
                    $index    = (int) $choice - 1;
                    $selected = $options->values()->get($index);
                } else {
                    $selected = $options->first(fn($v) => strtolower($v['name']) === strtolower($choice));
                }

                if (!$selected) {
                    $list = $options->values()->map(fn($v, $i) => ($i + 1) . '. ' . $v['name'])->implode("\n");
                    return ['vehicle', "Please choose a valid vehicle by number or name:\n\n{$list}", $data];
                }

                $data['chosen_vehicle']    = $selected['name'];
                $data['chosen_vehicle_id'] = $selected['id'];
                $data['chosen_seats']      = $selected['seats'];

                return ['days', "Great choice! *{$selected['name']}* selected.\n\nHow many days is your trip? (1–5)", $data];

            // ── Step 4: Days ──────────────────────────────────────────────────
            case 'days':
                $days = (int) $message;
                if (!is_numeric(trim($message)) || $days < 1 || $days > 5) {
                    return ['days', "Please enter a number between 1 and 5 for the number of days.", $data];
                }
                $data['days'] = $days;
                return ['pax', "How many passengers will be travelling?", $data];

            // ── Step 5: Passengers ────────────────────────────────────────────
            case 'pax':
                $pax = (int) $message;
                if (!is_numeric(trim($message)) || $pax < 1 || $pax > 100) {
                    return ['pax', "Please enter a valid number of passengers (1–100).", $data];
                }
                $data['pax'] = $pax;
                return ['ac', "Do you prefer AC or Non-AC?\nReply: *ac*, *non-ac*, or *both*", $data];

            // ── Step 6: AC preference ─────────────────────────────────────────
            case 'ac':
                $ac = strtolower(trim($message));
                if (!in_array($ac, ['ac', 'non-ac', 'both'])) {
                    return ['ac', "Please reply with *ac*, *non-ac*, or *both*.", $data];
                }
                $data['ac'] = $ac;
                return ['date', "What date would you like to travel?\n(e.g. 25 May 2026)", $data];

            // ── Step 7: Date + fare calculation ──────────────────────────────
            case 'date':
                $parsed = strtotime($message);
                if (!$parsed || $parsed < strtotime('today')) {
                    return ['date', "Please enter a valid future date (e.g. 25 May 2026).", $data];
                }
                $data['date'] = date('Y-m-d', $parsed);

                $origin         = $data['pickup_lat'] . ',' . $data['pickup_lng'];
                $dest           = $data['destination_lat'] . ',' . $data['destination_lng'];
                $distanceResult = $this->fetchDistance($origin, $dest);

                if (!$distanceResult) {
                    return ['pickup', "Sorry, I couldn't calculate the distance. Let's try again.\n\nWhere would you like to be picked up from?", []];
                }

                $data['distance_km']   = round($distanceResult['km'], 2);
                $data['duration_text'] = $distanceResult['duration'];

                $vehicle = Vehicle::find($data['chosen_vehicle_id']);
                $fare    = $this->fareEstimator->estimate(
                    $vehicle,
                    $data['distance_km'],
                    (int) $data['days'],
                    $data['ac'],
                    $data['trip'] ?? 'one-way',
                    ['formatted' => $data['pickup'], 'lat' => $data['pickup_lat'], 'lng' => $data['pickup_lng']],
                    ['formatted' => $data['destination'], 'lat' => $data['destination_lat'], 'lng' => $data['destination_lng']]
                );

                $data['chosen_ac']    = $fare['ac_label'];
                $data['chosen_cost']  = $fare['total_cost'];
                $data['driving_cost'] = $fare['driving_cost'];
                $data['stay_cost']    = $fare['stay_cost'];
                $data['price_per_km'] = $fare['price_per_km'];
                $data['effective_price_per_km'] = $fare['effective_price_per_km'];
                $data['trip_multiplier'] = $fare['trip_multiplier'];

                $summary = implode("\n", [
                    "Here's your booking summary:",
                    "",
                    "Vehicle:     *{$data['chosen_vehicle']}* ({$data['chosen_seats']} seats)",
                    "Pickup:      {$data['pickup']}",
                    "Destination: {$data['destination']}",
                    "Date:        {$data['date']}",
                    "Passengers:  {$data['pax']}",
                    "Comfort:     {$data['chosen_ac']}",
                    "Distance:    {$data['distance_km']} km ({$data['duration_text']})",
                    "Driving:     Rs. {$data['driving_cost']}",
                    "Stay:        Rs. {$data['stay_cost']}",
                    "Total:       Rs. {$data['chosen_cost']}",
                    "",
                    "Reply *yes* to confirm or *no* to start over.",
                ]);

                return ['confirm', $summary, $data];

            // ── Step 8: Confirm ───────────────────────────────────────────────
            case 'confirm':
                $answer = strtolower(trim($message));

                if (in_array($answer, ['yes', 'y', 'confirm', 'ok', 'book'])) {
                    $booking = $this->createBooking($data);
                    return ['done', implode("\n", [
                        "Your booking has been confirmed!",
                        "",
                        "Booking ID: *#{$booking->id}*",
                        "Vehicle:    {$data['chosen_vehicle']}",
                        "Pickup:     {$data['pickup']}",
                        "Destination:{$data['destination']}",
                        "Date:       {$data['date']}",
                        "Total:      Rs. {$data['chosen_cost']}",
                        "",
                        "We will contact you shortly. Thank you!",
                    ]), $data];
                }

                if (in_array($answer, ['no', 'n', 'cancel'])) {
                    Cache::forget("chatbot:{$sessionId}");
                    return ['pickup', "No problem! Let's start over.\n\nWhere would you like to be picked up from?", []];
                }

                return ['confirm', "Please reply *yes* to confirm your booking or *no* to start over.", $data];
        }

        return ['pickup', "Where would you like to be picked up from?", []];
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function buildVehicleList(): array
    {
        return Vehicle::all()->map(fn(Vehicle $v) => [
            'id'               => $v->id,
            'name'             => $v->name,
            'seats'            => $v->seats,
            'ac_available'     => $v->ac_available,
            'non_ac_available' => $v->non_ac_available,
        ])->values()->toArray();
    }

    private function geocode(string $address): ?array
    {
        $response = Http::withoutVerifying()->get('https://maps.googleapis.com/maps/api/geocode/json', [
            'address' => $address,
            'key'     => config('services.google.maps_key'),
        ]);

        $result = $response->json('results.0');
        if (!$result) return null;

        return [
            'lat'       => $result['geometry']['location']['lat'],
            'lng'       => $result['geometry']['location']['lng'],
            'formatted' => $result['formatted_address'],
        ];
    }

    private function reverseGeocode(float $lat, float $lng): array
    {
        $response = Http::withoutVerifying()->get('https://maps.googleapis.com/maps/api/geocode/json', [
            'latlng' => $lat . ',' . $lng,
            'key'    => config('services.google.maps_key'),
        ]);

        $result = $response->json('results.0');
        if (!$result) {
            return [
                'lat' => $lat,
                'lng' => $lng,
                'formatted' => sprintf('%s, %s', $lat, $lng),
            ];
        }

        return [
            'lat'       => $result['geometry']['location']['lat'],
            'lng'       => $result['geometry']['location']['lng'],
            'formatted' => $result['formatted_address'],
        ];
    }

    private function resolveLocation(array $input, string $type): ?array
    {
        $textKey = "{$type}_text";
        $latKey = "{$type}_lat";
        $lngKey = "{$type}_lng";

        if (!empty($input[$latKey]) && !empty($input[$lngKey])) {
            return $this->reverseGeocode((float) $input[$latKey], (float) $input[$lngKey]);
        }

        if (!empty($input[$textKey])) {
            return $this->geocode($input[$textKey]);
        }

        return null;
    }

    private function fetchDistance(string $origin, string $destination): ?array
    {
        $response = Http::withoutVerifying()->get('https://maps.googleapis.com/maps/api/distancematrix/json', [
            'origins'      => $origin,
            'destinations' => $destination,
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

    private function createBooking(array $data): Booking
    {
        return Booking::create([
            'vehicle'         => $data['chosen_vehicle'],
            'pickup'          => $data['pickup'],
            'pickup_lat'      => $data['pickup_lat'],
            'pickup_lng'      => $data['pickup_lng'],
            'destination'     => $data['destination'],
            'destination_lat' => $data['destination_lat'],
            'destination_lng' => $data['destination_lng'],
            'travel_date'     => $data['date'],
            'days'            => $data['days'],
            'trip'            => 'one-way',
            'passengers'      => $data['pax'],
            'ac'              => $data['ac'],
            'distance_km'     => $data['distance_km'],
            'distance_source' => 'route',
            'price_per_km'    => $data['price_per_km'] ?? null,
            'effective_price_per_km' => $data['effective_price_per_km'] ?? null,
            'trip_multiplier' => $data['trip_multiplier'] ?? 1,
            'driving_cost'    => $data['driving_cost'] ?? null,
            'stay_cost'       => $data['stay_cost'] ?? 0,
            'total_cost'      => $data['chosen_cost'] ?? null,
            'status'          => 'new',
        ]);
    }

    private function reply(string $message, string $step): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'message' => $message,
            'step'    => $step,
        ]);
    }
}
