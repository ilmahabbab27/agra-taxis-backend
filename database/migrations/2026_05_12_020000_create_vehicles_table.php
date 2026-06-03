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

    }

    public function down()
    {
        Schema::dropIfExists('vehicles');
        Schema::dropIfExists('vehicle_categories');
    }
}
