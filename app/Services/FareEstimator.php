<?php

namespace App\Services;

use App\Models\Vehicle;

class FareEstimator
{
    public const INCLUDED_KM_PER_DAY = 150;

    public function estimate(Vehicle $vehicle, float $distanceKm, int $days, string $acPref, string $trip, array $pickup = [], array $dropoff = []): array
    {
        $days = max(1, $days);
        $isAc = $this->usesAc($vehicle, $acPref);
        $isRoundTrip = in_array(strtolower($trip), ['round-trip', 'round trip'], true);
        $isHillCountry = $this->isHillCountry($pickup) || $this->isHillCountry($dropoff);
        $pricePerKm = $this->resolvePricePerKm($vehicle, $isAc, $isRoundTrip, $isHillCountry);

        $includedKm = $days * self::INCLUDED_KM_PER_DAY;
        $additionalKm = $distanceKm > $includedKm ? round($distanceKm - $includedKm, 1) : 0.0;
        $billableKm = min($distanceKm, $includedKm);

        $basePackageCharge = $this->packageCharge($vehicle, $days, $isAc, $isHillCountry);
        $oneDayPackageDistanceCharge = $this->packageCharge($vehicle, 1, $isAc, $isHillCountry);

        $includedDistanceCharge = $days === 1
            ? ($oneDayPackageDistanceCharge ?: ($billableKm * $pricePerKm))
            : ($basePackageCharge ?: ($billableKm * $pricePerKm));
        $additionalDistanceCharge = $additionalKm * $pricePerKm;

        $package1DayCharge = $pricePerKm
            ? $pricePerKm * self::INCLUDED_KM_PER_DAY
            : ($oneDayPackageDistanceCharge ?: 0);
        $package1BaseCharge = $package1DayCharge * $days;
        $package1Estimate = $distanceKm ? $package1BaseCharge + $additionalDistanceCharge : null;
        $package2Estimate = $distanceKm && $pricePerKm ? $distanceKm * $pricePerKm : null;

        $estimatedFare = $days === 1
            ? ($package1Estimate ?? $package2Estimate)
            : ($distanceKm && $pricePerKm ? $includedDistanceCharge + $additionalDistanceCharge : null);

        return [
            'ac_label' => $isAc ? 'AC' : 'Non-AC',
            'ac' => $isAc ? 'ac' : 'non-ac',
            'is_hill_country' => $isHillCountry,
            'pickup_is_hill_country' => $this->isHillCountry($pickup),
            'drop_is_hill_country' => $this->isHillCountry($dropoff),
            'price_per_km' => round($pricePerKm, 2),
            'effective_price_per_km' => round($pricePerKm, 2),
            'trip_multiplier' => $isRoundTrip ? 2 : 1,
            'included_km' => $includedKm,
            'additional_km' => $additionalKm,
            'billable_km' => round($billableKm, 2),
            'included_distance_charge' => round($includedDistanceCharge, 2),
            'additional_distance_charge' => round($additionalDistanceCharge, 2),
            'base_package_charge' => round($basePackageCharge ?: $package1BaseCharge, 2),
            'one_day_package_distance_charge' => round($oneDayPackageDistanceCharge, 2),
            'package1_estimate' => $package1Estimate === null ? null : round($package1Estimate, 2),
            'package2_estimate' => $package2Estimate === null ? null : round($package2Estimate, 2),
            'driving_cost' => round((float) ($estimatedFare ?? 0), 2),
            'stay_cost' => 0.0,
            'total_cost' => round((float) ($estimatedFare ?? 0), 2),
        ];
    }

    public function usesAc(Vehicle $vehicle, string $acPref): bool
    {
        $acPref = strtolower(trim($acPref));
        $useAc = in_array($acPref, ['ac', 'both'], true) && (bool) $vehicle->ac_available;
        if (in_array($acPref, ['non-ac', 'non ac'], true) && (bool) $vehicle->non_ac_available) {
            $useAc = false;
        }

        return $useAc;
    }

    public function isHillCountry(array $location): bool
    {
        $formatted = strtolower((string) ($location['formatted'] ?? ''));
        $keywords = [
            'nuwara eliya',
            'badulla',
            'bandarawela',
            'ella',
            'haputale',
            'kandy',
            'matale',
            'maskeliya',
            'hatton',
            'diyatalawa',
            'talawakele',
            'koslanda',
            'gampola',
        ];

        foreach ($keywords as $keyword) {
            if (str_contains($formatted, $keyword)) {
                return true;
            }
        }

        $lat = (float) ($location['lat'] ?? 0);
        $lng = (float) ($location['lng'] ?? 0);

        // Central highlands bounding box only
        return $lat >= 6.7 && $lat <= 7.4 && $lng >= 80.4 && $lng <= 81.2;
    }

    private function resolvePricePerKm(Vehicle $vehicle, bool $isAc, bool $isRoundTrip, bool $isHillCountry): float
    {
        $prices = $vehicle->per_km_prices ?? [];
        $group = $isAc ? ($prices['ac'] ?? []) : ($prices['nonAc'] ?? []);
        $trip = $isRoundTrip ? ($group['roundTrip'] ?? []) : ($group['oneWay'] ?? []);
        $field = $isHillCountry ? 'hill' : 'normal';
        $rate = (float) ($trip[$field] ?? 0);

        if ($rate > 0) {
            return $rate;
        }

        return $isAc
            ? ($isHillCountry ? (float) $vehicle->ac_hill_price_per_km : (float) $vehicle->ac_price_per_km)
            : ($isHillCountry ? (float) $vehicle->non_ac_hill_price_per_km : (float) $vehicle->non_ac_price_per_km);
    }

    private function packageCharge(Vehicle $vehicle, int $days, bool $isAc, bool $isHillCountry): float
    {
        $prices = $vehicle->package1_prices ?? [];
        $row = $prices['day' . max(1, $days)] ?? null;
        if (!$row) {
            return 0.0;
        }

        if (!$isAc) {
            return (float) ($row[$isHillCountry ? 'nonAcHill' : 'nonAcNormal'] ?? 0);
        }

        return (float) ($row[$isHillCountry ? 'acHill' : 'acNormal'] ?? 0);
    }
}
