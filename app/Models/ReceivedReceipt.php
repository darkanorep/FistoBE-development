<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class            ReceivedReceipt extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrders::class, 'received_receipt_id', 'id');
    }

    public function jobOrders() {
        return $this->hasMany(JobOrder::class, 'received_receipt_id', 'id');
    }

}
