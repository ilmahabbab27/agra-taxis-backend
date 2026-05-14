<?php

namespace Database\Seeders;

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
    }
}
