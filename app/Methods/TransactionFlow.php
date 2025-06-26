<?php

namespace App\Methods;

use App\Http\Controllers\TransactionController;
use App\Http\Controllers\TransactionFlowController;
use App\Methods\GenericMethod;
use App\Models\BankSeries;
use App\Models\Charging;
use App\Models\Cheque;
use App\Models\ClearingAccountTitle;
use App\Models\Executive;
use App\Models\GeneralJournal;
use App\Models\Issue;
use App\Models\VoucherAccountTitle;
use Carbon\Carbon;
use App\Models\Gas;

// For Pagination with Collection
use App\Models\File;
use App\Models\User;
use App\Models\Audit;

use App\Models\Clear;
use App\Models\Match;
use App\Models\Filing;
use App\Models\Reason;
use App\Models\POBatch;
use App\Models\Release;
use App\Models\Reverse;
use App\Models\Tagging;
use App\Models\Approver;
use App\Models\Transmit;
use App\Models\Treasury;
use App\Models\Associate;
use App\Models\ChequeInfo;
use App\Models\Specialist;
use App\Models\Transaction;
use App\Models\RequestorLogs;
use App\Models\ReturnVoucher;
use App\Models\ChequeClearing;
use App\Models\ChequeCreation;
use App\Models\ChequeReleased;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;

use Illuminate\Validation\ValidationException;
use Illuminate\Pagination\LengthAwarePaginator;

class TransactionFlow
{
    /**
     * @var \Illuminate\Support\HigherOrderCollectionProxy|mixed
     */
    private $isCutOffEnabledApproval;
    /**
     * @var mixed|null
     */
    private $cutOffApprovalDate;
    /**
     * @var bool
     */
    private $isCutOffEnabledVoucherEntry;
    /**
     * @var mixed|null
     */
    private $cutOffVoucherEntryDate;

    public function __construct()
    {
        $cutOffSettingApproval = DB::table('settings')
            ->where('key', 'voucher_approval_cutoff')
            ->first();

        $cutOffSettingVoucherEntry = DB::table('settings')
            ->where('key', 'voucher_entry_cutoff')
            ->first();

        // Check if the cut-off setting for voucher entry is enabled
        $this->isCutOffEnabledApproval = $cutOffSettingApproval ? $cutOffSettingApproval->value : false;
        // Set the cut-off date for approval if the setting is enabled
        $this->cutOffApprovalDate = $cutOffSettingApproval ? $cutOffSettingApproval->value1 : null;
        // Set the cut-off date for voucher entry if the setting is enabled
        $this->isCutOffEnabledVoucherEntry = $cutOffSettingVoucherEntry ? $cutOffSettingVoucherEntry->value : false;
        // Set the cut-off date for voucher entry if the setting is enabled
        $this->cutOffVoucherEntryDate = $cutOffSettingVoucherEntry ? $cutOffSettingVoucherEntry->value1 : null;
    }

    public static function updateInTransactionFlow($request, $id)
    {
        $instance = new self();
        $isCutOffEnabledApproval = $instance->isCutOffEnabledApproval;

        // return GenericMethod::floatvalue('46,072.50');
        $transaction = Transaction::find($id);
        if (!isset($transaction)) {
            return GenericMethod::resultResponse("not-found", "transaction", []);
        }
        $process = $request["process"];
        $subprocess = $request["subprocess"];
        $isConfidential = $transaction->is_confidential;
        $reason_id = isset($request["reason"]["id"]) ? $request["reason"]["id"] : null;
        $date_now = Carbon::now("Asia/Manila")->format("Y-m-d");
        $generic = new GenericMethod();

        $request_id = $transaction->request_id;
        $transaction_id = $transaction->transaction_id;
//      $transaction_id = $transaction->id;
        $remarks = $transaction->remarks;
        $users_id = $transaction->users_id;
        $transaction_type = $transaction->transaction_type;
        $assigned_id = $transaction->assigned_id;
        $charge_id = $transaction->charge_id;

        $typeOfTransactionId = data_get($request, 'transaction_type.id') ? data_get($request, 'transaction_type.id') : $transaction->voucher->first()->transaction_type_id ?? null;
        $typeOfTransactionName = data_get($request, 'transaction_type.name') ? data_get($request, 'transaction_type.name') : $transaction->voucher->first()->transaction_type_name ?? null;
        $inputTax = data_get($request, 'input_tax') ? data_get($request, 'input_tax') : $transaction->input_tax ?? null;
        $receipt_type = $request->receipt_type ?? $transaction->receipt_type;

        $tag_no = $transaction->tag_no;
        if ($subprocess == "tag") {
            $tag_no = GenericMethod::generateTagNo($receipt_type, $transaction->id, $isConfidential);
        }

        $previous_voucher_transaction = Transaction::with("transaction_voucher.account_title")
            ->where("transaction_id", $transaction["transaction_id"])
            ->latest()
            ->first();
        $previous_cheque_transaction = Transaction::with("transaction_cheque.account_title")
            ->where("transaction_id", $transaction["transaction_id"])
            ->latest()
            ->first();

        // $previous_percentage_tax = ($previous_voucher_transaction['transaction_voucher']->isEmpty())?NULL:$previous_voucher_transaction['transaction_voucher']->first()['percentage_tax'];
        // $previous_withholding_tax = ($previous_voucher_transaction['transaction_voucher']->isEmpty())?NULL:$previous_voucher_transaction['transaction_voucher']->first()['witholding_tax'];
        // $previous_net_amount = ($previous_voucher_transaction['transaction_voucher']->isEmpty())?NULL:$previous_voucher_transaction['transaction_voucher']->first()['net_amount'];
        $previous_receipt_type = $previous_voucher_transaction["transaction_voucher"]->isEmpty()
            ? null
            : $previous_voucher_transaction["transaction_voucher"]->first()["receipt_type"];
        $previous_voucher_no = $previous_voucher_transaction["transaction_voucher"]->isEmpty()
            ? null
            : $previous_voucher_transaction["voucher_no"];
        $previous_voucher_month = $previous_voucher_transaction["transaction_voucher"]->isEmpty()
            ? null
            : $previous_voucher_transaction["voucher_month"];
        // $previous_approver = [
        //   "id" => $previous_voucher_transaction["transaction_voucher"]->first()["approver_id"],
        //   "name" => $previous_voucher_transaction["transaction_voucher"]->first()["approver_name"],
        // ];

        $previous_approver = [];

        if (
            !is_null($previous_voucher_transaction["transaction_voucher"]) &&
            $previous_voucher_transaction["transaction_voucher"]->count() > 0
        ) {
            $firstVoucher = $previous_voucher_transaction["transaction_voucher"]->first();

            if (!is_null($firstVoucher["approver_id"]) && !is_null($firstVoucher["approver_name"])) {
                $previous_approver = [
                    "id" => $firstVoucher["approver_id"],
                    "name" => $firstVoucher["approver_name"],

//            "id" => $transaction->approver_id,
//            "name" => $transaction->approver_name,
                ];
            }
        }

        $previous_distributed = [
            "id" => $previous_voucher_transaction["distributed_id"],
            "name" => $previous_voucher_transaction["distributed_name"],

//        "id" => $transaction->distributed_id,
//        "name" => $transaction->distributed_name,
        ];

        $cheque_cheques = $previous_cheque_transaction["transaction_cheque"]->isEmpty()
            ? null
            : $previous_cheque_transaction["transaction_cheque"]->first()["cheques"];
        $cheque_account_title = $previous_cheque_transaction["transaction_cheque"]->isEmpty()
            ? null
            : $previous_cheque_transaction["transaction_cheque"]->first()["account_title"];
        // $voucher_account_title = $previous_voucher_transaction["transaction_voucher"]->first()["account_title"];

        $voucher_account_title = null;

        if (
            !is_null($previous_voucher_transaction["transaction_voucher"]) &&
            $previous_voucher_transaction["transaction_voucher"]->count() > 0
        ) {
            $firstVoucher = $previous_voucher_transaction["transaction_voucher"]->first();

            if (!is_null($firstVoucher["account_title"])) {
                $voucher_account_title = $firstVoucher["account_title"];
            }
        }

        $previous_cheque_transaction_account_title = GenericMethod::format_account_title($cheque_account_title);
        $previous_cheque_transaction_cheque = GenericMethod::format_cheque($cheque_cheques);

        $previous_cheque_transaction_account_title = $previous_cheque_transaction_account_title["accounts"] ?? null;
        $previous_cheque_transaction_cheque = $previous_cheque_transaction_cheque["cheques"] ?? null;

        $reason_description = $request["reason"]["description"] ?? null;
        $reason_remarks = $request["reason"]["remarks"] ?? null;
        $distributed_to = $request["distributed_to"] ?? null;
        $accounts = $request["accounts"] ?? null;
//        $validatedData = $request->validate([
//            'accounts.*.entry' => 'required',
//            'accounts.*.account_title.id' => 'required',
//            'accounts.*.account_title.code' => 'required',
//            'accounts.*.account_title.name' => 'required',
//            'accounts.*.department.id' => [
//                'required'
//            ],
//            'accounts.*.department.code' => 'required',
//            'accounts.*.department.name' => [
//                'required',
//                function ($attribute, $value, $fail) {
//                    $accounts = request()->input('accounts');
//                    $entries = array_column($accounts, 'entry');
//                    $account_titles = array_column($accounts, 'account_title.name');
//                    $departmentIds = array_column($accounts, 'department.name');
//
////                    $count = collect($accounts)->filter(function ($item) use ($entries, $departmentIds, $value) {
////                        return $item['department']['name'] == $value && $item['entry'] == $entries[array_search($value, $departmentIds)] && strtolower($item['entry']) == 'debit';
////                    })->count();
//                    $count = collect($accounts)->filter(function ($item) use ($entries, $departmentIds, $account_titles, $value) {
//                        $departmentIndex = array_search($value, $departmentIds);
//                        $accountTitleIndex = array_search($value, $account_titles);
//
//                        if ($departmentIndex === false || $accountTitleIndex === false) {
//                            return false;
//                        }
//
//                        return $item['department']['name'] == $value && $item['entry'] == $entries[$departmentIndex] && strtolower($item['entry']) == 'debit' && $item['account_title']['name'] == $account_titles[$accountTitleIndex];
//                    })->count();
//
//                    if ($count > 1) {
//                        $fail('The department has already been taken.');
//                    }
//
//                }
//            ],
//            'accounts.*.business_unit.id' => 'nullable',
//            'accounts.*.business_unit.code' => 'nullable',
//            'accounts.*.business_unit.name' => 'nullable',
//            'accounts.*.sub_unit.id' => 'nullable',
//            'accounts.*.sub_unit.code' => 'nullable',
//            'accounts.*.sub_unit.name' => 'nullable',
//            'accounts.*.location.id' => 'nullable',
//            'accounts.*.location.code' => 'nullable',
//            'accounts.*.location.name' => 'nullable',
//            'accounts.*.amount' => 'nullable',
//            'accounts.*.remarks' => 'nullable',
//            'accounts.*.is_default' => 'nullable',
//        ]);
//        $accounts = $validatedData['accounts'] ?? null;
        $cheque_cheques = $request["cheques"] ?? null;
        $date_cleared = $request["date_cleared"] ?? null;

        $voucher_no = data_get($request, "voucher.no", $transaction->voucher_no);
        $voucher_month = data_get($request, "voucher.month", $transaction->voucher_month);
        $voucher_code = data_get($request, "voucher.code", null);
        $voucher_account_titles = GenericMethod::with_previous_transaction($accounts, $voucher_account_title);
        $approver = GenericMethod::with_previous_transaction($request["approver"] ?? null, $previous_approver);
        $distributed = GenericMethod::with_previous_transaction($request["distributed_to"] ?? null, $previous_distributed);
//        $gj_number = $request->input('gj_number', null);

        $approver_id = data_get($request, 'approver.id') ?? $transaction->approver_id;
        $approver_name = data_get($request, 'approver.name') ?? $transaction->approver_name;

        $distributed_id = data_get($request, 'distributed_to.id') ?? $transaction->distributed_id;
        $distributed_name = data_get($request, 'distributed_to.name') ?? $transaction->distributed_name;

        $cheque_cheques = GenericMethod::with_previous_transaction($cheque_cheques, $previous_cheque_transaction_cheque);
        $cheque_account_titles = GenericMethod::with_previous_transaction(
            $accounts,
            $previous_cheque_transaction_account_title
        );

        if (isset($voucher_account_titles)) {
            $voucher_account_titles = GenericMethod::object_to_array($voucher_account_titles);
        }

        if (isset($cheque_account_titles)) {
            $cheque_account_titles = GenericMethod::object_to_array($cheque_account_titles);
        }

        if (isset($cheque_cheques)) {
            $cheque_cheques = GenericMethod::object_to_array($cheque_cheques);
        }

        if ($process == "Requestor") {
            $model = new RequestorLogs();
            if ($subprocess == "void") {
                $status = "requestor-void";
                $state = "void";
            }

            GenericMethod::insertRequestorLogs(
                $id,
                $transaction_id,
                $date_now,
                $remarks,
                $users_id,
                $status,
                $reason_id,
                $reason_description,
                $reason_remarks
            );
            GenericMethod::updateTransactionStatus(
                $id,
                $transaction_id,
                $request_id,
                $receipt_type,
                $tag_no,
                $status,
                $state,
                $reason_id,
                $reason_description,
                $reason_remarks,
                $voucher_no,
                $voucher_month,
                $distributed_id,
                $distributed_name,
                $approver_id,
                $approver_name,
                $inputTax,
                $transaction_type,
                $assigned_id,
                $charge_id
            );
        } elseif ($process == "tag") {
            $model = new Tagging();
            if ($subprocess == "receive") {
                $status = "tag-receive";
            } elseif ($subprocess == "hold") {
                $status = "tag-hold";
            } elseif ($subprocess == "return") {
                $status = "tag-return";

                // $document_type = Transaction::where('transaction_id', $transaction->transaction_id)->first();

                // if ($document_type && $document_type->document_type === 'PRM Multiple') {
                //     Transaction::where('transaction_id', $transaction_id)->where('status', 'tag-receive')->update([
                //         'status' => $status,
                //         'state' => $subprocess
                //     ]);
                // }

                // if ($transaction->document_id == 3) {
                //   Tagging::where("request_id", $transaction->request_id)->delete();
                // }
            } elseif ($subprocess == "void") {
                $status = "tag-void";
                static::voidTransaction($request, $id);
            } elseif ($subprocess == "tag") {
                $status = "tag-tag";
//        $receipt_type = $request->receipt_type;
//        $tag_no = GenericMethod::generateTagNo($receipt_type);
            } elseif (in_array($subprocess, ["unhold", "unreturn"])) {
                $status = GenericMethod::getStatus($process, $transaction);
            }
            if (!isset($status)) {
                return GenericMethod::resultResponse("invalid-access", "", "");
            }
            $state = $subprocess;

            $transaction->tag()->create([
                "request_id" => $request_id,
                "description" => $remarks,
                "status" => $status,
                "date_status" => $date_now,
                "reason_id" => $reason_id,
                "remarks" => $reason_remarks,
                "distributed_id" => $distributed_id,
                "distributed_name" => $distributed_name
            ]);
//            GenericMethod::tagTransaction(
//                $model,
//                $request_id,
////        $transaction_id,
//                $transaction->id,
//                $remarks,
//                $date_now,
//                $reason_id,
//                $reason_remarks,
//                $status,
//                $distributed_to
//            );
            GenericMethod::updateTransactionStatus(
                $id,
//        $transaction_id,
                $transaction->id,
                $request_id,
                $receipt_type,
                $tag_no,
                $status,
                $state,
                $reason_id,
                $reason_description,
                $reason_remarks,
                $voucher_no,
                $voucher_month,
                $distributed_id,
                $distributed_name,
                $approver_id,
                $approver_name,
                $inputTax,
                $transaction_type,
                $assigned_id,
                $charge_id
            );
        } elseif ($process == "extract") {
            $status = null;
            switch ($subprocess) {
                case 'receive':
                    $status = 'extract-receive';
                    break;
                case 'extract':
                    $status = 'extract-extract';
                    break;
            }

            $state = $subprocess;

            if ($subprocess == 'extract') {
                $state = 'transmit';
            }

            GenericMethod::tagTransaction(
                Tagging::class,
                $request_id,
//        $transaction_id,
                $transaction->id,
                $remarks,
                $date_now,
                $reason_id,
                $reason_remarks,
                $status,
                $distributed_to
            );

            GenericMethod::updateTransactionStatus(
                $id,
//        $transaction_id,
                $transaction->id,
                $request_id,
                $receipt_type,
                $tag_no,
                $status,
                $state,
                $reason_id,
                $reason_description,
                $reason_remarks,
                $voucher_no,
                $voucher_month,
                $distributed_id,
                $distributed_name,
                $approver_id,
                $approver_name,
                $inputTax,
                $transaction_type,
                $assigned_id,
                $charge_id
            );

        } elseif ($process == "voucher") {
            $account_titles = $voucher_account_titles;
            $model = new Associate();
            if ($subprocess == "receive") {
                $status = "voucher-receive";
            } elseif ($subprocess == "hold") {
                $status = "voucher-hold";
            } elseif ($subprocess == "return") {
                $status = "voucher-return";
            } elseif ($subprocess == "void") {
                $status = "voucher-void";
                static::voidTransaction($request, $id);
            } elseif ($subprocess == "voucher") {
                $status = "voucher-voucher";
                $transaction->account_titles()->delete();
                $transaction->treasuryCheque()->forceDelete();
                $transaction->treasuryAccountTitle()->delete();

//        $voucher_no = $generic->generateVoucherNo($transaction->id);

                $transaction->update([
                    "is_for_releasing" => null,
                    "is_for_voucher_audit" => null,
                ]);
            } elseif (in_array($subprocess, ["unhold", "unreturn"])) {
                $status = GenericMethod::getStatus($process, $transaction);
            }
            if (!isset($status)) {
                return GenericMethod::resultResponse("invalid-access", "", "");
            }
            $state = $subprocess;
            $document_amount = $transaction["document_amount"];
            if (!$document_amount) {
                $document_amount = $transaction["referrence_amount"];
            }

            if ($subprocess == "voucher") {
                if (!empty($account_titles)) {
                    $debit_entries_amount = array_filter($account_titles, function ($account_title) {
                        return strtolower($account_title["entry"]) != strtolower("credit");
                    });

                    $credit_entries_amount = array_filter($account_titles, function ($account_title) {
                        return strtolower($account_title["entry"]) != strtolower("debit");
                    });

                    $debit_amount = array_sum(array_column($debit_entries_amount, "amount"));
                    $credit_amount = array_sum(array_column($credit_entries_amount, "amount"));

//                    switch ($transaction->document_id) {
//                        case 3: //PRM Multiple
//                            if ($debit_amount != $credit_amount) {
//                                return GenericMethod::resultResponse("not-equal", "Total debit and credit", []);
//                            }
//                            if ($transaction->net_amount != $debit_amount) {
//                                return GenericMethod::resultResponse("not-equal", "Net amount and account title", []);
//                            }
//
//                            switch ($transaction->category) {
//                                case 'rental':
//                                    if ($transaction->gross_amount != $debit_amount) {
//                                        return GenericMethod::resultResponse("not-equal", "Document and account title", []);
//                                    }
//                                    break;
//
//                                default:
//                                    if (($transaction->principal + $transaction->interest) != $debit_amount) {
//                                        return GenericMethod::resultResponse("not-equal", "Document and account title", []);
//                                    }
//
//                                    if (floatval((number_format(($transaction->principal + $transaction->interest), 2, '.', ''))) != $debit_amount) {
//                                        return GenericMethod::resultResponse("not-equal", "Document and account title", []);
//                                    }
//                            }
//
//                            break;
//
//                        default:
//                            if ($debit_amount != $credit_amount) {
//                                return GenericMethod::resultResponse("not-equal", "Total debit and credit", []);
//                            }
//
//                            if ($document_amount != $debit_amount) {
//                                return GenericMethod::resultResponse("not-equal", "Document and account title", []);
//                            }
//                    }

                    $department_id = null;
                    foreach ($account_titles as $account_title) {
                        if (strtolower($account_title['entry']) == 'debit') {
                            $department_id = $account_title['department']['id'];
                            break;
                        }
                    }

                    $voucher_no = $generic->generateVoucherNo($transaction->id, $department_id, $voucher_month, $isConfidential, $voucher_code);

//                    if (isset($gj_number)) {
//                        GeneralJournal::where('gj_number', $gj_number)->update([
//                            'transaction_id' => $transaction->id,
//                            'voucher_no' => $voucher_no,
//                            'voucher_month' => $voucher_month,
//                        ]);
//                    }
                }

//        if (isset($account_titles)) {
//            $department_id = null;
//            foreach ($account_titles as $account_title) {
//                if (strtolower($account_title['entry']) == 'debit') {
//                    $department_id = $account_title['department']['id'];
//                    break;
//                }
//            }
//            $voucher_no = $generic->generateVoucherNo($transaction->id, $department_id);
//        }

//          $charging = Charging::where("transaction_id", $transaction->id)->first();
//
//          if ($charging) {
//              $charging->update([
//                  "company_id" => data_get($request, "company_id") ?? $transaction->company_id,
//                  "department_id" => data_get($request, "department_id") ?? $transaction->department_id,
//              ]);
//          } else {
//              Charging::create([
//                  "transaction_id" => $transaction->id,
//                  "company_id" => $transaction->company_id,
//                  "department_id" => $transaction->department_id,
//              ]);
//          }
            }

            GenericMethod::voucherTransaction(
                $model,
//        $transaction_id,
                $transaction->id,
                $tag_no,
                $reason_remarks,
                $date_now,
                $reason_id,
                $status,
                $voucher_no,
                $approver,
                $account_titles,
//          $request->transaction_type,
                $typeOfTransactionId,
                $typeOfTransactionName,
            );

            GenericMethod::updateTransactionStatus(
                $id,
                $transaction->id,
//        $transaction_id,
                $request_id,
                $receipt_type,
                $tag_no,
                $status,
                $state,
                $reason_id,
                $reason_description,
                $reason_remarks,
                $voucher_no,
                $voucher_month,
                $distributed_id,
                $distributed_name,
                $approver_id,
                $approver_name,
                $inputTax,
                $transaction_type,
                $assigned_id,
                $charge_id
            );
        } elseif ($process == "approve") {
            $model = new Approver();
            if ($subprocess == "receive") {
                $status = "approve-receive";
            } elseif ($subprocess == "hold") {
                $status = "approve-hold";
            } elseif ($subprocess == "return") {
                $status = "approve-return";
            } elseif ($subprocess == "void") {
                $status = "approve-void";
            } elseif ($subprocess == "approve") {
                $cutoffValidation = $instance->validateCutoffDate($transaction->voucher_month, $subprocess);
                if ($cutoffValidation) {
                    return $cutoffValidation;
                }
                $status = "approve-approve";

            } elseif (in_array($subprocess, ["unhold", "unreturn"])) {
                $status = GenericMethod::getStatus($process, $transaction);
            }

            if (!isset($status)) {
                return GenericMethod::resultResponse("invalid-access", "", "");
            }

            $state = $subprocess;

            GenericMethod::approveTransaction(
                $model,
//        $transaction_id,
                $transaction->id,
                $tag_no,
                $reason_remarks,
                $date_now,
                $reason_id,
                $status,
                $distributed_to
            );
            GenericMethod::updateTransactionStatus(
                $id,
//        $transaction_id,
                $transaction->id,
                $request_id,
                $receipt_type,
                $tag_no,
                $status,
                $state,
                $reason_id,
                $reason_description,
                $reason_remarks,
                $voucher_no,
                $voucher_month,
                $distributed_id,
                $distributed_name,
                $approver_id,
//          $transaction->approver_id,
                $approver_name,
//          $transaction->approver_name
                $inputTax,
                $transaction_type,
                $assigned_id,
                $charge_id
            );
        } elseif ($process == "transmit") {
            $model = new Transmit();

            if ($subprocess == "receive") {
                $status = "transmit-receive";

                if (
                    $transaction->document_id === 8 &&
                    $transaction->is_for_voucher_audit === false &&
                    $transaction->status == "inspect-inspect"
                ) {
                    $transaction->update([
                        "is_for_voucher_audit" => false,
                    ]);
                }
            } elseif ($subprocess == "transmit") {
                $status = "transmit-transmit";
                if ($transaction->document_id === 8) {
                    if ($transaction->status === "transmit-receive" && $transaction->is_for_voucher_audit === null) {
                        // Case 1: Update for voucher inspection
                        $transaction->update([
                            "is_for_voucher_audit" => true,
                        ]);
                    } elseif ($transaction->status === "transmit-receive" && !$transaction->is_for_voucher_audit) {
                        // Case 2: Update for transmission status
                        $transaction->update([
                            "is_for_voucher_audit" => null,
                            // "is_for_releasing" => false,
                        ]);
                    }
//        } elseif ($transaction->document_id === 9) { // auto-debit
//          $transaction->update([
//            "is_for_voucher_audit" => true,
//          ]);
                } else {
                    $transaction->update([
                        // "is_for_releasing" => false,
                        "is_for_releasing" => null,
                    ]);
                }
            }
            if (!isset($status)) {
                return GenericMethod::resultResponse("invalid-access", "", "");
            }
            $state = $subprocess;

            GenericMethod::transmitTransaction(
                $model,
//        $transaction_id,
                $transaction->id,
                $tag_no,
                $reason_remarks,
                $date_now,
                $reason_id,
                $status,
                $distributed_to,
                $transaction_type
            );
            GenericMethod::updateTransactionStatus(
                $id,
//        $transaction_id,
                $transaction->id,
                $request_id,
                $receipt_type,
                $tag_no,
                $status,
                $state,
                $reason_id,
                $reason_description,
                $reason_remarks,
                $voucher_no,
                $voucher_month,
                $distributed_id,
                $distributed_name,
                $approver_id,
//        $transaction->approver_id,
                $approver_name,
//        $transaction->approver_name,
//        $transaction_type
                $inputTax,
                $transaction_type,
                $assigned_id,
                $charge_id
            );
        } elseif ($process == "cheque") {
            $account_titles = $cheque_account_titles;
            $cheques = $cheque_cheques;

            $model = new Treasury();
            if ($subprocess == "receive") {
                $status = "cheque-receive";
                // $transaction->when($transaction->document_id === 8 && $transaction->is_for_voucher_audit, function ($query) {
                //   $query->update([
                //     "is_for_voucher_audit" => null,
                //   ]);
                // });
            } elseif ($subprocess == "hold") {
                $status = "cheque-hold";
            } elseif ($subprocess == "return") {
                $status = "cheque-return";
//                (new TransactionController())->chequeRevert1($request["bank_id"], $request["cheque_no"], $process, $request);

                if (isset($request["cheque_no"]) && isset($request["bank_id"])) {
                    (new TransactionController())->chequeRevert1($request["bank_id"], $request["cheque_no"], $process, $request);
                }

            } elseif ($subprocess == "void") {
                $status = "cheque-void";
            } elseif ($subprocess == "cheque") {
                $status = "cheque-cheque";
//        $transaction->treasuryCheque()->forceDelete();
//        $transaction->treasuryAccountTitle()->forceDelete();

                if ($transaction->is_for_releasing) {
                    $transaction->update([
                        "is_for_releasing" => false,
                    ]);
                } else {
                    $transaction->update([
                        "is_for_releasing" => false,
                    ]);
                }

//        $not_valid = GenericMethod::validateCheque($id, $cheques);
//        if ($not_valid) {
//          return GenericMethod::resultResponse("cheque-no-exist", "Cheque_no number already exist.", []);
//        }

                // $transaction->update([
                //   "is_for_cheque_audit" => true,
                // ]);
            } elseif ($subprocess == "release") {
                if ($transaction->is_for_releasing == 0) {
                    return response()->json(
                        [
                            "message" => "Not for releasing.",
                        ],
                        422
                    );
                }

                $cheques = GenericMethod::get_cheque_details_latest($id);
                $cheques = array_values(
                    array_filter($cheques, function ($item) {
                        return $item["transaction_type"] == "new";
                    })
                );
                $account_titles = GenericMethod::get_account_title_details_latest($id);
                $account_titles = array_values(
                    array_filter($account_titles, function ($item) {
                        return $item["transaction_type"] == "new";
                    })
                );

                $status = "cheque-release";
            } elseif ($subprocess == "reverse") {
                $old_cheques = GenericMethod::get_cheque_details($id);
                $old_cheques = isset($old_cheques) ? $old_cheques : [];
                $old_account_titles = GenericMethod::get_account_title_details($id);
                $old_account_titles = isset($old_account_titles) ? $old_account_titles : [];

                $old_cheques_with_type = array_map(function ($item) {
                    return array_merge($item, ["transaction_type" => "old"]);
                }, $old_cheques);

                $reverse_cheques_with_type = array_map(function ($item) {
                    return array_merge($item, ["transaction_type" => "reverse"]);
                }, $old_cheques);

                $new_cheques_with_type = array_map(function ($item) {
                    return array_merge($item, ["transaction_type" => "new"]);
                }, $cheques);

                $old_account_titles_with_type = array_map(function ($item) {
                    return array_merge($item, ["transaction_type" => "old"]);
                }, $old_account_titles);

                $reverse_account_titles_with_type = array_map(function ($item) {
                    return array_merge($item, ["transaction_type" => "reverse"]);
                }, $old_account_titles);

                $new_account_titles_with_type = array_map(function ($item) {
                    return array_merge($item, ["transaction_type" => "new"]);
                }, $account_titles);

                $cheques = array_merge($old_cheques_with_type, $reverse_cheques_with_type, $new_cheques_with_type);
                $account_titles = array_merge(
                    $old_account_titles_with_type,
                    $reverse_account_titles_with_type,
                    $new_account_titles_with_type
                );

                $new_cheque_with_type_amount = array_filter($cheques, function ($cheque) {
                    return $cheque["transaction_type"] == "new";
                });

                $new_cheque_amount = array_values($new_cheque_with_type_amount);
                $new_cheque_amount = array_sum(array_column($new_cheque_amount, "amount"));

                $status = "cheque-reverse";
            } elseif (in_array($subprocess, ["unhold", "unreturn"])) {
                $status = GenericMethod::getStatus($process, $transaction);
            } elseif ($subprocess == "file") {
                $status = "cheque-file";
            }

            if (!isset($status)) {
                return GenericMethod::resultResponse("invalid-access", "", "");
            }

            $state = $subprocess;

            $document_amount = $transaction["document_amount"];
            if (!$document_amount) {
                $document_amount = $transaction["referrence_amount"];
            }

            if (!empty($cheques)) {
                $cheque_amount = array_sum(array_column($cheques, "amount"));
                $cheque_amount = isset($new_cheque_amount) ? $new_cheque_amount : $cheque_amount;

                // if ($document_amount != $cheque_amount) {
                //   return GenericMethod::resultResponse("not-equal", "Document and cheque", []);
                // }

//        switch ($transaction->document_id) {
//          case 3:
////            if ($transaction->net_amount != $cheque_amount) {
////              return GenericMethod::resultResponse("not-equal", "Net amount and account title", []);
////            }
//
//            switch ($transaction->category) {
//
//                case 'rental':
//                    if ($transaction->gross_amount != $cheque_amount) {
//                        return GenericMethod::resultResponse("not-equal", "Document and account title", []);
//                    }
//                    break;
//
//                default:
//
//                    if (floatval((number_format(($transaction->principal + $transaction->interest), 2, '.', ''))) != $cheque_amount) {
//                        return GenericMethod::resultResponse("not-equal", "Document and account title", []);
//                    }
//            }
//            break;
//
//          default:
//            if ($document_amount != $cheque_amount) {
//              return GenericMethod::resultResponse("not-equal", "Document and cheque", []);
//            }
//            break;
//        }
            }

//      if (!empty($account_titles)) {
//        $debit_entries_amount = array_filter($account_titles, function ($account_title) {
//          if (isset($account_title["transaction_type"])) {
//            return strtolower($account_title["entry"]) != "credit" && $account_title["transaction_type"] == "new";
//          }
//          return strtolower($account_title["entry"]) != "credit";
//        });
//
//        $credit_entries_amount = array_filter($account_titles, function ($account_title) {
//          if (isset($account_title["transaction_type"])) {
//            return strtolower($account_title["entry"]) != "debit" && $account_title["transaction_type"] == "new";
//          }
//          return strtolower($account_title["entry"]) != "debit";
//        });
//
//        $debit_amount = array_sum(array_column($debit_entries_amount, "amount"));
//        $credit_amount = array_sum(array_column($credit_entries_amount, "amount"));
//
//        if ($debit_amount != $credit_amount) {
//          return GenericMethod::resultResponse("not-equal", "Total debit and credit", []);
//        }
//
//        if ($cheque_amount != $debit_amount) {
//          return GenericMethod::resultResponse("not-equal", "Cheque and account title", []);
//        }
//      }
            GenericMethod::chequeTransaction(
                $model,
//        $transaction_id,
                $transaction->id,
                $tag_no,
                $reason_remarks,
                $date_now,
                $reason_id,
                $status,
                $cheques,
                $account_titles
            );
            GenericMethod::updateTransactionStatus(
                $id,
//        $transaction_id,
                $transaction->id,
                $request_id,
                $receipt_type,
                $tag_no,
                $status,
                $state,
                $reason_id,
                $reason_description,
                $reason_remarks,
                $voucher_no,
                $voucher_month,
                $distributed_id,
                $distributed_name,
                $approver_id,
                $approver_name,
                $inputTax,
                $transaction_type,
                $assigned_id,
                $charge_id
            );
        } elseif ($process == "audit") {
            $date_now = Carbon::now("Asia/Manila")->format("Y-m-d H:i:s");
            $audit_by = auth()->user()->id;
            $type = "cheque";
            // $transaction = Transaction::find($id);

            if ($subprocess == "receive") {
                $status = "audit-receive";
                // if ($transaction->document_id === 8 && $transaction->is_for_voucher_audit == true) {
                //   $status = "inspect-receive";
                //   $type = "voucher";
                // }

                // if ($status == "inspect-receive" && $transaction->is_for_voucher_audit == true) {
                //   if ($type == "voucher") {
                //     $audit->auditCheque($id, $date_now, $status, $reason_id, $reason_remarks, null, null, "voucher");
                //   }
                // } elseif ($status == "audit-receive" && $transaction->is_for_voucher_audit == false) {
                //   $audit->auditCheque($id, $date_now, $status, $reason_id, $reason_remarks, null, null, "cheque");
                // }

                // if ($transaction->is_for_voucher_audit == false) {
                //   $audit->auditCheque($id, $date_now, $status, $reason_id, $reason_remarks, null, null, "cheque");
                // }
            } elseif ($subprocess == "hold") {
                $status = "audit-hold";
            } elseif ($subprocess == "return") {
                $status = "audit-return";

                (new TransactionController())->chequeRevert1($request["bank_id"], $request["cheque_no"], $process, $request);
//        Cheque::where("transaction_id", $transaction->id)->update([
//          "is_returned" => true,
//        ]);
                // if ($transaction->document_id == 8 && !$transaction->is_for_voucher_audit) {
                //   $status = "audit-return";

                //   $transaction->update([
                //     "is_for_voucher_audit" => null,
                //   ]);
                // }

                // if (
                //   $transaction->document_id == 8 &&
                //   $transaction->is_for_voucher_audit == false &&
                //   $transaction->status == "inspect-inspect"
                // ) {
                //   $status = "inspect-return";

                //   $transaction->update([
                //     "is_for_voucher_audit" => null,
                //   ]);
                // }

                // if ($transaction->document_id === 8 && $transaction->is_for_voucher_audit == true) {
                //   $status = "inspect-return";

                //   $transaction->update([
                //     "is_for_voucher_audit" => null,
                //   ]);
                // }
            } elseif ($subprocess == "void") {
                $status = "audit-void";
            } elseif ($subprocess == "audit") {
                $status = "audit-audit";
                $audit_date = $date_now;
                $type = "cheque";
                // if ($transaction->document_id === 8 && $transaction->is_for_voucher_audit == true) {
                //   $subprocess = "inspect";
                //   $status = "inspect-inspect";
                //   $type = "voucher";

                //   $transaction->update([
                //     "is_for_voucher_audit" => false,
                //   ]);
                // }
                $transaction->update([
                    "is_for_voucher_audit" => null,
                ]);
                // $audit->auditCheque($id, null, $status, $reason_id, $reason_remarks, $audit_by, $audit_date, "cheque");
            } elseif (in_array($subprocess, ["unhold", "unreturn"])) {
                // if ($transaction->document_id === 8 && $transaction->status == "inspect-return") {
                //   $process = "inspect";
                //   $transaction->update([
                //     "is_for_voucher_audit" => true,
                //   ]);
                // }

                // if ($transaction->document_id === 8 && $transaction->status == "audit-return") {
                //   $transaction->update([
                //     "is_for_voucher_audit" => false,
                //   ]);
                // }

                if ($transaction->document_id === 8) {
                    $transaction->update([
                        "is_for_voucher_audit" => false,
                    ]);
                }
                $status = GenericMethod::getStatus($process, $transaction);
            }

            if (!isset($status)) {
                return GenericMethod::resultResponse("invalid-access", "", "");
            }

            $state = $subprocess;
            $generic->auditCheque($id, null, $status, $reason_id, $reason_remarks, $audit_by, null, $type);

            // if ($state == "inspect" && $transaction->is_for_voucher_audit == true) {
            //   if ($type === "voucher") {
            //     $audit->auditCheque($id, $date_now, $status, $reason_id, $reason_remarks, $audit_by, $audit_date, "voucher");
            //   }
            // } elseif ($status == "audit-audit") {
            //   $audit->auditCheque($id, $date_now, $status, $reason_id, $reason_remarks, $audit_by, $audit_date, "cheque");
            // } elseif ($status == "inspect-inspect") {
            //   $audit->auditCheque($id, $date_now, $status, $reason_id, $reason_remarks, $audit_by, $audit_date, "voucher");
            // }

            GenericMethod::updateTransactionStatus(
                $id,
                $transaction_id,
                $request_id,
                $receipt_type,
                $tag_no,
                $status,
                $state,
                $reason_id,
                $reason_description,
                $reason_remarks,
                $voucher_no,
                $voucher_month,
                $distributed_id,
                $distributed_name,
                $approver_id,
                $approver_name,
                $inputTax,
                $transaction_type,
                $assigned_id,
                $charge_id
            );
        } elseif ($process == "inspect") {
            $date_now = Carbon::now("Asia/Manila")->format("Y-m-d H:i:s");
            $type = "voucher";
            $audit_by = auth()->user()->id;
            if ($subprocess == "receive") {
                $status = "inspect-receive";
                // if ($transaction->document_id === 8 && $transaction->is_for_voucher_audit == true) {
                //   $status = "inspect-receive";
                // }

                // if ($transaction->is_for_voucher_audit == true) {
                //   $voucher->auditCheque($id, $date_now, $status, $reason_id, $reason_remarks, null, null, "voucher");
                // }
            } elseif ($subprocess == "inspect") {
                $status = "inspect-inspect";
                $audit_date = $date_now;
                $type = "voucher";

                if ($transaction->document_id === 9) {
                    $transaction->update([
                        "is_for_releasing" => true,
                    ]);
                } else {
                    $transaction->update([
                        "is_for_voucher_audit" => false,
                    ]);
                }

                // $voucher->auditCheque($id, null, $status, $reason_id, $reason_remarks, $audit_by, $audit_date, $type);
            } elseif ($subprocess == "return") {
                $status = "inspect-return";

                // $transaction->update([
                //   "is_for_voucher_audit" => null,
                // ]);
            } elseif ($subprocess == "hold") {
                $status = "inspect-hold";
            } elseif ($subprocess == "void") {
                $status = "inspect-void";
            } elseif (in_array($subprocess, ["unhold", "unreturn"])) {
                $status = GenericMethod::getStatus($process, $transaction);

                // $transaction->update([
                //   "is_for_voucher_audit" => true,
                // ]);
            }

            if (!isset($status)) {
                return GenericMethod::resultResponse("invalid-access", "", "");
            }

            $state = $subprocess;
            $generic->auditCheque($id, null, $status, $reason_id, $reason_remarks, $audit_by, null, $type);

            GenericMethod::updateTransactionStatus(
                $id,
                $transaction_id,
                $request_id,
                $receipt_type,
                $tag_no,
                $status,
                $state,
                $reason_id,
                $reason_description,
                $reason_remarks,
                $voucher_no,
                $voucher_month,
                $distributed_id,
                $distributed_name,
                $approver_id,
                $approver_name,
                $inputTax,
                $transaction_type,
                $assigned_id,
                $charge_id
            );
        } elseif ($process == "executive") {
            $date_now = Carbon::now("Asia/Manila")->format("Y-m-d H:i:s");
            // $transaction = Transaction::find($id);

            if ($subprocess == "receive") {
                $status = "executive-receive";
                $generic->executiveSign($id, $date_now, $status, $reason_id, $reason_remarks);
            } elseif ($subprocess == "hold") {
                $status = "executive-hold";
            } elseif ($subprocess == "return") {
                $status = "executive-return";
            } elseif ($subprocess == "void") {
                $status = "executive-void";
            } elseif ($subprocess == "executive") {
                $status = "executive-executive";
                $signed_date = $date_now;
                $signed_by = auth()->user()->id;
                $subprocess = "transmit";

                $transaction->update([
                    "is_for_releasing" => true,
                ]);

                if ($transaction->document_id === 8) {
                    $transaction->update([
                        "is_for_voucher_audit" => null,
                    ]);
                }
                // $transaction->update([
                //   "is_for_voucher_audit" => null,
                // ]);
                $generic->executiveSign($id, null, $status, $reason_id, $reason_remarks, $signed_by, $signed_date);
            } elseif (in_array($subprocess, ["unhold", "unreturn"])) {
                $status = GenericMethod::getStatus($process, $transaction);
            }

            if (!isset($status)) {
                return GenericMethod::resultResponse("invalid-access", "", "");
            }

            $state = $subprocess;

            // if ($subprocess == "executive sign") {
            //   $executive->executiveSign($id, null, $status, $reason_id, $reason_remarks, $signed_by, $signed_date);
            // } else {
            //   $executive->executiveSign($id, $date_now, $status, $reason_id, $reason_remarks);
            // }

            GenericMethod::updateTransactionStatus(
                $id,
                $transaction_id,
                $request_id,
                $receipt_type,
                $tag_no,
                $status,
                $state,
                $reason_id,
                $reason_description,
                $reason_remarks,
                $voucher_no,
                $voucher_month,
                $distributed_id,
                $distributed_name,
                $approver_id,
                $approver_name,
                $inputTax,
                $transaction_type,
                $assigned_id,
                $charge_id
            );
        } elseif ($process == "release") {
            $model = new Release();
            if ($subprocess == "receive") {
                $status = "release-receive";
            } elseif ($subprocess == "return") {
                $status = "release-return";
                (new TransactionController())->chequeRevert1($request["bank_id"], $request["cheque_no"], $process, $request);
            } elseif ($subprocess == "release") {
                $status = "release-release";
            } elseif (in_array($subprocess, ["unreturn"])) {
                $status = GenericMethod::getStatus($process, $transaction);
            }
            if (!isset($status)) {
                return GenericMethod::resultResponse("invalid-access", "", "");
            }
            $state = $subprocess;
            GenericMethod::releaseTransaction(
                $model,
//        $transaction_id,
                $transaction->id,
                $tag_no,
                $remarks,
                $date_now,
                $reason_id,
                $reason_remarks,
                $status,
                $distributed_to
            );
            GenericMethod::updateTransactionStatus(
                $id,
//        $transaction_id,
                $transaction->id,
                $request_id,
                $receipt_type,
                $tag_no,
                $status,
                $state,
                $reason_id,
                $reason_description,
                $reason_remarks,
                $voucher_no,
                $voucher_month,
                $distributed_id,
                $distributed_name,
                $approver_id,
                $approver_name,
                $inputTax,
                $transaction_type,
                $assigned_id,
                $charge_id
            );
        } elseif ($process == "file") {
            $model = new File();
            if ($subprocess == "receive") {
                $status = "file-receive";
            } elseif ($subprocess == "return") {
                $status = "file-return";

                (new TransactionController())->chequeRevert($transaction->id, $request);
            } elseif ($subprocess == "file") {
                $status = "file-file";
            } elseif (in_array($subprocess, ["unreturn"])) {
                $status = GenericMethod::getStatus($process, $transaction);
            }

            if (!isset($status)) {
                return GenericMethod::resultResponse("invalid-access", "", "");
            }

            $state = $subprocess;
            GenericMethod::fileTransaction(
                $model,
//        $transaction_id,
                $transaction->id,
                $tag_no,
                $reason_remarks,
                $date_now,
                $reason_id,
                $status,
                $receipt_type,
                $percentage_tax = 0,
                $withholding_tax = 0,
                $net_amount = 0,
                $voucher_no,
                [],
                []
            );
            GenericMethod::updateTransactionStatus(
                $id,
//        $transaction_id,
                $transaction->id,
                $request_id,
                $receipt_type,
                $tag_no,
                $status,
                $state,
                $reason_id,
                $reason_description,
                $reason_remarks,
                $voucher_no,
                $voucher_month,
                $distributed_id,
                $distributed_name,
                $approver_id,
                $approver_name,
                $inputTax,
                $transaction_type,
                $assigned_id,
                $charge_id,
                $request->box_no ?? $transaction->box_no
            );
        } elseif ($process == "reverse") {
            $model = new Reverse();
            $role = Auth::user()->role;

            if ($role == "AP Associate" || $role == "AP Specialist") {
                if ($subprocess == "receive-approver") {
                    $status = "reverse-receive-approver";
                } elseif ($subprocess == "approve") {
                    $status = "reverse-approve";
                }

                if (!isset($status)) {
                    return GenericMethod::resultResponse("invalid-access", "", "");
                }
            } else {
                if ($subprocess == "request") {
                    $status = "reverse-request";
                } elseif ($subprocess == "receive-requestor") {
                    $status = "reverse-receive-requestor";
                } elseif ($subprocess == "return") {
                    $status = "reverse-return";
                }
            }

            if (!isset($status)) {
                return GenericMethod::resultResponse("invalid-access", "", "");
            }
            $state = $subprocess;
            GenericMethod::reverseTransaction(
                $model,
                $transaction_id,
                $tag_no,
                $reason_remarks,
                $date_now,
                $reason_id,
                $status,
                $role,
                $distributed_to
            );
            GenericMethod::updateTransactionStatus(
                $id,
                $transaction_id,
                $request_id,
                $receipt_type,
                $tag_no,
                $status,
                $state,
                $reason_id,
                $reason_description,
                $reason_remarks,
                $voucher_no,
                $voucher_month,
                $distributed_id,
                $distributed_name,
                $approver_id,
                $approver_name,
                $inputTax,
                $transaction_type,
                $assigned_id,
                $charge_id
            );
            return GenericMethod::resultResponse($state, "", "");

        } elseif ($process == "clear") {
            $account_titles = $cheque_account_titles;
            $model = new Clear();
            if ($subprocess == "receive") {
                $status = "clear-receive";
            } elseif ($subprocess == "clear") {
                $status = "clear-clear";
            }

            if (!isset($status)) {
                return GenericMethod::resultResponse("invalid-access", "", "");
            }

//      $state = $subprocess;
            $state = $transaction->state;
            GenericMethod::clearTransaction($model, $tag_no, $date_now, $status, $account_titles, $subprocess, $date_cleared, $transaction->id);
            GenericMethod::updateTransactionStatus(
                $id,
                $transaction_id,
                $request_id,
                $receipt_type,
                $tag_no,
                $status,
//          $transaction->status,
                $state,
                $reason_id,
                $reason_description,
                $reason_remarks,
                $voucher_no,
                $voucher_month,
                $distributed_id,
                $distributed_name,
                $approver_id,
                $approver_name,
                $inputTax,
                $transaction_type,
                $assigned_id,
                $charge_id,
                $request->box_no ?? $transaction->box_no
            );
        } elseif ($process == "issue") {
            $account_titles = $cheque_account_titles;
            $cheques = $cheque_cheques;
            $model = new Treasury();
            if ($subprocess == "receive") {
                $status = "issue-receive";
            } elseif ($subprocess == "issue") {
                $status = "issue-issue";

                $document_amount = $transaction["document_amount"];
                if (!$document_amount) {
                    $document_amount = $transaction["referrence_amount"];
                }

                if (!empty($cheques)) {
                    $cheque_amount = array_sum(array_column($cheques, "amount"));
                    $cheque_amount = isset($new_cheque_amount) ? $new_cheque_amount : $cheque_amount;

                    // if ($document_amount != $cheque_amount) {
                    //   return GenericMethod::resultResponse("not-equal", "Document and cheque", []);
                    // }

//          switch ($transaction->document_id) {
//            case 3:
//                  switch ($transaction->category) {
//
//                      case 'rental':
//                          if ($transaction->gross_amount != $cheque_amount) {
//                              return GenericMethod::resultResponse("not-equal", "Document and account title", []);
//                          }
//                          break;
//
//                      default:
//
//                          if (floatval((number_format(($transaction->principal + $transaction->interest), 2, '.', ''))) != $cheque_amount) {
//                              return GenericMethod::resultResponse("not-equal", "Document and account title", []);
//                          }
//                  }
//                  break;
//
//            default:
//              if ($document_amount != $cheque_amount) {
//                return GenericMethod::resultResponse("not-equal", "Document and cheque", []);
//              }
//              break;
//          }
                }

                if (!empty($account_titles)) {
                    $debit_entries_amount = array_filter($account_titles, function ($account_title) {
                        if (isset($account_title["transaction_type"])) {
                            return strtolower($account_title["entry"]) != "credit" && $account_title["transaction_type"] == "new";
                        }
                        return strtolower($account_title["entry"]) != "credit";
                    });

                    $credit_entries_amount = array_filter($account_titles, function ($account_title) {
                        if (isset($account_title["transaction_type"])) {
                            return strtolower($account_title["entry"]) != "debit" && $account_title["transaction_type"] == "new";
                        }
                        return strtolower($account_title["entry"]) != "debit";
                    });

                    $debit_amount = array_sum(array_column($debit_entries_amount, "amount"));
                    $credit_amount = array_sum(array_column($credit_entries_amount, "amount"));

                    if ($debit_amount != $credit_amount) {
                        return GenericMethod::resultResponse("not-equal", "Total debit and credit", []);
                    }

                    if ($cheque_amount != $debit_amount) {
                        return GenericMethod::resultResponse("not-equal", "Cheque and account title", []);
                    }
                }

//          $not_valid = GenericMethod::validateCheque($id, $cheques);
//          if ($not_valid) {
//              return GenericMethod::resultResponse("cheque-no-exist", "Cheque_no number already exist.", []);
//          }

            } elseif ($subprocess == "hold") {
                $status = "issue-hold";
            } elseif ($subprocess == "return") {
                $status = "issue-return";
                (new TransactionController())->chequeRevert1($request["bank_id"], $request["cheque_no"], $process, $request);
            } elseif ($subprocess == "void") {
                $status = "issue-void";
            } elseif (in_array($subprocess, ["unhold", "unreturn"])) {
                $status = GenericMethod::getStatus($process, $transaction);
            }

            if (!isset($status)) {
                return GenericMethod::resultResponse("invalid-access", "", "");
            }

            $subprocess == "issue" ? $state = "release" : $state = $subprocess;
//      $state = $subprocess;
//      $generic->auditCheque($id, null, $status, $reason_id, $reason_remarks, null, null, "date");
            $generic->issue($id, $status, $reason_id, $reason_remarks);


            if ($subprocess == "issue") {
                GenericMethod::chequeTransaction(
                    $model,
//        $transaction_id,
                    $transaction->id,
                    $tag_no,
                    $reason_remarks,
                    $date_now,
                    $reason_id,
                    'cheque-cheque',
                    $cheques,
                    $account_titles
                );
            }
            GenericMethod::updateTransactionStatus(
                $id,
//        $transaction_id,
                $transaction->id,
                $request_id,
                $receipt_type,
                $tag_no,
                $status,
                $state,
                $reason_id,
                $reason_description,
                $reason_remarks,
                $voucher_no,
                $voucher_month,
                $distributed_id,
                $distributed_name,
                $approver_id,
                $approver_name,
                $inputTax,
                $transaction_type,
                $assigned_id,
                $charge_id
            );
        } elseif ($process == "debit") {
            $account_titles = $accounts;
            if ($subprocess == "receive") {
                $status = "debit-receive";
            } elseif ($subprocess == "file") {
                $status = "debit-file";
                if (!empty($account_titles)) {
                    $debit_entries_amount = array_filter($account_titles, function ($account_title) {
                        return strtolower($account_title["entry"]) != strtolower("credit");
                    });

                    $credit_entries_amount = array_filter($account_titles, function ($account_title) {
                        return strtolower($account_title["entry"]) != strtolower("debit");
                    });

                    $debit_amount = array_sum(array_column($debit_entries_amount, "amount"));
                    $credit_amount = array_sum(array_column($credit_entries_amount, "amount"));

                    switch ($transaction->document_id) {
                        case 3:
                            if ($debit_amount != $credit_amount) {
                                return GenericMethod::resultResponse("not-equal", "Total debit and credit", []);
                            }
                            if ($transaction->net_amount != $debit_amount) {
                                return GenericMethod::resultResponse("not-equal", "Net amount and account title", []);
                            }

                            break;

                        default:
                            if ($debit_amount != $credit_amount) {
                                return GenericMethod::resultResponse("not-equal", "Total debit and credit", []);
                            }

                            if ($transaction->document_amount != $debit_amount) {
                                return GenericMethod::resultResponse("not-equal", "Document and account title", []);
                            }
                    }
                }
                ClearingAccountTitle::where('clear_id', $tag_no)->delete();
                foreach ($account_titles as $account_title) {
                    ClearingAccountTitle::create([
                        'clear_id' => $tag_no,
                        'entry' => $account_title['entry'],
                        'account_title_id' => $account_title['account_title']['id'],
                        'account_title_name' => $account_title['account_title']['name'],
                        'amount' => $account_title['amount'],
                        'remarks' => $account_title['remarks'],
                        'transaction_type' => 'debit'
                    ]);
                }
            } elseif ($subprocess == "return") {
                $status = "debit-return";
            } elseif ($subprocess == "hold") {
                $status = "debit-hold";
            } elseif ($subprocess == "void") {
                $status = "debit-void";
            } elseif (in_array($subprocess, ["unhold", "unreturn"])) {
                $status = GenericMethod::getStatus($process, $transaction);
            }

            if (!isset($status)) {
                return GenericMethod::resultResponse("invalid-access", "", "");
            }

            $state = $subprocess;
//      return $transaction->document_amount;
            Filing::create([
                "tag_id" => $transaction->id,
                "date_received" => $date_now,
                "status" => $status,
                "date_status" => $date_now,
                "reason_id" => $reason_id,
                "remarks" => $reason_remarks,
            ]);

            GenericMethod::updateTransactionStatus(
                $id,
                $transaction_id,
                $request_id,
                $receipt_type,
                $tag_no,
                $status,
                $state,
                $reason_id,
                $reason_description,
                $reason_remarks,
                $voucher_no,
                $voucher_month,
                $distributed_id,
                $distributed_name,
                $approver_id,
                $approver_name,
                $inputTax,
                $transaction_type,
                $assigned_id,
                $charge_id
            );
        } elseif ($process == 'gas') {
            if ($subprocess == 'receive') {
                $status = 'gas-receive';
            } elseif ($subprocess == 'gas') {
                $status = 'gas-gas';
            } elseif ($subprocess == 'return') {
                $status = 'gas-return';
            } elseif ($subprocess == 'hold') {
                $status = 'gas-hold';
            } elseif ($subprocess == 'void') {
                $status = 'gas-void';
            } elseif (in_array($subprocess, ['unhold', 'unreturn'])) {
                $status = GenericMethod::getStatus($process, $transaction);
            }

            $state = $subprocess;
            if ($subprocess == 'gas') {
                $state = 'transmit';
            }

            $generic->gasTransaction($id, $status, $reason_id, $reason_remarks);
            GenericMethod::updateTransactionStatus(
                $id,
                $transaction_id,
                $request_id,
                $receipt_type,
                $tag_no,
                $status,
                $state,
                $reason_id,
                $reason_description,
                $reason_remarks,
                $voucher_no,
                $voucher_month,
                $distributed_id,
                $distributed_name,
                $approver_id,
                $approver_name,
                $inputTax,
                $transaction_type,
                $assigned_id,
                $charge_id
            );
        } elseif ($process == 'discharge') {
            if ($subprocess == 'receive') {
                $status = 'discharge-receive';
            } elseif ($subprocess == 'discharge') {
                $subprocess = 'transmit';
                $status = 'discharge-discharge';
            } elseif ($subprocess == 'return') {
                $status = 'discharge-return';
            } elseif ($subprocess == 'hold') {
                $status = 'discharge-hold';
            } elseif ($subprocess == 'void') {
                $status = 'discharge-void';
            } elseif (in_array($subprocess, ['unhold', 'unreturn'])) {
                $status = GenericMethod::getStatus($process, $transaction);
            }

            $state = $subprocess;

            $generic->gasTransaction($id, $status, $reason_id, $reason_remarks);

            GenericMethod::updateTransactionStatus(
                $id,
                $transaction_id,
                $request_id,
                $receipt_type,
                $tag_no,
                $status,
                $state,
                $reason_id,
                $reason_description,
                $reason_remarks,
                $voucher_no,
                $voucher_month,
                $distributed_id,
                $distributed_name,
                $approver_id,
                $approver_name,
                $inputTax,
                $transaction_type,
                $assigned_id,
                $charge_id
            );
        } elseif ($process == 'pass') {
            if ($subprocess == 'receive') {
                $status = 'pass-receive';
            } elseif ($subprocess == 'pass') {
                $subprocess = 'transmit';
                $status = 'pass-pass';
            }

            $state = $subprocess;

            GenericMethod::updateTransactionStatus(
                $id,
                $transaction_id,
                $request_id,
                $receipt_type,
                $tag_no,
                $status,
                $state,
                $reason_id,
                $reason_description,
                $reason_remarks,
                $voucher_no,
                $voucher_month,
                $distributed_id,
                $distributed_name,
                $approver_id,
                $approver_name,
                $inputTax,
                $transaction_type,
                $assigned_id,
                $charge_id
            );
        }
        $transaction->touch();

        return GenericMethod::resultResponse($state, "", "");
    }

    public static function validateVoucherNo($request)
    {
        $voucher_no = $request["voucher_no"];
        $id = $request["id"];
        $transaction = Transaction::where("voucher_no", $voucher_no)
            ->where("id", "<>", $id)
            ->where("state", "!=", "void")
            ->exists();

        if ($transaction) {
            $errorMessage = GenericMethod::resultLaravelFormat("voucher.no", ["Voucher number already exist."]);
            return GenericMethod::resultResponse("invalid", "", $errorMessage);
        }
        return GenericMethod::resultResponse("success-no-content", "", []);
    }

    public static function validateChequeNo($request)
    {
        $cheque_no = $request["cheque_no"];
        $bank_id = $request->bank_id;
        $id = $request["id"];

//        $transaction = Transaction::whereHas("cheques.cheques", function ($query) use ($cheque_no, $bank_id, $id) {
//            $query->where("cheque_no", $cheque_no)
//                ->where("bank_id", $bank_id);
//        })
//            ->exists();

        $cheque_transaction_id = Cheque::withTrashed()
            ->where("cheque_no", $cheque_no)
            ->where("bank_id", $bank_id)
            ->pluck("transaction_id")
            ->toArray();

        $transaction = Cheque::withTrashed()
            ->where('cheque_no', $cheque_no)
            ->where('bank_id', $bank_id)
//          ->where('transaction_id', '<>', $id)
            ->when(in_array($id, $cheque_transaction_id), function ($query) use ($id) {
                $query->whereNotNull('reason_id');
            })
            ->whereNotNull('is_cancelled')
            ->exists();

        if ($transaction) {
            $errorMessage = GenericMethod::resultLaravelFormat("cheque.no", ["Cheque number already exist."]);
            return GenericMethod::resultResponse("invalid", "", $errorMessage);
        }
        return GenericMethod::resultResponse("success-no-content", "", []);
    }

    public function bankDocuments(Request $request) {
        return BankSeries::where('bank_id', $request->bank_id)
            ->where('is_used', false)
            ->get(['id', 'document_name']);
    }

    public function availableChequeNo(Request $request) {
        $bankSeriesId = $request->bank_series_id;
        $temporaryUsedCheques = $request->temporary_used_cheques ?? [];

        $chequeSeries = BankSeries::where('id', $bankSeriesId)
            ->where('is_used', false)
            ->select('bank_id', 'from', 'to')
            ->first();

        if (!$chequeSeries) {
            return response()->json(['message' => 'No available cheque series found,'], 404);
        }

        $alreadyUsedChequeNos = Cheque::where('bank_id', $chequeSeries->bank_id)
            ->whereNull('is_cancelled')
            ->pluck('cheque_no')
            ->toArray();

        // Exclude both already used and temporary used cheques
        $excludeCheques = array_merge($alreadyUsedChequeNos, $temporaryUsedCheques);
        $availableChequeNos = array_diff(range($chequeSeries->from, $chequeSeries->to), $excludeCheques);
        $availableChequeNos = array_filter($availableChequeNos, function($no) { return $no != 0; });
        $firstAvailable = reset($availableChequeNos);

        if (!$firstAvailable) {
            return response()->json(['message' => 'No available cheque number found.'], 404);
        }

        return response()->json(['available_cheque_no' => $firstAvailable], 200);
    }

    public static function transfer($request, $id)
    {
        $user_info = Auth::user();
        $from_user_id = $user_info->id;
        $from_full_name = GenericMethod::getFullnameNoMiddle(
            $user_info->first_name,
            $user_info->last_name,
            $user_info->suffix
        );
        $to_user_id = $request["id"];
        $to_full_name = $request["name"];

        GenericMethod::transferTransaction($id, $from_user_id, $from_full_name, $to_user_id, $to_full_name);
        return GenericMethod::resultResponse("transfer", "", "");
    }

    public static function voidTransaction($request, $id)
    {
//      $void = new TransactionController();
//      $void->voidTransaction($request, $id);
        (new TransactionController())->voidTransaction($request, $id);
    }

    public function chequeFlow(Request $request)
    {
        $bankId = $request->bank_id ?? data_get($request, 'cheques.bank_id');
        $chequeNo = $request->cheque_no ?? data_get($request, 'cheques.cheque_no');

        $context = [
            "process" => $request->process,
            "subprocess" => $request->subprocess,
            "cheques" => $request->cheques,
            "cheque" => $request->cheque,
            "accounts" => $request->accounts,
            "bank_id" => $bankId,
            "cheque_no" => $chequeNo,

            "reason" => [
                "id" => data_get($request, 'reason.id'),
                "description" => data_get($request, 'reason.description'),
                "remarks" => data_get($request, 'reason.remarks')
            ]
        ];

        $transactionIds = Cheque::where('bank_id', $bankId)
            ->where('cheque_no', $chequeNo)
            ->pluck('transaction_id')
            ->unique()
            ->toArray();

        if (!empty($transactionIds)) {

            switch ($context['subprocess']) {
                case 'issue':
                    return $this->issueCheque($request, $transactionIds);
                    break;
                case 'release':
                    return $this->releaseCheque($request, $transactionIds);
                    break;
                case 'clear':
                    return $this->clearCheque($request, $transactionIds);
                    break;
                case 'executive':
                case 'audit':
                    return $this->chequeTransmit($request, $transactionIds);
                    break;
                case 'receive':
                    return $this->chequeSingleReceive($request, $transactionIds);
                    break;
                case 'return':
                    Cheque::whereIn('transaction_id', $transactionIds)->update(['is_received' => null]);
                    break;
                case 'unhold':
                case 'unreturn':
                    return $this->unreturnUnhold($transactionIds, $context);
                    break;
                case 'cancel':
                    return $this->cancelCheque($request);
                case 'abort':
                    return $this->abortCheque($request);
                case 'decline':
                    return $this->declineCheque($request);
            }

            foreach ($transactionIds as $transactionId) {
                static::updateInTransactionFlow($context, $transactionId);
            }

            return GenericMethod::resultResponse($request->subprocess, "", "");
        } else {
            return GenericMethod::resultResponse("not-found", "", null);
        }
    }

    public function multipleChequeProcess(Request $request)
    {
        $cheques = collect($request->input('cheques'));

        $cheques->each(function ($cheque) use ($request) {
            $chequeRequest = new Request(array_merge($request->all(), ['cheques' => $cheque]));
            $this->chequeFlow($chequeRequest);
        });

        return GenericMethod::resultResponse($request->subprocess, "", "");
    }
    //Issue Cheque
//    function issueCheque($request, $transactionIds) {
//
//        for($i = 0; $i < count($transactionIds); $i++) {
//
//            Cheque::where('transaction_id', $transactionIds[$i])
//                ->where('bank_id', data_get($request, 'cheque.bank.id'))
//                ->where('cheque_no', data_get($request, 'cheque.no'))
//                ->whereNull('issue_id')->delete();
//
//            $transaction = Transaction::find($transactionIds[$i]);
//
//            $issue = $transaction->issue()->create([
//                'status' => 'issue-issue',
//            ]);
//
//            for ($j = 0; $j < count($request->accounts); $j++) {
//                $account = $request->accounts[$j];
//
//                $issue->accountTitles()->create([
//                    'entry' => data_get($account, 'entry'),
//                    'account_title_id' => data_get($account, 'account_title.id'),
//                    'account_title_code' => data_get($account, 'account_title.code'),
//                    'account_title_name' => data_get($account, 'account_title.name'),
//                    'amount' => data_get($account, 'amount'),
//                    'remarks' => data_get($account, 'remarks'),
//                    'transaction_type' => 'new',
//                    'company_id' => data_get($account, 'company.id'),
//                    'company_code' => data_get($account, 'company.code'),
//                    'company_name' => data_get($account, 'company.name'),
//                    'department_id' => data_get($account, 'department.id'),
//                    'department_code' => data_get($account, 'department.code'),
//                    'department_name' => data_get($account, 'department.name'),
//                    'location_id' => data_get($account, 'location.id'),
//                    'location_code' => data_get($account, 'location.code'),
//                    'location_name' => data_get($account, 'location.name'),
//                    'business_unit_id' => data_get($account, 'business_unit.id'),
//                    'business_unit_code' => data_get($account, 'business_unit.code'),
//                    'business_unit_name' => data_get($account, 'business_unit.name'),
//                    'sub_unit_id' => data_get($account, 'sub_unit.id'),
//                    'sub_unit_code' => data_get($account, 'sub_unit.code'),
//                    'sub_unit_name' => data_get($account, 'sub_unit.name'),
//                ]);
//            }
//
//            $issue->issueCheques()->create([
//                'transaction_id' => $transactionIds[$i],
//                'entry_type' => data_get($request, 'cheque.type'),
//                'bank_id' => data_get($request, 'cheque.bank.id'),
//                'bank_name' => data_get($request, 'cheque.bank.name'),
//                'cheque_no' => data_get($request, 'cheque.no'),
//                'cheque_date' => date('Y-m-d', strtotime(data_get($request, 'cheque.date'))),
//                'cheque_amount' => data_get($request, 'cheque.amount'),
//                'transaction_type' => 'new',
//                'is_issued' => true,
//            ]);
//        }
//
//        $this->chequeIssueChecker($request, $transactionIds);
//
//        return GenericMethod::resultResponse($request->subprocess, "", "");
//    }

    function issueCheque($request, $transactionIds)
    {
        for ($i = 0; $i < count($transactionIds); $i++) {

            $transaction = Transaction::find($transactionIds[$i]);

            $issue = $transaction->issue()->create([
                'status' => 'issue-issue',
            ]);

            for ($j = 0; $j < count($request->accounts); $j++) {
                $account = $request->accounts[$j];

                $issue->accountTitles()->create([
                    'entry' => data_get($account, 'entry'),
                    'bank_id' => data_get($account, 'bank_id'),
                    'account_title_id' => data_get($account, 'account_title.id'),
                    'account_title_code' => data_get($account, 'account_title.code'),
                    'account_title_name' => data_get($account, 'account_title.name'),
                    'amount' => data_get($account, 'amount'),
                    'remarks' => data_get($account, 'remarks'),
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
                    'unit_id' => data_get($account, 'unit.id'),
                    'unit_code' => data_get($account, 'unit.code'),
                    'unit_name' => data_get($account, 'unit.name'),
                    'sub_unit_id' => data_get($account, 'sub_unit.id'),
                    'sub_unit_code' => data_get($account, 'sub_unit.code'),
                    'sub_unit_name' => data_get($account, 'sub_unit.name'),
                ]);
            }

            Cheque::where('transaction_id', $transactionIds[$i])
//                ->where('bank_id', data_get($request, 'cheque.bank.id'))
//                ->where('cheque_no', data_get($request, 'cheque.no'))
                ->where([
                    'bank_id' => data_get($request, 'cheque.bank.id'),
                    'cheque_no' => data_get($request, 'cheque.no')
                ])
                ->update([
                    'is_received' => null,
                    'is_issued' => true,
                    'cheque_date' => date('Y-m-d', strtotime(data_get($request, 'cheque.date'))),
                    'issue_id' => $issue->id,
                    'reason_id' => null,
                    'reason' => null
                ]);
        }

        $this->chequeIssueChecker($request, $transactionIds);

//        foreach($transactionIds as $transaction) {
//            $transaction = Transaction::find($transaction);
//
//            if ($transaction->is_mc == 1) {
//                Cheque::where('transaction_id', $transaction->id)
//                    ->update([
//                        'is_released' => true
//                    ]);
//
//                Transaction::where('id', $transaction->id)
//                    ->update([
//                        'state' => 'release',
//                        'status' => 'release-release'
//                    ]);
//            }
//        }

        return GenericMethod::resultResponse($request->subprocess, "", "");
    }

    //Release Cheque
    function releaseCheque($request, $transactionIds)
    {

        $cheques = collect(Cheque::whereIn('transaction_id', $transactionIds)
            ->pluck('is_received')->toArray());

        if ($cheques->contains(null)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Other Cheque is not yet received.'
            ], 400);
        }

        for ($i = 0; $i < count($transactionIds); $i++) {

            $transaction = Transaction::find($transactionIds[$i]);

            $transaction->release()->create([
                'status' => 'release-release',
                'distributed_id' => data_get($request, 'distributed_to.id'),
                'distributed_name' => data_get($request, 'distributed_to.name'),
                'description' => $transaction->remarks,
                'date_status' => Carbon::now('Asia/Manila')->format('Y-m-d H:i:s'),
            ]);
        }

        Cheque::whereIn('transaction_id', $transactionIds)
            ->whereNull('is_released')
            ->update([
                'is_released' => true,
                'is_uncollected' => false,
                'uncollected_date' => null,
            ]);

        Transaction::whereIn('id', $transactionIds)->update([
            'state' => $request->subprocess,
            'status' => $request->process . '-' . $request->subprocess,
        ]);

        return GenericMethod::resultResponse($request->subprocess, "", "");
    }

    //Clear Cheque
    function clearCheque($request, $transactionIds)
    {
        for ($i = 0; $i < count($transactionIds); $i++) {

            $cheque = Cheque::where('bank_id', data_get($request, 'bank_id'))
                ->where('cheque_no', data_get($request, 'cheque_no'))
                ->first();

            Cheque::where('bank_id', data_get($request, 'bank_id'))
                ->where('cheque_no', data_get($request, 'cheque_no'))
                ->update([
                    'date_cleared' => date('Y-m-d', strtotime(data_get($request, 'date'))),
                    'is_cleared' => true,
                ]);

            $transaction = Transaction::find($transactionIds[$i]);

            $clear = $transaction->clear()->create([
                'status' => 'clear-clear',
                'user_id' => auth()->user()->id,
                'tag_id' => $transaction->id,
                'date_status' => Carbon::now('Asia/Manila')->format('Y-m-d H:i:s'),
            ]);

            for ($j = 0; $j < count($request->accounts); $j++) {
                $account = $request->accounts[$j];

                $clear->account_title()->create([
                    'entry' => data_get($account, 'entry'),
                    'cheque_id' => $cheque->id,
                    'account_title_id' => data_get($account, 'account_title.id'),
                    'account_title_code' => data_get($account, 'account_title.code'),
                    'account_title_name' => data_get($account, 'account_title.name'),
                    'amount' => data_get($account, 'amount'),
                    'remarks' => data_get($account, 'remarks'),
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
                    'unit_id' => data_get($account, 'unit.id'),
                    'unit_code' => data_get($account, 'unit.code'),
                    'unit_name' => data_get($account, 'unit.name'),
                    'sub_unit_id' => data_get($account, 'sub_unit.id'),
                    'sub_unit_code' => data_get($account, 'sub_unit.code'),
                    'sub_unit_name' => data_get($account, 'sub_unit.name'),
                ]);
            }
        }

        return GenericMethod::resultResponse($request->subprocess, "", "");
    }


    function chequeSingleReceive($request, $transactionIds)
    {

        foreach ($transactionIds as $transactionId) {

            $transaction = Transaction::find($transactionId);

            switch ($request->process) {
                case 'audit':
                    $transaction->audit()->create([
                        'type' => 'Cheque',
                        'status' => $request->process . '-receive',
                        'date_status' => Carbon::now('Asia/Manila')->format('Y-m-d H:i:s'),
                        'user_id' => auth()->user()->id,
                    ]);
                    break;

                case 'executive':
                    $transaction->executive()->create([
                        'transaction_id' => $transaction,
                        'status' => $request->process . '-receive',
                    ]);
                    break;

                case 'issue':
                    $transaction->issue()->create([
                        'transaction_id' => $transaction,
                        'status' => $request->process . '-receive',
                    ]);
                    break;

                case 'release':
                    $transaction->release()->create([
                        'date_status' => Carbon::now('Asia/Manila')->format('Y-m-d H:i:s'),
                        'transaction_id' => $transaction,
                        'status' => $request->process . '-receive',
                    ]);
                    break;
            }
        }


        Cheque::where('bank_id', $request->bank_id)
            ->where('cheque_no', $request->cheque_no)
            ->update([
                'is_received' => true,
            ]);

        $cheques = Cheque::whereIn('transaction_id', $transactionIds)->whereNull('is_received')->get();

        if ($cheques->isEmpty()) {
            Transaction::whereIn('id', $transactionIds)->update([
                'state' => $request->subprocess,
                'status' => $request->process . '-' . $request->subprocess,
            ]);
        }

        return GenericMethod::resultResponse($request->subprocess, "", "");
    }

    function chequeTransmit($request, $transactionIds)
    {

        $bankId = $request->bank_id ?? data_get($request, 'cheques.bank_id');
        $chequeNo = $request->cheque_no ?? data_get($request, 'cheques.cheque_no');

        foreach ($transactionIds as $transaction) {
            switch ($request->subprocess) {
                case 'audit':
                    $is_processed = 'is_audited';
                    Audit::create([
                        'transaction_id' => $transaction,
                        'type' => 'Cheque',
                        'status' => $request->subprocess . '-' . $request->subprocess,
                        'date_status' => date('Y-m-d'),
                        'user_id' => auth()->user()->id,
                    ]);
                    break;
                case 'executive':
                    $is_processed = 'is_executived';
                    Executive::create([
                        'transaction_id' => $transaction,
                        'status' => $request->subprocess . '-' . $request->subprocess,
                    ]);
                    break;
            }
        }


        Cheque::where('bank_id', $bankId)
            ->where('cheque_no', $chequeNo)
            ->update([
                'is_received' => null,
                $is_processed => true,
            ]);

//        Cheque::whereIn('transaction_id', $transactionIds)
//            ->update([
//                'is_received' => null,
//                $is_processed => true,
//            ]);

        $cheques = Cheque::whereIn('transaction_id', $transactionIds)->whereNull($is_processed)->get();

        if ($cheques->isEmpty()) {
            Transaction::whereIn('id', $transactionIds)->update([
                'state' => $request->subprocess,
                'status' => $request->process . '-' . $request->subprocess,
            ]);
        }

        return GenericMethod::resultResponse($request->subprocess, "", "");

    }

    function unreturnUnhold($transactionIds, $context)
    {


//        $transaction = Transaction::where('id', $transactionIds[0])->first();

        switch ($context['process']) {
            case 'audit':
                $is_processed = 'is_audited';
                break;

            case 'release':
                $is_processed = 'is_released';
//                $issue_id = $transaction->issue->first()->id;
                break;
        }

        $processed = Cheque::whereIn('transaction_id', $transactionIds)
            ->whereNull($is_processed)->count();

        $is_processed = Cheque::whereIn('transaction_id', $transactionIds)
            ->whereNotNull($is_processed)->count();

        $no_of_transaction = count($transactionIds);

        if ($is_processed == ($no_of_transaction || $processed)) {
            Transaction::whereIn('id', $transactionIds)
                ->update([
                    'state' => $context['process'],
                    'status' => $context['process'] . '-' . $context['process'],
                ]);
        }

        if ($processed) {
            Transaction::whereIn('id', $transactionIds)
                ->update([
                    'state' => $context['subprocess'],
                    'status' => $context['process'] . '-receive',
                    'reason_id' => null,
                    'reason' => null,
                    'reason_remarks' => null
                ]);

            Cheque::whereIn('transaction_id', $transactionIds)
                ->update([
//                    'issue_id' => $issue_id,
                    'reason_id' => null,
                    'reason' => null,
                ]);
        }

        return GenericMethod::resultResponse($context['subprocess'], "", "");
    }

    function chequeIssueChecker($request, $transactionIds)
    {

        switch ($request->subprocess) {

            case 'issue':

                $cheques = Cheque::whereIn('transaction_id', $transactionIds)->whereNull('issue_id')->whereNull('deleted_at')->get();

                if ($cheques->isEmpty()) {
                    Transaction::whereIn('id', $transactionIds)->update([
                        'is_for_releasing' => true,
                        'state' => 'transmit',
                        'status' => $request->subprocess . '-' . $request->process,
                    ]);
                }

                break;

            case 'release':

//              Transaction::whereIn('id', $transactionIds)->update([
//                  'state' => $request->subprocess,
//                  'status' => $request->subprocess . '-' . $request->process,
//              ]);

                $cheques = Cheque::whereIn('transaction_id', $transactionIds)->whereNull('is_released')->get();

                if ($cheques->isEmpty()) {
                    Transaction::whereIn('id', $transactionIds)->update([
                        'state' => $request->subprocess,
                        'status' => $request->subprocess . '-' . $request->process,
                    ]);
                }

                break;
        }
    }

    function cancelCheque($request)
    {
        $bank = $request->bank_id;
        $cheque = $request->cheque_no;
        $process = $request->process;
        $subprocess = $request->subprocess;
        $reason_id = data_get($request, 'reason.id');
        $reason = data_get($request, 'reason.reason');


        Cheque::where([
            'bank_id' => $bank,
            'cheque_no' => $cheque
        ])
            ->update([
                'is_cancelled' => 0,
                'reason_id' => $reason_id,
                'reason' => $reason,
            ]);

        return GenericMethod::resultResponse($subprocess, "", "");
    }

    function abortCheque($request)
    {
        $bank = $request->bank_id;
        $cheque = $request->cheque_no;
        $process = $request->process;
        $subprocess = $request->subprocess;

        $cheques = Cheque::where([
            'bank_id' => $bank,
            'cheque_no' => $cheque
        ])->get();

        Transaction::whereIn('id', $cheques->pluck('transaction_id')->toArray())
            ->update([
                'state' => 'void',
                'status' => 'release' . '-' . 'void',
            ]);

        $cheques->each(function ($cheque) {
            $cheque->update([
                'is_cancelled' => 1,
            ]);
        });

        return GenericMethod::resultResponse($subprocess, "", "");
    }

    function declineCheque($request)
    {
        $bank = $request->bank_id;
        $cheque = $request->cheque_no;
        $process = $request->process;
        $subprocess = $request->subprocess;


        Cheque::where([
            'bank_id' => $bank,
            'cheque_no' => $cheque
        ])
            ->update([
                'is_cancelled' => null,
                'reason_id' => null,
                'reason' => null,
            ]);

        return GenericMethod::resultResponse($subprocess, "", "");
    }

    private function validateCutoffDate($voucherMonth, $subprocess)
    {
        if ($this->isCutOffEnabledApproval) {
            $voucherDate = Carbon::parse($voucherMonth);
            $cutoffDate = Carbon::parse($voucherDate)->addMonth()->setDay($this->cutOffApprovalDate);
            $currentDate = Carbon::now();

            if ($currentDate->greaterThanOrEqualTo($cutoffDate)) {
                return response()->json([
                    "message" => "Cannot {$subprocess} this transaction. This transaction has already passed the cutoff date.",
                ], 422);
            }
        }

        return null;
    }
}
