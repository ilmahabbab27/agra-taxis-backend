<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'category',
        'img',
        'img2',
        'seats',
        'ac_price_per_km',
        'non_ac_price_per_km',
        'ac_hill_price_per_km',
        'non_ac_hill_price_per_km',
        'per_km_prices',
        'ac_available',
        'non_ac_available',
        'package1_prices',
        'package2_prices',
        'stay_price_day1',
        'stay_price_day2',
        'stay_price_day3',
        'stay_price_day4',
        'stay_price_day5',
    ];

    protected $casts = [
        'seats' => 'integer',
        'ac_price_per_km' => 'decimal:2',
        'non_ac_price_per_km' => 'decimal:2',
        'ac_hill_price_per_km' => 'decimal:2',
        'non_ac_hill_price_per_km' => 'decimal:2',
        'per_km_prices' => 'array',
        'ac_available' => 'boolean',
        'non_ac_available' => 'boolean',
        'package1_prices' => 'array',
        'package2_prices' => 'array',
        'stay_price_day1' => 'decimal:2',
        'stay_price_day2' => 'decimal:2',
        'stay_price_day3' => 'decimal:2',
        'stay_price_day4' => 'decimal:2',
        'stay_price_day5' => 'decimal:2',
    ];
}
