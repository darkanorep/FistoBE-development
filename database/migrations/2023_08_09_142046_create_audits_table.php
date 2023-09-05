<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAuditsTable extends Migration
{
  /**
   * Run the migrations.
   *
   * @return void
   */
  public function up()
  {
    Schema::create("audits", function (Blueprint $table) {
      $table->id();
      $table->foreignId("transaction_id")->constrained("transactions");
      $table->string("type")->nullable();
      $table->timestamp("date_received")->nullable();
      $table->string("status");
      $table
        ->foreignId("reason_id")
        ->nullable()
        ->constrained("reasons");
      $table->string("remarks")->nullable();
      $table
        ->foreignId("user_id")
        ->nullable()
        ->constrained("users");
      $table->timestamp("date_audited")->nullable();
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
    Schema::dropIfExists("audits");
  }
}
