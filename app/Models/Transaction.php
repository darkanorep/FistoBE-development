<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Transaction extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = "transactions";

    protected $fillable = [
        "users_id",
        "id_prefix",
        "id_no",
        "first_name",
        "middle_name",
        "last_name",
        "department_details",
        "receipt_type",
        "tag_no",
        "voucher_no",
        "voucher_month",
        "transaction_id",
        "request_id",
        "date_requested",
        "capex_no",
        "document_id",
        "document_type",
        "document_no",
        "document_amount",
        "document_date",
        "payment_type",
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
        "balance_po_ref_amount",
        "referrence_id",
        "referrence_type",
        "referrence_no",
        "referrence_amount",
        "pcf_name",
        "pcf_date",
        "pcf_letter",
        "utilities_from",
        "utilities_to",
        "utilities_category_id",
        "utilities_category",
        "utilities_account_no_id",
        "utilities_account_no",
        "utilities_consumption",
        "utilities_location_id",
        "utilities_location",
        "utilities_receipt_no",
        "payroll_from",
        "payroll_to",
        "payroll_client",
        "payroll_category_id",
        "payroll_category",
        "payroll_type",
        "payroll_control_no",
        "remarks",
        "state",
        "status",
        "reason_id",
        "reason",
        "reason_remarks",
        "distributed_id",
        "distributed_name",
        "approver_id",
        "approver_name",
        "total_gross",
        "total_cwt",
        "total_net",
        "period_covered",
        "prm_multiple_from",
        "prm_multiple_to",
        "cheque_date",
        "gross_amount",
        "witholding_tax",
        "net_amount",
        "release_date",
        "batch_no",
        "amortization",
        "interest",
        "cwt",
        "dst",
        "principal",
        "transaction_type",
        "is_allowable",
        "is_not_editable",
        "is_new",
        "is_for_releasing",
        "is_for_voucher_audit",
        "business_unit_id",
        "business_unit",
        "unit_id",
        "unit",
        "sub_unit_id",
        "sub_unit",
        "input_tax",
        "box_no",
        "is_confidential",
        "is_mc",
        "is_mcl",
        "assigned_id",
        "charge_id"
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
        return $this->hasMany(POBatch::class, "request_id", "id")
            ->select('request_id', 'is_add', 'is_editable', 'po_no', 'po_amount', 'po_total_amount', 'previous_balance', 'rr_group', 'is_modifiable', 'created_at', 'updated_at', 'deleted_at');
    }

    public function users()
    {
        return $this->belongsTo(User::class, "users_id", "id");
    }

    public function payableAssociates()
    {
        return $this->belongsTo(User::class, "distributed_id", "id");
    }

    public function treasuryAssociates()
    {
        return $this->belongsTo(User::class, "assigned_id", "id");
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, "supplier_id", "id")
            ->withTrashed()
            ->select(["id", "supplier_type_id", "name"]);
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

    public function service_batches() {
        return $this->hasMany(ServiceBatch::class, 'transaction_id', 'id')
            ->select('transaction_id', 'company_name', 'business_unit_name', 'department_name', 'sub_unit_name', 'location_name', 'amount');
    }

    public function cheque()
    {
//        return $this->hasMany(Cheque::class, "transaction_id", "transaction_id")->latest();
        return $this->hasMany(Cheque::class, "transaction_id", "id")
            ->latest();
    }


    public function chequeRelatedTransactions() {
        return $this->hasMany(Cheque::class, "transaction_id", "id")->latest();
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
    public function chequeHistory() {
        return $this->hasMany(Treasury::class, "transaction_id", "id")
            ->where('status', 'cheque-cheque')
            ->select('transaction_id', 'status', 'created_at', 'batch_no')
            ->latest();
    }

    public function clear()
    {
//    return $this->hasMany(Clear::class, "tag_id", "tag_no")
        return $this->hasMany(Clear::class, "transaction_id", "id")
//      ->select("tag_id", "id", "date_status as date", "status", "date_cleared")
            ->select("transaction_id", "id", "date_status as date", "status", "date_cleared", "user_id")
            ->latest();
    }

    // Transaction Flow

    public function tag()
    {
        return $this->hasMany(Tagging::class)
            ->whereIn('status', ['tag-receive', 'tag-tag', 'tag-return', 'tag-hold', 'tag-void'])
            ->select("transaction_id", "status", "created_at")
            ->latest();
//            ->limit(1);
    }

    public function tagHistory() {
        return $this->hasMany(Tagging::class)
            ->select("transaction_id", "status", "created_at")
            ->where('status', 'tag-tag')
            ->latest();
//            ->limit(1);
    }

    public function extract() {
        return $this->hasMany(Tagging::class, "transaction_id")
            ->whereIn("status", ["extract-extract", "extract-receive"])
            ->select("transaction_id", "status", "created_at")
            ->latest();
//            ->limit(1);
    }

    public function voucher()
    {
        return $this->hasMany(Associate::class, "transaction_id", "id")
            ->select('id', 'transaction_id', 'status', 'approver_id', 'approver_name', 'created_at')
            ->latest();
    }

    public function voucherHistory() {
        return $this->hasMany(Associate::class, "transaction_id", "id")
            ->where("status", "voucher-voucher")
            ->latest()
            ->limit(1);
    }

    public function account_titles()
    {
        return $this->hasManyThrough(
            VoucherAccountTitle::class,
            Associate::class,
            'transaction_id',
            'associate_id',
            'id',
            'id'
        )->orderBy((new VoucherAccountTitle())->getTable() . '.id', 'asc');
    }

    public function approve()
    {
        return $this->hasMany(Approver::class, "transaction_id", "id")
            ->select("transaction_id", "status", "created_at")
            ->latest();
//            ->limit(1);
    }

    public function approveHistory() {
        return $this->hasMany(Approver::class, "transaction_id", "id")
            ->where('status', 'approve-approve')
            ->latest()
            ->limit(1);
    }

    public function cheques()
    {
        return $this->hasMany(Treasury::class, "transaction_id", "id")
            ->select('id', 'transaction_id', 'status', 'batch_no', 'user_id', 'created_at')
            ->latest();
//            ->limit(1);
    }


    public function transmit()
    {
        return $this->hasMany(Transmit::class, "transaction_id", "id")
            ->whereNotIn('status', ['pass-receive', 'pass-pass'])
            ->select("transaction_id", "status", "created_at")
            ->latest();
//            ->limit(1);
    }

    public function release()
    {
//    return $this->hasMany(Release::class, "tag_id", "tag_no")
        return $this->hasMany(Release::class, "transaction_id", "id")
            ->select("transaction_id", "status", "created_at")
//            ->select(
//                "transaction_id",
//                "tag_id",
//                "id",
//                "distributed_id",
//                "distributed_name",
//                "date_status as date",
//                "status",
//                "reason_id",
//                "remarks"
//            )
            ->latest();
//            ->limit(1);
    }

    public function file()
    {
//    return $this->hasMany(File::class, "tag_id", "tag_no")
        return $this->hasMany(File::class, "transaction_id", "id")
            ->latest();
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

    public function inspect() {
        return $this->hasMany(Audit::class, "transaction_id")
            ->whereIn("status", ["inspect-inspect", "inspect-receive", "inspect-hold", "inspect-return"])
            ->select("transaction_id", "status", "created_at")
            ->latest();
//            ->limit(1);
    }

    public function inspectHistory() {
        return $this->hasMany(Audit::class, "transaction_id")
            ->where('status', 'inspect-inspect')
            ->latest()
            ->limit(1);
    }

    public function audit()
    {
        return $this->hasMany(Audit::class, "transaction_id")
            ->whereIn("status", ["audit-receive", "audit-audit"])
            ->select("transaction_id", "status", "created_at")
            ->latest();
//            ->limit(1);
    }

    public function gas()
    {
        return $this->hasMany(Gas::class, "transaction_id")
            ->whereIn("status", ["gas-receive", "gas-gas", "gas-return", "gas-void"])
            ->select("transaction_id", "status", "created_at")
            ->latest();
//            ->limit(1);
    }

    public function discharge()
    {
        return $this->hasMany(Gas::class, "transaction_id")
            ->whereIn("status", ["discharge-receive", "discharge-discharge"])
            ->select('transaction_id', 'status', 'created_at')
            ->latest();
//            ->limit(1);
    }

    public function executive()
    {
        return $this->hasMany(Executive::class, "transaction_id")
            ->select("transaction_id", "status", "created_at")
            ->latest();
//            ->limit(1);
    }


    public function issue() {
        return $this->hasMany(Issue::class, "transaction_id")
            ->select("transaction_id", "status", "reason_id", "created_at")
            ->latest();
//            ->limit(1);
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

    public function debit_file(): HasMany
    {
        return $this->hasMany(ClearingAccountTitle::class, 'clear_id', 'tag_no')
            ->where('transaction_type', 'debit');
    }

    public function voucher_associate()
    {
        return $this->hasOne(Associate::class, 'tag_id', 'tag_no')->latest()->limit(1);
    }

    public function treasuryCheque()
    {
        return $this->hasManyThrough(
            Cheque::class,
            Treasury::class,
            'transaction_id',
            'treasury_id',
            'id',
            'id'
        );
    }

    public function treasuryChequeTrashed()
    {
        return $this->treasuryCheque()->withTrashed()->get();
    }

    public function treasuryChequeHistory() {
        return $this->treasuryCheque()->onlyTrashed()->get();
    }

    public function treasuryChequeRelatedTransactions($bankId, $chequeNo) {
        return $this->treasuryCheque()->where('bank_id', $bankId)
            ->where('cheque_no', $chequeNo)
            ->get();
    }

    public function accountTitleClear()
    {
        return $this->hasManyThrough(
            ClearingAccountTitle::class,
            Clear::class,
            'transaction_id',
            'clear_id',
            'id',
            'id'
        )->orderBy('entry', 'desc');
    }

    public function treasuryAccountTitle()
    {
        return $this->hasManyThrough(
            VoucherAccountTitle::class,
            Treasury::class,
            'transaction_id',
            'treasury_id',
            'id',
            'id'
        );
    }

    public function chequeIssue() {
        return $this->hasManyThrough(
            Cheque::class,
            Issue::class,
            'transaction_id',
            'issue_id',
            'id',
            'id'
        );
    }

    public function accountTitleIssue() {
        return $this->hasManyThrough(
            VoucherAccountTitle::class,
            Issue::class,
            'transaction_id',
            'issue_id',
            'id',
            'id'
        );
    }


    public function company_info() {
        return $this->hasOne(Company::class, 'id', 'company_id')->withTrashed();
    }

    public function department_info() {
        return $this->hasOne(Department::class, 'id', 'department_id')->withTrashed();
    }

    public function location_info() {
        return $this->hasOne(Location::class, 'id', 'location_id')->withTrashed();
    }

    public function business_unit_info() {
        return $this->hasOne(BusinessUnit::class, 'sync_id', 'business_unit_id')->withTrashed();
    }

    public function unit_info() {
        return $this->hasOne(Unit::class, 'sync_id', 'unit_id')->withTrashed();
    }

    public function sub_unit_info() {
        return $this->hasOne(SubUnit::class, 'id', 'sub_unit_id')->withTrashed();
    }

    public function scopeRental($query, $transaction_id) {
        $query->where('transaction_id', $transaction_id)
            ->select([
                "status",
                "period_covered",
                "gross_amount",
                "witholding_tax as wht",
                "net_amount as net_of_amount",
                "cheque_date",
//                "date_requested"
        ]);
    }

    public function scopeLeasing($query, $transaction_id) {
        $query->where('transaction_id', $transaction_id)
            ->select([
                "status",
                "amortization",
                "principal",
                "interest",
                "cwt",
                "net_amount as net_of_amount",
                "cheque_date",
//                "date_requested"
        ]);
    }

    public function scopeLoans($query, $transaction_id) {
        $query->where('transaction_id', $transaction_id)
            ->select([
                "status",
                "principal",
                "interest",
                "cwt",
                "net_amount as net_of_amount",
                "cheque_date",
//                "date_requested"
        ]);
    }

    public function scopeVnumbers($query, $process) {
        $query->where('status', $process)
//            ->whereNotIn('is_confidential', [1])
                ->where([
                    'is_confidential' => 0,
                    'status' => $process
            ])
            ->select([
                "id",
                "voucher_no"
            ]);
    }

    public function receivedReceipts()
    {
        return $this->hasMany(ReceivedReceipt::class, 'transaction_id', 'id')->withTrashed();
    }

    public function utilityLocation()
    {
        return $this->belongsTo(UtilityLocation::class, 'utilities_location_id', 'id')
            ->withTrashed()
            ->select('id','location');
    }

    public function businessUnit()
    {
        return $this->belongsTo(BusinessUnit::class, 'business_unit_id', 'id')
            ->withTrashed();
    }

    public function receivedReceiptsStatus()
    {
        return $this->hasMany(ReceivedReceiptStatus::class, 'transaction_id', 'id')->withTrashed();
    }
}
