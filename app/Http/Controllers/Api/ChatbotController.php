<?php

namespace App\Http\Controllers\Api;

use App\Models\Booking;
use App\Models\Vehicle;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class ChatbotController extends Controller
{
    private const TTL = 1800;

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

    private function handleStep(string $step, string $message, array $data, string $sessionId): array
    {
        switch ($step) {
            case 'pickup':
                $resolved = $this->geocode($message);
                if (!$resolved) {
                    return ['pickup', "I couldn't find that location. Could you be more specific? (e.g. \"Taj Mahal, Agra\")", $data];
                }
                $data['pickup']     = $resolved['formatted'];
                $data['pickup_lat'] = $resolved['lat'];
                $data['pickup_lng'] = $resolved['lng'];
                return ['destination', "Got it! Pickup: *{$resolved['formatted']}*\n\nWhere are you heading to?", $data];

            case 'destination':
                $resolved = $this->geocode($message);
                if (!$resolved) {
                    return ['destination', "I couldn't find that location. Could you be more specific?", $data];
                }
                $data['destination']     = $resolved['formatted'];
                $data['destination_lat'] = $resolved['lat'];
                $data['destination_lng'] = $resolved['lng'];
                return ['days', "Great! Destination: *{$resolved['formatted']}*\n\nHow many days is your trip? (1–5)", $data];

            case 'days':
                $days = (int) $message;
                if (!is_numeric(trim($message)) || $days < 1 || $days > 5) {
                    return ['days', "Please enter a number between 1 and 5 for the number of days.", $data];
                }
                $data['days'] = $days;
                return ['pax', "How many passengers will be travelling?", $data];

            case 'pax':
                $pax = (int) $message;
                if (!is_numeric(trim($message)) || $pax < 1 || $pax > 100) {
                    return ['pax', "Please enter a valid number of passengers (1–100).", $data];
                }
                $data['pax'] = $pax;
                return ['ac', "Do you prefer AC or Non-AC?\nReply: *ac*, *non-ac*, or *both*", $data];

            case 'ac':
                $ac = strtolower(trim($message));
                if (!in_array($ac, ['ac', 'non-ac', 'both'])) {
                    return ['ac', "Please reply with *ac*, *non-ac*, or *both*.", $data];
                }
                $data['ac'] = $ac;
                return ['date', "What date would you like to travel?\n(e.g. 25 May 2026)", $data];

            case 'date':
                $parsed = strtotime($message);
                if (!$parsed || $parsed < strtotime('today')) {
                    return ['date', "Please enter a valid future date (e.g. 25 May 2026).", $data];
                }
                $data['date'] = date('Y-m-d', $parsed);
                $estimates    = $this->getEstimates($data);

                if ($estimates === null) {
                    return ['pickup', "Sorry, I couldn't calculate the distance. Let's try again.\n\nWhere would you like to be picked up from?", []];
                }

                $data['estimates']     = $estimates['estimates'];
                $data['distance_km']   = $estimates['distance_km'];
                $data['duration_text'] = $estimates['duration_text'];

                return ['vehicle', $this->formatEstimates($estimates, $data), $data];

            case 'vehicle':
                $choice  = trim($message);
                $options = collect($data['estimates']);

                // Match by number (1, 2, 3...) or by name
                if (is_numeric($choice)) {
                    $index   = (int) $choice - 1;
                    $selected = $options->values()->get($index);
                } else {
                    $selected = $options->first(fn($e) => strtolower($e['vehicle']) === strtolower($choice));
                }

                if (!$selected) {
                    $list = $options->values()->map(fn($e, $i) => ($i + 1) . '. ' . $e['vehicle'])->implode("\n");
                    return ['vehicle', "Please choose a valid vehicle by number or name:\n\n{$list}", $data];
                }

                $data['chosen_vehicle'] = $selected['vehicle'];
                $data['chosen_ac']      = $selected['options'][0]['ac'] ? 'AC' : 'Non-AC';
                $data['chosen_cost']    = $selected['options'][0]['total_cost'];

                $summary = implode("\n", [
                    "Great choice! Here's your booking summary:",
                    "",
                    "🚗 Vehicle: *{$selected['vehicle']}*",
                    "📍 Pickup: {$data['pickup']}",
                    "📍 Destination: {$data['destination']}",
                    "📅 Date: {$data['date']}",
                    "👥 Passengers: {$data['pax']}",
                    "❄️ AC: {$data['chosen_ac']}",
                    "📏 Distance: {$data['distance_km']} km ({$data['duration_text']})",
                    "💰 Estimated Total: ₹{$data['chosen_cost']}",
                    "",
                    "Reply *yes* to confirm or *no* to start over.",
                ]);

                return ['confirm', $summary, $data];

            case 'confirm':
                $answer = strtolower(trim($message));

                if (in_array($answer, ['yes', 'y', 'confirm', 'ok', 'book'])) {
                    $booking = $this->createBooking($data);
                    return ['done', implode("\n", [
                        "✅ Your booking has been confirmed!",
                        "",
                        "Booking ID: *#{$booking->id}*",
                        "Vehicle: {$data['chosen_vehicle']}",
                        "Pickup: {$data['pickup']}",
                        "Destination: {$data['destination']}",
                        "Date: {$data['date']}",
                        "Estimated Cost: ₹{$data['chosen_cost']}",
                        "",
                        "We will contact you shortly. Thank you! 🙏",
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

    private function formatEstimates(array $estimates, array $data): string
    {
        $lines   = [];
        $lines[] = "Here are the available vehicles for *{$data['days']} day(s)*, {$data['pax']} passenger(s):";
        $lines[] = "Route: {$data['pickup']} → {$data['destination']}";
        $lines[] = "Distance: {$estimates['distance_km']} km | Drive time: {$estimates['duration_text']}";
        $lines[] = "";

        foreach ($estimates['estimates'] as $i => $e) {
            $num     = $i + 1;
            $lines[] = "{$num}. 🚗 *{$e['vehicle']}* ({$e['seats']} seats)";
            foreach ($e['options'] as $opt) {
                $label   = $opt['ac'] ? 'AC' : 'Non-AC';
                $lines[] = "   {$label}: ₹{$opt['total_cost']} (driving ₹{$opt['driving_cost']} + stay ₹{$opt['stay_cost']})";
            }
        }

        $lines[] = "";
        $lines[] = "Reply with the *number* or *name* of your preferred vehicle.";

        return implode("\n", $lines);
    }

    private function getEstimates(array $data): ?array
    {
        $origin = $data['pickup_lat'] . ',' . $data['pickup_lng'];
        $dest   = $data['destination_lat'] . ',' . $data['destination_lng'];

        $distanceResult = $this->fetchDistance($origin, $dest);

        if (!$distanceResult) {
            return null;
        }

        $distanceKm = $distanceResult['km'];
        $days       = (int) $data['days'];
        $acPref     = $data['ac'];

        $estimates = Vehicle::all()->map(function (Vehicle $vehicle) use ($distanceKm, $days, $acPref) {
            $options = [];

            if (in_array($acPref, ['ac', 'both']) && $vehicle->ac_available) {
                $options[] = $this->buildOption($vehicle, $distanceKm, $days, true);
            }

            if (in_array($acPref, ['non-ac', 'both']) && $vehicle->non_ac_available) {
                $options[] = $this->buildOption($vehicle, $distanceKm, $days, false);
            }

            if (empty($options)) return null;

            return [
                'vehicle' => $vehicle->name,
                'seats'   => $vehicle->seats,
                'options' => $options,
            ];
        })->filter()->values()->toArray();

        return [
            'distance_km'   => round($distanceKm, 2),
            'duration_text' => $distanceResult['duration'],
            'estimates'     => $estimates,
        ];
    }

    private function buildOption(Vehicle $vehicle, float $distanceKm, int $days, bool $ac): array
    {
        $pricePerKm  = $ac ? (float) $vehicle->ac_price_per_km : (float) $vehicle->non_ac_price_per_km;
        $stayField   = 'stay_price_day' . $days;
        $stayPrice   = (float) ($vehicle->$stayField ?? 0);
        $drivingCost = round($distanceKm * $pricePerKm, 2);

        return [
            'ac'           => $ac,
            'price_per_km' => $pricePerKm,
            'driving_cost' => $drivingCost,
            'stay_cost'    => $stayPrice,
            'total_cost'   => round($drivingCost + $stayPrice, 2),
        ];
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
