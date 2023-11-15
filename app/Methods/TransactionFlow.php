<?php
namespace App\Methods;

use App\Http\Controllers\TransactionController;
use App\Models\Charging;
use App\Models\ClearingAccountTitle;
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
use App\Methods\GenericMethod;
use App\Models\ChequeClearing;
use App\Models\ChequeCreation;
use App\Models\ChequeReleased;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;

use Illuminate\Validation\ValidationException;
use Illuminate\Pagination\LengthAwarePaginator;

class TransactionFlow
{
  public static function updateInTransactionFlow($request, $id)
  {
    // return GenericMethod::floatvalue('46,072.50');
    $transaction = Transaction::find($id);
    if (!isset($transaction)) {
      return GenericMethod::resultResponse("not-found", "transaction", []);
    }
    $process = $request["process"];
    $subprocess = $request["subprocess"];
    $reason_id = isset($request["reason"]["id"]) ? $request["reason"]["id"] : null;
    $date_now = Carbon::now("Asia/Manila")->format("Y-m-d");
    $generic = new GenericMethod();

    $request_id = $transaction->request_id;
    $transaction_id = $transaction->transaction_id;
//      $transaction_id = $transaction->id;
    $remarks = $transaction->remarks;
    $users_id = $transaction->users_id;

    $typeOfTransactionId = data_get($request,'transaction_type.id') ? data_get($request, 'transaction_type.id') : $transaction->voucher->first()->transaction_type_id ?? null;
    $typeOfTransactionName = data_get($request, 'transaction_type.name') ? data_get($request, 'transaction_type.name') : $transaction->voucher->first()->transaction_type_name ?? null;
    $inputTax = data_get($request, 'input_tax') ? data_get($request, 'input_tax') : $transaction->input_tax ?? null;
    $receipt_type = isset($request->receipt_type) ? $request->receipt_type : $transaction->receipt_type;

    $tag_no = $transaction->tag_no;
    if ($subprocess == "tag") {
        $tag_no = GenericMethod::generateTagNo($receipt_type, $transaction->id);
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
        ];
      }
    }

    $previous_distributed = [
      "id" => $previous_voucher_transaction["distributed_id"],
      "name" => $previous_voucher_transaction["distributed_name"],
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

    $previous_cheque_transaction_account_title = isset($previous_cheque_transaction_account_title["accounts"])
      ? $previous_cheque_transaction_account_title["accounts"]
      : null;
    $previous_cheque_transaction_cheque = isset($previous_cheque_transaction_cheque["cheques"])
      ? $previous_cheque_transaction_cheque["cheques"]
      : null;

    $reason_description = isset($request["reason"]["description"]) ? $request["reason"]["description"] : null;
    $reason_remarks = isset($request["reason"]["remarks"]) ? $request["reason"]["remarks"] : null;
    $distributed_to = isset($request["distributed_to"]) ? $request["distributed_to"] : null;
    $accounts = isset($request["accounts"]) ? $request["accounts"] : null;
    $cheque_cheques = isset($request["cheques"]) ? $request["cheques"] : null;
    $date_cleared = isset($request["date_cleared"]) ? $request["date_cleared"] : null;

    // $percentage_tax = GenericMethod::with_previous_transaction($request['tax']['percentage_tax'],$previous_percentage_tax);
    // $withholding_tax = GenericMethod::with_previous_transaction($request['tax']['withholding_tax'],$previous_withholding_tax);
    // $net_amount = GenericMethod::with_previous_transaction($request['tax']['net_amount'],$previous_net_amount);
//    $receipt_type = GenericMethod::with_previous_transaction($request["receipt_type"], $previous_receipt_type);
    // $voucher_no = GenericMethod::with_previous_transaction($request["voucher"]["no"], $previous_voucher_no);
    // $voucher_no = null;

    // if (isset($request["voucher"]["no"]) && !is_null($previous_voucher_no)) {
    //   $voucher_no = GenericMethod::with_previous_transaction($request["voucher"]["no"], $previous_voucher_no);
    // } else {
    //   $voucher_no = data_get($request, "voucher.no");
    // }

    $voucher_no = data_get($request, "voucher.no", $transaction->voucher_no);
    // $voucher_month = GenericMethod::with_previous_transaction($request["voucher"]["month"], $previous_voucher_month);
    // $voucher_month = null;

    // if (
    //   isset($request["voucher"]["month"]) &&
    //   !is_null($request["voucher"]["month"]) &&
    //   !is_null($previous_voucher_month)
    // ) {
    //   $voucher_month = GenericMethod::with_previous_transaction($request["voucher"]["month"], $previous_voucher_month);
    // } else {
    //   $voucher_month = data_get($request, "voucher.month");
    // }

    $voucher_month = data_get($request, "voucher.month", $transaction->voucher_month);
    $voucher_account_titles = GenericMethod::with_previous_transaction($accounts, $voucher_account_title);
    $approver = GenericMethod::with_previous_transaction($request["approver"], $previous_approver);
    $distributed = GenericMethod::with_previous_transaction($request["distributed_to"], $previous_distributed);

//    $approver_id = isset($approver["id"]) ? $approver["id"] : null;
//    $approver_name = isset($approver["name"]) ? $approver["name"] : null;

      $approver_id = $approver["id"] ?? $transaction->approver_id;
      $approver_name = $approver["name"] ?? $transaction->approver_name;

    // $audit_by = data_get($request, "audit_by.id", null);
    $distributed_id = isset($distributed["id"]) ? $distributed["id"] : null;
    $distributed_name = isset($distributed["name"]) ? $distributed["name"] : null;

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

    if ($process == "requestor") {
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
          $inputTax
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
        // if ($transaction->document_id == 4 && $transaction->payment_type == "Partial") {
        //   switch ($transaction->is_not_editable) {
        //     case false:
        //       $poNos = $transaction->po_details()->pluck("po_no");

        //       $currentRequestIds = POBatch::whereIn("po_no", $poNos)
        //         ->pluck("request_id")
        //         ->toArray();

        //       Transaction::where("request_id", end($currentRequestIds) - 1)->update([
        //         "is_not_editable" => false,
        //       ]);
        //       break;

        //     case true:
        //       $poNo = $transaction
        //         ->po_details()
        //         ->pluck("po_no")
        //         ->last();

        //       $lastRequestId = POBatch::where("po_no", $poNo)
        //         ->pluck("request_id")
        //         ->last();

        //       $currentBalance =
        //         $transaction->referrence_amount +
        //         Transaction::where("request_id", $lastRequestId)->value("balance_po_ref_amount");

        //       // $poAmount = POBatch::where("po_no", $poNo)
        //       //   ->where("request_id", $lastRequestId)
        //       //   ->value("po_amount");

        //       // $newPoAmount = $poAmount + $transaction->referrence_amount;

        //       // POBatch::where("po_no", $poNo)
        //       //   ->where("request_id", $lastRequestId)
        //       //   ->update(["po_amount" => $newPoAmount]);

        //       Transaction::updateOrInsert(
        //         ["request_id" => $lastRequestId],
        //         ["balance_po_ref_amount" => $currentBalance]
        //       );
        //       break;
        //   }
        // } elseif ($transaction->document_id == 3) {
        //   $test = Transaction::find($id);
        //   $test->state = $subprocess;
        //   $test->save();

        //   switch ($transaction->category) {
        //     case "rental":
        //       $gross_amount = Transaction::where("transaction_id", $test->transaction_id)
        //         ->where("state", "!=", "void")
        //         ->sum("gross_amount");

        //       Transaction::where("transaction_id", $test->transaction_id)
        //         ->where("state", "!=", "void")
        //         ->update([
        //           "total_gross" => $gross_amount,
        //           "document_amount" => $gross_amount,
        //         GenericMethod::resultResponse

        //       break;

        //     case "leasing":
        //       $transactionData = Transaction::where("transaction_id", $transaction->transaction_id)
        //         ->where("state", "!=", "void")
        //         ->selectRaw(
        //           "SUM(principal) as principal_amount, SUM(interest) as interest_amount, SUM(cwt) as cwt_amount"
        //         )
        //         ->first();

        //       $document_amount =
        //         $transactionData->principal_amount + $transactionData->interest_amount - $transactionData->cwt_amount;

        //       Transaction::where("transaction_id", $transaction->transaction_id)
        //         ->where("state", "!=", "void")
        //         ->update([
        //           "document_amount" => $document_amount,
        //         ]);

        //       break;
        //   }
        // }
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
      GenericMethod::tagTransaction(
        $model,
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
          $inputTax
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
      } elseif ($subprocess == "voucher") {
        // GenericMethod::voucherNoValidationUponSaving($voucher_no, $id);
        $status = "voucher-voucher";

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

          switch ($transaction->document_id) {
            case 3:
              if ($debit_amount != $credit_amount) {
                return GenericMethod::resultResponse("not-equal", "Total debit and credit", []);
              }
//              if ($transaction->net_amount != $debit_amount) {
//                return GenericMethod::resultResponse("not-equal", "Net amount and account title", []);
//              }

              switch ($transaction->category) {
                  case 'rental':
                        if ($transaction->gross_amount != $debit_amount) {
                            return GenericMethod::resultResponse("not-equal", "Document and account title", []);
                        }
                      break;

                  default:
//                      if (($transaction->principal + $transaction->interest) != $debit_amount) {
//                          return GenericMethod::resultResponse("not-equal", "Document and account title", []);
//                      }

                      if (floatval((number_format(($transaction->principal + $transaction->interest), 2, '.', ''))) != $debit_amount) {
                          return GenericMethod::resultResponse("not-equal", "Document and account title", []);
                      }
              }

              break;

            default:
              if ($debit_amount != $credit_amount) {
                return GenericMethod::resultResponse("not-equal", "Total debit and credit", []);
              }

              if ($document_amount != $debit_amount) {
                return GenericMethod::resultResponse("not-equal", "Document and account title", []);
              }
          }

            $department_id = null;
            foreach ($account_titles as $account_title) {
                if (strtolower($account_title['entry']) == 'debit') {
                    $department_id = $account_title['department']['id'];
                    break;
                }
            }

            $voucher_no = $generic->generateVoucherNo($transaction->id, $department_id);
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
          $inputTax
      );
    } elseif ($process == "transmit") {
      $transaction_type = $request["transaction_type"];
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
            // $status = "transmit-transmit"; // Is this line necessary? It's commented out.
          }
        } elseif ($transaction->document_id === 9) {
          $transaction->update([
            // "is_for_releasing" => false,
            "is_for_voucher_audit" => true,
          ]);
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
          $inputTax
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
      } elseif ($subprocess == "void") {
        $status = "cheque-void";
      } elseif ($subprocess == "cheque") {
        $status = "cheque-cheque";
        // if (
        //   $transaction->document_id === 8 &&
        //   $transaction->status == "cheque-receive"
        //   // && $transaction->is_for_voucher_audit == null
        // ) {
        //   $transaction->update([
        //     // "is_for_voucher_audit" => false,
        //     "is_for_voucher_audit" => null,
        //   ]);
        // }

        // if ($transaction->is_for_releasing == false && $transaction->status == "cheque-receive") {
        //   $transaction->update([
        //     "is_for_releasing" => null,
        //   ]);
        // }

        if ($transaction->is_for_releasing) {
          $transaction->update([
            "is_for_releasing" => true,
          ]);
        } else {
          $transaction->update([
            "is_for_releasing" => false,
          ]);
        }

        $not_valid = GenericMethod::validateCheque($id, $cheques);
        if ($not_valid) {
          return GenericMethod::resultResponse("cheque-no-exist", "Cheque_no number already exist.", []);
        }

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

        switch ($transaction->document_id) {
          case 3:
//            if ($transaction->net_amount != $cheque_amount) {
//              return GenericMethod::resultResponse("not-equal", "Net amount and account title", []);
//            }

            switch ($transaction->category) {

                case 'rental':
                    if ($transaction->gross_amount != $cheque_amount) {
                        return GenericMethod::resultResponse("not-equal", "Document and account title", []);
                    }
                    break;

                default:

                    if (floatval((number_format(($transaction->principal + $transaction->interest), 2, '.', ''))) != $cheque_amount) {
                        return GenericMethod::resultResponse("not-equal", "Document and account title", []);
                    }
            }
            break;

          default:
            if ($document_amount != $cheque_amount) {
              return GenericMethod::resultResponse("not-equal", "Document and cheque", []);
            }
            break;
        }
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
        $inputTax
      );
    } elseif ($process == "audit") {
      $date_now = Carbon::now("Asia/Manila")->format("Y-m-d H:i:s");
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

        $audit_by = Auth::user()->id;
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
      $generic->auditCheque($id, null, $status, $reason_id, $reason_remarks, null, null, $type);

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
        $inputTax
      );
    } elseif ($process == "inspect") {
      $date_now = Carbon::now("Asia/Manila")->format("Y-m-d H:i:s");
      $type = "voucher";
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

        $audit_by = Auth::user()->id;
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
      $generic->auditCheque($id, null, $status, $reason_id, $reason_remarks, null, null, $type);

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
        $inputTax
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
        $signed_by = Auth::user()->id;
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
        $inputTax
      );
    } elseif ($process == "release") {
      $model = new Release();
      if ($subprocess == "receive") {
        $status = "release-receive";
      } elseif ($subprocess == "return") {
        $status = "release-return";
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
          $inputTax
      );
    } elseif ($process == "file") {
      $model = new File();
      if ($subprocess == "receive") {
        $status = "file-receive";
      } elseif ($subprocess == "return") {
        $status = "file-return";
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
          'cheque',
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
          $inputTax
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
//        $status,
          $transaction->status,
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
            'cheque',
            $request->box_no ?? $transaction->box_no,
          true
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

          switch ($transaction->document_id) {
            case 3:
              if ($transaction->net_amount != $cheque_amount) {
                return GenericMethod::resultResponse("not-equal", "Document amount and account title", []);
              }
              break;

            default:
              if ($document_amount != $cheque_amount) {
                return GenericMethod::resultResponse("not-equal", "Document and cheque", []);
              }
              break;
          }
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

          $not_valid = GenericMethod::validateCheque($id, $cheques);
          if ($not_valid) {
              return GenericMethod::resultResponse("cheque-no-exist", "Cheque_no number already exist.", []);
          }

      } elseif ($subprocess == "hold") {
        $status = "issue-hold";
      } elseif ($subprocess == "return") {
        $status = "issue-return";
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
          $inputTax
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
          $inputTax
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
            $inputTax
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
            $inputTax
        );
    }

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

    $transaction = Transaction::with("cheques.cheques")
      ->whereHas("cheques.cheques", function ($query) use ($cheque_no, $bank_id) {
        $query->where("cheque_no", $cheque_no)->where("bank_id", $bank_id);
      })
      ->where("id", "<>", $id)
      ->exists();

    if ($transaction) {
      $errorMessage = GenericMethod::resultLaravelFormat("cheque.no", ["Cheque number already exist."]);
      return GenericMethod::resultResponse("invalid", "", $errorMessage);
    }
    return GenericMethod::resultResponse("success-no-content", "", []);
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

  public static function voidTransaction($request, $id) {
      $test = new TransactionController();
      $test->voidTransaction($request, $id);
  }
}
