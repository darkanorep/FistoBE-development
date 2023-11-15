<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentCoa extends Model
{
    use HasFactory;

    protected $table = 'document_coa';
    protected $hidden = ['created_at', 'document_id', 'company_id', 'business_unit_id', 'department_id', 'sub_unit_id', 'location_id', 'account_title_id'];

    protected $fillable = [
        'entry',
        'document_id',
        'company_id',
        'business_unit_id',
        'department_id',
        'sub_unit_id',
        'location_id',
        'account_title_id',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function business_unit(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function sub_unit(): BelongsTo
    {
        return $this->belongsTo(SubUnit::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function account_title(): BelongsTo
    {
        return $this->belongsTo(AccountTitle::class);
    }

}
