<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCostAndStopsToBookingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->json('stops')->nullable()->after('destination');
            $table->decimal('price_per_km', 10, 2)->nullable()->after('distance_km');
            $table->decimal('driving_cost', 10, 2)->nullable()->after('price_per_km');
            $table->decimal('stay_cost', 10, 2)->nullable()->after('driving_cost');
            $table->decimal('total_cost', 10, 2)->nullable()->after('stay_cost');
        });
    }

    public function down()
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['stops', 'price_per_km', 'driving_cost', 'stay_cost', 'total_cost']);
        });
    }
}
