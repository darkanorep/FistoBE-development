<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Resources\Json\JsonResource;

class SubUnit extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'department_id',
        'code',
        'subunit',
    ];

    protected $hidden = [
        'created_at',
        'department_id'
    ];

    public function department()
    {
        return $this->belongsTo(Department::class)->withTrashed();
    }
}
