<?php

namespace App\Exports;

use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ChequeExport implements FromCollection, WithHeadings, WithStyles, WithColumnFormatting
{
    /**
    * @return \Illuminate\Support\Collection
    */

    protected $fromDate;
    protected $toDate;

    public function __construct($voucherMonth)
    {
        $this->fromDate = Carbon::parse($voucherMonth . '-01')->startOfMonth()->startOfDay();
        $this->toDate   = Carbon::parse($voucherMonth . '-01')->endOfMonth()->endOfDay();
    }

        public function columnFormats(): array
    {
        return [
            'C' => 'yyyy-mm',
            'F' => 'yyyy-mm-dd hh:mm AM/PM',
            'G' => 'yyyy-mm-dd hh:mm AM/PM',
            'H' => 'yyyy-mm-dd hh:mm AM/PM',
            'K' => 'yyyy-mm-dd hh:mm AM/PM',
            'L' => 'yyyy-mm-dd hh:mm AM/PM',
        ];
    }

    public function headings(): array
    {
        return [
            'Tag No',
            'Receipt Type',
            'Voucher Month',
            'Voucher No',
            'Supplier',
            'Vouchered At',
            'Validated At',
            'Chequed At',
            'Bank Name',
            'Cheque No',
            'Treasury Released At',
            'Tagging Release At'
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FF4472C4'],
                ],
            ],
        ];
    }

    public function collection() {
        $latestVoucherAssociate = DB::table('associates')
            ->select('transaction_id', DB::raw('MAX(id) as latest_id'), DB::raw('MAX(created_at) as latest_created_at'))
            ->where('status', 'voucher-voucher')
            ->groupBy('transaction_id');

        $latestApproved = DB::table('approvers')
            ->select('transaction_id', DB::raw('MAX(id) as latest_id'), DB::raw('MAX(created_at) as latest_created_at'))
            ->where('status', 'approve-approve')
            ->groupBy('transaction_id');

        $latestTreasury = DB::table('treasuries')
            ->select('transaction_id', DB::raw('MAX(id) as latest_id'), DB::raw('MAX(created_at) as latest_created_at'))
            ->where('status', 'cheque-cheque')
            ->groupBy('transaction_id');

        $latestIssue = DB::table('issues')
            ->select('transaction_id', DB::raw('MAX(id) as latest_id'), DB::raw('MAX(created_at) as latest_created_at'))
            ->where('status', 'issue-issue')
            ->groupBy('transaction_id');

        $latestRelease = DB::table('releases')
            ->select('transaction_id', DB::raw('MAX(id) as latest_id'), DB::raw('MAX(created_at) as latest_created_at'))
            ->where('status', 'release-release')
            ->groupBy('transaction_id');

        $allCheques = DB::table('cheques as tc')
            ->select(
                'treasuries.transaction_id',
                'tc.id as cheque_id',
                'tc.bank_name',
                'tc.cheque_no',
                DB::raw("'treasury' as cheque_source")
            )
            ->join('treasuries', 'treasuries.id', '=', 'tc.treasury_id')
            ->where('treasuries.status', 'cheque-cheque')
            ->whereNull('tc.deleted_at')
            ->union(
                DB::table('cheques as ic')
                    ->select(
                        'issues.transaction_id',
                        'ic.id as cheque_id',
                        'ic.bank_name',
                        'ic.cheque_no',
                        DB::raw("'issue' as cheque_source")
                    )
                    ->join('issues', 'issues.id', '=', 'ic.issue_id')
                    ->where('issues.status', 'issue-issue')
                    ->whereNull('ic.deleted_at')
            );

        $distinctCheques = DB::table($allCheques, 'all_cheques')
            ->select(
                'transaction_id',
                'bank_name',
                'cheque_no',
                'cheque_source',
                DB::raw('MIN(cheque_id) as cheque_id')
            )
            ->groupBy('transaction_id', 'bank_name', 'cheque_no', 'cheque_source');

        $query = Transaction::query()
            ->leftJoinSub($latestVoucherAssociate, 'latest_associates', function ($join) {
                $join->on('transactions.id', '=', 'latest_associates.transaction_id');
            })
            ->leftJoinSub($latestApproved, 'latest_approvers', function ($join) {
                $join->on('transactions.id', '=', 'latest_approvers.transaction_id');
            })
            ->leftJoinSub($latestTreasury, 'latest_cheques', function ($join) {
                $join->on('transactions.id', '=', 'latest_cheques.transaction_id');
            })
            ->leftJoinSub($latestIssue, 'latest_issues', function ($join) {
                $join->on('transactions.id', '=', 'latest_issues.transaction_id');
            })
            ->leftJoinSub($latestRelease, 'latest_releases', function ($join) {
                $join->on('transactions.id', '=', 'latest_releases.transaction_id');
            })
            ->leftJoinSub($distinctCheques, 'all_cheques', function ($join) {
                $join->on('all_cheques.transaction_id', '=', 'transactions.id');
            })
            ->select(
                'transactions.tag_no',
                'transactions.receipt_type',
                'transactions.voucher_month',
                'transactions.voucher_no',
                'transactions.supplier',
                'latest_associates.latest_created_at as vouchered_at',
                'latest_approvers.latest_created_at as validated_at',
                'latest_cheques.latest_created_at as chequed_at',
                'all_cheques.bank_name',
                'all_cheques.cheque_no',
                // 'all_cheques.cheque_source',
                'latest_issues.latest_created_at as issued_at',
                'latest_releases.latest_created_at as released_at'
            )
            ->distinct()
            ->whereBetween('transactions.voucher_month', [$this->fromDate, $this->toDate])
            ->orderBy('transactions.voucher_month')
            ->orderBy('transactions.voucher_no')
            ->orderBy('all_cheques.bank_name')
            ->orderBy('all_cheques.cheque_no')
            ->get();

        return $query->map(function ($item) {

            if ($item->voucher_month) {
                $item->voucher_month = Carbon::parse($item->voucher_month)->format('Y-m');
            }

            if ($item->vouchered_at || $item->validated_at || $item->chequed_at || $item->issued_at || $item->released_at) {
                $item->vouchered_at = $item->vouchered_at ? Carbon::parse($item->vouchered_at)->format('Y-m-d h:i A') : null;
                $item->validated_at = $item->validated_at ? Carbon::parse($item->validated_at)->format('Y-m-d h:i A') : null;
                $item->chequed_at = $item->chequed_at ? Carbon::parse($item->chequed_at)->format('Y-m-d h:i A') : null;
                $item->issued_at = $item->issued_at ? Carbon::parse($item->issued_at)->format('Y-m-d h:i A') : null;
                $item->released_at = $item->released_at ? Carbon::parse($item->released_at)->format('Y-m-d h:i A') : null;
            }

            if (($item->chequed_at && !$item->cheque_no) || ($item->issued_at && !$item->bank_name) || ($item->released_at && !$item->cheque_no)) {
                $item->chequed_at = null;
                $item->issued_at = null;
                $item->released_at = null;
            }

            return $item;
        });
    }
}
