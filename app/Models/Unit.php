<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Unit extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'units';

    protected $fillable = [
        'sync_id',
        'code',
        'name',
        'department_sync_id'];

    protected $hidden = ['created_at', 'department_id'];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_sync_id', 'sync_id')->withTrashed();
    }

    public function subUnit() {
        return $this->hasOne(SubUnit::class, 'unit_sync_id', 'sync_id');
    }

}
