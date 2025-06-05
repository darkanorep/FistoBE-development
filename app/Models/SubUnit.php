<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SubUnit extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'sync_id',
        'unit_sync_id',
        'code',
        'name',
    ];

    protected $hidden = [
        'created_at',
        'unit_sync_id',
        'pivot'
    ];

    public function unit()
    {
        return $this->belongsTo(Unit::class, 'unit_sync_id', 'sync_id')->withTrashed();
    }

    public function locations()
    {
        return $this->belongsToMany(
            Location::class, // Related model
            'location_sub_units', // Pivot table
            'sub_unit_id', // Foreign key in the pivot table referencing `sync_id` in `sub_units`
            'location_id', // Foreign key in the pivot table referencing `sync_id` in `locations`
            'sync_id', // Local key in the `sub_units` table
            'sync_id' // Local key in the `locations` table
        )->withTrashed();
    }
}
