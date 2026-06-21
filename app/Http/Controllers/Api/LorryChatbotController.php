<?php

namespace App\Http\Controllers\Api;

use App\Models\Booking;
use App\Models\Lorry;
use App\Http\Controllers\Controller;
use App\Services\LorryEstimator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;

class LorryChatbotController extends Controller
{
    private const TTL = 1800;

    public function __construct(private LorryEstimator $estimator) {}

    /**
     * Handle WhatsApp message for lorry booking
     * POST /api/lorry-chatbot/message
     */
    public function message(Request $request)
    {
        $request->validate([
            'session_id' => ['required', 'string', 'max:100'],
            'message'    => ['required', 'string', 'max:500'],
        ]);

        $sessionId = $request->input('session_id');
        $message   = trim($request->input('message'));

        $session = Cache::get("lorry_chatbot:{$sessionId}", [
            'step' => 'pickup',
            'data' => [],
        ]);

        if (strtolower($message) === 'restart') {
            Cache::forget("lorry_chatbot:{$sessionId}");
            return $this->reply('Sure! Let\'s start over. Where would you like to be picked up from?', 'pickup');
        }

        $step = $session['step'];
        $data = $session['data'];

        [$next, $reply, $data] = $this->handleStep($step, $message, $data, $sessionId);

        if ($next !== 'done') {
            Cache::put("lorry_chatbot:{$sessionId}", ['step' => $next, 'data' => $data], self::TTL);
        } else {
            Cache::forget("lorry_chatbot:{$sessionId}");
        }

        return $this->reply($reply, $next);
    }

    private function handleStep(string $step, string $message, array $data, string $sessionId): array
    {
        switch ($step) {

            // ── Step 1: Pickup Location ───────────────────────────────────────
            case 'pickup':
                $resolved = $this->geocode($message);
                if (!$resolved) {
                    return ['pickup', "❌ I couldn't find that location.\n\nCould you be more specific?\n(e.g. \"Colombo\", \"Kandy\", \"Colombo Fort\")", $data];
                }
                $data['pickup_text'] = $resolved['formatted'];
                $data['pickup_lat']  = $resolved['lat'];
                $data['pickup_lng']  = $resolved['lng'];

                return ['destination', "✅ Got it!\n\nPickup: *{$resolved['formatted']}*\n\n📍 Where are you heading to?", $data];

            // ── Step 2: Destination Location ──────────────────────────────────
            case 'destination':
                $resolved = $this->geocode($message);
                if (!$resolved) {
                    return ['destination', "❌ I couldn't find that location.\n\nCould you be more specific?", $data];
                }
                $data['destination_text'] = $resolved['formatted'];
                $data['destination_lat']  = $resolved['lat'];
                $data['destination_lng']  = $resolved['lng'];

                return ['trip_type', "✅ Destination: *{$resolved['formatted']}*\n\n🛣️ What type of trip?\n\n1. One Way\n2. Round Trip\n\nReply with number or name.", $data];

            // ── Step 3: Trip Type Selection ───────────────────────────────────
            case 'trip_type':
                $choice = strtolower(trim($message));
                $trip = null;

                if ($choice === '1' || $choice === 'one way' || $choice === 'one-way') {
                    $trip = 'one-way';
                } elseif ($choice === '2' || $choice === 'round trip' || $choice === 'round-trip') {
                    $trip = 'round-trip';
                } else {
                    return ['trip_type', "❌ Invalid choice.\n\nPlease reply with:\n1️⃣ One Way\n2️⃣ Round Trip", $data];
                }

                $data['trip_type'] = $trip;

                // Build lorry list
                $lorries = $this->buildLorryList();
                $data['lorry_list'] = $lorries;

                $lines = [];
                $lines[] = "Great! *" . ucfirst($trip) . "* selected.";
                $lines[] = "";
                $lines[] = "🚚 Available Lorry Types:";
                $lines[] = "";
                foreach ($lorries as $i => $l) {
                    $baseRate = number_format($l['base_rate']);
                    $lines[] = ($i + 1) . ". *{$l['type']}* — Rs. {$baseRate} (0-{$l['window_km']} km)";
                }
                $lines[] = "";
                $lines[] = "Reply with number or lorry type.";

                return ['lorry_type', implode("\n", $lines), $data];

            // ── Step 4: Lorry Type Selection ──────────────────────────────────
            case 'lorry_type':
                $choice  = trim($message);
                $options = collect($data['lorry_list'] ?? []);

                if (is_numeric($choice)) {
                    $index    = (int) $choice - 1;
                    $selected = $options->values()->get($index);
                } else {
                    $selected = $options->first(fn($l) =>
                        strtolower($l['type']) === strtolower($choice) ||
                        strtolower($l['size']) === strtolower($choice)
                    );
                }

                if (!$selected) {
                    $list = $options->values()->map(fn($l, $i) => ($i + 1) . '. ' . $l['type'])->implode("\n");
                    return ['lorry_type', "Please choose a valid lorry by number or name:\n\n{$list}", $data];
                }

                $data['lorry_type']  = $selected['type'];
                $data['lorry_id']    = $selected['id'];
                $data['rate_type']   = $selected['rate_key'];

                return ['distance', "✅ Great! *{$selected['type']}* selected.\n\n📏 What's the distance in kilometers?\n\n(e.g. 250)\n\nOr type 'auto' to calculate from locations.", $data];

            // ── Step 5: Distance Input ────────────────────────────────────────
            case 'distance':
                if (is_numeric($message)) {
                    $distance = (float) $message;
                    if ($distance <= 0) {
                        return ['distance', "Please enter a distance greater than 0 km.", $data];
                    }
                    $data['distance_km'] = $distance;
                    return $this->calculateFareAndConfirm($data, $sessionId);
                }

                if (strtolower(trim($message)) === 'auto' || strtolower(trim($message)) === 'calculate') {
                    // Auto-calculate distance from stored coordinates
                    $distResult = $this->fetchDistance(
                        $data['pickup_lat'] . ',' . $data['pickup_lng'],
                        $data['destination_lat'] . ',' . $data['destination_lng']
                    );

                    if (!$distResult) {
                        return ['distance', "I couldn't calculate the distance. Please enter it manually (e.g. 250 km).", $data];
                    }

                    $data['distance_km']   = $distResult['km'];
                    $data['duration_text'] = $distResult['duration'];

                    return $this->calculateFareAndConfirm($data, $sessionId);
                }

                return ['distance', "Please enter a number for distance in km (e.g. 250), or type 'auto' to calculate from locations.", $data];

            // ── Step 6: Confirmation ──────────────────────────────────────────
            case 'confirm':
                $answer = strtolower(trim($message));

                if (in_array($answer, ['yes', 'y', 'confirm', 'ok', 'book'])) {
                    $booking = $this->createBooking($data);
                    return ['done', implode("\n", [
                        "✅ Your booking has been confirmed!",
                        "",
                        "Booking ID: *#{$booking->id}*",
                        "Lorry Type: {$data['lorry_type']}",
                        "Pickup: {$data['pickup_text']}",
                        "Destination: {$data['destination_text']}",
                        "Distance: {$data['distance_km']} km",
                        "Trip: {$data['trip_type']}",
                        "Estimated Fare: *Rs. {$data['total_cost']}*",
                        "",
                        "We will contact you shortly. Thank you!",
                    ]), $data];
                }

                if (in_array($answer, ['no', 'n', 'cancel'])) {
                    Cache::forget("lorry_chatbot:{$sessionId}");
                    return ['pickup', "No problem! Let's start over.\n\nWhere would you like to be picked up from?", []];
                }

                return ['confirm', "Please reply *yes* to confirm your booking or *no* to start over.", $data];
        }

        return ['pickup', "Where would you like to be picked up from?", []];
    }

    private function calculateFareAndConfirm(array $data, string $sessionId): array
    {
        // Determine hill country
        $isHillCountry = $this->checkHillCountry(
            $data['pickup_text'] ?? '',
            $data['pickup_lat'] ?? 0,
            $data['pickup_lng'] ?? 0,
            $data['destination_text'] ?? '',
            $data['destination_lat'] ?? 0,
            $data['destination_lng'] ?? 0
        );
        $data['is_hill_country'] = $isHillCountry;

        // Get lorry and rate table
        $lorry = Lorry::findOrFail($data['lorry_id']);
        $rateTable = $lorry->rate_table;

        if (!is_array($rateTable) || !isset($rateTable[$data['rate_type']])) {
            return ['distance', "Error: Rate table not found for {$data['lorry_type']}. Please try again.", $data];
        }

        $rate = $rateTable[$data['rate_type']];

        // Calculate fare
        $fare = $this->estimator->estimate(
            $rate,
            (float) $data['distance_km'],
            $data['trip_type'],
            $isHillCountry,
            0
        );

        // Store fare details
        $data['total_cost']       = $fare['total_cost'];
        $data['base_fee']         = $fare['base_fee'];
        $data['extra_fee']        = $fare['extra_fee'];
        $data['waiting_charge']   = $fare['waiting_charge'];
        $data['extra_km']         = $fare['extra_km'];
        $data['duration_text']    = $data['duration_text'] ?? 'Not calculated';

        // Build summary message with proper line breaks
        $lines = [
            "📋 *Booking Summary*",
            "",
            "Pickup: {$data['pickup_text']}",
            "Destination: {$data['destination_text']}",
            "Distance: {$data['distance_km']} km ({$data['duration_text']})",
            "Lorry: {$data['lorry_type']}",
            "Trip: {$data['trip_type']}",
            "Route: " . ($isHillCountry ? "🏔️ Hill Country" : "🚗 Normal"),
            "",
            "💰 *Fare Breakdown*",
            "Base Fee: Rs. " . number_format($data['base_fee']),
        ];

        if ($data['extra_km'] > 0) {
            $lines[] = "Extra KM ({$data['extra_km']} km): Rs. " . number_format($data['extra_fee']);
        }

        $lines[] = $data['waiting_charge'] > 0
            ? "Waiting Charge: Rs. " . number_format($data['waiting_charge']) . " (info only)"
            : "Waiting: 2 free hours, then Rs. 500/hour";

        $lines[] = "";
        $lines[] = "💵 *Total Fare: Rs. " . number_format($data['total_cost']) . "*";
        $lines[] = "";
        $lines[] = "Reply *yes* to confirm or *no* to start over.";

        $summary = implode("\n", $lines);
        return ['confirm', $summary, $data];
    }

    private function buildLorryList(): array
    {
        return Lorry::all()->map(function (Lorry $lorry) {
            $rateTable = $lorry->rate_table ?? [];
            $firstRate = reset($rateTable) ?: [];
            $windows   = $firstRate['windows'] ?? [];
            $firstWindow = reset($windows) ?: [];

            return [
                'id'         => $lorry->id,
                'name'       => $lorry->name,
                'type'       => $firstRate['type'] ?? 'Unknown',
                'size'       => $firstRate['type'] ?? 'Unknown',
                'rate_key'   => key($rateTable) ?: '7ft',
                'base_rate'  => $firstWindow['rate'] ?? 0,
                'window_km'  => $firstWindow['toKm'] ?? 50,
                'category'   => $lorry->category,
            ];
        })->sortBy('base_rate')->values()->toArray();
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
            'vehicle'         => $data['lorry_type'],
            'pickup'          => $data['pickup_text'],
            'pickup_lat'      => $data['pickup_lat'],
            'pickup_lng'      => $data['pickup_lng'],
            'destination'     => $data['destination_text'],
            'destination_lat' => $data['destination_lat'],
            'destination_lng' => $data['destination_lng'],
            'travel_date'     => now()->addDay()->format('Y-m-d'),
            'days'            => 1,
            'trip'            => $data['trip_type'],
            'passengers'      => 1,
            'ac'              => 'n/a',
            'distance_km'     => $data['distance_km'],
            'distance_source' => 'chatbot',
            'price_per_km'    => null,
            'total_cost'      => $data['total_cost'],
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
