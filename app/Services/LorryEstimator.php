<?php

namespace App\Services;

class LorryEstimator
{
    /**
     * Rate table row shape (windows-based):
     *   type                   – lorry type name (e.g., "7 FT")
     *   windows               – array of km range windows with rate, extraPerKm
     *   extraUpDownCharge     – flat round-trip surcharge
     *   waitingChargePerHour  – hourly waiting charge
     *
     * Each window:
     *   fromKm                – start km for this window
     *   toKm                  – end km (null = open-ended)
     *   rate                  – base rate for this window
     *   extraPerKm            – per-km charge beyond toKm
     *   hillExtraPerKm        – hill country surcharge per km
     */
    public function estimate(array $rate, float $distanceKm, string $trip, bool $isHillCountry, float $waitingHours = 0): array
    {
        $isRoundTrip = in_array(strtolower($trip), ['round-trip', 'round trip'], true);

        // Step 1: Find base fee and determine extra km
        $baseFee = 0.0;
        $extraKm = 0.0;
        $extraPerKm = 0.0;
        $hillExtraPerKm = 0.0;

        if (isset($rate['windows']) && is_array($rate['windows'])) {
            $windows = $rate['windows'];
            foreach ($windows as $window) {
                $fromKm = (float) ($window['fromKm'] ?? 0);
                $toKm = isset($window['toKm']) ? (float) $window['toKm'] : null;

                if ($distanceKm >= $fromKm) {
                    if ($toKm === null || $distanceKm <= $toKm) {
                        $baseFee = (float) ($window['rate'] ?? 0);
                        break;
                    } else {
                        $baseFee = (float) ($window['rate'] ?? 0);
                        $extraKm = $distanceKm - $toKm;
                        $extraPerKm = (float) ($window['extraPerKm'] ?? 0);
                        $hillExtraPerKm = (float) ($window['hillExtraPerKm'] ?? 0);
                    }
                }
            }
        }

        // Step 2: Calculate extra KM fee based on trip type and location
        // Formula:
        //   Round Trip + Hill: extraKm × upDownHill
        //   Round Trip + Non-Hill: extraKm × upDownNonHill
        //   One-Way + Hill: extraKm × (extraPerKm + hillExtraPerKm)
        //   One-Way + Non-Hill: extraKm × extraPerKm
        $extraFee = 0.0;
        if ($extraKm > 0) {
            if ($isRoundTrip) {
                if ($isHillCountry) {
                    $extraFee = round($extraKm * (float) ($rate['upDownHill'] ?? 0), 2);
                } else {
                    $extraFee = round($extraKm * (float) ($rate['upDownNonHill'] ?? 0), 2);
                }
            } else {
                if ($isHillCountry) {
                    $extraFee = round($extraKm * ($extraPerKm + $hillExtraPerKm), 2);
                } else {
                    $extraFee = round($extraKm * $extraPerKm, 2);
                }
            }
        }

        // Step 3: Calculate waiting charge (informational, NOT added to total)
        $freeWaitingHours = (float) ($rate['freeWaitingHours'] ?? 0);
        $waitingChargePerHour = (float) ($rate['waitingChargePerHour'] ?? 0);
        $chargeableWaitingHours = 0.0;
        $waitingCharge = 0.0;

        if ($waitingHours > $freeWaitingHours && $waitingChargePerHour > 0) {
            $chargeableWaitingHours = $waitingHours - $freeWaitingHours;
            $waitingCharge = round($chargeableWaitingHours * $waitingChargePerHour, 2);
        }

        // Total = Base Fee + Extra Fee (waiting charge NOT included)
        $totalFare = round($baseFee + $extraFee, 2);

        return [
            'trip'                      => $isRoundTrip ? 'round-trip' : 'one-way',
            'distance_km'               => round($distanceKm, 2),
            'is_hill_country'           => $isHillCountry,
            'base_fee'                  => round($baseFee, 2),
            'extra_km'                  => round($extraKm, 2),
            'extra_fee'                 => $extraFee,
            'waiting_hours'             => round($waitingHours, 2),
            'free_waiting_hours'        => $freeWaitingHours,
            'chargeable_waiting_hours'  => round($chargeableWaitingHours, 2),
            'waiting_charge'            => $waitingCharge,
            'total_cost'                => $totalFare,
        ];
    }
}
