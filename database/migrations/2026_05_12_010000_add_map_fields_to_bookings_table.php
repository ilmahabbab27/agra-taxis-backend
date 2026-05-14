<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMapFieldsToBookingsTable extends Migration
{
    public function up()
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->decimal('pickup_lat', 10, 7)->nullable()->after('pickup');
            $table->decimal('pickup_lng', 10, 7)->nullable()->after('pickup_lat');
            $table->decimal('destination_lat', 10, 7)->nullable()->after('destination');
            $table->decimal('destination_lng', 10, 7)->nullable()->after('destination_lat');
            $table->decimal('distance_km', 8, 2)->nullable()->after('ac');
            $table->string('distance_source')->nullable()->after('distance_km');
            $table->text('map_url')->nullable()->after('distance_source');
        });
    }

    public function down()
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn([
                'pickup_lat',
                'pickup_lng',
                'destination_lat',
                'destination_lng',
                'distance_km',
                'distance_source',
                'map_url',
            ]);
        });
    }
}
