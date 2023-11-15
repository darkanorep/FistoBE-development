<?php

namespace App\Models;

use Awobaz\Compoships\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransactionTypeCoa extends Model
{
    use HasFactory;

    protected $table = 'transaction_type_coa';

    protected $hidden = ['created_at', 'transaction_type_id', 'company_id', 'business_unit_id', 'department_id', 'sub_unit_id', 'location_id', 'account_title_id'];

    protected $fillable = [
        'transaction_type_id',
        'entry',
        'document_id',
        'company_id',
        'business_unit_id',
        'department_id',
        'sub_unit_id',
        'location_id',
        'account_title_id',
    ];

    public function transaction_type()
    {
        return $this->belongsTo(TransactionType::class)->withTrashed();
    }

    public function company()
    {
        return $this->belongsTo(Company::class)->withTrashed();
    }

    public function business_unit()
    {
        return $this->belongsTo(BusinessUnit::class)->withTrashed();
    }

    public function department()
    {
        return $this->belongsTo(Department::class)->withTrashed();
    }

    public function sub_unit()
    {
        return $this->belongsTo(SubUnit::class)->withTrashed();
    }

    public function location()
    {
        return $this->belongsTo(Location::class)->withTrashed();
    }

    public function account_title()
    {
        return $this->belongsTo(AccountTitle::class)->withTrashed();
    }
}
