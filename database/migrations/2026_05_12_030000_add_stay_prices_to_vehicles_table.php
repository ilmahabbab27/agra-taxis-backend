<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddStayPricesToVehiclesTable extends Migration
{
    public function up()
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->decimal('stay_price_day1', 10, 2)->default(0)->after('non_ac_available');
            $table->decimal('stay_price_day2', 10, 2)->default(0)->after('stay_price_day1');
            $table->decimal('stay_price_day3', 10, 2)->default(0)->after('stay_price_day2');
            $table->decimal('stay_price_day4', 10, 2)->default(0)->after('stay_price_day3');
            $table->decimal('stay_price_day5', 10, 2)->default(0)->after('stay_price_day4');
        });

        // Seed default stay prices for existing vehicles
        $defaults = [
            'Toyota Axio / Premio'  => [3500, 6500,  9000, 11500, 14000],
            'Toyota KDH Van'        => [5000, 9500,  13500, 17000, 20000],
            'Toyota Land Cruiser'   => [6500, 12000, 17000, 22000, 26000],
            'Mercedes-Benz E-Class' => [9000, 17000, 24000, 30000, 35000],
            'Coaster Mini Bus'      => [8000, 15000, 21000, 27000, 32000],
            'Tourist Coach'         => [12000, 22000, 31000, 39000, 46000],
        ];

        foreach ($defaults as $name => $prices) {
            DB::table('vehicles')->where('name', $name)->update([
                'stay_price_day1' => $prices[0],
                'stay_price_day2' => $prices[1],
                'stay_price_day3' => $prices[2],
                'stay_price_day4' => $prices[3],
                'stay_price_day5' => $prices[4],
            ]);
        }
    }

    public function down()
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn([
                'stay_price_day1',
                'stay_price_day2',
                'stay_price_day3',
                'stay_price_day4',
                'stay_price_day5',
            ]);
        });
    }
}
