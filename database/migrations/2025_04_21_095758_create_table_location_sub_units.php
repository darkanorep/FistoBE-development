<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTableLocationSubUnits extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('location_sub_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->references('sync_id')->on('locations');
            $table->foreignId('sub_unit_id')->references('sync_id')->on('sub_units');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('location_sub_units');
    }
}
