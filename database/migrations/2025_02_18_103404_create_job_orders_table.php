<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateJobOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('job_orders', function (Blueprint $table) {
            $table->increments('id');
            $table->foreignId('received_receipt_id')->references('id')->on('received_receipts');
            $table->string('jo_number')->nullable();
            $table->double('jo_amount')->nullable();
            $table->double('consumed_amount')->nullable();
            $table->double('remaining_amount')->nullable();
            $table->string('jo_description')->nullable();
            $table->string('type_name')->nullable();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('company_code')->nullable();
            $table->string('company_name')->nullable();
            $table->unsignedBigInteger('business_unit_id')->nullable();
            $table->string('business_unit_code')->nullable();
            $table->string('business_unit_name')->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->string('department_code')->nullable();
            $table->string('department_name')->nullable();
            $table->unsignedBigInteger('unit_id')->nullable();
            $table->string('unit_code')->nullable();
            $table->string('unit_name')->nullable();
            $table->unsignedBigInteger('sub_unit_id')->nullable();
            $table->string('sub_unit_code')->nullable();
            $table->string('sub_unit_name')->nullable();
            $table->unsignedBigInteger('location_id')->nullable();
            $table->string('location_code')->nullable();
            $table->string('location_name')->nullable();
            $table->unsignedBigInteger('account_title_id')->nullable();
            $table->string('account_title_code')->nullable();
            $table->string('account_title_name')->nullable();
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
        Schema::dropIfExists('job_orders');
    }
}
