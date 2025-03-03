<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Journal extends Model
{
    use HasFactory;

    protected $fillable = [
        'journable_id',
        'journable_type',
        'status',
        'user_id',
        'reason_id',
        'reason',
    ];

    public function journable()
    {
        return $this->morphTo();
    }
}
