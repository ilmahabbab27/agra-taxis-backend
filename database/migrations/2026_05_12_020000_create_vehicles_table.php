<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateVehiclesTable extends Migration
{
    public function up()
    {
        Schema::create('vehicle_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        DB::table('vehicle_categories')->insert([
            ['name' => 'Cars', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Vans', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'SUVs', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Luxury', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Mini Buses', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Buses', 'created_at' => now(), 'updated_at' => now()],
        ]);

        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('category');
            $table->longText('img')->nullable();
            $table->unsignedInteger('seats')->default(1);
            $table->decimal('ac_price_per_km', 10, 2)->default(0);
            $table->decimal('non_ac_price_per_km', 10, 2)->default(0);
            $table->boolean('ac_available')->default(true);
            $table->boolean('non_ac_available')->default(false);
            $table->timestamps();
        });

        DB::table('vehicles')->insert([
            [
                'name' => 'Toyota Axio / Premio',
                'category' => 'Cars',
                'img' => '/assets/car.jpg',
                'seats' => 4,
                'ac_price_per_km' => 180,
                'non_ac_price_per_km' => 150,
                'ac_available' => true,
                'non_ac_available' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Toyota KDH Van',
                'category' => 'Vans',
                'img' => '/assets/van.jpg',
                'seats' => 9,
                'ac_price_per_km' => 240,
                'non_ac_price_per_km' => 210,
                'ac_available' => true,
                'non_ac_available' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Toyota Land Cruiser',
                'category' => 'SUVs',
                'img' => '/assets/suv.jpg',
                'seats' => 6,
                'ac_price_per_km' => 320,
                'non_ac_price_per_km' => 0,
                'ac_available' => true,
                'non_ac_available' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Mercedes-Benz E-Class',
                'category' => 'Luxury',
                'img' => '/assets/luxury.jpg',
                'seats' => 4,
                'ac_price_per_km' => 420,
                'non_ac_price_per_km' => 0,
                'ac_available' => true,
                'non_ac_available' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Coaster Mini Bus',
                'category' => 'Mini Buses',
                'img' => '/assets/minibus.jpg',
                'seats' => 22,
                'ac_price_per_km' => 360,
                'non_ac_price_per_km' => 320,
                'ac_available' => true,
                'non_ac_available' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Tourist Coach',
                'category' => 'Buses',
                'img' => '/assets/bus.jpg',
                'seats' => 45,
                'ac_price_per_km' => 520,
                'non_ac_price_per_km' => 460,
                'ac_available' => true,
                'non_ac_available' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down()
    {
        Schema::dropIfExists('vehicles');
        Schema::dropIfExists('vehicle_categories');
    }
}
