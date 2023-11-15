<?php

namespace App\Models;

use App\Models\AccountTitle;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use Carbon\Carbon;

class Bank extends Model
{
  use HasFactory;
  use SoftDeletes;

  protected $table = 'banks';
  protected $fillable = [
      'code',
      'name',
      'branch',
      'account_no',
      'location',
      'account_title_1',
      'account_title_2',
      'company_id_1',
      'company_id_2',
      'business_unit_id_1',
      'business_unit_id_2',
      'department_id_1',
      'department_id_2',
      'sub_unit_id_1',
      'sub_unit_id_2',
      'location_id_1',
      'location_id_2'
  ];
  protected $hidden = [
      'account_title_1',
      'account_title_2',
      'company_id_1',
      'company_id_2',
        'business_unit_id_1',
        'business_unit_id_2',
        'department_id_1',
      'department_id_2',
        'sub_unit_id_1',
        'sub_unit_id_2',
        'location_id_1',
        'location_id_2',
      'created_at'];

  public function getCreatedAtAttribute($value){
    $date = Carbon::parse($value);
    return $date->format('Y-m-d H:i');
  }

  public function getUpdatedAtAttribute($value){
    $date = Carbon::parse($value);
    return $date->format('Y-m-d H:i');
  }

  public function AccountTitleOne() {
    return $this->hasOne(AccountTitle::class, 'id', 'account_title_1')->select('id','title as name', 'code')->withTrashed();
  }

  public function AccountTitleTwo() {
    return $this->hasOne(AccountTitle::class, 'id', 'account_title_2')->select('id','title as name', 'code')->withTrashed();
  }

    public function CompanyOne() {
        return $this->hasOne(Company::class, 'id', 'company_id_1')->select('id', 'company as name', 'code')->withTrashed();
    }

    public function CompanyTwo() {
        return $this->hasOne(Company::class, 'id', 'company_id_2')->select('id', 'company as name', 'code')->withTrashed();
    }

    public function BusinessUnitOne() {
        return $this->hasOne(BusinessUnit::class, 'id', 'business_unit_id_1')->select('id', 'business_unit as name', 'code')->withTrashed();
    }

    public function BusinessUnitTwo() {
        return $this->hasOne(BusinessUnit::class, 'id', 'business_unit_id_2')->select('id', 'business_unit as name', 'code')->withTrashed();
    }

    public function DepartmentOne() {
        return $this->hasOne(Department::class, 'id', 'department_id_1')->select('id', 'department as name', 'code')->withTrashed();
    }

    public function DepartmentTwo() {
        return $this->hasOne(Department::class, 'id', 'department_id_2')->select('id', 'department as name', 'code')->withTrashed();
    }

    public function SubUnitOne() {
        return $this->hasOne(SubUnit::class, 'id', 'sub_unit_id_1')->select('id', 'subunit as name', 'code')->withTrashed();
    }

    public function SubUnitTwo() {
        return $this->hasOne(SubUnit::class, 'id', 'sub_unit_id_2')->select('id', 'subunit as name', 'code')->withTrashed();
    }

    public function LocationOne() {
        return $this->hasOne(Location::class, 'id', 'location_id_1')->select('id', 'location as name', 'code')->withTrashed();
    }

    public function LocationTwo() {
        return $this->hasOne(Location::class, 'id', 'location_id_2')->select('id', 'location as name', 'code')->withTrashed();
    }

}
