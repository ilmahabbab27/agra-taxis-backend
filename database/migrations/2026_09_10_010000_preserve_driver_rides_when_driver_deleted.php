<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up()
    {
        DB::statement('ALTER TABLE driver_rides DROP FOREIGN KEY driver_rides_driver_id_foreign');
        DB::statement('ALTER TABLE driver_rides MODIFY driver_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE driver_rides ADD CONSTRAINT driver_rides_driver_id_foreign FOREIGN KEY (driver_id) REFERENCES drivers(id) ON DELETE SET NULL');
    }

    public function down()
    {
        DB::statement('ALTER TABLE driver_rides DROP FOREIGN KEY driver_rides_driver_id_foreign');
        DB::statement('ALTER TABLE driver_rides MODIFY driver_id BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE driver_rides ADD CONSTRAINT driver_rides_driver_id_foreign FOREIGN KEY (driver_id) REFERENCES drivers(id) ON DELETE CASCADE');
    }
};
