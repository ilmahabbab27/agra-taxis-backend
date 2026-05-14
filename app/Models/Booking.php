<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    use HasFactory;

    public const STATUSES = ['new', 'contacted', 'confirmed', 'cancelled'];

    protected $fillable = [
        'vehicle',
        'pickup',
        'pickup_lat',
        'pickup_lng',
        'destination',
        'destination_lat',
        'destination_lng',
        'stops',
        'travel_date',
        'days',
        'trip',
        'passengers',
        'ac',
        'distance_km',
        'distance_source',
        'map_url',
        'price_per_km',
        'driving_cost',
        'stay_cost',
        'total_cost',
        'status',
        'customer_name',
        'customer_phone',
        'notes',
    ];

    protected $casts = [
        'travel_date'    => 'date:Y-m-d',
        'days'           => 'integer',
        'passengers'     => 'integer',
        'pickup_lat'     => 'decimal:7',
        'pickup_lng'     => 'decimal:7',
        'destination_lat'=> 'decimal:7',
        'destination_lng'=> 'decimal:7',
        'distance_km'    => 'decimal:2',
        'stops'          => 'array',
        'price_per_km'   => 'decimal:2',
        'driving_cost'   => 'decimal:2',
        'stay_cost'      => 'decimal:2',
        'total_cost'     => 'decimal:2',
    ];
}
