<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateYearEndJournalsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('year_end_journals', function (Blueprint $table) {
            $table->id();
            $table->date('adjustment_month')->nullable();
            $table->foreignId('division_id')->nullable()->references('id')->on('companies');
            $table->string('division_name')->nullable();
            $table->string('tag_no')->nullable();
            $table->date('transaction_date')->nullable();
            $table->foreignId('supplier_id')->nullable()->references('id')->on('suppliers');
            $table->string('supplier_code')->nullable();
            $table->string('supplier_name')->nullable();
            $table->string('entry')->nullable();
            $table->foreignId('account_title_id')->nullable()->references('id')->on('account_titles');
            $table->string('account_title_code')->nullable();
            $table->string('account_title_name')->nullable();
            $table->foreignId('company_id')->nullable()->references('id')->on('companies');
            $table->string('company_code')->nullable();
            $table->string('company_name')->nullable();
            $table->foreignId('department_id')->nullable()->references('id')->on('departments');
            $table->string('department_code')->nullable();
            $table->string('department_name')->nullable();
            $table->foreignId('location_id')->nullable()->references('id')->on('locations');
            $table->string('location_code')->nullable();
            $table->string('location_name')->nullable();
            $table->foreignId('business_unit_id')->nullable()->references('id')->on('business_units');
            $table->string('business_unit_code')->nullable();
            $table->string('business_unit_name')->nullable();
            $table->foreignId('unit_id')->nullable()->references('id')->on('units');
            $table->string('unit_code')->nullable();
            $table->string('unit_name')->nullable();
            $table->foreignId('sub_unit_id')->nullable()->references('id')->on('sub_units');
            $table->string('sub_unit_code')->nullable();
            $table->string('sub_unit_name')->nullable();
            $table->double('amount', 15, 2)->nullable();
            $table->string('description')->nullable();
            $table->string('po_no')->nullable();
            $table->string('rr_no')->nullable();
            $table->string('reference_no')->nullable();
            $table->double('quantity')->nullable();
            $table->text('remaining_bv_for_depr')->nullable();
            $table->text('system_name')->nullable();
            $table->string('released_date')->nullable();
            $table->string('cheque_date')->nullable();
            $table->string('change_to')->nullable();
            $table->text('payroll_type')->nullable();
            $table->string('cheque_no')->nullable();
            $table->string('bank_name')->nullable();
            $table->text('checking_remarks')->nullable();
            $table->text('reason_remarks')->nullable();
            $table->string('changed_to')->nullable();
            $table->string('from')->nullable();
            $table->text('jean_remarks')->nullable();
            $table->string('farm_type')->nullable();
            $table->string('particulars')->nullable();
            $table->string('useful_life')->nullable();
            $table->text('additional_description_for_depr')->nullable();
            $table->string('payroll_type2')->nullable();
            $table->string('payroll_type1')->nullable();
            $table->string('position')->nullable();
            $table->string('payroll_period')->nullable();
            $table->string('asset_cip')->nullable();
            $table->text('mark2')->nullable();
            $table->text('mark')->nullable();
            $table->string('batch')->nullable();
            $table->string('unit_responsible')->nullable();
            $table->string('financial_statement')->nullable();
            $table->string('account_sub_group')->nullable();
            $table->string('account_group')->nullable();
            $table->string('allocation')->nullable();
            $table->string('account_type')->nullable();
            $table->string('item_code')->nullable();
            $table->string('uom')->nullable();
            $table->string('unit')->nullable();
            $table->double('unit_price', 15, 2)->nullable();
            $table->string('voucher_number')->nullable();
            $table->string('asset_code')->nullable();
            $table->string('asset_name')->nullable();
            $table->string('service_provider_code')->nullable();
            $table->string('service_provider_name')->nullable();
            $table->string('remarks')->nullable();
            $table->string('boa')->nullable();
            $table->foreignId('user_id')->nullable()->references('id')->on('users');
            $table->foreignId('approver_id')->nullable()->references('id')->on('users');
            $table->boolean('is_approved')->nullable();
            $table->string('journal_name')->nullable();
            $table->string('journal_description')->nullable();
            $table->string('gj_number')->nullable();
            $table->string('batch_no')->nullable();
            $table->boolean('is_posted')->default(0);
            $table->date('posted_at')->nullable();
            $table->boolean('is_year_end')->default(0);
            $table->string('reason_id')->nullable();
            $table->string('reason')->nullable();
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
        Schema::dropIfExists('year_end_journals');
    }
}
