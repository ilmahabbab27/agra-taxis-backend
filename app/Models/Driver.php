<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Driver extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $fillable = [
        'full_name', 'email', 'phone', 'username', 'password', 'province', 'district', 'location',
        'vehicle_category', 'vehicle_name', 'vehicle_registration_number', 'vehicle_colour',
        'seat_capacity', 'air_conditioning', 'vehicle_photos', 'driver_document', 'insurance_document', 'status',
    ];

    protected $hidden = ['password', 'remember_token'];
    protected $casts = ['vehicle_photos' => 'array'];
}
