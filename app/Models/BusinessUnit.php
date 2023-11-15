<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BusinessUnit extends Model
{
    use HasFactory, softDeletes;

    protected $fillable = [
        'company_id',
        'code',
        'business_unit',
    ];

    protected $hidden = [
        'updated_at',
        'deleted_at',
    ];

    protected $messages = [
        'company_id.exists' => 'Company is not exists.',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'business_unit_users', 'business_unit_id', 'user_id')->withTrashed();
    }

public function company()
    {
        return $this->belongsTo(Company::class)->withTrashed();
    }
}
