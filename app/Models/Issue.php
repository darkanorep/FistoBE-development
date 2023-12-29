<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Issue extends Model
{
    use HasFactory;

    protected $fillable =[
        "transaction_id",
        "status",
        "reason_id",
        "remarks",
        ""
    ];

    public function reason()
    {
        return $this->belongsTo(Reason::class, "reason_id");
    }

    public function accountTitles() {
        return $this->hasMany(VoucherAccountTitle::class, "issue_id", "id");
    }

    public function issueCheques() {
        return $this->hasMany(Cheque::class, "issue_id", "id");
    }
}
