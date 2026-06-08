<?php

namespace App\Services;

class LorryEstimator
{
    /**
     * Rate table row shape:
     *   start            – base fare (covers up to dropMaxKm for one-way drop)
     *   extra            – per-km charge beyond dropMaxKm
     *   between100And130 – flat fare when distance is between 100–130 km
     *   upDown           – flat round-trip (up-and-down) fare (up to maxUpDownKm)
     *   waiting          – flat waiting charge (per occurrence / half-day)
     *   waitingHour      – per-hour waiting charge
     *   hillExtraPerKm   – surcharge per km for hill-country routes
     *   dropMinKm        – minimum km threshold for the base drop fare
     *   dropMaxKm        – km ceiling covered by the base start fare
     *   maxUpDownKm      – km ceiling covered by the flat upDown fare
     */
    public function estimate(array $rate, float $distanceKm, string $trip, bool $isHillCountry, float $waitingHours = 0): array
    {
        $isRoundTrip   = in_array(strtolower($trip), ['round-trip', 'round trip'], true);
        $dropMinKm     = (float) ($rate['dropMinKm']     ?? 0);
        $dropMaxKm     = (float) ($rate['dropMaxKm']     ?? 130);
        $maxUpDownKm   = (float) ($rate['maxUpDownKm']   ?? 150);
        $start         = (float) ($rate['start']         ?? 0);
        $extra         = (float) ($rate['extra']         ?? 0);
        $upDown        = (float) ($rate['upDown']        ?? 0);
        $between       = (float) ($rate['between100And130'] ?? 0);
        $waitingFlat   = (float) ($rate['waiting']       ?? 0);
        $waitingHourly = (float) ($rate['waitingHour']   ?? 0);
        $hillExtra     = (float) ($rate['hillExtraPerKm'] ?? 0);

        // ── Base fare ─────────────────────────────────────────────────────────
        $startCharge = 0.0;
        $extraKm     = 0.0;
        $extraCharge = 0.0;

        if ($isRoundTrip) {
            if ($distanceKm <= $maxUpDownKm && $upDown > 0) {
                $startCharge = $upDown;
            } else {
                $startCharge = $upDown ?: $start;
                if ($distanceKm > $maxUpDownKm && $extra > 0) {
                    $extraKm     = round($distanceKm - $maxUpDownKm, 2);
                    $extraCharge = round($extraKm * $extra, 2);
                }
            }
        } else {
            if ($between > 0 && $distanceKm >= 100 && $distanceKm <= 130) {
                $startCharge = $between;
            } elseif ($distanceKm <= max($dropMaxKm, $dropMinKm)) {
                $startCharge = $start;
            } else {
                $startCharge = $start;
                if ($extra > 0) {
                    $extraKm     = round($distanceKm - $dropMaxKm, 2);
                    $extraCharge = round($extraKm * $extra, 2);
                }
            }
        }

        $baseFare = $startCharge + $extraCharge;

        // ── Hill-country surcharge ────────────────────────────────────────────
        $hillCharge = $isHillCountry && $hillExtra > 0 ? round($distanceKm * $hillExtra, 2) : 0.0;

        // ── Waiting charge ────────────────────────────────────────────────────
        $waitingCharge = 0.0;
        if ($waitingHours > 0) {
            $waitingCharge = $waitingHourly > 0
                ? round($waitingHours * $waitingHourly, 2)
                : $waitingFlat;
        }

        $totalFare = round($startCharge + $extraCharge + $hillCharge + $waitingCharge, 2);

        return [
            'trip'            => $isRoundTrip ? 'round-trip' : 'one-way',
            'distance_km'     => round($distanceKm, 2),
            'is_hill_country' => $isHillCountry,
            'start_charge'    => round($startCharge, 2),
            'extra_km'        => $extraKm,
            'extra_charge'    => $extraCharge,
            'base_fare'       => round($baseFare, 2),
            'hill_charge'     => $hillCharge,
            'waiting_charge'  => round($waitingCharge, 2),
            'total_cost'      => $totalFare,
            'rate_applied'    => [
                'start'            => $start,
                'extra'            => $extra,
                'upDown'           => $upDown,
                'between100And130' => $between,
                'hillExtraPerKm'   => $hillExtra,
                'dropMinKm'        => $dropMinKm,
                'dropMaxKm'        => $dropMaxKm,
                'maxUpDownKm'      => $maxUpDownKm,
            ],
        ];
    }
}
