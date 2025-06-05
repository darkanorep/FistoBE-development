<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class Location extends Model
{
  use HasFactory;
  use SoftDeletes;

  protected $table = "locations";
  protected $fillable = [
      "sync_id",
      "code",
      "location",
      "departments",
  ];
  protected $hidden = ["pivot", "created_at"];
  protected $cast = [
    "department" => "array",
  ];

  public function getCreatedAtAttribute($value)
  {
    $date = Carbon::parse($value);
    return $date->format("Y-m-d H:i");
  }

  public function getUpdatedAtAttribute($value)
  {
    $date = Carbon::parse($value);
    return $date->format("Y-m-d H:i");
  }

  //   public function getDeletedAtAttribute($value)
  //   {
  //     $date = Carbon::parse($value);
  //     return $date->format("Y-m-d H:i");
  //   }

  public function departments()
  {
    return $this->belongsToMany(Department::class, "location_departments")
//        ->select(
//      "departments.id",
//      "departments.department as name")
        ->withTrashed();
  }

    public function subUnits() {
        return $this->belongsToMany(
            SubUnit::class, // Related model
            "location_sub_units", // Pivot table
            "location_id", // Foreign key in the pivot table referencing `sync_id` in `locations`
            "sub_unit_id", // Foreign key in the pivot table referencing `id` in `sub_units`
            "sync_id", // Local key in the `locations` table
            "sync_id" // Local key in the `sub_units` table
        )->withTrashed();
    }
}
