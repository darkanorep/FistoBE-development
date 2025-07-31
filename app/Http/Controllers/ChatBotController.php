<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class ChatBotController extends Controller
{
    public function handleQuery(Request $request)
    {
        $message = strtolower($request->input('message'));


        if (preg_match('/tag number(?:\s?#?(\d+))?(?:\s+receipt\s+(\w+))?/i', $message, $matches)) {
            $tagNumber = $matches[1] ?? null;
            $receiptType = $matches[2] ?? null;

            return $this->getTransactionsByPO($tagNumber, $receiptType);
        }


        return response()->json([
            'reply' => "Sorry, I didn't understand your request. You can ask about transactions by month, PO number, or account title."
        ]);
    }

    private function getTransactionsByMonthYear($args)
    {
        $transactions = DB::table('transactions')
            ->whereMonth('date', $args['month'])
            ->whereYear('date', $args['year'])
            ->get();

        return response()->json([
            'reply' => "Transactions for {$args['month']}/{$args['year']}:",
            'data' => $transactions
        ]);
    }

    private function getTransactionByPONumber($args)
    {
        $transactions = DB::table('transactions')
            ->where('po_number', $args['po_number'])
            ->get();

        return response()->json([
            'reply' => "Transactions for PO number {$args['po_number']}:",
            'data' => $transactions
        ]);
    }

    private function getTransactionsByPO($tagNumber = null, $receiptType = null) {
        $query =  DB::select(DB::raw(" SELECT
                transactions.business_unit,
                transactions.first_name,
                transactions.transaction_type,
                transactions.status,
                transactions.state,
                transactions.document_amount,
                transactions.id,
                transactions.voucher_month,
                transactions.supplier,
                transactions.receipt_type,
                transactions.document_type,
                transactions.voucher_no,
                transactions.assigned_id,
                users.first_name AS user_first_name,
                transactions.tag_no,
                transactions.deleted_at,
                transactions.status,
                transactions.distributed_name,
                transactions.approver_name,
                transactions.receipt_type
            FROM transactions
            LEFT JOIN users ON transactions.assigned_id = users.id
            WHERE transactions.tag_no IN ($tagNumber)
            AND transactions.receipt_type = '$receiptType'
        "));

//        return $query;

        return collect($query)->transform(function ($item) {

            $statuses = [
                'Pending' =>  'Pending for tagging.',
                'tag-receive' => 'Received for tagging.',
                'tag-return' => 'Returned to Proponent',
                'tag-hold' => 'On Hold by Tagging',
                'tag-tag' => $item->receipt_type != 'Official'
                    ?'Pending for Transmittal of Official Receipt'
                    :'Pending for Creation of Voucher of ' . $item->distributed_name,
                'gas-receive' => 'Received for Official Receipt ' . $item->distributed_name,
                'gas-gas' => 'Pending for Creation of Voucher of ' . $item->distributed_name,
                'voucher-receive' => 'Received for Creation of Voucher by ' . $item->distributed_name,
                'voucher-hold' => 'On Hold by ' . $item->distributed_name,
                'voucher-voucher' => 'Pending for Approval by ' . $item->approver_name,
                'approve-receive' => 'Received for Approval by ' . $item->approver_name,
                'approve-hold' => 'On Hold by ' . $item->approver_name,
                'approve-return' => 'Returned to Creation of Voucher by ' . $item->distributed_name,
                'approve-approve' => 'Pending for Transmittal of Documents',
                'transmit-receive' => 'Received for Transmittal of Documents',
                'transmit-transmit' => $item->receipt_type == 'Official'
                    ? 'Pending for Auditing of Voucher'
                    : $item->assigned_id == null
                        ? 'Pending for Creation of Cheque but no Treasurer Assigned'
                        : $item->user_first_name == null
                            ?'Pending for Creation of Cheque by ' . $item->user_first_name
                            :'Pending for Creation of Cheque but no Treasurer Assigned',
                'inspect-receive' => 'Received for Auditing of Voucher',
                'inspect-inspect' => $item->assigned_id == null
                    ? 'Pending for Creation of Cheque but no Treasurer Assigned'
                    : 'Pending for Creation of Cheque by ' . $item->user_first_name,
                'cheque-receive' => 'Received for Cheque Creation by ' . $item->user_first_name,
                'cheque-hold' => $item->user_first_name == null
                    ?'On Hold by Treasury but no Treasurer Assigned'
                    :'On Hold by ' . $item->distributed_name,
                'cheque-return' => 'Returned to Creation of Voucher by ' . $item->distributed_name,
                'cheque-cheque' => 'Pending for Auditing of Cheque',
                'audit-receive' => 'Received for Auditing of Cheque',
                'audit-hold' => 'On Hold by Auditor',
                'audit-return' => $item->user_first_name == null
                    ? 'Returned to Creation of Cheque but no Treasurer Assigned'
                    : 'Returned to Creation of Cheque by ' . $item->user_first_name,
                'audit-audit' => 'Pending for Signing of Cheque',
                'executive-receive' => 'Received for Signing of Cheque',
                'executive-executive' => 'Pending for Release of Cheque',
                'release-receive' => 'Received for Release of Cheque',
                'release-release' => $item->receipt_type == 'Official'
                    ? 'Pending for Filing of Official Receipt'
                    : 'Pending for Transmittal for Filing of Voucher',
                'discharge-receive' => 'Received for Filing of Official Receipt',
                'discharge-discharge' => 'Pending for Transmittal for Filing of Voucher',
                'file-receive' => 'Received for Filing of Voucher by ' . $item->distributed_name,
                'file-return' => $item->user_first_name == null
                    ? 'Returned to Creation of Cheque but no Treasurer Assigned'
                    : 'Returned to Creation of Cheque by ' . $item->user_first_name,
                'file-file' => 'Voucher Filed Successfully',
            ];

            return [
                'Tag Number' => $item->tag_no,
                'Receipt Type' => $item->receipt_type,
                'Voucher No' => $item->voucher_no,
                'Assigned AP' => $item->distributed_name,
                'Assigned Approver' => $item->approver_name,
                'Assigned Treasurer' => $item->user_first_name,
                'Findings' => $statuses[$item->status] ?? 'Status not defined.',
            ];
        });
    }
}
