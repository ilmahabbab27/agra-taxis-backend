<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lorry extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'category',
        'img',
        'img2',
        'img3',
        'img4',
        'img5',
        'seats',
        'ac_available',
        'non_ac_available',
        'rate_table',
    ];

    protected $casts = [
        'rate_table' => 'array',
    ];
}
