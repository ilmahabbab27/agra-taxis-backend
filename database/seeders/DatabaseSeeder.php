<?php

namespace Database\Seeders;

use App\Models\Lorry;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        User::updateOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@agrataxis.lk')],
            [
                'name' => 'Agra Taxis Admin',
                'password' => Hash::make(env('ADMIN_PASSWORD', 'agra2026')),
            ]
        );

        Lorry::updateOrCreate(
            ['name' => 'Agra Lorry'],
            [
                'category' => 'Lorries',
                'img' => '/assets/car.jpg',
                'img2' => null,
                'img3' => null,
                'img4' => null,
                'img5' => null,
                'rate_table' => [
                    '7ft' => [
                        'type' => '7 FT',
                        'start' => 2750,
                        'extra' => 160,
                        'upDown' => 120,
                        'waiting' => 600,
                        'waitingHour' => 600,
                        'between100And130' => 2500,
                        'notes' => [
                            'u_d' => 'Up & Down trips must not exceed 150 km.',
                            'drop_100_130' => 'Drop trips between 100-130 km include the starting fee.',
                            'base_10km' => 'Initial 10 km is the standard starting fee.',
                            'terrain' => 'Add Rs. 10 per km for mountainous terrain.',
                            'long_distance' => 'Starting fees do not apply above 130 km.',
                        ],
                    ],
                    '8_5ft' => [
                        'type' => '8.5 FT',
                        'start' => 3750,
                        'extra' => 180,
                        'upDown' => 130,
                        'waiting' => 700,
                        'waitingHour' => 700,
                        'between100And130' => 2000,
                    ],
                    '10_5ft' => [
                        'type' => '10.5 FT',
                        'start' => 6250,
                        'extra' => 230,
                        'upDown' => 170,
                        'waiting' => 800,
                        'waitingHour' => 800,
                        'between100And130' => 4500,
                    ],
                    '12_5ft' => [
                        'type' => '12.5 FT',
                        'start' => 7750,
                        'extra' => 250,
                        'upDown' => 180,
                        'waiting' => 800,
                        'waitingHour' => 800,
                        'between100And130' => 4500,
                    ],
                    '14_5ft' => [
                        'type' => '14.5 FT',
                        'start' => 10250,
                        'extra' => 320,
                        'upDown' => 210,
                        'waiting' => 1000,
                        'waitingHour' => 1000,
                        'between100And130' => 7000,
                    ],
                    '16_5ft' => [
                        'type' => '16.5 FT',
                        'start' => 11250,
                        'extra' => 330,
                        'upDown' => 220,
                        'waiting' => 1000,
                        'waitingHour' => 1000,
                        'between100And130' => 8000,
                    ],
                    '18_5ft' => [
                        'type' => '18.5 FT',
                        'start' => 15250,
                        'extra' => 380,
                        'upDown' => 270,
                        'waiting' => 1200,
                        'waitingHour' => 1200,
                        'between100And130' => 9000,
                    ],
                    '20ft' => [
                        'type' => '20 FT',
                        'start' => 18250,
                        'extra' => 450,
                        'upDown' => 300,
                        'waiting' => 1500,
                        'waitingHour' => 1500,
                        'between100And130' => 11000,
                    ],
                ],
            ]
        );
    }
}
