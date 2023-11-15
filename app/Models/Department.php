<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class Department extends Model
{
  use HasFactory;
  use SoftDeletes;
  protected $table = "departments";
  protected $fillable = ["code", "department", "company", "voucher_code_id"];
  protected $hidden = ["created_at", "pivot", "voucher_code_id"];

  public function Company()
  {
    return $this->hasOne(Company::class, "id", "company")->select("id", "company as name")->withTrashed();
  }

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

  public function companyCharging()
  {
    return $this->belongsTo(Company::class);
  }

  public function locations()
  {
    return $this->belongsToMany(Location::class, "location_departments", "department_id", "location_id");
  }

  public function voucherCode() {
      return $this->hasOne(VoucherCode::class, "id", "voucher_code_id")->withTrashed();
  }
}
