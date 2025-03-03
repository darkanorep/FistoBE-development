<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class JobOrder extends Model
{
    use HasFactory, softDeletes;

    protected $fillable = [
        'received_receipt_id',
        'jo_number',
        'jo_amount',
        'consumed_amount',
        'remaining_amount',
        'jo_description',
        'type_name',
        'company_id',
        'company_code',
        'company_name',
        'business_unit_id',
        'business_unit_code',
        'business_unit_name',
        'department_id',
        'department_code',
        'department_name',
        'unit_id',
        'unit_code',
        'unit_name',
        'sub_unit_id',
        'sub_unit_code',
        'sub_unit_name',
        'location_id',
        'location_code',
        'location_name',
        'account_title_id',
        'account_title_code',
        'account_title_name'
    ];
}
