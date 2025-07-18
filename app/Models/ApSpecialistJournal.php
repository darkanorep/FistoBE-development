<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia\HasMediaTrait;

class ApSpecialistJournal extends Model
{
    use HasFactory, SoftDeletes, HasMediaTrait;

    protected $guarded = [];
//    protected $fillable = [
//        'adjustment_month',
//        'division_id',
//        'division_name',
//        'tag_no',
//        'transaction_date',
//        'supplier_id',
//        'supplier_code',
//        'supplier_name',
//        'entry',
//        'account_title_id',
//        'account_title_code',
//        'account_title_name',
//        'company_id',
//        'company_code',
//        'company_name',
//        'department_id',
//        'department_code',
//        'department_name',
//        'location_id',
//        'location_code',
//        'location_name',
//        'business_unit_id',
//        'business_unit_code',
//        'business_unit_name',
//        'unit_id',
//        'unit_code',
//        'unit_name',
//        'sub_unit_id',
//        'sub_unit_code',
//        'sub_unit_name',
//        'amount',
//        'description',
//        'po_no',
//        'rr_no',
//        'reference_no',
//        'quantity',
//        'unit',
//        'unit_price',
//        'voucher_number',
//        'asset_code',
//        'asset_name',
//        'service_provider_code',
//        'service_provider_name',
//        'remarks',
//        'boa',
//        'user_id',
//        'approver_id',
//        'is_approved',
//        'journal_name',
//        'journal_description',
//        'gj_number',
//        'batch_no',
//        'is_posted',
//        'posted_at',
//        'is_year_end',
//        'reason_id',
//        'reason',
//        'uom',
//        'item_code',
//        'batch'
//    ];

    public function journals()
    {
        return $this->morphMany(Journal::class, 'journable');
    }
}
