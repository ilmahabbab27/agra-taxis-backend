<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->string('img2')->nullable()->after('img');
            $table->string('img3')->nullable()->after('img2');
            $table->string('img4')->nullable()->after('img3');
            $table->string('img5')->nullable()->after('img4');
            $table->decimal('ac_hill_price_per_km', 10, 2)->default(0)->after('ac_price_per_km');
            $table->decimal('non_ac_hill_price_per_km', 10, 2)->default(0)->after('non_ac_price_per_km');
            $table->json('per_km_prices')->nullable()->after('non_ac_hill_price_per_km');
            $table->json('package1_prices')->nullable()->after('non_ac_available');
            $table->json('package2_prices')->nullable()->after('package1_prices');
            $table->json('lorry_rates')->nullable()->after('package2_prices');
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn([
                'img2',
                'img3',
                'img4',
                'img5',
                'ac_hill_price_per_km',
                'non_ac_hill_price_per_km',
                'per_km_prices',
                'package1_prices',
                'package2_prices',
                'lorry_rates',
            ]);
        });
    }
};
