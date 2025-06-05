<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RawReportController extends Controller
{
    public function index(Request $request) {
        $date = $request->input("date");
        $book = $request->input("book");

        $year = date("Y", strtotime($date));
        $month = date("m", strtotime($date));

        switch ($book) {
            case "cash_disbursement":

                $data = DB::table('transactions')
//                    ->leftJoin('users', function ($join) {
//                        $join->on('transactions.assigned_id', '=', 'users.id')
//                            ->select('users.id_prefix', 'users.id_no', 'users.first_name', 'users.last_name');
//                    })
                    ->where('transactions.state', '!=', 'void')
                    ->join(DB::raw("(SELECT transaction_id, MAX(id) as latest_id
                     FROM approvers
                     WHERE status = 'approve-approve'
                     GROUP BY transaction_id) as latest_approvers"),
                        function ($join) {
                            $join->on('transactions.id', '=', 'latest_approvers.transaction_id');
                        })
                    ->leftJoin('treasuries', function ($join) {
                        $join->on('transactions.id', '=', 'treasuries.transaction_id')
                            ->where(function ($query) {
                                $query->select(DB::raw(1))
                                    ->from("treasuries")
                                    ->whereRaw("treasuries.transaction_id = transactions.id")
                                    ->where("treasuries.status", "cheque-cheque");
                            })->leftJoin("cheques", function ($join){
                                $join->on('treasuries.id', '=', 'cheques.treasury_id')
                                    ->where('cheques.deleted_at', '=', null);
                            })
                            ->leftJoin('voucher_account_title', function ($join) {
                                $join->on('treasuries.id', '=', 'voucher_account_title.treasury_id')
                                    ->where('voucher_account_title.deleted_at', '=', null);
                            });
                    })
                    ->leftJoin('issues', function ($join){
                        $join->on('transactions.id', '=', 'issues.transaction_id')
                            ->where('issues.status', 'issue-issue')
                            ->where(function ($query) {
                                $query->select(DB::raw(1))
                                    ->from("issues")
                                    ->whereRaw("issues.transaction_id = transactions.id")
                                    ->where("issues.status", "issue-issue");
                            });

                    })
//                    ->leftJoin('p_o_batches', function ($join) {
//                        $join->on('transactions.request_id', '=', 'p_o_batches.request_id')
//                            ->where('p_o_batches.deleted_at', '=', null);
//                    })
//                    ->leftJoin('received_receipts', function ($join) {
//                        $join->on('transactions.id', '=', 'received_receipts.transaction_id')
//                            ->select('received_receipts.id', 'received_receipts.rr_number')
//                            ->leftJoin('purchase_orders', function ($join) {
//                                $join->on('received_receipts.id', '=', 'purchase_orders.received_receipt_id')
//                                    ->select('purchase_orders.id', 'purchase_orders.po_number');
//                            })->leftJoin('job_orders', function ($join) {
//                                $join->on('received_receipts.id', '=', 'job_orders.received_receipt_id')
//                                    ->select('job_orders.id', 'job_orders.jo_number');
//                            });
//                    })
                    ->distinct()
//                    ->whereNotNull([
//                        "voucher_account_title.issue_id",
//                    ])
                    ->where(function ($query) use ($month, $year) {
                        $query->whereMonth("issues.created_at", $month)
                            ->whereYear("issues.created_at", $year);
                    })
                    ->select(
                        'transactions.voucher_no',
                        'cheques.bank_name',
                        'cheques.cheque_no',
                        'cheques.cheque_date',
                        'transactions.status',
                        'issues.created_at'
                    )
                    ->get();
                break;
            default:
                $data = [];
                break;
        }

        return response()->json($data);
    }
}
