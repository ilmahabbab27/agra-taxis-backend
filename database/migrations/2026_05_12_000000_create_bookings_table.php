<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBookingsTable extends Migration
{
    public function up()
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('vehicle');
            $table->string('pickup');
            $table->string('destination');
            $table->date('travel_date');
            $table->unsignedSmallInteger('days')->default(1);
            $table->string('trip')->default('One Way');
            $table->unsignedSmallInteger('passengers')->default(1);
            $table->string('ac')->default('AC');
            $table->string('status')->default('new');
            $table->string('customer_name')->nullable();
            $table->string('customer_phone')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('bookings');
    }
}
