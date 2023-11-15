<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Transaction extends Model
{
  use HasFactory;

  protected $table = "transactions";

  protected $fillable = [
    "users_id",
    "id_prefix",
    "id_no",
    "first_name",
    "middle_name",
    "last_name",
    "suffix",
    "department_details",
    "transaction_id",
    "request_id",
    "document_id",
    "capex_no",
    "document_type",
    "document_date",
    "category_id",
    "category",
    "company_id",
    "company",
    "department_id",
    "department",
    "location_id",
    "location",
    "supplier_id",
    "supplier",
    "po_total_amount",
    "po_total_qty",
    "rr_total_qty",
    "referrence_total_amount",
    "referrence_total_qty",
    "date_requested",
    "remarks",
    "payment_type",
    "status",
    "state",
    "reason_id",
    "reason",
    "document_no",
    "document_amount",
    "pcf_name",
    "pcf_date",
    "pcf_letter",
    "utilities_from",
    "utilities_to",

    "po_total_amount",
    "po_total_qty",
    "rr_total_qty",
    "referrence_total_amount",
    "referrence_total_qty",
    "balance_document_po_amount",
    "balance_document_ref_amount",
    "balance_po_ref_amount",
    "balance_po_ref_qty",
      "receipt_type",
    "tag_no",

    "utilities_category_id",
    "utilities_category",
    "utilities_location_id",
    "utilities_location",
    "utilities_account_no_id",
    "utilities_account_no",
    "utilities_consumption",
    "utilities_uom",
    "utilities_receipt_no",
    "payroll_client",
    "payroll_category_id",
    "payroll_category",
    "payroll_type",
    "payroll_from",
    "payroll_to",
    "payroll_control_no",

    "referrence_type",
    "referrence_no",
    "referrence_amount",
    "referrence_qty",
    "referrence_id",
    "is_allowable",
    "period_covered",
    "prm_multiple_from",
    "prm_multiple_to",
    "cheque_date",
    "gross_amount",
    "witholding_tax",
    "net_amount",
    "total_gross",
    "total_cwt",
    "total_net",

    "release_date",
    "batch_no",
    "amortization",
    "interest",
    "cwt",
    "dst",
    "principal",
    "is_not_editable",
    "voucher_no",
    "is_for_releasing",
    "is_for_voucher_audit",
      "business_unit_id",
      "business_unit",
      "sub_unit_id",
      "sub_unit",
      "input_tax",
      "box_no",
      "is_cleared"
  ];

  public $timestamps = ["created_at"];

  protected $attributes = [
    "status" => "Pending",
    "state" => "pending",
  ];

  protected $casts = [
    // 'po_group' => 'array',
    "referrence_group" => "array",
    "payroll_client" => "array",
  ];

  public function po_details()
  {
    return $this->hasMany(POBatch::class, "request_id", "request_id");
  }

  public function users()
  {
    return $this->belongsTo(User::class, "users_id", "id");
  }

  public function supplier()
  {
    return $this->belongsTo(Supplier::class, "supplier_id", "id")->select(["id", "supplier_type_id", "name"]);
  }

  public function auto_debit()
  {
    return $this->hasMany(DebitBatch::class, "request_id", "request_id")->select([
      "request_id",
      "pn_no",
      "interest_from",
      "interest_to",
      "outstanding_amount",
      "interest_rate",
      "no_of_days",
      "principal_amount",
      "interest_due",
      "cwt",
      "dst",
    ]);
  }

  public function cheque()
  {
    return $this->hasMany(Cheque::class, "transaction_id", "transaction_id")->latest();
  }

  public function transaction_voucher()
  {
    return $this->hasMany(Associate::class, "transaction_id", "transaction_id")
      ->where("status", "voucher-voucher")
      ->select(
        "transaction_id",
        "tag_id",
        "id",
        "receipt_type",
        "percentage_tax",
        "witholding_tax",
        "net_amount",
        "approver_id",
        "approver_name",
        "date_status as date",
        "status",
        "reason_id",
        "remarks",
        "transaction_type_id",
        "transaction_type_name"
      )
      ->latest();
  }

  public function transaction_cheque()
  {
    return $this->hasMany(Treasury::class, "transaction_id", "transaction_id")
      ->select("transaction_id", "tag_id", "id", "date_status as date", "status", "reason_id", "remarks")
      ->where("status", "cheque-cheque")
      ->latest();
  }

  public function clear()
  {
//    return $this->hasMany(Clear::class, "tag_id", "tag_no")
      return $this->hasMany(Clear::class, "transaction_id", "id")
//      ->select("tag_id", "id", "date_status as date", "status", "date_cleared")
          ->select("transaction_id", "id", "date_status as date", "status", "date_cleared")
      ->latest();
  }

  // Transaction Flow

  public function tag()
  {
    return $this->hasMany(Tagging::class)
//      ->select(
//        "request_id",
//        "tag_id",
//        "transaction_id",
//        "date_status as date",
//        "status",
//        "distributed_id",
//        "distributed_name",
//        "reason_id",
//        "remarks"
//      )
      ->latest()
      ->limit(1);
  }

  public function voucher()
  {
      return $this->hasMany(Associate::class, "transaction_id", "id")
//    return $this->hasMany(Associate::class, "tag_id", "tag_no")
      ->select(
        "transaction_id",
        "tag_id",
        "id",
//        "receipt_type",
        "percentage_tax",
        "witholding_tax",
        "net_amount",
        "approver_id",
        "approver_name",
        "date_status as date",
        "status",
        "reason_id",
        "remarks",
        "transaction_type_id",
        "transaction_type_name"
      )
      ->latest()
      ->limit(1);
  }

  public function account_titles() {
      return $this->hasManyThrough(
          VoucherAccountTitle::class,
          Associate::class,
          'transaction_id',
          'associate_id',
          'id',
          'id'
      );
  }

  public function approve()
  {
    return $this->hasMany(Approver::class, "transaction_id", "id")
      ->select(
        "transaction_id",
        "tag_id",
        "id",
        "distributed_id",
        "distributed_name",
        "date_status as date",
        "status",
        "reason_id",
        "remarks"
      )
      ->latest()
      ->limit(1);
  }

  public function cheques()
  {
//    return $this->hasMany(Treasury::class, "tag_id", "tag_no")
      return $this->hasMany(Treasury::class, "transaction_id", "id")
      ->select("transaction_id", "tag_id", "id", "date_status as date", "status", "reason_id", "remarks")
      ->latest()
      ->limit(1);
  }


  public function transmit()
  {
    return $this->hasMany(Transmit::class, "transaction_id", "id")
      ->select("transaction_id", "tag_id", "id", "date_status as date", "status")
      ->latest()
      ->limit(1);
  }

  public function release()
  {
//    return $this->hasMany(Release::class, "tag_id", "tag_no")
        return $this->hasMany(Release::class, "transaction_id", "id")
      ->select(
        "transaction_id",
        "tag_id",
        "id",
        "distributed_id",
        "distributed_name",
        "date_status as date",
        "status",
        "reason_id",
        "remarks"
      )
      ->latest()
      ->limit(1);
  }

  public function file()
  {
//    return $this->hasMany(File::class, "tag_id", "tag_no")
        return $this->hasMany(File::class, "transaction_id", "id")
      ->select(
        "transaction_id",
        "tag_id",
        "id",
        "receipt_type",
        "percentage_tax",
        "witholding_tax",
        "net_amount",
        "approver_id",
        "approver_name",
        "date_status as date",
        "status",
        "reason_id",
        "remarks"
      )
      ->latest()
      ->limit(1);
  }

  public function reverse()
  {
    return $this->hasMany(Reverse::class, "tag_id", "tag_no")
      ->select(
        "transaction_id",
        "tag_id",
        "id",
        "user_role",
        "user_id",
        "user_name",
        "date_status as date",
        "status",
        "reason_id",
        "remarks",
        "distributed_id",
        "distributed_name"
      )
      ->latest()
      ->limit(1);
  }

  public function transfer_voucher()
  {
    return $this->hasMany(Transfer::class, "tag_id", "tag_no")
      ->where("process", "voucher")
      ->latest()
      ->limit(1);
  }
  public function transfer_transmit()
  {
    return $this->hasMany(Transfer::class, "tag_id", "tag_no")
      ->where("process", "transmit")
      ->latest()
      ->limit(1);
  }
  public function transfer_file()
  {
    return $this->hasMany(Transfer::class, "tag_id", "tag_no")
      ->where("process", "file")
      ->latest()
      ->limit(1);
  }

  // public function receipt()
  // {
  //   return $this->hasOne(Receipt::class, "transactions_id", "id");
  // }

  public function receiveVoucher()
  {
    return $this->hasOne(Audit::class, "transaction_id")
      // ->with([
      //   "auditedBy" => function ($query) {
      //     $query->select(["id", "first_name", "last_name", DB::raw("CONCAT(first_name, ' ', last_name) AS name")]);
      //   },
      // ])
      ->select(["created_at"])
      ->where("type", "voucher")
      ->whereIn("status", ["inspect-receive"])
      ->latest();
  }

  public function auditVoucher()
  {
    return $this->hasOne(Audit::class, "transaction_id")
      // ->with([
      //   "auditedBy" => function ($query) {
      //     $query->select(["id", "first_name", "last_name", DB::raw("CONCAT(first_name, ' ', last_name) AS name")]);
      //   },
      // ])
      ->select(["created_at"])
      ->where("type", "voucher")
      ->whereIn("status", ["inspect-inspect"])
      ->latest();
  }

  public function reasonVoucher()
  {
    return $this->hasOne(Audit::class, "transaction_id")
      // ->with([
      //   "auditedBy" => function ($query) {
      //     $query->select(["id", "first_name", "last_name", DB::raw("CONCAT(first_name, ' ', last_name) AS name")]);
      //   },
      // ])
      ->select(["transaction_id", "reason_id", "remarks"])
      ->where("type", "voucher")
      ->latest()
      ->limit(1);
  }

  public function statusVoucher()
  {
    return $this->hasOne(Audit::class, "transaction_id")
      ->with([
        "reason" => function ($query) {
          $query->select(["reason"]);
        },
      ])
      ->select(["status"])
      ->where("type", "voucher")
      ->latest()
      ->limit(1);
  }
  #----------------------------------
  public function receive()
  {
    return $this->hasOne(Audit::class, "transaction_id")
      // ->with([
      //   "auditedBy" => function ($query) {
      //     $query->select(["id", "first_name", "last_name", DB::raw("CONCAT(first_name, ' ', last_name) AS name")]);
      //   },
      // ])
      ->select(["created_at"])
      ->where("type", "cheque")
      ->where("status", "audit-receive")
      ->latest()
      ->limit(1);
  }

  public function audit()
  {
    return $this->hasOne(Audit::class, "transaction_id")
      // ->with([
      //   "auditedBy" => function ($query) {
      //     $query->select(["id", "first_name", "last_name", DB::raw("CONCAT(first_name, ' ', last_name) AS name")]);
      //   },
      // ])
      ->select(["created_at"])
      ->where("type", "cheque")
      ->where("status", "audit-audit")
      ->latest()
      ->limit(1);
  }

  public function reasonAudit()
  {
    return $this->hasOne(Audit::class, "transaction_id")
      // ->with([
      //   "auditedBy" => function ($query) {
      //     $query->select(["id", "first_name", "last_name", DB::raw("CONCAT(first_name, ' ', last_name) AS name")]);
      //   },
      // ])
      ->select(["transaction_id", "reason_id", "remarks"])
      ->where("type", "cheque")
      ->latest()
      ->limit(1);
  }

  public function statusAudit()
  {
    return $this->hasOne(Audit::class, "transaction_id")
      ->with([
        "reason" => function ($query) {
          $query->select(["reason"]);
        },
      ])
      ->select(["status"])
      ->where("type", "cheque")
      ->latest()
      ->limit(1);
  }

  #----------------------------------

  // public function auditVoucher()
  // {
  //   return $this->hasOne(Audit::class, "transaction_id")
  //     ->with([
  //       "auditedBy" => function ($query) {
  //         $query->select(["id", "first_name", "last_name", DB::raw("CONCAT(first_name, ' ', last_name) AS name")]);
  //       },
  //     ])
  //     ->where("type", "voucher")
  //     ->whereIn("status", ["inspect-inspect", "inspect-receive"])
  //     ->latest()
  //     ->limit(1);
  // }

    public function receiveGas()
    {
        return $this->hasOne(Gas::class, "transaction_id")
            ->select(["transaction_id", "status", "created_at"])
            ->where("status", "gas-receive")
            ->latest()
            ->limit(1);
    }

    public function gas() {
        return $this->hasOne(Gas::class, "transaction_id")
            ->select(["transaction_id", "status", "created_at"])
            ->where("status", "gas-gas")
            ->latest()
            ->limit(1);
    }

    public function reasonGas() {
        return $this->hasOne(Gas::class, "transaction_id")
            ->wherein("status", ["gas-hold", "gas-return", "gas-void"])
                ->select(["transaction_id", "reason_id", "remarks"])
                ->latest()
                ->limit(1);
    }

    public function statusGas() {
        return $this->hasOne(Gas::class, "transaction_id")
            ->whereIn('status', ["gas-receive","gas-gas","gas-hold", "gas-return", "gas-void"])
                ->with([
                    "reason" => function ($query) {
                        $query->select(["reason"]);
                    },
                ])
                ->select(["status"])
                ->latest()
                ->limit(1);
    }

    public function receiveDischarge() {
        return $this->hasOne(Gas::class, "transaction_id")
            ->select(["transaction_id", "status", "created_at"])
            ->where("status", "discharge-receive")
            ->latest()
            ->limit(1);
    }

    public function discharge() {
        return $this->hasOne(Gas::class, "transaction_id")
            ->select(["transaction_id", "status", "created_at"])
            ->where("status", "discharge-discharge")
            ->latest()
            ->limit(1);
    }

    public function reasonDischarge() {
        return $this->hasOne(Gas::class, "transaction_id")
            ->wherein("status", ["discharge-hold", "discharge-return", "discharge-void"])
            ->select(["transaction_id", "reason_id", "remarks"])
            ->latest()
            ->limit(1);
    }

    public function statusDischarge() {
        return $this->hasOne(Gas::class, "transaction_id")
            ->whereIn('status', ["discharge-receive","discharge-discharge","discharge-hold", "discharge-return", "discharge-void"])
            ->with([
                "reason" => function ($query) {
                    $query->select(["reason"]);
                },
            ])
            ->select(["status"])
            ->latest()
            ->limit(1);
    }

  public function receiveExecutive()
  {
    return $this->hasOne(Executive::class, "transaction_id")
      ->select(["transaction_id", "status", "created_at"])
      ->where("status", "executive-receive")
      ->latest()
      ->limit(1);
  }

  public function executive()
  {
    return $this->hasOne(Executive::class, "transaction_id")
      // ->with([
      //   "executiveSignedBy" => function ($query) {
      //     $query->select(["id", "first_name", "last_name", DB::raw("CONCAT(first_name, ' ', last_name) AS name")]);
      //   },
      // ])
      ->select(["transaction_id", "status", "created_at"])
      ->where("status", "executive-executive")
      ->latest()
      ->limit(1);
  }

  public function reasonExecutive()
  {
    return $this->hasOne(Executive::class, "transaction_id")
      // ->with([
      //   "auditedBy" => function ($query) {
      //     $query->select(["id", "first_name", "last_name", DB::raw("CONCAT(first_name, ' ', last_name) AS name")]);
      //   },
      // ])
      ->select(["transaction_id", "reason_id", "remarks"])
      ->latest()
      ->limit(1);
  }

  public function statusExecutive()
  {
    return $this->hasOne(Executive::class, "transaction_id")
      ->with([
        "reason" => function ($query) {
          $query->select(["reason"]);
        },
      ])
      ->select(["status"])
      ->latest()
      ->limit(1);
  }

  public function issueReceive()
  {
    return $this->hasOne(Issue::class, "transaction_id")
      ->select(["created_at"])
//      ->where("type", "date")
      ->where("status", "issue-receive")
      ->latest()
      ->limit(1);
  }

  public function issueIssue()
  {
    return $this->hasOne(Issue::class, "transaction_id")
      ->select(["created_at"])
//      ->where("type", "date")
      ->where("status", "issue-issue")
      ->latest()
      ->limit(1);
  }

  public function issueStatus()
  {
    return $this->hasOne(Issue::class, "transaction_id")
      ->with([
        "reason" => function ($query) {
          $query->select(["reason"]);
        },
      ])
      ->select(["status"])
//      ->where("type", "date")
      ->latest()
      ->limit(1);
  }

  public function issueReason()
  {
    return $this->hasOne(Issue::class, "transaction_id")
      ->select(["transaction_id", "reason_id", "remarks"])
//      ->where("type", "date")
      ->latest()
      ->limit(1);
  }

  public function debitReceive()
  {
    return $this->hasOne(Filing::class, "tag_id")
      ->select(["created_at"])
      ->where("status", "debit-receive")
      ->latest()
      ->limit(1);
  }

  public function debitFile()
  {
    return $this->hasOne(Filing::class, "tag_id")
      ->select(["created_at"])
      ->where("status", "debit-file")
      ->latest()
      ->limit(1);
  }

  public function debitStatus()
  {
    return $this->hasOne(Filing::class, "tag_id")
      ->with([
        "reason" => function ($query) {
          $query->select(["reason"]);
        },
      ])
      ->select(["status"])
      ->latest()
      ->limit(1);
  }

  public function debitReason()
  {
    return $this->hasOne(Filing::class, "tag_id")
      ->select(["tag_id", "reason_id", "remarks"])
      ->latest()
      ->limit(1);
  }

  public function debit_file(): HasMany {
      return $this->hasMany(ClearingAccountTitle::class, 'clear_id', 'tag_no')
          ->where('transaction_type', 'debit');
  }

  public function voucher_associate() {
        return $this->hasOne(Associate::class, 'tag_id', 'tag_no')->latest()->limit(1);
  }
}
