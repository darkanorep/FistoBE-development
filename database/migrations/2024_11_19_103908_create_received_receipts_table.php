<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateReceivedReceiptsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('received_receipts', function (Blueprint $table) {
            $table->increments('id');
            $table->foreignId('purchase_order_id');
            $table->string('rr_number')->nullable();
            $table->string('item_code')->nullable();
            $table->string('item_name')->nullable();
            $table->double('price')->nullable();
            $table->double('quantity')->nullable();
            $table->string('uom_code')->nullable();
            $table->string('uom_name')->nullable();
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
        Schema::dropIfExists('received_receipts');
    }
}
