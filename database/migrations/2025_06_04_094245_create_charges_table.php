<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateChargesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('charges', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('sync_id')->nullable();
            $table->string('code')->nullable();
            $table->string('name')->nullable();
            $table->bigInteger('company_id')->nullable();
            $table->string('company_code')->nullable();
            $table->string('company_name')->nullable();
            $table->bigInteger('business_unit_id')->nullable();
            $table->string('business_unit_code')->nullable();
            $table->string('business_unit_name')->nullable();
            $table->bigInteger('department_id')->nullable();
            $table->string('department_code')->nullable();
            $table->string('department_name')->nullable();
            $table->bigInteger('unit_id')->nullable();
            $table->string('unit_code')->nullable();
            $table->string('unit_name')->nullable();
            $table->bigInteger('sub_unit_id')->nullable();
            $table->string('sub_unit_code')->nullable();
            $table->string('sub_unit_name')->nullable();
            $table->bigInteger('location_id')->nullable();
            $table->string('location_code')->nullable();
            $table->string('location_name')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('charges');
    }
}
