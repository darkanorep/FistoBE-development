<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddNewColumns extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            //COMPANY
            $table->unsignedBigInteger('company_id')->nullable()->after('type_name');
            $table->string('company_code')->nullable()->after('company_id');
            $table->string('company_name')->nullable()->after('company_code');

            //BUSINESS UNIT
            $table->unsignedBigInteger('business_unit_id')->nullable()->after('company_name');
            $table->string('business_unit_code')->nullable()->after('business_unit_id');
            $table->string('business_unit_name')->nullable()->after('business_unit_code');

            //DEPARTMENT
            $table->unsignedBigInteger('department_id')->nullable()->after('business_unit_name');
            $table->string('department_code')->nullable()->after('department_id');
            $table->string('department_name')->nullable()->after('department_code');

            //UNIT
            $table->unsignedBigInteger('unit_id')->nullable()->after('department_name');
            $table->string('unit_code')->nullable()->after('unit_id');
            $table->string('unit_name')->nullable()->after('unit_code');

            //SUB UNIT
            $table->unsignedBigInteger('sub_unit_id')->nullable()->after('unit_name');
            $table->string('sub_unit_code')->nullable()->after('sub_unit_id');
            $table->string('sub_unit_name')->nullable()->after('sub_unit_code');

            //LOCATION
            $table->unsignedBigInteger('location_id')->nullable()->after('sub_unit_name');
            $table->string('location_code')->nullable()->after('location_id');
            $table->string('location_name')->nullable()->after('location_code');

            //ACCOUNT TITLE
            $table->unsignedBigInteger('account_title_id')->nullable()->after('location_name');
            $table->string('account_title_code')->nullable()->after('account_title_id');
            $table->string('account_title_name')->nullable()->after('account_title_code');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            //
        });
    }
}
