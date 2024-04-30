<?php

namespace App\Http\Controllers;

use App\Methods\GenericMethod;
use App\Models\Approver;
use App\Models\Associate;
use App\Models\Audit;
use App\Models\Cheque;
use App\Models\Executive;
use App\Models\File;
use App\Models\Gas;
use App\Models\Issue;
use App\Models\Release;
use App\Models\Tagging;
use App\Models\Transaction;
use App\Models\Transmit;
use App\Models\Treasury;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Methods\TransactionFlow;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class TransactionFlowController extends Controller
{
    /**
     * @var TransactionController
     */
    private $transactionController;

    public function __construct(TransactionController $transactionController) {
        $this->transactionController = $transactionController;
    }
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

        $second = 1;
        foreach ($transactions as $transaction) {
            switch ($process) {
                case 'tag':
                case 'extract':
                    Tagging::create([
                        'transaction_id' => $transaction ,
                        'status' => $process . '-receive',
                        'date_status' => date('Y-m-d'),
                    ]);
                    break;
                case 'gas':
                    Gas::create([
                        'transaction_id' => $transaction,
                        'status' => $process . '-receive',
                    ]);
                    break;
                case 'voucher':
                    Associate::create([
                        'transaction_id' => $transaction ,
                        'status' => $process . '-receive',
                        'date_status' => date('Y-m-d'),
                        'tag_id' => Transaction::where('id', $transaction)->first()->tag_no,
                    ]);
                    break;
                case 'approve':
                    Approver::create([
                        'tag_id' => Transaction::where('id', $transaction)->first()->tag_no,
                        'status' => $process . '-receive',
                        'date_status' => date('Y-m-d'),
                        'transaction_id' => $transaction,
                    ]);
                    break;
                case 'executive':
                    Executive::create([
                        'transaction_id' => $transaction,
                        'status' => $process . '-receive',
                    ]);
                    break;
                case 'discharge':
                    Gas::create([
                        'transaction_id' => $transaction,
                        'status' => $process . '-receive',
                    ]);
                    break;
                //issue
                case 'issue':
                    Issue::create([
                        'transaction_id' => $transaction,
                        'status' => $process . '-receive',
                    ]);
                    break;
                //release
                case 'release':
                    Release::create([
                        'transaction_id' => $transaction,
                        'status' => $process . '-receive',
                        'date_status' => date('Y-m-d'),
                        'tag_id' => Transaction::where('id', $transaction)->first()->tag_no
                    ]);
                    break;
                //file
                case 'file':
                    File::create([
                        'transaction_id' => $transaction,
                        'status' => $process . '-receive',
                        'tag_id' => Transaction::where('id', $transaction)->first()->tag_no,
                        'receipt_type' => Transaction::where('id', $transaction)->first()->receipt_type,
                        'date_status' => date('Y-m-d')
                    ]);
                    break;
                case 'transmit':
                    Transmit::create([
                        'transaction_id' => $transaction,
                        'status' => $process . '-receive',
                        'tag_id' => Transaction::where('id', $transaction)->first()->tag_no,
                        'date_status' => date('Y-m-d')
                    ]);
                    break;
                case 'cheque':
                    Treasury::create([
                        'transaction_id' => $transaction,
                        'status' => $process . '-receive',
                        'tag_id' => Transaction::where('id', $transaction)->first()->tag_no,
                        'date_status' => date('Y-m-d')
                    ]);
                    break;
                case 'audit':
                    Audit::create([
                        'transaction_id' => $transaction,
                        'type' => 'Cheque',
                        'status' => $process . '-receive',
                        'date_status' => date('Y-m-d')
                    ]);
                    break;
            }

            Transaction::where('id', $transaction)
            ->update([
                'state' => 'receive',
                'status' => $process . '-receive',
                'updated_at' => now()->addSeconds($second++)->format('Y-m-d H:i:s'),
            ]);
        }

        return GenericMethod::resultResponse("receive", null, []);
    }

    public function multipleTag(Request $request) {
        $process = $request->input('process');
        $transactions = $request->input('transactions');
        $receipt_type = $request->input('receipt_type');
        $distributed_to = $request->input('distributed_to');

        $tagData = [
//            'status' => $process . '-tag',
            'status' => $process . '-'. $process,
            'date_status' => date('Y-m-d'),
            'distributed_id' => data_get($distributed_to, 'id') ?? null,
            'distributed_name' => data_get($distributed_to, 'name') ?? null
        ];

//        foreach ($transactions as $transaction) {
//            Tagging::create(array_merge(['transaction_id' => $transaction], $tagData));
//        }

        $second = 1;
        foreach ($transactions as $transaction) {
            Tagging::create(array_merge(['transaction_id' => $transaction], $tagData));

            switch ($process) {
                case 'tag':
                    Transaction::where('id', $transaction)
                        ->update([
                            'state' => $process,
                            'status' => $process . '-'. $process,
                            'receipt_type' => $receipt_type ?? $transaction->receipt_type,
                            'distributed_id' => data_get($distributed_to, 'id'),
                            'distributed_name' => data_get($distributed_to, 'name'),
                            'tag_no' => GenericMethod::generateTagNo($receipt_type, $transaction),
                            'updated_at' => now()->addSeconds($second++)->format('Y-m-d H:i:s'),
                        ]);
                    break;

                case 'extract':
                    Transaction::where('id', $transaction)
                        ->update([
                            'state' => 'transmit',
                            'status' => $process . '-'. $process,
                            'updated_at' => now()->addSeconds($second++)->format('Y-m-d H:i:s'),
                        ]);
                    break;
            }
        }

        return GenericMethod::result(200, "Transaction has been saved.", []);

    }

    public function multipleCheque(Request $request) {
        $process = $request->process;
        $transactions = $request->transactions;
        $accounts = $request->accounts;
        $cheques = $request->cheques;

        $batch_no = $this->transactionController->generateBatchNo();

//        Treasury::whereIn('transaction_id', $transactions)->where('status', $process.'-'.$process)->delete();
//        Cheque::whereIn('transaction_id', $transactions)->forceDelete();
        foreach($transactions as $transaction) {
            $treasury = Treasury::create([
                'transaction_id' => $transaction,
                'tag_id' => Transaction::where('id', $transaction)->first()->tag_no,
                'status' => $process . '-' . $process,
                'date_status' => Carbon::now("Asia/Manila")->format("Y-m-d"),
                'batch_no' => $batch_no,
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
        }
        return GenericMethod::result(200, "Transaction has been saved.", []);
    }

    public function multipleChequeReceive(Request $request) {
        $process = $request->input('process');
        $banks = $request->input('banks');
        $transactions = [];

        $bankIds = data_get($banks, '*.id');
        $chequeNos = data_get($banks, '*.cheque_no');

        $transactionIds = Cheque::whereIn('bank_id', $bankIds)
            ->whereIn('cheque_no', $chequeNos)
            ->pluck('transaction_id')
            ->toArray();

        $transactions = array_map('intval', $transactionIds);

        $this->multipleChequeReceiveProcess($process, $transactions);

        Cheque::whereIn('bank_id', $bankIds)
            ->whereIn('cheque_no', $chequeNos)
            ->update([
                'is_received' => true
            ]);

        $this->chequeIsReceivedChecker($process, $transactionIds);

//        $transactions = array_map('intval', $transactionIds);

        return GenericMethod::resultResponse("receive", null, []);
    }

    function multipleChequeReceiveProcess($process, $transactions) {

        foreach ($transactions as $transaction) {
            switch ($process) {

                case 'audit':
                    Audit::create([
                        'transaction_id' => $transaction,
                        'type' => 'Cheque',
                        'status' => $process . '-receive',
                        'date_status' => date('Y-m-d')
                    ]);

                    break;

                case 'executive':
                    Executive::create([
                        'transaction_id' => $transaction,
                        'status' => $process . '-receive',
                    ]);
                    break;

                case 'issue':
                    Issue::create([
                        'transaction_id' => $transaction,
                        'status' => $process . '-receive',
                    ]);
                    break;

                case 'release':

                    Release::create([
                        'transaction_id' => $transaction,
                        'status' => $process . '-receive',
                        'date_status' => date('Y-m-d'),
                        'tag_id' => Transaction::where('id', $transaction)->first()->tag_no,
                        'description' => Transaction::where('id', $transaction)->first()->remarks
                    ]);

                    break;
            }
        }
    }

    function chequeIsReceivedChecker($process, $transactionIds) {

        $cheques = Cheque::whereIn('transaction_id', $transactionIds)->whereNull('is_received')->get();

        if ($cheques->isEmpty()) {
            Transaction::whereIn('id', $transactionIds)->update([
                'state' => 'receive',
                'status' => $process . '-' . 'receive',
            ]);
        }

    }


    public function updateReceiptTypeTransaction(Request $request, $id) {

        $transaction = Transaction::find($id);

        if ($transaction) {
            $request->validate([
                'receipt_type' => 'required'
            ]);

            $transaction->timestamps = false;
            $transaction->receipt_type = $request->receipt_type;
            $transaction->save();


            return $this->resultResponse("update", "Transaction", $transaction);
        } else {
            return $this->resultResponse("not-found", "Transaction", []);
        }
    }

    public function updateTransactionRemarks(Request $request, $id) {

            $transaction = Transaction::find($id);

            if ($transaction) {
                $request->validate([
                    'remarks' => 'required'
                ]);

                $transaction->timestamps = false;
                $transaction->remarks = $request->remarks;
                $transaction->save();

                return $this->resultResponse("update", "Transaction", $transaction);
            } else {
                return $this->resultResponse("not-found", "Transaction", []);
            }
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
