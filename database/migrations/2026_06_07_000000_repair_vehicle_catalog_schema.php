<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('vehicle_categories')) {
            Schema::create('vehicle_categories', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->timestamps();
            });
        }

        foreach (['Cars', 'Vans', 'SUVs', 'Luxury', 'Mini Buses', 'Buses', 'Lorries'] as $category) {
            DB::table('vehicle_categories')->updateOrInsert(
                ['name' => $category],
                ['updated_at' => now(), 'created_at' => now()]
            );
        }

        Schema::table('vehicles', function (Blueprint $table) {
            if (! Schema::hasColumn('vehicles', 'img2')) {
                $table->longText('img2')->nullable()->after('img');
            }
            if (! Schema::hasColumn('vehicles', 'img3')) {
                $table->longText('img3')->nullable()->after('img2');
            }
            if (! Schema::hasColumn('vehicles', 'img4')) {
                $table->longText('img4')->nullable()->after('img3');
            }
            if (! Schema::hasColumn('vehicles', 'img5')) {
                $table->longText('img5')->nullable()->after('img4');
            }
            if (! Schema::hasColumn('vehicles', 'ac_hill_price_per_km')) {
                $table->decimal('ac_hill_price_per_km', 10, 2)->default(0)->after('ac_price_per_km');
            }
            if (! Schema::hasColumn('vehicles', 'non_ac_hill_price_per_km')) {
                $table->decimal('non_ac_hill_price_per_km', 10, 2)->default(0)->after('non_ac_price_per_km');
            }
            if (! Schema::hasColumn('vehicles', 'per_km_prices')) {
                $table->json('per_km_prices')->nullable()->after('non_ac_hill_price_per_km');
            }
            if (! Schema::hasColumn('vehicles', 'package1_prices')) {
                $table->json('package1_prices')->nullable()->after('non_ac_available');
            }
            if (! Schema::hasColumn('vehicles', 'package2_prices')) {
                $table->json('package2_prices')->nullable()->after('package1_prices');
            }
            if (! Schema::hasColumn('vehicles', 'lorry_rates')) {
                $table->json('lorry_rates')->nullable()->after('package2_prices');
            }
        });
    }

    public function down(): void
    {
        // Intentionally left empty. This is a production-safe repair migration.
    }
};
