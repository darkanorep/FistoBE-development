<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDocumentCoasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('document_coa', function (Blueprint $table) {
            $table->id();
            $table->string("entry")->nullable();
            $table->foreignId('document_id')->constrained('documents')->nullable();
            $table->foreignId('company_id')->constrained('companies')->nullable();
            $table->foreignId('business_unit_id')->constrained('business_units')->nullable();
            $table->foreignId('department_id')->constrained('departments')->nullable();
            $table->foreignId('sub_unit_id')->constrained('sub_units')->nullable();
            $table->foreignId('location_id')->constrained('locations')->nullable();
            $table->foreignId('account_title_id')->constrained('account_titles')->nullable();
            $table->softDeletes();
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
        Schema::dropIfExists('document_coa');
    }
}
