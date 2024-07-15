<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGeneralJournalsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('general_journals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->nullable()->constrained('transactions');
            $table->string('voucher_no')->nullable();
            $table->string('entry')->nullable();
            $table->double('amount', 16, 2)->nullable();
            $table->foreignId('account_title_id')->nullable()->constrained('account_titles');
            $table->string('account_title_code')->nullable();
            $table->string('account_title_name')->nullable();
            $table->string('remarks')->nullable();
            $table->string('gj_number')->nullable();
            $table->string('type')->nullable();
            $table->foreignId('company_id')->nullable()->constrained('companies');
            $table->string('company_code')->nullable();
            $table->string('company_name')->nullable();
            $table->foreignId('department_id')->nullable()->constrained('departments');
            $table->string('department_code')->nullable();
            $table->string('department_name')->nullable();
            $table->foreignId('location_id')->nullable()->constrained('locations');
            $table->string('location_code')->nullable();
            $table->string('location_name')->nullable();
            $table->foreignId('business_unit_id')->nullable()->constrained('business_units');
            $table->string('business_unit_code')->nullable();
            $table->string('business_unit_name')->nullable();
            $table->foreignId('sub_unit_id')->nullable()->constrained('sub_units');
            $table->string('sub_unit_code')->nullable();
            $table->string('sub_unit_name')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->boolean('is_reversed')->nullable();
            $table->dateTime('voucher_month')->nullable();
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
        Schema::dropIfExists('general_journals');
    }
}
