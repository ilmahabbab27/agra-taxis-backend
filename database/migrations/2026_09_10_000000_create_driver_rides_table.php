<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('driver_rides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained('drivers')->cascadeOnDelete();
            $table->date('ride_date');
            $table->string('customer_name');
            $table->string('customer_phone')->nullable();
            $table->string('pickup');
            $table->string('destination');
            $table->string('trip_type')->default('One Way');
            $table->decimal('distance_km', 10, 2)->nullable();
            $table->decimal('ride_amount', 12, 2)->default(0);
            $table->decimal('driver_payment', 12, 2)->default(0);
            $table->decimal('other_charges', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->enum('payment_status', ['paid', 'unpaid', 'partial'])->default('unpaid');
            $table->string('payment_method')->nullable();
            $table->text('notes')->nullable();
            $table->string('payment_proof')->nullable();
            $table->timestamps();
        });
    }

    public function down() { Schema::dropIfExists('driver_rides'); }
};
