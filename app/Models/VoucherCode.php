<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class VoucherCode extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        "code"
    ];

    protected $hidden = [
        "created_at",
    ];

//    public function departments()
//    {
//        return $this->hasMany(Department::class, 'voucher_code_id', 'id');
//    }
}
