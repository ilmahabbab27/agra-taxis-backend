<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriverRide extends Model
{
    protected $fillable = ['driver_id', 'ride_date', 'customer_name', 'customer_phone', 'pickup', 'destination', 'trip_type', 'distance_km', 'ride_amount', 'driver_payment', 'other_charges', 'total_amount', 'payment_status', 'payment_method', 'notes', 'payment_proof'];
    protected $casts = ['ride_date' => 'date:Y-m-d', 'distance_km' => 'decimal:2', 'ride_amount' => 'decimal:2', 'driver_payment' => 'decimal:2', 'other_charges' => 'decimal:2', 'total_amount' => 'decimal:2'];
}
