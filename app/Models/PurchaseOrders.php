<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrders extends Model
{
    use HasFactory;

    protected $casts = [
        'transaction_ids' => 'array',
        'rr_no' => 'array',
    ];

    protected $fillable = [
        'transaction_ids',
        'po_no',
        'total_amount',
        'payment_type',
        'company_id',
        'rr_no',
    ];

}
