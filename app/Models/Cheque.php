<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cheque extends Model
{
  use HasFactory;

  protected $table = "cheques";

  protected $fillable = [
    "transaction_id",
    "treasury_id",
    "bank_id",
    "bank_name",
    "cheque_no",
    "cheque_date",
    "cheque_amount",
    "transaction_type",
    "entry_type",
      "is_cleared",
      'date_cleared'
  ];

  public function transaction(){
    return $this->belongsTo(Transaction::class,'transaction_id','id')->select([
        "id",
        "tag_no",
        "transaction_id",
        "receipt_type",
        "payment_type",
        "document_id",
        "document_type",
        "document_no",
        "document_amount",
        "referrence_no",
        "referrence_amount",
        "date_requested",
        "company_id",
        "company",
        "department_id",
        "department",
        "location_id",
        "location",
        "supplier_id",
        "supplier",
        "voucher_no",
        "voucher_month",
        "remarks",
        "status",
        "state"
    ]);
  }
}
