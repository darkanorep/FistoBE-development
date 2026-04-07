<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateReceivedReceiptStatusesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('received_receipt_statuses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('transaction_id')->nullable();
            $table->string('rr_id')->nullable();
            $table->string('rr_number')->nullable();
            $table->string('tag_no')->nullable();
            $table->date('voucher_month')->nullable();
            $table->string('voucher_no')->nullable();
            $table->string('status')->nullable();
            $table->string('company')->nullable();
            $table->string('business_unit')->nullable();
            $table->string('department')->nullable();
            $table->string('unit')->nullable();
            $table->string('sub_unit')->nullable();
            $table->string('location')->nullable();
            $table->timestamps();
            $table->timestamp('tagged_at')->nullable();
            $table->timestamp('vouchered_at')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->timestamp('synced_at')->nullable();
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
        Schema::dropIfExists('received_receipt_statuses');
    }
}
