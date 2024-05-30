<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePurchaseOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->json('transaction_ids')->nullable();
            $table->string('po_no')->nullable();
            $table->double('total_amount', 15, 2)->nullable();
            $table->string('payment_type')->nullable();
            $table->foreignId('company_id')->constrained('companies')->nullable();
            $table->json('rr_no')->nullable();
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
        Schema::dropIfExists('purchase_orders');
    }
}
