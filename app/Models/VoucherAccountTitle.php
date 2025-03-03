<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VoucherAccountTitle extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = "voucher_account_title";
    protected $fillable = [
        "associate_id",
        "treasury_id",
        "issue_id",
        "purchase_order_id",
        "job_order_id",
        "bank_id",
        "entry",
        "account_title_id",
        "account_title_code",
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
        "is_default"
    ];

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

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrders::class, 'purchase_order_id');
    }
}
