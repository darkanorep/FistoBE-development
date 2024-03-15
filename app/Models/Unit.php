<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Unit extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['code', 'name', 'department_id'];

    protected $hidden = ['created_at', 'department_id'];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class)->withTrashed();
    }

}
