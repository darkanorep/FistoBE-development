<?php

namespace App\Http\Controllers;

use App\Methods\GenericMethod;
use App\Models\Approver;
use App\Models\Associate;
use App\Models\Tagging;
use App\Models\Transaction;
use App\Models\Treasury;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Methods\TransactionFlow;

class TransactionFlowController extends Controller
{
    public function updateInTransactionFlow(Request $request,$id){
        return TransactionFlow::updateInTransactionFlow($request,$id);
    }

    public function validateVoucherNo(Request $request){
        return TransactionFlow::validateVoucherNo($request);
    }

    public function validateChequeNo(Request $request){
        return TransactionFlow::validateChequeNo($request);
    }

    public function transfer(Request $request, $id){

       return TransactionFlow::transfer($request, $id);
    }

    public function multipleReceive(Request $request) {
        $process = $request->input('process');
        $transactions = $request->input('transactions');

        switch ($process) {
            case 'tag':
                foreach ($transactions as $transaction) {
                    Tagging::create([
                        'transaction_id' => $transaction ,
                        'status' => $process . '-receive',
                        'date_status' => date('Y-m-d'),
                    ]);
                }
                break;
            case 'voucher':
                foreach ($transactions as $transaction) {
                    Associate::create([
                        'transaction_id' => $transaction ,
                        'status' => $process . '-receive',
                        'date_status' => date('Y-m-d'),
                        'tag_id' => Transaction::where('id', $transaction)->first()->tag_no,
                    ]);
                }
                break;
            case 'approve':
                foreach ($transactions as $transaction){
                    Approver::create([
                        'tag_id' => Transaction::where('id', $transaction)->first()->tag_no,
                        'status' => $process . '-receive',
                        'date_status' => date('Y-m-d'),
                        'transaction_id' => $transaction,
                    ]);
                }
                break;
        }

        Transaction::whereIn('id', $transactions)
            ->update([
                'state' => 'receive',
                'status' => $process . '-receive',
            ]);

        return GenericMethod::resultResponse("receive", null, []);
    }

    public function multipleTag(Request $request) {
        $process = $request->input('process');
        $transactions = $request->input('transactions');
        $receipt_type = $request->input('receipt_type');
        $distributed_to = $request->input('distributed_to');

        $tagData = [
            'status' => $process . '-tag',
            'date_status' => date('Y-m-d'),
            'distributed_id' => data_get($distributed_to, 'id'),
            'distributed_name' => data_get($distributed_to, 'name'),
        ];

        foreach ($transactions as $transaction) {
            Tagging::create(array_merge(['transaction_id' => $transaction], $tagData));
        }

        foreach ($transactions as $transaction) {
            Transaction::where('id', $transaction)
                ->update([
                    'state' => 'tag',
                    'status' => $process . '-tag',
                    'receipt_type' => $receipt_type,
                    'distributed_id' => data_get($distributed_to, 'id'),
                    'distributed_name' => data_get($distributed_to, 'name'),
                    'tag_no' => GenericMethod::generateTagNo($receipt_type, $transaction)
                ]);
        }

        return GenericMethod::result(200, "Transaction has been saved.", []);

    }

    public function multipleCheque(Request $request) {
        $process = $request->process;
        $transactions = $request->transactions;
        $accounts = $request->accounts;
        $cheques = $request->cheques;

        foreach($transactions as $transaction) {
            $treasury = Treasury::create([
                'transaction_id' => $transaction,
                'tag_id' => Transaction::where('id', $transaction)->first()->tag_no,
                'status' => $process . '-' . $process,
                'date_status' => Carbon::now("Asia/Manila")->format("Y-m-d"),
            ]);

            foreach ($accounts as $account) {
                $treasury->account_title()->create([
                    'entry' => $account['entry'],
                    'account_title_id' => data_get($account, 'account_title.id'),
                    'account_title_code' => data_get($account, 'account_title.code'),
                    'account_title_name' => data_get($account, 'account_title.name'),
                    'amount' => $account['amount'],
                    'remarks' => $account['remarks'],
                    'transaction_type' => 'new',
                    'company_id' => data_get($account, 'company.id'),
                    'company_code' => data_get($account, 'company.code'),
                    'company_name' => data_get($account, 'company.name'),
                    'department_id' => data_get($account, 'department.id'),
                    'department_code' => data_get($account, 'department.code'),
                    'department_name' => data_get($account, 'department.name'),
                    'location_id' => data_get($account, 'location.id'),
                    'location_code' => data_get($account, 'location.code'),
                    'location_name' => data_get($account, 'location.name'),
                ]);
            }

            foreach($cheques as $cheque) {
                $treasury->cheques()->create([
                    'transaction_id' => $transaction,
                    'bank_id' => data_get($cheque, 'bank.id'),
                    'bank_name' => data_get($cheque, 'bank.name'),
                    'cheque_no' => $cheque['no'],
                    'cheque_date' => $cheque['date'],
                    'cheque_amount' => $cheque['amount'],
                    'transaction_type' => 'new',
                    'entry_type' => 'Cheque'
                ]);
            }

            Transaction::where('id', $transaction)
                ->update([
                    'state' => $process,
                    'status' => $process . '-' . $process,
                    'is_for_releasing' => false
                ]);

            return GenericMethod::result(200, "Transaction has been saved.", []);
        }

//        $tessst = [];
//        foreach ($transactions as $transaction) {
//            $test1 = Transaction::where('id', $transaction)->first()->voucher;
//
//            foreach ($test1 as $shees) {
//                $tessst [] = $shees->account_title->where('entry', 'Debit')->sum('amount');
//            }
//        }
//
//        return array_sum($tessst);
    }
    // public function pullRequest(Request $request){
    //     $process =  $request['process'];
    //     $subprocess =  $request['subprocess'];
    //     return TransactionFlow::pullRequest($process,$subprocess,$id=0);
    // }

    // public function pullSingleRequest(Request $request,$id){
    //     $process =  $request['process'];
    //     $subprocess =  $request['subprocess'];
    //     return TransactionFlow::pullSingleRequest($process,$subprocess,$id);
    // }

    // public function receivedRequest(Request $request,$id){
    //     return TransactionFlow::receivedRequest($request, $id);
    // }

    // public function searchRequest(Request $request){
    //     $process =  $request['process'];
    //     $subprocess =  $request['subprocess'];
    //     $search =  $request['search'];
    //     return TransactionFlow::searchRequest($process,$subprocess,$search);
    // }

}
