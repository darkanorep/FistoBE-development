<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrders extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'transaction_id',
        'po_number',
        'po_description'
    ];

    public function receivedReceipts()
    {
        return $this->hasMany(ReceivedReceipt::class, 'purchase_order_id', 'id');
    }
}
