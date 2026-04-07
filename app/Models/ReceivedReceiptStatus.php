<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReceivedReceiptStatus extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    public function transactions()
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }
}
