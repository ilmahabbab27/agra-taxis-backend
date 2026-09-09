<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('drivers', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('email')->unique();
            $table->string('phone');
            $table->string('username')->unique();
            $table->string('password');
            $table->string('province');
            $table->string('district');
            $table->string('location');
            $table->string('vehicle_category');
            $table->string('vehicle_name');
            $table->string('vehicle_registration_number');
            $table->string('vehicle_colour');
            $table->unsignedSmallInteger('seat_capacity');
            $table->enum('air_conditioning', ['ac', 'non_ac']);
            $table->json('vehicle_photos')->nullable();
            $table->string('driver_document');
            $table->string('insurance_document');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('drivers');
    }
};
