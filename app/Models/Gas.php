<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Gas extends Model
{
    use HasFactory;

    protected $table = "gas";

    protected $fillable = [
        'transaction_id',
        'status',
        'reason_id',
        'remarks',
    ];

    public function reason()
    {
        return $this->belongsTo(Reason::class, "reason_id");
    }
}
