<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TransactionType extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'transaction_type', 'transaction_types_coa_id'
    ];

    protected $casts = [
        'transaction_types_coa_id' => 'array',
    ];

    protected $hidden = [
        'created_at',
        'deleted_at',
        'transaction_types_coa_id',
    ];

    public function accounts(): HasMany
    {
        return $this->hasMany(TransactionTypeCoa::class, 'transaction_type_id', 'id');
    }
}
