<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class GeneralJournal extends Model
{
    use HasFactory, softDeletes;

    protected $fillable = [
        'transaction_id',
        'voucher_no',
        'entry',
        'amount',
        'account_title_id',
        'account_title_code',
        'account_title_name',
        'remarks',
        'gj_number',
        'type',
        'company_id',
        'company_code',
        'company_name',
        'department_id',
        'department_code',
        'department_name',
        'location_id',
        'location_code',
        'location_name',
        'business_unit_id',
        'business_unit_code',
        'business_unit_name',
        'sub_unit_id',
        'sub_unit_code',
        'sub_unit_name',
        'user_id',
        'voucher_month',
        'is_reversed'
    ];

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function account_titles()
    {
        return $this->hasMany(AccountTitle::class, 'id', 'account_title_id');
    }

}
