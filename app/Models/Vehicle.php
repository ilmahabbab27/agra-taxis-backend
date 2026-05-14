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
        'seats',
        'ac_price_per_km',
        'non_ac_price_per_km',
        'ac_available',
        'non_ac_available',
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
        'ac_available' => 'boolean',
        'non_ac_available' => 'boolean',
        'stay_price_day1' => 'decimal:2',
        'stay_price_day2' => 'decimal:2',
        'stay_price_day3' => 'decimal:2',
        'stay_price_day4' => 'decimal:2',
        'stay_price_day5' => 'decimal:2',
    ];
}
