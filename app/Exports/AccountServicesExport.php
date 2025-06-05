<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class AccountServicesExport implements FromQuery, WithHeadings, WithChunkReading
{
    use Exportable;
    protected $month, $year, $user, $companies;

    public function __construct($month, $year, $user, $companies)
    {
        $this->month = $month;
        $this->year = $year;
        $this->user = $user;
        $this->companies = $companies;
    }

    public function query()
    {
        return DB::table('transactions')
            ->where(function ($query) {
                $query->whereMonth('transactions.voucher_month', $this->month)
                    ->whereYear('transactions.voucher_month', $this->year);
            })
            ->where('transactions.state', '!=', 'void')
//            ->when($this->user, function ($query) {
//                $query->where('transactions.distributed_id', $this->user);
//            }, function ($query) {
//                $query->whereIn('transactions.company_id', $this->companies);
//            })
            ->join(DB::raw("(SELECT transaction_id, MAX(id) as latest_id
                     FROM approvers
                     WHERE status = 'approve-approve'
                     GROUP BY transaction_id) as latest_approvers"),
                function ($join) {
                    $join->on('transactions.id', '=', 'latest_approvers.transaction_id');
                })
            ->leftJoin('users', 'transactions.distributed_id', '=', 'users.id')
            ->leftJoin('p_o_batches', function ($join) {
                $join->on('transactions.request_id', '=', 'p_o_batches.request_id')
                    ->whereNull('p_o_batches.deleted_at');
            })
            ->leftJoin('associates', function ($join) {
                $join->on('transactions.id', '=', 'associates.transaction_id')
                    ->whereExists(function ($query) {
                        $query->select(DB::raw(1))
                            ->from("associates")
                            ->whereRaw("associates.transaction_id = transactions.id")
                            ->where("associates.status", "voucher-voucher");
                    });
            })
            ->leftJoin('voucher_account_title', function ($join) {
                $join->on('associates.id', '=', 'voucher_account_title.associate_id')
                    ->whereNull('voucher_account_title.deleted_at');
            })
            ->leftJoin('received_receipts', 'transactions.id', '=', 'received_receipts.transaction_id')
            ->leftJoin('purchase_orders', 'received_receipts.id', '=', 'purchase_orders.received_receipt_id')
            ->leftJoin('job_orders', 'received_receipts.id', '=', 'job_orders.received_receipt_id')
            ->whereNotNull("voucher_account_title.associate_id")
            ->select(
                "users.id_prefix",
                "users.id_no",
                "users.first_name",
                "users.last_name",
                "transactions.tag_no",
                "transactions.date_requested",
                "transactions.supplier",
                "transactions.referrence_no",
                "transactions.voucher_no",
                "transactions.voucher_month",
                "transactions.capex_no",
                "transactions.document_no",
                "transactions.utilities_receipt_no",
                "transactions.company",
                "transactions.pcf_letter",
                "transactions.pcf_date",
                "voucher_account_title.id",
                "voucher_account_title.amount",
                "voucher_account_title.entry",
                "voucher_account_title.remarks as line_description",
                "voucher_account_title.account_title_code",
                "voucher_account_title.account_title_name",
                "voucher_account_title.company_code",
                "voucher_account_title.company_name",
                "voucher_account_title.department_code",
                "voucher_account_title.department_name",
                "voucher_account_title.location_code",
                "voucher_account_title.location_name",
                "voucher_account_title.business_unit_code",
                "voucher_account_title.business_unit_name",
                "voucher_account_title.sub_unit_code",
                "voucher_account_title.sub_unit_name",
                "p_o_batches.po_no",
                "p_o_batches.rr_group",
                "received_receipts.rr_number as rr_number",
                "purchase_orders.po_number as po_number",
                "job_orders.jo_number as jo_number"
            )
            ->orderBy('voucher_account_title.id', 'asc');
    }

    public function headings(): array
    {
        return [
            'ID Prefix',
            'ID No',
            'First Name',
            'Last Name',
            'Tag No',
            'Date Requested',
            'Supplier',
            'Referrence No',
            'Voucher No',
            'Voucher Month',
            'Capex No',
            'Document No',
            'Utilities Receipt No',
            'Company',
            'PCF Letter',
            'PCF Date',
            'ID',
            'Amount',
            'Entry',
            'Line Description',
            'Account Title Code',
            'Account Title Name',
            'Company Code',
            'Company Name',
            'Department Code',
            'Department Name',
            'Location Code',
            'Location Name',
            'Business Unit Code',
            'Business Unit Name',
            'Sub Unit Code',
            'Sub Unit Name',
            'PO No',
            'RR Group',
            'RR Number',
            'PO Number',
            'JO Number'
        ];
    }

    public function chunkSize(): int
    {
        return 500;
    }
}
