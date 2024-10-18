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
                        'date_status' => date('Y-m-d'),
                        'user_id' => auth()->user()->id
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

    public static function multipleTag(Request $request) {
        $process = $request->input('process');
        $transactions = $request->input('transactions');
        $receipt_type = $request->input('receipt_type');
        $distributed_to = $request->input('distributed_to');
//        $isConfidential = $request->input('is_confidential', 0);


        $tagData = [
//            'status' => $process . '-tag',
            'status' => $process . '-'. $process,
            'date_status' => date('Y-m-d'),
            'distributed_id' => data_get($distributed_to, 'id') ?? null,
            'distributed_name' => data_get($distributed_to, 'name') ?? null
        ];

        $second = 1;
        foreach ($transactions as $transaction) {
            $trx = Transaction::where('id',$transaction)->first();
            switch ($process) {

                case 'tag':
                case 'extract':
                    Tagging::create(array_merge(['transaction_id' => $transaction], $tagData));

                    if ($process == 'tag') {
                        Transaction::where('id', $transaction)
                            ->update([
                                'state' => $process,
                                'status' => $process . '-'. $process,
                                'receipt_type' => $receipt_type ?? $transaction->receipt_type ?? null,
                                'distributed_id' => data_get($distributed_to, 'id'),
                                'distributed_name' => data_get($distributed_to, 'name'),
                                'tag_no' => GenericMethod::generateTagNo($receipt_type, $transaction, $trx->is_confidential),
                                'updated_at' => now()->addSeconds($second++)->format('Y-m-d H:i:s'),
                            ]);
                    } else {
                        Transaction::where('id', $transaction)
                            ->update([
                                'state' => 'transmit',
                                'status' => $process . '-'. $process,
                                'updated_at' => now()->addSeconds($second++)->format('Y-m-d H:i:s'),
                            ]);
                    }

                    break;

                case 'gas':
                    (new GenericMethod())->gasTransaction($transaction, $process, null, null);
                    $trx->update([
                        'state' => 'transmit',
                        'status' => $process . '-'. $process,
                        'updated_at' => now()->addSeconds($second++)->format('Y-m-d H:i:s'),
                    ]);
                    break;

                case 'approve':

                    $trx->approve()->create([
                        'status' => $process . '-'. $process,
                        'date_status' => date('Y-m-d'),
                        'tag_id' => $trx->tag_no,
                        'distributed_id' => $trx->distributed_id,
                        'distributed_name' => $trx->distributed_name
                    ]);

                    $trx->update([
                        'state' => $process,
                        'status' => $process . '-'. $process,
                        'updated_at' => now()->addSeconds($second++)->format('Y-m-d H:i:s'),
                    ]);

                    break;
                case 'transmit':
                case 'pass':
                    $trx->transmit()->create([
                        'status' => $process . '-'. $process,
                        'date_status' => date('Y-m-d'),
                        'tag_id' => $trx->tag_no,
                    ]);

                    $trx->update([
                        'state' => $process,
                        'status' => $process . '-'. $process,
                        'updated_at' => now()->addSeconds($second++)->format('Y-m-d H:i:s'),
                        'is_for_voucher_audit' => ($trx->document_id == 8) ? 1 : null
                    ]);
                    break;
            }
        }

        return GenericMethod::result(200, "Transaction has been saved.", []);

    }

    public function multipleCheque(Request $request) {
        $process = $request->process;
//        $transactions = $request->transactions;
        $transactions = $request->input('transactions', []);
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
                'user_id' => auth()->user()->id
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
                    'entry_type' => data_get($cheque, 'type')
                ]);
            }
        }

        Transaction::whereIn('id', $transactions)
            ->update([
                'state' => $process,
                'status' => $process . '-' . $process,
                'is_for_releasing' => false
            ]);
        return GenericMethod::result(200, "Transaction has been saved.", []);
    }

    public function applicationForLoan (Request $request) {
        $transactions = collect($request->input('transactions'));

        $transactions->each(function ($item) {
            $transaction = Transaction::find($item);

            if ($transaction->is_mc == 0) {
                $transaction->update([
                    'is_mc' => 1,
                    'is_mcl' => 1
                ]);
            } else {
                $transaction->update([
                    'is_mc' => 0,
                    'is_mcl' => 0
                ]);
            }
        });

        return $this->resultResponse("update", "Transaction", null);
    }

    public function multipleChequeReceive(Request $request) {
        $process = $request->input('process');
        $banks = $request->input('banks');

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

    public function multipleChequeDateIssue(Request $request)
    {
        $process = $request->input('process');
        $cheques = $request->input('cheques');
        $date = $request->input('date');

        $bankId = data_get($cheques, '*.bank_id');
        $chequeNos = data_get($cheques, '*.cheque_no');
        $transactionIds = Arr::flatten(data_get($cheques, '*.transactions'));

        for ($i = 0; $i < count($cheques); $i++) {
            $transactions = $cheques[$i]['transactions'];
            $accounts = $cheques[$i]['accounts'];

            foreach ($transactions as $transaction) {
                $transaction = Transaction::find($transaction);

                $issue = $transaction->issue()->create([
                    'status' => $process . '-' . $process,
                ]);

                Cheque::where('transaction_id', $transaction->id)
                    ->where('cheque_no', $chequeNos[$i])
                    ->where('bank_id', $bankId[$i])
                    ->update([
                        'is_received' => null,
                        'is_issued' => true,
                        'issue_id' => $issue->id,
                        'cheque_date' => date('Y-m-d', strtotime($date)),
                        'reason_id' => null,
                        'reason' => null
                    ]);

                foreach ($accounts as $account) {
                    $issue->accountTitles()->create([
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
                        'business_unit_id' => data_get($account, 'business_unit.id'),
                        'business_unit_code' => data_get($account, 'business_unit.code'),
                        'business_unit_name' => data_get($account, 'business_unit.name'),
                        'sub_business_unit_id' => data_get($account, 'sub_business_unit.id'),
                        'sub_business_unit_code' => data_get($account, 'sub_business_unit.code'),
                        'sub_business_unit_name' => data_get($account, 'sub_business_unit.name'),
                    ]);
                }
            }
        }

        $chequesTransactions = Cheque::whereIn('transaction_id', $transactionIds)->whereNull('issue_id')->whereNull('deleted_at')->get();

        if ($chequesTransactions->isEmpty()) {

            for ($i = 0; $i < count($cheques); $i++) {
                $transactions = $cheques[$i]['transactions'];

                foreach($transactions as $transaction) {
                    $transaction = Transaction::find($transaction);

                    if ($transaction->is_mc == 1 || $transaction->is_mcl == 1) {
                        Cheque::where('transaction_id', $transaction->id)
                            ->update([
                                'is_released' => true
                            ]);

                        Transaction::where('id', $transaction->id)
                            ->update([
                                'state' => 'release',
                                'status' => 'release-release'
                            ]);
                    } else {
                        Transaction::where('id', $transaction->id)
                            ->update([
                                'is_for_releasing' => true,
                                'state' => 'transmit',
                                'status' => $process . '-' . $process,
                         ]);
                    }
                }

            }

//            Transaction::whereIn('id', $transactionIds)->update([
//                'is_for_releasing' => true,
//                'state' => 'transmit',
//                'status' => $process . '-' . $process,
//            ]);
        }

        return $this->resultResponse("update", "Transaction", null);
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
//            $transaction->state = 'tag';
//            $transaction->status = 'tag-tag';
            $transaction->save();

//            $transaction->treasuryCheque()->delete();


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

//    public function adjustEntries(Request $request) {
//
//        $rental = [
//            'stall a rental',
//            'stall b rental',
//            'stall c rental',
//            'stall d rental',
//            'cusa rental',
//            'dorm rental',
//            'additional rental',
//            'lounge rental',
//            'corporate special program - education',
//            'official store rental',
//            'unofficial store rental',
//            'rental'
//        ];
//
//        $transactions = Transaction::with([
//            'account_titles' => function ($query) {
//                $query->with([
//                    'accountType',
//                    'accountGroup',
//                    'accountSubGroup',
//                    'financialStatement',
//                    'normalBalance',
//                    'unit',
//                ]);
//            }
//        ])->where('voucher_no', $request->input('voucher_no'))
//            ->where('distributed_id', auth()->user()->id)
//            ->select([
//                'id',
//                'tag_no',
//                'receipt_type',
//                'category',
//                'voucher_no',
//                'voucher_month',
//                'supplier_id',
//                'supplier',
//                'input_tax',
//                'document_amount',
//                'referrence_amount',
//                'document_id',
//                'document_type',
//                'gross_amount',
//                'principal',
//                'interest',
//            ])
//            ->get();
//
//        $transactions->transform(function ($transactions) use ($rental){
//            return [
//                'id' => $transactions->id,
//                'receipt_type' => $transactions->receipt_type,
//                'tag_no' => $transactions->tag_no,
//                'input_tax' => $transactions->input_tax ?? 0,
//                'document_amount' => ($transactions->document_id == 3)
//                ? ($transactions->category == in_array($transactions->category, $rental)
//                        ? $transactions->gross_amount
//                        : floatval((number_format(($transactions->principal + $transactions->interest), 2, '.', ''))))
//                    : $transactions->document_amount ?? $transactions->referrence_amount,
////                'document_amount' => ($transactions->document_id == 3)
////                    ? ($transactions->category == in_array($transactions->category, $rental)
////                        ? $transactions->gross_amount : floatval((number_format(($transactions->principal + $transactions->interest), 2, '.', ''))))
////                    : $transactions->document_amount ?? $transactions->referrence_amount,
//                'voucher' => [
//                    'voucher_no' => $transactions->voucher_no,
//                    'voucher_month' => $transactions->voucher_month,
//                ],
//                'supplier' => [
//                    'id' => $transactions->supplier_id,
//                    'name' => $transactions->supplier,
//                ],
//                'account_titles' => $transactions->account_titles->map(function ($account) {
//                    return [
//                        'id' => $account->id,
//                        'entry' => $account->entry,
//                        'account_title' => [
//                            'id' => $account->account_title_id,
//                            'code' => $account->account_title_code,
//                            'name' => $account->account_title_name,
//                        ],
//                        'amount' => $account->amount,
//                        'remarks' => $account->remarks,
//                        'company' => [
//                            'id' => $account->company_id,
//                            'code' => $account->company_code,
//                            'name' => $account->company_name,
//                        ],
//                        'department' => [
//                            'id' => $account->department_id,
//                            'code' => $account->department_code,
//                            'name' => $account->department_name,
//                        ],
//                        'location' => [
//                            'id' => $account->location_id,
//                            'code' => $account->location_code,
//                            'name' => $account->location_name,
//                        ],
//                        'business_unit' => [
//                            'id' => $account->business_unit_id,
//                            'code' => $account->business_unit_code,
//                            'name' => $account->business_unit_name,
//                        ],
//                        'sub_unit' => [
//                            'id' => $account->sub_business_unit_id,
//                            'code' => $account->sub_business_unit_code,
//                            'name' => $account->sub_business_unit_name,
//                        ]
//                    ];
//                }),
//            ];
//        });
//
//        return $transactions->first();
//    }

public function updateTransactionEntries(Request $request, $id)
{

    $transaction = Transaction::find($id);

    if ($transaction) {
        $request->validate([
            'account_titles' => 'required|array',
            'account_titles.*.id' => 'required|integer',
            'account_titles.*.entry' => 'required',
            'account_titles.*.account_title.id' => 'required|integer',
            'account_titles.*.amount' => 'required|numeric',
            'account_titles.*.remarks' => 'required',
            'account_titles.*.company.id' => 'required|integer',
            'account_titles.*.department.id' => 'required|integer',
            'account_titles.*.location.id' => 'required|integer',
            'account_titles.*.business_unit.id' => 'required|integer',
            'account_titles.*.sub_business_unit.id' => 'required|integer',
        ]);

        $transaction->account_titles()->delete();

        foreach ($request->account_titles as $account) {
            $transaction->account_titles()->create([
                'entry' => $account['entry'],
                'account_title_id' => $account['account_title']['id'],
                'account_title_code' => $account['account_title']['code'],
                'account_title_name' => $account['account_title']['name'],
                'amount' => $account['amount'],
                'remarks' => $account['remarks'],
                'transaction_type' => 'new',
                'company_id' => $account['company']['id'],
                'company_code' => $account['company']['code'],
                'company_name' => $account['company']['name'],
                'department_id' => $account['department']['id'],
                'department_code' => $account['department']['code'],
                'department_name' => $account['department']['name'],
                'location_id' => $account['location']['id'],
                'location_code' => $account['location']['code'],
                'location_name' => $account['location']['name'],
                'business_unit_id' => $account['business_unit']['id'],
                'business_unit_code' => $account['business_unit']['code'],
                'business_unit_name' => $account['business_unit']['name'],
                'sub_business_unit_id' => $account['sub_business_unit']['id'],
                'sub_business_unit_code' => $account['sub_business_unit']['code'],
                'sub_business_unit_name' => $account['sub_business_unit']['name'],
            ]);
        }
    }
}

public function mutlipleProcess(Request $request) {
    $transactions = collect($request->input('transactions'));
    $subprocess = $request->subprocess;

    $transactions->each(function ($transaction) use ($request) {
        static::updateInTransactionFlow($request, $transaction);
    });

    return GenericMethod::resultResponse($subprocess, "", "");
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
