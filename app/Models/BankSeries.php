<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BankSeries extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'bank_id',
        'document_name',
        'category',
        'from',
        'to',
        'is_used'
//        'year'
    ];

    protected $hidden = [
        'created_at'
    ];

    public function bank() {
        return $this->belongsTo(Bank::class);
    }
}
