<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClearingAccountTitle extends Model
{
    use HasFactory;

    protected $fillable = [
        "clear_id",
        "entry",
        "account_title_id",
        "account_title_name",
        "amount",
        "remarks",
        "transaction_type",
        "company_id",
        "company_code",
        "company_name",
        "department_id",
        "department_code",
        "department_name",
        "location_id",
        "location_code",
        "location_name",
        "business_unit_id",
        "business_unit_code",
        "business_unit_name",
        "sub_unit_id",
        "sub_unit_code",
        "sub_unit_name",
    ];

    public function accountTitles() {
        return $this->belongsTo(AccountTitle::class, "account_title_id");
    }

    public function accountType() {
        return $this->hasManyThrough(
            AccountTitleGreatGrandParent::class,
            AccountTitle::class,
            "id",
            "id",
            "account_title_id",
            "account_title_ggparent_id"
        )->withTrashed();
    }

    public function accountGroup() {
        return $this->hasManyThrough(AccountTitleGrandParent::class, AccountTitle::class, "id", "id", "account_title_id", "account_title_gparent_id");
    }

    public function accountSubGroup() {
        return $this->hasManyThrough(AccountTitleParent::class, AccountTitle::class, "id", "id", "account_title_id", "account_title_parent_id");
    }

    public function financialStatement() {
        return $this->hasManyThrough(AccountTitleChild::class, AccountTitle::class, "id", "id", "account_title_id", "account_title_child_id");
    }

    public function normalBalance() {
        return $this->hasManyThrough(AccountTitlePnL::class, AccountTitle::class, "id", "id", "account_title_id", "account_title_pnl_id");
    }

    public function unit() {
        return $this->hasManyThrough(AccountTitleUnit::class, AccountTitle::class, "id", "id", "account_title_id", "account_title_unit_id");
    }
}
