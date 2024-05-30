<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class POBalance extends Model
{
    use HasFactory;

    protected $casts = [
        'purchase_order_ids' => 'array',
    ];

    protected $fillable = [
        'purchase_order_ids',
        'balance',
    ];

}
