<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SubUnit extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'unit_id',
        'code',
        'subunit',
    ];

    protected $hidden = [
        'created_at',
        'unit_id',
    ];

    public function unit()
    {
        return $this->belongsTo(Unit::class)->withTrashed();
    }
}
