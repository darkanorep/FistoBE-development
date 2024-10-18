<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_id',
        'company_name',
        'business_unit_name',
        'department_name',
        'sub_unit_name',
        'location_name',
        'amount'
    ];

    protected $hidden = [
        'created_at',
        'updated_at'
    ];

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }
}
