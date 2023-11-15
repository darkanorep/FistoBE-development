<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class TransactionTypesCoa extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transaction_type_coa', function (Blueprint $table) {
            $table->id();
            $table->string('entry')->nullable();
            $table->foreignId('transaction_type_id')->nullable()->constrained('transaction_types');
            $table->foreignId('company_id')->nullable()->constrained('companies');
            $table->foreignId('business_unit_id')->nullable()->constrained('business_units');
            $table->foreignId('department_id')->nullable()->constrained('departments');
            $table->foreignId('sub_unit_id')->nullable()->constrained('sub_units');
            $table->foreignId('location_id')->nullable()->constrained('locations');
            $table->foreignId('account_title_id')->nullable()->constrained('account_titles');
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
        Schema::dropIfExists('transaction_type_coa');
    }
}
