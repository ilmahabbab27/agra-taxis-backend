<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lorries', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('category')->default('Lorries');
            $table->string('img')->nullable();
            $table->string('img2')->nullable();
            $table->string('img3')->nullable();
            $table->string('img4')->nullable();
            $table->string('img5')->nullable();
            $table->unsignedTinyInteger('seats')->default(1);
            $table->boolean('ac_available')->default(false);
            $table->boolean('non_ac_available')->default(false);
            $table->json('rate_table')->nullable()->comment('Stores lorry rate windows, upDown charges, and waiting charges as JSON');
            $table->timestamps();
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lorries');
    }
};
