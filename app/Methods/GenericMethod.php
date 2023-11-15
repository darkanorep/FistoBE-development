<?php

namespace App\Methods;

use App\Exceptions\FistoException;
use App\Exceptions\FistoLaravelException;
use App\Models\AccountTitle;
use App\Models\Approver;
use App\Models\Associate;
use App\Models\Audit;
use App\Models\BusinessUnit;
use App\Models\Cheque;
use App\Models\ClearingAccountTitle;
use App\Models\Company;
use App\Models\DebitBatch;
use App\Models\Department;
use App\Models\Executive;
use App\Models\Filing;
use App\Models\Gas;
use App\Models\Issue;
use App\Models\Location;
use App\Models\POBatch;
use App\Models\Reason;
use App\Models\ReferrenceBatch;
use App\Models\RequestorLogs;
use App\Models\Reverse;
use App\Models\SubUnit;
use App\Models\Tagging;
use App\Models\Transaction;
use App\Models\TransactionClient;
use App\Models\Transfer;
use App\Models\Treasury;
use App\Models\User;
use App\Models\UserDocumentCategory;
use App\Models\VoucherAccountTitle;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// For Pagination with Collection

class GenericMethod
{
  ##########################################################################################################
  #########################################      REUSABLE FUNCTION    ######################################
  ##########################################################################################################

  public static function get_account_title_details($id)
  {
    $account_title_details = Transaction::with("cheques.account_title")
      ->where("id", $id)
      ->where("status", "<>", "void")
      ->get();
    $account_title_details = $account_title_details->first()->cheques->first()->account_title;

    if (!$account_title_details->isEmpty()) {
      $account_title_details = $account_title_details->mapToGroups(function ($item, $key) {
        return [
          [
            "entry" => $item["entry"],
            "account_title" => [
              "id" => $item["account_title_id"],
              "name" => $item["account_title_name"],
            ],
            "amount" => $item["amount"],
            "remarks" => $item["remarks"],
          ],
        ];
      });
      return $account_title_details->first()->toArray();
    }
  }

  public static function get_cheque_details($id)
  {
    $cheque_details = Transaction::with("cheques.cheques")
      ->where("id", $id)
      ->where("status", "<>", "void")
      ->get();
    $cheque_details = $cheque_details->first()->cheques->first()->cheques;

    if (!$cheque_details->isEmpty()) {
      $cheque_details = $cheque_details->mapToGroups(function ($item, $key) {
        return [
          [
            "bank" => [
              "id" => $item["bank_id"],
              "name" => $item["bank_name"],
            ],
            "no" => $item["cheque_no"],
            "date" => $item["cheque_date"],
            "amount" => $item["cheque_amount"],
          ],
        ];
      });
      return $cheque_details->first()->toArray();
    }
  }

  public static function get_account_title_details_latest($id)
  {
    $account_title_details = Transaction::with("cheques.account_title")
      ->where("id", $id)
      ->where("status", "<>", "void")
      ->get();
    $account_title_details = $account_title_details->first()->cheques->first()->account_title;

    if (!$account_title_details->isEmpty()) {
      $account_title_details = $account_title_details->mapToGroups(function ($item, $key) {
        return [
          [
            "transaction_type" => $item["transaction_type"],
            "entry" => $item["entry"],
            "account_title" => [
              "id" => $item["account_title_id"],
              "name" => $item["account_title_name"],
            ],
            "amount" => $item["amount"],
            "remarks" => $item["remarks"],
          ],
        ];
      });
      return $account_title_details->first()->toArray();
    }
  }

  public static function get_cheque_details_latest($id)
  {
    $cheque_details = Transaction::with("cheques.cheques")
      ->where("id", $id)
      ->where("status", "<>", "void")
      ->get();
    $cheque_details = $cheque_details->first()->cheques->first()->cheques;

    if (!$cheque_details->isEmpty()) {
      $cheque_details = $cheque_details->mapToGroups(function ($item, $key) {
        return [
          [
            "transaction_type" => $item["transaction_type"],
            "bank" => [
              "id" => $item["bank_id"],
              "name" => $item["bank_name"],
            ],
            "no" => $item["cheque_no"],
            "date" => $item["cheque_date"],
            "amount" => $item["cheque_amount"],
          ],
        ];
      });
      return $cheque_details->first()->toArray();
    }
  }

  public static function floatvalue($val)
  {
    $val = str_replace(",", ".", $val);
    $val = preg_replace("/\.(?=.*\.)/", "", $val);
    return floatval($val);
  }

  public static function get_account_title($id)
  {
    return Transaction::with("cheques.cheques")
      ->with("cheques.account_title")
      ->where("id", $id)
      ->where("status", "<>", "void")
      ->get();
  }

  public static function format_account_title($account_title)
  {
    if (!empty($account_title)) {
      if (!$account_title->isEmpty()) {
        return $account_title->mapToGroups(function ($item, $key) {
          return [
            "accounts" => [
              "entry" => $item["entry"],
              "account_title" => [
                "id" => $item["account_title_id"],
                "name" => $item["account_title_name"],
              ],
              "amount" => $item["amount"],
              "remarks" => $item["remarks"],
            ],
          ];
        });
      } else {
        return [];
      }
    } else {
      return [];
    }
  }

  public static function format_cheque($cheques)
  {
    if (!empty($cheques)) {
      if (!$cheques->isEmpty()) {
        return $cheques->mapToGroups(function ($item, $key) {
          return [
            "cheques" => [
              "type" => $item["entry_type"],
              "bank" => [
                "id" => $item["bank_id"],
                "name" => $item["bank_name"],
              ],
              "no" => $item["cheque_no"],
              "date" => $item["cheque_date"],
              "amount" => $item["cheque_amount"],
            ],
          ];
        });
      } else {
        return [];
      }
    } else {
      return [];
    }
  }

  public static function object_to_array($object)
  {
    if (gettype($object) == "object") {
      return $object = $object->toArray();
    }
    return $object;
  }

  public static function with_previous_transaction($current_transaction, $old_transaction)
  {
    if ($current_transaction) {
      return $current_transaction;
    } else {
      if ($old_transaction) {
        return $old_transaction;
      } else {
        return null;
      }
    }
  }

  public static function getStatus($process, $transaction)
  {
    if ($process == "tag") {
      $model = new Tagging();
      $field = "tag_no";
    } elseif ($process == "voucher") {
      $model = new Associate();
      $field = "voucher_no";
//    } elseif ($process == "approve") {
//      $model = new Approver();
//      $field = "distributed_id";
    } elseif ($process == "cheque") {
      $model = new Treasury();
      $field = "cheque_no";
    } elseif ($process == "release") {
      $model = new Tagging();
      $field = "";
    } elseif ($process == "file") {
      $model = new Associate();
      $field = "";
    } elseif ($process == "audit") {
      $model = new Audit();
      $field = "";
    } elseif ($process == "executive") {
      // $model = new Executive();
      $field = "";
    } elseif ($process == "inspect") {
      $field = "";
    } elseif ($process == "issue") {
      $field = "";
    } elseif ($process == "debit") {
      $field = "";
    } elseif ($process == "approve") {
        $field = "";
    } elseif ($process == "gas") {
        $field = "";
    } elseif($process == "discharge") {
        $field = "";
    }

    $status = $process . "-" . $process;

    if ($process == "debit") {
      $status = $process . "-" . "file";
    }
    //---------------------------------------------------------------//
      $is_approved = Approver::where("tag_id", $transaction->tag_no)
          ->where("status", "approve-approve")
          ->exists();

    if ($process == "approve" and $is_approved) {
        return $status;
    }
      //---------------------------------------------------------------//
    $is_exists = Cheque::where("transaction_id", $transaction["transaction_id"])->exists();
    if ($process == "cheque" and $is_exists) {
      return $status;
    }

    $is_audited = Audit::where("transaction_id", $transaction->id)
      ->where("status", "audit-audit")
      ->exists();

    if ($process == "audit" and $is_audited) {
      return $status;
    }

    $is_inspected = Audit::where("transaction_id", $transaction->id)
      ->where("status", "inspect-inspect")
      ->exists();

    if ($process == "inspect" and $is_inspected) {
      return $status;
    }

    $is_issued = Audit::where("transaction_id", $transaction->id)
      ->where("status", "issue-issue")
      ->exists();

    if ($process == "issue" and $is_issued) {
      return $status;
    }

    $is_debited = Filing::where("tag_id", $transaction->id)
      ->where("status", "debit-file")
      ->exists();

    if ($process == "debit" and $is_debited) {
      return $status;
    }

    $is_gas = Gas::where("transaction_id", $transaction->id)
      ->where("status", "gas-gas")
      ->exists();

    if($process == "gas" and $is_gas){
        return $status;
    }

    $is_discharged = Gas::where("transaction_id", $transaction->id)
        ->where("status", "discharge-discharge")
        ->exists();

    if($process == "discharge" and $is_discharged){
        return $status;
    }

    if (!$transaction["$field"]) {
      $status = $process . "-receive";
    }

    return $status;
  }

  // public static function getStatus($process, $transaction)
  // {
  //   $status = $process . "-" . $process;

  //   switch ($process) {
  //     case "tag":
  //       $model = new Tagging();
  //       $field = "tag_no";
  //       break;
  //     case "voucher":
  //       $model = new Associate();
  //       $field = "voucher_no";
  //       break;
  //     case "approve":
  //       $model = new Approver();
  //       $field = "distributed_id";
  //       break;
  //     case "cheque":
  //       $model = new Treasury();
  //       $field = "cheque_no";
  //       break;
  //     case "release":
  //       $model = new Tagging();
  //       $field = "";
  //       break;
  //     case "file":
  //       $model = new Associate();
  //       $field = "";
  //       break;
  //     case "audit":
  //       $model = new Audit();
  //       $field = "";
  //       break;
  //     default:
  //       $model = null;
  //       $field = "";
  //   }

  //   if ($model && $field) {
  //     if (!$transaction->$field) {
  //       $status = $process . "-receive";
  //     }
  //   }

  //   if ($process == "cheque" && Cheque::where("transaction_id", $transaction["transaction_id"])->exists()) {
  //     return $status;
  //   }

  //   if (in_array($process, ["audit", "inspect", "issue"])) {
  //     $is_status = $process . "-" . $process;
  //     if (
  //       Audit::where("transaction_id", $transaction->id)
  //         ->where("status", $is_status)
  //         ->exists()
  //     ) {
  //       return $status;
  //     }
  //   }

  //   return $status;
  // }

  public static function isTransactionExistInFlow($model, $transaction_id, $status)
  {
    if (
      $model
        ::where("transaction_id", $transaction_id)
        ->where("status", $status)
        ->exists()
    ) {
      return GenericMethod::resultResponse("exist-flow", "received", []);
    }
  }

  public static function tagTransaction(
    $model,
    $request_id,
    $transaction_id,
    $remarks,
    $date_now,
    $reason_id,
    $reason_remarks,
    $status,
    $distributed_to = []
  ) {
    $distributed_id = null;
    $distributed_name = null;
    if (!empty($distributed_to)) {
      $distributed_id = $distributed_to["id"];
      $distributed_name = $distributed_to["name"];
    }
    $model::Create([
      "transaction_id" => $transaction_id,
      "request_id" => $request_id,
      "description" => $remarks,
      "status" => $status,
      "date_status" => $date_now,
      "reason_id" => $reason_id,
      "remarks" => $reason_remarks,
      "distributed_id" => $distributed_id,
      "distributed_name" => $distributed_name
    ]);
  }

  public function gasTransaction($transaction_id, $status, $reason_id, $remarks) {
      Gas::create([
          'transaction_id' => $transaction_id,
          'status' => $status,
          'reason_id' => $reason_id,
          'remarks' => $remarks
      ]);
  }

  public static function voucherTransaction(
    $model,
    $transaction_id,
    $tag_no,
    $reason_remarks,
    $date_now,
    $reason_id,
    $status,
    $voucher_no,
    $approver,
    $account_titles,
      $transaction_type_id,
      $transaction_type_name
  ) {
    $approver_id = isset($approver["id"])
      ? $approver["id"]
      : (isset($approver["approver"]["id"])
        ? $approver["approver"]["id"]
        : null);
    $approver_name = isset($approver["name"])
      ? $approver["name"]
      : (isset($approver["approver"]["name"])
        ? $approver["approver"]["name"]
        : null);

    $voucher_transaction = $model::Create([
      "transaction_id" => $transaction_id,
      "tag_id" => $tag_no,
      "approver_id" => $approver_id,
      "approver_name" => $approver_name,
      "status" => $status,
      "date_status" => $date_now,
      "reason_id" => $reason_id,
      "remarks" => $reason_remarks,
        "transaction_type_id" => $transaction_type_id,
        "transaction_type_name" => $transaction_type_name
    ]);

    if (isset($account_titles)) {
      if (count($account_titles) > 0) {
        $id = $voucher_transaction->id;
        $process = "associate";
        return GenericMethod::addAccountTitleEntry($process, $id, $account_titles);
      }
    }
  }

  public static function approveTransaction(
    $model,
    $transaction_id,
    $tag_no,
    $reason_remarks,
    $date_now,
    $reason_id,
    $status,
    $distributed_to = []
  ) {
    $distributed_id = null;
    $distributed_name = null;
    if (!empty($distributed_to)) {
      $distributed_id = $distributed_to["id"];
      $distributed_name = $distributed_to["name"];
    }
    $model::Create([
      "transaction_id" => $transaction_id,
      "tag_id" => $tag_no,
      "remarks" => $reason_remarks,
      "date_status" => $date_now,
      "reason_id" => $reason_id,
      "status" => $status,
      "distributed_id" => $distributed_id,
      "distributed_name" => $distributed_name,
    ]);
  }

  public static function transmitTransaction(
    $model,
    $transaction_id,
    $tag_no,
    $reason_remarks,
    $date_now,
    $reason_id,
    $status,
    $distributed_to = [],
    $transaction_type = "cheque"
  ) {
    $model::Create([
      "transaction_id" => $transaction_id,
      "tag_id" => $tag_no,
      "date_status" => $date_now,
      "reason_id" => $reason_id,
      "transaction_type" => $transaction_type,
      "status" => $status,
    ]);
  }

  // public function auditCheque(
  //   $transaction_id,
  //   $date_received,
  //   $status,
  //   $reason_id,
  //   $remarks,
  //   $user_id = null,
  //   $date_audit = null,
  //   $type = null
  // ) {
  //   Audit::updateOrCreate(
  //     ["transaction_id" => $transaction_id],
  //     [
  //       "type" => $type,
  //       "date_received" => $date_received,
  //       "status" => $status,
  //       "reason_id" => $reason_id,
  //       "remarks" => $remarks,
  //       "user_id" => $user_id,
  //       "date_audited" => $date_audit,
  //     ]
  //   );
  // }

  public function auditCheque(
    $transaction_id,
    $date_received,
    $status,
    $reason_id,
    $remarks,
    $user_id = null,
    $date_audit = null,
    $type = null
  ) {
    if ($type === "voucher") {
      // Always insert with "inspect-inspect" status and type "voucher"
      Audit::create([
        "transaction_id" => $transaction_id,
        "type" => "voucher",
        "status" => $status,
        "date_received" => $date_received,
        "reason_id" => $reason_id,
        "remarks" => $remarks,
        "user_id" => $user_id,
        "date_audited" => $date_audit,
      ]);

      // if ($status === "inspect-inspect") {
      //   Audit::create([
      //     "transaction_id" => $transaction_id,
      //     "type" => "voucher",
      //     "status" => $status,
      //     "date_received" => $date_received,
      //     "reason_id" => $reason_id,
      //     "remarks" => $remarks,
      //     "user_id" => $user_id,
      //     "date_audited" => $date_audit,
      //   ]);
      // }
    } else {
      // Insert with the provided status and type "cheque"
      Audit::create([
        "transaction_id" => $transaction_id,
        "type" => $type,
        "status" => $status,
        "date_received" => $date_received,
        "reason_id" => $reason_id,
        "remarks" => $remarks,
        "user_id" => $user_id,
        "date_audited" => $date_audit,
      ]);
    }
  }

  public function issue($transaction_id, $status, $reason_id, $remarks) {
      Issue::create([
          'transaction_id' => $transaction_id,
          'status' => $status,
          'reason_id' => $reason_id,
          'remarks' => $remarks
      ]);
  }

  public function executiveSign(
    $transaction_id,
    $date_received,
    $status,
    $reason_id,
    $remarks,
    $user_id = null,
    $date_signed = null
  ) {
    Executive::create(
      // ["transaction_id" => $transaction_id],
      [
        "transaction_id" => $transaction_id,
        "date_received" => $date_received,
        "status" => $status,
        "reason_id" => $reason_id,
        "remarks" => $remarks,
        "user_id" => $user_id,
        "date_signed" => $date_signed,
      ]
    );
  }

  public static function chequeTransaction(
    $model,
    $transaction_id,
    $tag_no,
    $reason_remarks,
    $date_now,
    $reason_id,
    $status,
    $cheques,
    $account_titles
  ) {
    $cheque_transaction = $model::Create([
      "transaction_id" => $transaction_id,
      "tag_id" => $tag_no,
      "status" => $status,
      "date_status" => $date_now,
      "reason_id" => $reason_id,
      "remarks" => $reason_remarks,
    ]);

    if (isset($cheques)) {
      if (count($cheques) > 0) {
        $id = $cheque_transaction->id;

        GenericMethod::addCheque($transaction_id, $id, $cheques);
      }
    }

    if (isset($account_titles)) {
      if (count($account_titles) > 0) {
        $id = $cheque_transaction->id;
        $process = "treasury";
        GenericMethod::addAccountTitleEntry($process, $id, $account_titles);
      }
    }
  }

  public static function releaseTransaction(
    $model,
    $transaction_id,
    $tag_no,
    $remarks,
    $date_now,
    $reason_id,
    $reason_remarks,
    $status,
    $distributed_to = []
  ) {
    $distributed_id = null;
    $distributed_name = null;
    if (!empty($distributed_to)) {
      $distributed_id = $distributed_to["id"];
      $distributed_name = $distributed_to["name"];
    }
    $model::Create([
      "transaction_id" => $transaction_id,
      "description" => $remarks,
      "status" => $status,
      "date_status" => $date_now,
      "reason_id" => $reason_id,
      "remarks" => $reason_remarks,
      "distributed_id" => $distributed_id,
      "distributed_name" => $distributed_name,
      "tag_id" => $tag_no,
    ]);
  }

  public static function fileTransaction(
    $model,
    $transaction_id,
    $tag_no,
    $reason_remarks,
    $date_now,
    $reason_id,
    $status,
    $receipt_type,
    $percentage_tax,
    $witholding_tax,
    $net_amount,
    $voucher_no,
    $approver,
    $account_titles
  ) {
    $approver_id = isset($approver["id"])
      ? $approver["id"]
      : (isset($approver["approver"]["id"])
        ? $approver["approver"]["id"]
        : null);
    $approver_name = isset($approver["name"])
      ? $approver["name"]
      : (isset($approver["approver"]["name"])
        ? $approver["approver"]["name"]
        : null);

    $voucher_transaction = $model::Create([
      "transaction_id" => $transaction_id,
      "tag_id" => $tag_no,
      "receipt_type" => $receipt_type,
      "percentage_tax" => $percentage_tax,
      "witholding_tax" => $witholding_tax,
      "net_amount" => $net_amount,
      "approver_id" => $approver_id,
      "approver_name" => $approver_name,
      "status" => $status,
      "date_status" => $date_now,
      "reason_id" => $reason_id,
      "remarks" => $reason_remarks,
    ]);
  }

  public static function reverseTransaction(
    $model,
    $transaction_id,
    $tag_no,
    $reason_remarks,
    $date_now,
    $reason_id,
    $status,
    $user_role,
    $distributed_to
  ) {
    $distributed_id = null;
    $distributed_name = null;

    $reverse_reason = Reverse::where("tag_id", $tag_no)
      ->whereNotNull("reason_id")
      ->latest("id")
      ->limit(1)
      ->get();

    $reverse_reason = $reverse_reason->first();
    if ($reverse_reason) {
      $reason_id = $reverse_reason->reason_id;
      $reason_remarks = $reverse_reason->remarks;
    }

    if (!empty($distributed_to)) {
      $distributed_id = $distributed_to["id"];
      $distributed_name = $distributed_to["name"];
    }

    $user_info = Auth::user();
    $user_id = $user_info->id;
    $full_name = GenericMethod::getFullnameNoMiddle($user_info->first_name, $user_info->last_name, $user_info->suffix);

    $reverse_transaction = $model::Create([
      "transaction_id" => $transaction_id,
      "tag_id" => $tag_no,
      "user_role" => $user_role,
      "user_id" => $user_id,
      "user_name" => $full_name,
      "status" => $status,
      "date_status" => $date_now,
      "reason_id" => $reason_id,
      "remarks" => $reason_remarks,
      "distributed_id" => $distributed_id,
      "distributed_name" => $distributed_name,
    ]);
  }

  public static function clearTransaction(
    $model,
    $tag_no,
    $date_now,
    $status,
    $account_titles,
    $subprocess,
    $date_cleared,
      $transaction_id
  ) {
    $clear_transaction = $model::Create([
      "tag_id" => $tag_no,
      "status" => $status,
      "date_status" => $date_now,
      "date_cleared" => $date_cleared,
        "transaction_id" => $transaction_id
    ]);

    if ($subprocess == "clear") {
      $id = $clear_transaction->id;
      GenericMethod::addClearAccountTitleEntry($id, $account_titles);
    }
  }

  public static function transferTransaction($id, $transaction_id, $tag_no, $request)
  {
    $user_info = Auth::user();
    $from_user_id = $user_info->id;
    $from_full_name = GenericMethod::getFullnameNoMiddle(
      $user_info->first_name,
      $user_info->last_name,
      $user_info->suffix
    );
    $to_user_id = $request["transfer"]["id"];
    $to_full_name = $request["transfer"]["name"];
    $process = $request["process"];

    $transfer_transaction_log = Transfer::Create([
      "process" => $process,
      "transaction_id" => $transaction_id,
      "tag_id" => $tag_no,
      "from_distributed_id" => $from_user_id,
      "from_distributed_name" => $from_full_name,
      "to_distributed_id" => $to_user_id,
      "to_distributed_name" => $to_full_name,
    ]);

    $update_transaction = DB::table("transactions")
      ->where("transaction_id", $transaction_id)
      ->where("tag_no", $tag_no)
      ->update([
        "distributed_id" => $to_user_id,
        "distributed_name" => $to_full_name,
        "status" => $process . "-transfer",
        "state" => "transfer",
      ]);

    if (isset($update_transaction)) {
      return true;
    }
  }

  public static function validateCheque($id, $cheques)
  {
    $duplicate_count = 0;
    foreach ($cheques as $specific_cheques) {
      $cheque_no = $specific_cheques["no"];
      $transaction = Transaction::with("cheques.cheques")
        ->whereHas("cheques.cheques", function ($query) use ($cheque_no, $specific_cheques) {
          $query->where("cheque_no", $cheque_no)->where("bank_id", $specific_cheques["bank"]["id"]);
        })
        ->where("id", "<>", $id)
        // ->when($id, function ($query, $id) {
        //   $query->where("id", "<>", $id);
        // })
        ->exists();

      if ($transaction) {
        $duplicate_count++;
      }
    }
    return $duplicate_count;
  }

  public static function addCheque($transaction_id, $id, $cheques)
  {
    foreach ($cheques as $specific_cheques) {
      $entry_type = isset($specific_cheques["transaction_type"])
        ? $specific_cheques["transaction_type"]
        : $specific_cheques["type"];
      $bank_id = $specific_cheques["bank"]["id"];
      $bank_name = $specific_cheques["bank"]["name"];
      $cheque_no = $specific_cheques["no"];
      $cheque_date = $specific_cheques["date"];
      $cheque_amount = $specific_cheques["amount"];
      $transaction_type = isset($specific_cheques["transaction_type"]) ? $specific_cheques["transaction_type"] : "new";

      Cheque::Create([
        "transaction_id" => $transaction_id,
        "treasury_id" => $id,
        "entry_type" => $entry_type,
        "bank_id" => $bank_id,
        "bank_name" => $bank_name,
        "cheque_no" => $cheque_no,
        "cheque_date" => $cheque_date,
        "cheque_amount" => $cheque_amount,
        "transaction_type" => $transaction_type,
      ]);
    }
  }

  public static function addAccountTitleEntry($process, $id, $account_titles)
  {
    $associate_id = null;
    $treasury_id = null;

    if ($process == "associate") {
      $associate_id = $id;
    } elseif ($process == "treasury") {
      $treasury_id = $id;
    }
    foreach ($account_titles as $specific_account_title) {
      $entry = $specific_account_title["entry"];
      $account_title_id = isset($specific_account_title["account_title"]["id"])
        ? $specific_account_title["account_title"]["id"]
        : $specific_account_title["account_title_id"];
      $account_title_name = isset($specific_account_title["account_title"]["name"])
        ? $specific_account_title["account_title"]["name"]
        : $specific_account_title["account_title_name"];
      $amount = $specific_account_title["amount"];
      $remarks = $specific_account_title["remarks"];
      $transaction_type = isset($specific_account_title["transaction_type"])
        ? $specific_account_title["transaction_type"]
        : "new";

      VoucherAccountTitle::Create([
        "associate_id" => $associate_id,
        "treasury_id" => $treasury_id,
        "entry" => $entry,
        "account_title_id" => $account_title_id,
        "account_title_name" => $account_title_name,
          "account_title_code" => AccountTitle::where("title", $specific_account_title["account_title"]["name"])->first()->code ?? null,
        "amount" => $amount,
        "remarks" => $remarks,
        "transaction_type" => $transaction_type,
          "company_id" => $specific_account_title["company"]["id"] ?? null,
          "company_name" => $specific_account_title["company"]["name"] ?? null,
          "company_code" => isset($specific_account_title["company"]["name"]) ? Company::where("company", $specific_account_title["company"]["name"])->first()->code : null,
          "department_id" => $specific_account_title["department"]["id"] ?? null,
          "department_name" => $specific_account_title["department"]["name"] ?? null,
          "department_code" => isset($specific_account_title["department"]["name"]) ? Department::where("department", $specific_account_title["department"]["name"])->first()->code : null,
          "location_id" => $specific_account_title["location"]["id"] ?? null,
          "location_name" => $specific_account_title["location"]["name"] ?? null,
          "location_code" => isset($specific_account_title["location"]["name"]) ? Location::where("location", $specific_account_title["location"]["name"])->first()->code : null,
          "business_unit_id" => $specific_account_title["business_unit"]["id"] ?? null,
          "business_unit_name" => $specific_account_title["business_unit"]["name"] ?? null,
          "business_unit_code" => isset($specific_account_title["business_unit"]["name"]) ? BusinessUnit::where("business_unit", $specific_account_title["business_unit"]["name"])->first()->code : null,
          "sub_unit_id" => $specific_account_title["sub_unit"]["id"] ?? null,
          "sub_unit_name" => $specific_account_title["sub_unit"]["name"] ?? null,
          "sub_unit_code" => isset($specific_account_title["sub_unit"]["name"]) ? SubUnit::where("subunit", $specific_account_title["sub_unit"]["name"])->first()->code : null,
          "is_default" => $specific_account_title["is_default"] ?? null,
      ]);
    }
  }

  public static function addClearAccountTitleEntry($id, $account_titles)
  {
    $clear_id = $id;
    foreach ($account_titles as $specific_account_title) {
      $entry = $specific_account_title["entry"];
      $account_title_id = isset($specific_account_title["account_title"]["id"])
        ? $specific_account_title["account_title"]["id"]
        : $specific_account_title["account_title_id"];
      $account_title_name = isset($specific_account_title["account_title"]["name"])
        ? $specific_account_title["account_title"]["name"]
        : $specific_account_title["account_title_name"];
      $amount = $specific_account_title["amount"];
      $remarks = $specific_account_title["remarks"];
      $transaction_type = isset($specific_account_title["transaction_type"])
        ? $specific_account_title["transaction_type"]
        : "new";

      ClearingAccountTitle::Create([
        "clear_id" => $clear_id,
        "entry" => $entry,
        "account_title_id" => $account_title_id,
        "account_title_name" => $account_title_name,
        "amount" => $amount,
        "remarks" => $remarks,
        "transaction_type" => $transaction_type,
      ]);
    }
  }

  public static function validateWith1PesoDifference($affeced_field, $type, $transaction_amount, $po_total_amount)
  {
    if (!(abs($transaction_amount - $po_total_amount) >= 0.0 && abs($transaction_amount - $po_total_amount) < 1.0)) {
      return $errorMessage = GenericMethod::resultLaravelFormat($affeced_field, [
        $type . " amount and total po amount are not equal.",
      ]);
    }
  }

  public static function getTransactionChanges($request_id, $transaction, $id)
  {
    $current_transaction = Transaction::with("po_details")->findOrFail($id);
    $original_transaction = Transaction::with("po_details")->findOrFail($id);

    $capex_no = isset($transaction["document"]["capex_no"]) ? $transaction["document"]["capex_no"] : null;
    $document_no = isset($transaction["document"]["no"]) ? $transaction["document"]["no"] : null;
    $document_date = isset($transaction["document"]["date"]) ? $transaction["document"]["date"] : null;
    $category_id = isset($transaction["document"]["category"]["id"])
      ? $transaction["document"]["category"]["id"]
      : null;
    $category_name = isset($transaction["document"]["category"]["name"])
      ? $transaction["document"]["category"]["name"]
      : null;
    $document_from = isset($transaction["document"]["from"]) ? $transaction["document"]["from"] : null;
    $document_to = isset($transaction["document"]["to"]) ? $transaction["document"]["to"] : null;
    $amount = isset($transaction["document"]["amount"])
      ? $transaction["document"]["amount"]
      : $transaction["document"]["reference"]["amount"];

    // ---------------------------------------------------------------------------------------------------------------------------------
    // Utilities
    $receipt_no = isset($transaction["document"]["utility"]["receipt_no"])
      ? $transaction["document"]["utility"]["receipt_no"]
      : null;
    $consumption = isset($transaction["document"]["utility"]["consumption"])
      ? $transaction["document"]["utility"]["consumption"]
      : null;
    $location_id = isset($transaction["document"]["utility"]["location"]["id"])
      ? $transaction["document"]["utility"]["location"]["id"]
      : null;
    $location_name = isset($transaction["document"]["utility"]["location"]["name"])
      ? $transaction["document"]["utility"]["location"]["name"]
      : null;
    $utility_category_id = isset($transaction["document"]["utility"]["category"]["id"])
      ? $transaction["document"]["utility"]["category"]["id"]
      : null;
    $utility_category_name = isset($transaction["document"]["utility"]["category"]["name"])
      ? $transaction["document"]["utility"]["category"]["name"]
      : null;
    $account_no_id = isset($transaction["document"]["utility"]["account_no"]["id"])
      ? $transaction["document"]["utility"]["account_no"]["id"]
      : null;
    $account_no = isset($transaction["document"]["utility"]["account_no"]["no"])
      ? $transaction["document"]["utility"]["account_no"]["no"]
      : null;

    $current_transaction->utilities_from = $document_from;
    $current_transaction->utilities_to = $document_to;
    $current_transaction->utilities_receipt_no = $receipt_no;
    $current_transaction->utilities_consumption = $consumption;
    $current_transaction->utilities_location_id = $location_id;
    $current_transaction->utilities_location = $location_name;
    $current_transaction->utilities_category_id = $utility_category_id;
    $current_transaction->utilities_category = $utility_category_name;
    $current_transaction->utilities_account_no_id = $account_no_id;
    $current_transaction->utilities_account_no = $account_no;
    // ---------------------------------------------------------------------------------------------------------------------------------------
    // Payroll
    $payroll_type = isset($transaction["document"]["payroll"]["type"])
      ? $transaction["document"]["payroll"]["type"]
      : null;
    $payroll_category_id = isset($transaction["document"]["payroll"]["category"]["id"])
      ? $transaction["document"]["payroll"]["category"]["id"]
      : null;
    $payroll_category_name = isset($transaction["document"]["payroll"]["category"]["name"])
      ? $transaction["document"]["payroll"]["category"]["name"]
      : null;
    $clients = isset($transaction["document"]["payroll"]["clients"])
      ? $transaction["document"]["payroll"]["clients"]
      : null;

    $current_transaction->payroll_from = $document_from;
    $current_transaction->payroll_to = $document_to;
    $current_transaction->payroll_type = $payroll_type;
    $current_transaction->payroll_category_id = $payroll_category_id;
    $current_transaction->payroll_category = $payroll_category_name;
    $current_transaction->payroll_client = $clients;

    // ---------------------------------------------------------------------------------------------------------------------------------------
    // PCF
    $pcf_name = isset($transaction["document"]["pcf_batch"]["name"])
      ? $transaction["document"]["pcf_batch"]["name"]
      : null;
    $pcf_date = isset($transaction["document"]["pcf_batch"]["date"])
      ? $transaction["document"]["pcf_batch"]["date"]
      : null;
    $pcf_letter = isset($transaction["document"]["pcf_batch"]["letter"])
      ? $transaction["document"]["pcf_batch"]["letter"]
      : null;

    $current_transaction->pcf_name = $pcf_name;
    $current_transaction->pcf_date = $pcf_date;
    $current_transaction->pcf_letter = $pcf_letter;
    // ---------------------------------------------------------------------------------------------------------------------------------------

    // ---------------------------------------------------------------------------------------------------------------------------------------
    // Receipt
    $reference_id = isset($transaction["document"]["reference"]["id"])
      ? $transaction["document"]["reference"]["id"]
      : null;
    $reference_type = isset($transaction["document"]["reference"]["type"])
      ? $transaction["document"]["reference"]["type"]
      : null;
    $reference_no = isset($transaction["document"]["reference"]["no"])
      ? $transaction["document"]["reference"]["no"]
      : null;
    $reference_amount = isset($transaction["document"]["reference"]["amount"])
      ? $transaction["document"]["reference"]["amount"]
      : null;
    $is_allowable = isset($transaction["document"]["reference"]["allowable"])
      ? $transaction["document"]["reference"]["allowable"]
      : 0;

    $current_transaction->referrence_id = $reference_id;
    $current_transaction->referrence_type = $reference_type;
    $current_transaction->referrence_no = $reference_no;
    $current_transaction->referrence_amount = $reference_amount;

    // ---------------------------------------------------------------------------------------------------------------------------------------

    // Contractor's Billing
    $current_transaction->capex_no = $capex_no;

    $current_transaction->users_id = $transaction["requestor"]["id"];
    $current_transaction->id_prefix = $transaction["requestor"]["id_prefix"];
    $current_transaction->id_no = $transaction["requestor"]["id_no"];
    $current_transaction->first_name = $transaction["requestor"]["first_name"];
    $current_transaction->middle_name = $transaction["requestor"]["middle_name"];
    $current_transaction->last_name = $transaction["requestor"]["last_name"];
    $current_transaction->suffix = $transaction["requestor"]["suffix"];
    $current_transaction->department_details = $transaction["requestor"]["department"];

    $current_transaction->document_no = $document_no;
    $current_transaction->document_date = $document_date;
    $current_transaction->category_id = $category_id;
    $current_transaction->category = $category_name;
    $current_transaction->document_id = $transaction["document"]["id"];
    $current_transaction->document_type = $transaction["document"]["name"];
    $current_transaction->company_id = $transaction["document"]["company"]["id"];
    $current_transaction->company = $transaction["document"]["company"]["name"];
    $current_transaction->department_id = $transaction["document"]["department"]["id"];
    $current_transaction->department = $transaction["document"]["department"]["name"];
    $current_transaction->location_id = $transaction["document"]["location"]["id"];
    $current_transaction->location = $transaction["document"]["location"]["name"];
    $current_transaction->supplier_id = $transaction["document"]["supplier"]["id"];
    $current_transaction->supplier = $transaction["document"]["supplier"]["name"];
    $current_transaction->payment_type = $transaction["document"]["payment_type"];
    $current_transaction->document_amount = $amount;
    $current_transaction->remarks = $transaction["document"]["remarks"];

    if (isset($transaction["po_group"])) {
      $original_po_no = $original_transaction->po_details->pluck("po_no"); //628,629
      $input_po_no = GenericMethod::arrayPluck($transaction->po_group, "no"); //628

      $existing = [];
      $additional = [];
      $for_remove = [];
      foreach ($original_po_no as $k => $v) {
        if (in_array($v, $input_po_no)) {
          if ($original_transaction->po_details[$k]->id == null) {
            array_push($additional, $v);
          }

          //    echo $original_transaction->po_details[$k]->id.'_';
          //    echo $original_transaction->po_details[$k]->po_no.'_';
          //    echo $v.'_||||';
          // array_push($existing,$v);
        } else {
          array_push($for_remove, $v);
        }
      }
    }
    // return $additional;

    $newPO = [];
    $modifiedPO = [];
    //     $po_changes = [];
    //     $old = [];
    //     foreach($current_transaction->po_details as $k=>$v){
    //         foreach($transaction['po_group'] as $l=>$w){
    //            if($current_transaction->po_details[$k]->request_id == $transaction['po_group'][$l]['request_id']){
    //                if(
    //                    ($current_transaction->po_details[$k]->po_no != $transaction['po_group'][$l]['no'])||
    //                    ($current_transaction->po_details[$k]->po_amount != $transaction['po_group'][$l]['amount'])||
    //                    ($current_transaction->po_details[$k]->rr_group) != ($transaction['po_group'][$l]['rr_no'])

    //                ){
    //                    $modifiedPO[$k] = [
    //                        "old"=>[
    //                            "po_no"=>$current_transaction->po_details[$k]->po_no,
    //                            "po_amount"=>$current_transaction->po_details[$k]->po_amount,
    //                            "rr_group"=>$current_transaction->po_details[$k]->rr_group
    //                        ]
    //                        ,
    //                        "new"=>[
    //                            "po_no"=>$transaction['po_group'][$l]['no'],
    //                            "po_amount"=>$transaction['po_group'][$l]['amount'],
    //                            "rr_group"=>($transaction['po_group'][$l]['rr_no'])
    //                        ]
    //                    ];
    //                }

    //            }else{
    //                $newPO[$l] = [
    //                    "new"=>[
    //                        "po_no"=>$transaction['po_group'][$l]['no'],
    //                        "po_amount"=>$transaction['po_group'][$l]['amount'],
    //                        "rr_group"=>($transaction['po_group'][$l]['rr_no'])
    //                    ]
    //                ];
    //            }

    //        }
    //    }

    // $newPO = array_values($newPO);
    // $modifiedPO = array_values($modifiedPO);

    // $newPO = array_unique($newPO, 'new');
    $po_changes = [
      "modified" => $modifiedPO,
      "additional" => $newPO,
    ];
    $modifiedFields = array_keys($current_transaction->getDirty());
    $oldTransaction = collect();
    foreach ($modifiedFields as $field) {
      $oldTransaction->PUT("$field", $original_transaction->$field);
    }

    return $changes = [
      "old" => $oldTransaction,
      "new" => $current_transaction->getDirty(),
      "po_details" => $po_changes,
    ];
    // ---------------------------------------------------------------------------------------------------------------------------------------
  }

  public static function arrayPluck($array, $key)
  {
    return array_map(function ($object) use ($key) {
      return $object[$key];
    }, $array);
  }

  public static function insertTransaction(
    $transaction_id,
    $po_total_amount = 0,
    $request_id,
    $date_requested,
    $fields,
    $balance_po_ref_amount = 0
  ) {
    $status = "create";

    $requestor = Auth::user();

    if ($fields["document"]["id"] == 6) {
      //Utilities

      $new_transaction = Transaction::create([
        "transaction_id" => $transaction_id,
        // "users_id" => $fields["requestor"]["id"],
        // "id_prefix" => $fields["requestor"]["id_prefix"],
        // "id_no" => $fields["requestor"]["id_no"],
        // "first_name" => $fields["requestor"]["first_name"],
        // "middle_name" => $fields["requestor"]["middle_name"],
        // "last_name" => $fields["requestor"]["last_name"],
        // "suffix" => $fields["requestor"]["suffix"],
        // "department_details" => $fields["requestor"]["department"],

        "users_id" => $requestor->id,
        "id_prefix" => $requestor->id_prefix,
        "id_no" => $requestor->id_no,
        "first_name" => $requestor->first_name,
        "middle_name" => $requestor->middle_name,
        "last_name" => $requestor->last_name,
        "suffix" => $requestor->suffix,
        "department_details" => $requestor->department[0]["name"],

        "document_id" => $fields["document"]["id"],
        "company_id" => $fields["document"]["company"]["id"],
        "company" => $fields["document"]["company"]["name"],
        "department_id" => $fields["document"]["department"]["id"],
        "department" => $fields["document"]["department"]["name"],
        "location_id" => $fields["document"]["location"]["id"],
        "location" => $fields["document"]["location"]["name"],
        "supplier_id" => $fields["document"]["supplier"]["id"],
        "supplier" => $fields["document"]["supplier"]["name"],
        "payment_type" => $fields["document"]["payment_type"],
        "document_amount" => $fields["document"]["amount"],
        "remarks" => $fields["document"]["remarks"],
        "document_type" => $fields["document"]["name"],

        "utilities_from" => $fields["document"]["from"],
        "utilities_to" => $fields["document"]["to"],

        "utilities_receipt_no" => $fields["document"]["utility"]["receipt_no"],
        "utilities_consumption" => $fields["document"]["utility"]["consumption"],
        "utilities_location_id" => $fields["document"]["utility"]["location"]["id"],
        "utilities_location" => $fields["document"]["utility"]["location"]["name"],
        "utilities_category_id" => $fields["document"]["utility"]["category"]["id"],
        "utilities_category" => $fields["document"]["utility"]["category"]["name"],
        "utilities_account_no_id" => $fields["document"]["utility"]["account_no"]["id"],
        "utilities_account_no" => $fields["document"]["utility"]["account_no"]["no"],

        "po_total_amount" => $po_total_amount,

        "request_id" => $request_id,

        "date_requested" => $date_requested,
        "status" => "Pending",
          "business_unit_id" => $fields["document"]["business_unit"]["id"] ?? null,
          "business_unit" => $fields["document"]["business_unit"]["name"] ?? null,
          "sub_unit_id" => $fields["document"]["sub_unit"]["id"] ?? null,
          "sub_unit" => $fields["document"]["sub_unit"]["name"] ?? null,
      ]);
    } elseif ($fields["document"]["id"] == 8) {
      //PCF

      $new_transaction = Transaction::create([
        "transaction_id" => $transaction_id,
        // "users_id" => $fields["requestor"]["id"],
        // "id_prefix" => $fields["requestor"]["id_prefix"],
        // "id_no" => $fields["requestor"]["id_no"],
        // "first_name" => $fields["requestor"]["first_name"],
        // "middle_name" => $fields["requestor"]["middle_name"],
        // "last_name" => $fields["requestor"]["last_name"],
        // "suffix" => $fields["requestor"]["suffix"],
        // "department_details" => $fields["requestor"]["department"],

        "users_id" => $requestor->id,
        "id_prefix" => $requestor->id_prefix,
        "id_no" => $requestor->id_no,
        "first_name" => $requestor->first_name,
        "middle_name" => $requestor->middle_name,
        "last_name" => $requestor->last_name,
        "suffix" => $requestor->suffix,
        "department_details" => $requestor->department[0]["name"],

        "document_id" => $fields["document"]["id"],
        "company_id" => $fields["document"]["company"]["id"],
        "company" => $fields["document"]["company"]["name"],
        "department_id" => $fields["document"]["department"]["id"],
        "department" => $fields["document"]["department"]["name"],
        "location_id" => $fields["document"]["location"]["id"],
        "location" => $fields["document"]["location"]["name"],
        "supplier_id" => $fields["document"]["supplier"]["id"],
        "supplier" => $fields["document"]["supplier"]["name"],
        "payment_type" => $fields["document"]["payment_type"],
        "document_date" => $fields["document"]["date"],
        "document_amount" => $fields["document"]["amount"],
        "remarks" => $fields["document"]["remarks"],
        "document_type" => $fields["document"]["name"],

        "pcf_name" => $fields["document"]["pcf_batch"]["name"],
        "pcf_date" => $fields["document"]["pcf_batch"]["date"],
        "pcf_letter" => $fields["document"]["pcf_batch"]["letter"],
        "request_id" => $request_id,

        "date_requested" => $date_requested,
          "business_unit_id" => $fields["document"]["business_unit"]["id"] ?? null,
          "business_unit" => $fields["document"]["business_unit"]["name"] ?? null,
          "sub_unit_id" => $fields["document"]["sub_unit"]["id"] ?? null,
          "sub_unit" => $fields["document"]["sub_unit"]["name"] ?? null,
      ]);
    } elseif ($fields["document"]["id"] == 7) {
      //Payrol

      $new_transaction = Transaction::create([
        "transaction_id" => $transaction_id,
        // "users_id" => $fields["requestor"]["id"],
        // "id_prefix" => $fields["requestor"]["id_prefix"],
        // "id_no" => $fields["requestor"]["id_no"],
        // "first_name" => $fields["requestor"]["first_name"],
        // "middle_name" => $fields["requestor"]["middle_name"],
        // "last_name" => $fields["requestor"]["last_name"],
        // "suffix" => $fields["requestor"]["suffix"],
        // "department_details" => $fields["requestor"]["department"],

        "users_id" => $requestor->id,
        "id_prefix" => $requestor->id_prefix,
        "id_no" => $requestor->id_no,
        "first_name" => $requestor->first_name,
        "middle_name" => $requestor->middle_name,
        "last_name" => $requestor->last_name,
        "suffix" => $requestor->suffix,
        "department_details" => $requestor->department[0]["name"],

        "document_id" => $fields["document"]["id"],
        "company_id" => $fields["document"]["company"]["id"],
        "company" => $fields["document"]["company"]["name"],
        "department_id" => $fields["document"]["department"]["id"],
        "department" => $fields["document"]["department"]["name"],
        "location_id" => $fields["document"]["location"]["id"],
        "location" => $fields["document"]["location"]["name"],
        "supplier_id" => $fields["document"]["supplier"]["id"],
        "supplier" => $fields["document"]["supplier"]["name"],
        "payment_type" => $fields["document"]["payment_type"],
        "document_amount" => $fields["document"]["amount"],
        "remarks" => $fields["document"]["remarks"],
        "document_type" => $fields["document"]["name"],

        "payroll_from" => $fields["document"]["from"],
        "payroll_to" => $fields["document"]["to"],
        "payroll_category_id" => $fields["document"]["payroll"]["category"]["id"],
        "payroll_category" => $fields["document"]["payroll"]["category"]["name"],
        "payroll_control_no" => $fields["document"]["payroll"]["control_no"],
        "payroll_type" => $fields["document"]["payroll"]["type"],
        "payroll_client" => $fields["document"]["payroll"]["clients"],
        "request_id" => $request_id,

        "date_requested" => $date_requested,
          "business_unit_id" => $fields["document"]["business_unit"]["id"] ?? null,
          "business_unit" => $fields["document"]["business_unit"]["name"] ?? null,
          "sub_unit_id" => $fields["document"]["sub_unit"]["id"] ?? null,
          "sub_unit" => $fields["document"]["sub_unit"]["name"] ?? null,
      ]);
    } elseif ($fields["document"]["id"] == 4) {
      // Receipt

      $new_transaction = Transaction::create([
        "transaction_id" => $transaction_id,
        // "users_id" => $fields["requestor"]["id"],
        // "id_prefix" => $fields["requestor"]["id_prefix"],
        // "id_no" => $fields["requestor"]["id_no"],
        // "first_name" => $fields["requestor"]["first_name"],
        // "middle_name" => $fields["requestor"]["middle_name"],
        // "last_name" => $fields["requestor"]["last_name"],
        // "suffix" => $fields["requestor"]["suffix"],
        // "department_details" => $fields["requestor"]["department"],

        "users_id" => $requestor->id,
        "id_prefix" => $requestor->id_prefix,
        "id_no" => $requestor->id_no,
        "first_name" => $requestor->first_name,
        "middle_name" => $requestor->middle_name,
        "last_name" => $requestor->last_name,
        "suffix" => $requestor->suffix,
        "department_details" => $requestor->department[0]["name"],

        "document_id" => $fields["document"]["id"],
        "category_id" => $fields["document"]["category"]["id"],
        "category" => $fields["document"]["category"]["name"],
        "company_id" => $fields["document"]["company"]["id"],
        "company" => $fields["document"]["company"]["name"],
        "department_id" => $fields["document"]["department"]["id"],
        "department" => $fields["document"]["department"]["name"],
        "location_id" => $fields["document"]["location"]["id"],
        "location" => $fields["document"]["location"]["name"],
        "supplier_id" => $fields["document"]["supplier"]["id"],
        "supplier" => $fields["document"]["supplier"]["name"],
        "payment_type" => $fields["document"]["payment_type"],
        "document_date" => $fields["document"]["date"],
        "remarks" => $fields["document"]["remarks"],
        "document_type" => $fields["document"]["name"],

        "po_total_amount" => $po_total_amount,
        "balance_po_ref_amount" => $balance_po_ref_amount,

        "referrence_type" => $fields["document"]["reference"]["type"],
        "referrence_no" => $fields["document"]["reference"]["no"],
        "referrence_amount" => $fields["document"]["reference"]["amount"],
        "referrence_id" => $fields["document"]["reference"]["id"],
        "is_allowable" => $fields["document"]["reference"]["allowable"],

        "request_id" => $request_id,

        "date_requested" => $date_requested,
        "status" => "Pending",
        "is_not_editable" => false,
          "business_unit_id" => $fields["document"]["business_unit"]["id"] ?? null,
          "business_unit" => $fields["document"]["business_unit"]["name"] ?? null,
          "sub_unit_id" => $fields["document"]["sub_unit"]["id"] ?? null,
          "sub_unit" => $fields["document"]["sub_unit"]["name"] ?? null,
      ]);
    } elseif ($fields["document"]["id"] == 5) {
      //Contractor's Billing

      $new_transaction = Transaction::create([
        "transaction_id" => $transaction_id,
        // "users_id" => $fields["requestor"]["id"],
        // "id_prefix" => $fields["requestor"]["id_prefix"],
        // "id_no" => $fields["requestor"]["id_no"],
        // "first_name" => $fields["requestor"]["first_name"],
        // "middle_name" => $fields["requestor"]["middle_name"],
        // "last_name" => $fields["requestor"]["last_name"],
        // "suffix" => $fields["requestor"]["suffix"],
        // "department_details" => $fields["requestor"]["department"],

        "users_id" => $requestor->id,
        "id_prefix" => $requestor->id_prefix,
        "id_no" => $requestor->id_no,
        "first_name" => $requestor->first_name,
        "middle_name" => $requestor->middle_name,
        "last_name" => $requestor->last_name,
        "suffix" => $requestor->suffix,
        "department_details" => $requestor->department[0]["name"],

        "document_id" => $fields["document"]["id"],
        "capex_no" => $fields["document"]["capex_no"],
        "category_id" => $fields["document"]["category"]["id"],
        "category" => $fields["document"]["category"]["name"],
        "company_id" => $fields["document"]["company"]["id"],
        "company" => $fields["document"]["company"]["name"],
        "department_id" => $fields["document"]["department"]["id"],
        "department" => $fields["document"]["department"]["name"],
        "location_id" => $fields["document"]["location"]["id"],
        "location" => $fields["document"]["location"]["name"],
        "supplier_id" => $fields["document"]["supplier"]["id"],
        "supplier" => $fields["document"]["supplier"]["name"],
        "payment_type" => $fields["document"]["payment_type"],
        "document_date" => $fields["document"]["date"],
        "document_amount" => $fields["document"]["amount"],
        "remarks" => $fields["document"]["remarks"],
        "document_type" => $fields["document"]["name"],

        "po_total_amount" => $po_total_amount,

        "request_id" => $request_id,

        "date_requested" => $date_requested,
        "status" => "Pending",
          "business_unit_id" => $fields["document"]["business_unit"]["id"] ?? null,
          "business_unit" => $fields["document"]["business_unit"]["name"] ?? null,
          "sub_unit_id" => $fields["document"]["sub_unit"]["id"] ?? null,
          "sub_unit" => $fields["document"]["sub_unit"]["name"] ?? null,
      ]);
    } elseif ($fields["document"]["id"] == 3) {
      //PRM Multiple

      if (isset($fields["transaction"])) {
        $transaction_id = $fields["transaction"]["no"];
        $request_id = $fields["transaction"]["request_id"];
        $is_transacted = Tagging::where("transaction_id", $transaction_id)
          ->whereNotIn("status", ["tag-return", "tag-void"])
          ->exists();

        if ($is_transacted) {
          return "On Going Transaction";
        }
      }

      $category = $fields["document"]["category"]["name"];
      $prm_group = $fields["prm_group"];

      switch ($category) {
        case "additional rental":
        case "lounge rental":
        case "stall a rental":
        case "stall b rental":
        case "stall c rental":
        case "stall d rental":
        case "cusa rental":
        case "dorm rental":
        case "corporate special program - education":
        case "official store rental":
        case "unofficial store rental":
        case "rental":
          $errors = [];
          $error_date_format = [];
          $error_period_covered = [];
          $error_multiple_cheque = [];
          $error_amount_per_line = [];
          $total_gross = array_sum(array_column($prm_group, "gross_amount"));
          $total_cwt = array_sum(array_column($prm_group, "wht"));
          $total_net = array_sum(array_column($prm_group, "net_of_amount"));
          $total_witholding_and_net = $total_cwt + $total_net;
          $cheque_dates_array = array_column($prm_group, "cheque_date");
          $period_covered_array = array_column($prm_group, "period_covered");

          $message_if_error = "Document Amount and Total Gross amount not equal.";
          $validate_document_amount = GenericMethod::validate_document_amount(
            $fields["document"]["amount"],
            $total_gross,
            $message_if_error
          );
          if ($validate_document_amount) {
            return $validate_document_amount;
          }

          if (isset($fields["transaction"])) {
            Transaction::where("transaction_id", $transaction_id)->delete();
          }

          $error_date_format = GenericMethod::validate_prm_date_range_format($prm_group, $errors);
          $error_period_covered = GenericMethod::validate_period_covered($period_covered_array, $errors);
          $error_multiple_cheque = GenericMethod::validate_multiple_cheque_dates($cheque_dates_array, $errors);
          // $error_amount_per_line = GenericMethod::validate_amount_per_line($prm_group, $errors);
          $error_duplicate_transaction = GenericMethod::validate_duplicate_prm_multiple_transaction(
            $prm_group,
            $fields
          );
          $errors = array_merge(
            $error_date_format,
            $error_period_covered,
            $error_multiple_cheque,
            $error_amount_per_line,
            $error_duplicate_transaction
          );

          if ($errors) {
            $errors = collect($errors)
              ->sortBy(["line", "description"])
              ->values();
            $error_list = $errors
              ->unique(function ($item) {
                return $item["line"] . $item["description"];
              })
              ->values();
            // $error_list =  collect($errors)->unique('description')->all();
            return GenericMethod::resultResponse("upload-error", "", $error_list);
          }

          // PROCEED RENTAL
          foreach ($prm_group as $key => $prm_batch) {
            $period_covered = isset($prm_batch["period_covered"]) ? $prm_batch["period_covered"] : null;
            $period_covered_array = explode("-", $period_covered);
            $prm_multiple_from = date("Y-m-d", strtotime(trim($period_covered_array[0])));
            $prm_multiple_to = date("Y-m-d", strtotime(trim($period_covered_array[1])));
            $cheque_date = isset($prm_batch["cheque_date"]) ? $prm_batch["cheque_date"] : null;
            $gross_amount = isset($prm_batch["gross_amount"]) ? $prm_batch["gross_amount"] : null;
            $witholding_tax = isset($prm_batch["wht"]) ? $prm_batch["wht"] : null;
            $net_amount = isset($prm_batch["net_of_amount"]) ? $prm_batch["net_of_amount"] : null;
            $temporary_request_id = $request_id + $key;

            $new_transaction = Transaction::create([
              "transaction_id" => $transaction_id,
              // "users_id" => $fields["requestor"]["id"],
              // "id_prefix" => $fields["requestor"]["id_prefix"],
              // "id_no" => $fields["requestor"]["id_no"],
              // "first_name" => $fields["requestor"]["first_name"],
              // "middle_name" => $fields["requestor"]["middle_name"],
              // "last_name" => $fields["requestor"]["last_name"],
              // "suffix" => $fields["requestor"]["suffix"],
              // "department_details" => $fields["requestor"]["department"],

              "users_id" => $requestor->id,
              "id_prefix" => $requestor->id_prefix,
              "id_no" => $requestor->id_no,
              "first_name" => $requestor->first_name,
              "middle_name" => $requestor->middle_name,
              "last_name" => $requestor->last_name,
              "suffix" => $requestor->suffix,
              "department_details" => $requestor->department[0]["name"],

              "document_id" => $fields["document"]["id"],
              "category_id" => $fields["document"]["category"]["id"],
              "category" => $fields["document"]["category"]["name"],
              "company_id" => $fields["document"]["company"]["id"],
              "company" => $fields["document"]["company"]["name"],
              "department_id" => $fields["document"]["department"]["id"],
              "department" => $fields["document"]["department"]["name"],
              "location_id" => $fields["document"]["location"]["id"],
              "location" => $fields["document"]["location"]["name"],
              "supplier_id" => $fields["document"]["supplier"]["id"],
              "supplier" => $fields["document"]["supplier"]["name"],
              "payment_type" => $fields["document"]["payment_type"],
              "document_no" => $fields["document"]["no"],
              "document_date" => isset($fields["document"]["date"]) ? $fields["document"]["date"] : null,
              "document_amount" => $fields["document"]["amount"],
              "remarks" => $fields["document"]["remarks"],
              "document_type" => $fields["document"]["name"],
              "po_total_amount" => $po_total_amount,
              // "request_id" => $temporary_request_id ? $temporary_request_id : null,
              "request_id" => isset($temporary_request_id) ? $temporary_request_id : null,

              "date_requested" => $date_requested,
              "status" => "Pending",
              "period_covered" => $period_covered ? $period_covered : null,
              "prm_multiple_from" => $prm_multiple_from ? $prm_multiple_from : null,
              "prm_multiple_to" => $prm_multiple_to ? $prm_multiple_to : null,
              "cheque_date" => $cheque_date ? $cheque_date : null,
              "gross_amount" => $gross_amount ? $gross_amount : null,
              "witholding_tax" => $witholding_tax ? $witholding_tax : null,
              "net_amount" => $net_amount ? $net_amount : null,
              "total_gross" => $total_gross ? $total_gross : null,
              "total_cwt" => $total_cwt ? $total_cwt : null,
              "total_net" => $total_net ? $total_net : null,
                "business_unit_id" => $fields["document"]["business_unit"]["id"] ?? null,
                "business_unit" => $fields["document"]["business_unit"]["name"] ?? null,
                "sub_unit_id" => $fields["document"]["sub_unit"]["id"] ?? null,
                "sub_unit" => $fields["document"]["sub_unit"]["name"] ?? null,
            ]);
          }

          static::prmMultiplerequestUpdateID($new_transaction);

          break;
        case "official store leasing":
        case "unofficial store leasing":
        case "leasing":
          $errors = [];
          $error_multiple_cheque = [];
          $error_amount_per_line = [];
          $error_duplicate_transaction = [];
          $total_principal = array_sum(array_column($prm_group, "principal"));
          $total_net_of_amount = array_sum(array_column($prm_group, "net_of_amount"));
          $cheque_dates_array = array_column($prm_group, "cheque_date");

          $message_if_error = "Document amount and total Net of amount not equal.";
          $validate_document_amount = GenericMethod::validate_document_amount(
            $fields["document"]["amount"],
            $total_net_of_amount,
            $message_if_error
          );
          if ($validate_document_amount) {
            return $validate_document_amount;
          }

          if (isset($fields["transaction"])) {
            Transaction::where("transaction_id", $transaction_id)->delete();
          }

          $error_multiple_cheque = GenericMethod::validate_multiple_cheque_dates($cheque_dates_array, $errors);
          // $error_amount_per_line = GenericMethod::validate_amount_per_line_leasing($prm_group, $errors);
          $error_duplicate_transaction = GenericMethod::validate_duplicate_prm_multiple_transaction_leasing_and_loans(
            $prm_group,
            $fields
          );
          $errors = array_merge($error_multiple_cheque, $error_amount_per_line, $error_duplicate_transaction);

          if ($errors) {
            $errors = collect($errors)
              ->sortBy(["line", "description"])
              ->values();
            $error_list = $errors
              ->unique(function ($item) {
                return $item["line"] . $item["description"];
              })
              ->values();
            // $error_list =  collect($errors)->unique('description')->all();
            return GenericMethod::resultResponse("upload-error", "", $error_list);
          }
          // PROCEED LEASING
          foreach ($prm_group as $key => $prm_batch) {
            $amortization = isset($prm_batch["amortization"]) ? $prm_batch["amortization"] : null;
            $interest = isset($prm_batch["interest"]) ? $prm_batch["interest"] : null;
            $cwt = isset($prm_batch["cwt"]) ? $prm_batch["cwt"] : null;
            $principal = isset($prm_batch["principal"]) ? $prm_batch["principal"] : null;
            $net_of_amount = isset($prm_batch["net_of_amount"]) ? $prm_batch["net_of_amount"] : null;
            $cheque_date = isset($prm_batch["cheque_date"]) ? $prm_batch["cheque_date"] : null;
            $temporary_request_id = $request_id + $key;

            $new_transaction = Transaction::create([
              "transaction_id" => $transaction_id,
              // "users_id" => $fields["requestor"]["id"],
              // "id_prefix" => $fields["requestor"]["id_prefix"],
              // "id_no" => $fields["requestor"]["id_no"],
              // "first_name" => $fields["requestor"]["first_name"],
              // "middle_name" => $fields["requestor"]["middle_name"],
              // "last_name" => $fields["requestor"]["last_name"],
              // "suffix" => $fields["requestor"]["suffix"],
              // "department_details" => $fields["requestor"]["department"],

              "users_id" => $requestor->id,
              "id_prefix" => $requestor->id_prefix,
              "id_no" => $requestor->id_no,
              "first_name" => $requestor->first_name,
              "middle_name" => $requestor->middle_name,
              "last_name" => $requestor->last_name,
              "suffix" => $requestor->suffix,
              "department_details" => $requestor->department[0]["name"],

              "document_id" => $fields["document"]["id"],
              "category_id" => $fields["document"]["category"]["id"],
              "category" => $fields["document"]["category"]["name"],
              "company_id" => $fields["document"]["company"]["id"],
              "company" => $fields["document"]["company"]["name"],
              "department_id" => $fields["document"]["department"]["id"],
              "department" => $fields["document"]["department"]["name"],
              "location_id" => $fields["document"]["location"]["id"],
              "location" => $fields["document"]["location"]["name"],
              "supplier_id" => $fields["document"]["supplier"]["id"],
              "supplier" => $fields["document"]["supplier"]["name"],
              "payment_type" => $fields["document"]["payment_type"],
              "document_no" => $fields["document"]["no"],
              "document_date" => isset($fields["document"]["date"]) ? $fields["document"]["date"] : null,
              "document_amount" => $fields["document"]["amount"],
              "remarks" => $fields["document"]["remarks"],
              "document_type" => $fields["document"]["name"],
              "po_total_amount" => $po_total_amount,
//              "request_id" => $temporary_request_id ? $temporary_request_id : null,
              "request_id" => isset($temporary_request_id) ? $temporary_request_id : null,

              "date_requested" => $date_requested,
              "status" => "Pending",
              "amortization" => $amortization ? $amortization : null,
              "interest" => $interest ? $interest : null,
              "cwt" => $cwt ? $cwt : null,
              "principal" => $principal ? $principal : null,
              "net_amount" => $net_of_amount ? $net_of_amount : null,
              "cheque_date" => $cheque_date ? $cheque_date : null,
              "release_date" => $fields["document"]["release_date"],
              "batch_no" => $fields["document"]["batch_no"],
                "business_unit_id" => $fields["document"]["business_unit"]["id"] ?? null,
                "business_unit" => $fields["document"]["business_unit"]["name"] ?? null,
                "sub_unit_id" => $fields["document"]["sub_unit"]["id"] ?? null,
                "sub_unit" => $fields["document"]["sub_unit"]["name"] ?? null,
            ]);
          }
          static::prmMultiplerequestUpdateID($new_transaction);

          break;

        case "loans":
          $errors = [];
          $error_multiple_cheque = [];
          $error_amount_per_line = [];
          $error_duplicate_transaction = [];
          $total_principal = array_sum(array_column($prm_group, "principal"));
          $total_net_of_amount = array_sum(array_column($prm_group, "net_of_amount"));
          $cheque_dates_array = array_column($prm_group, "cheque_date");

          $message_if_error = "Document amount and total Net of amount not equal.";
          $validate_document_amount = GenericMethod::validate_document_amount(
            $fields["document"]["amount"],
            $total_net_of_amount,
            $message_if_error
          );

          if ($validate_document_amount) {
            return $validate_document_amount;
          }

          if (isset($fields["transaction"])) {
            Transaction::where("transaction_id", $transaction_id)->delete();
          }

          $error_multiple_cheque = GenericMethod::validate_multiple_cheque_dates($cheque_dates_array, $errors);
          //   return  $error_amount_per_line = GenericMethod::validate_amount_per_line_loans($prm_group, $errors);
          $error_duplicate_transaction = GenericMethod::validate_duplicate_prm_multiple_transaction_leasing_and_loans(
            $prm_group,
            $fields
          );
          $errors = array_merge($error_multiple_cheque, $error_amount_per_line, $error_duplicate_transaction);

          if ($errors) {
            $errors = collect($errors)
              ->sortBy(["line", "description"])
              ->values();
            $error_list = $errors
              ->unique(function ($item) {
                return $item["line"] . $item["description"];
              })
              ->values();
            // $error_list =  collect($errors)->unique('description')->all();
            return GenericMethod::resultResponse("upload-error", "", $error_list);
          }
          // PROCEED LOANS
          foreach ($prm_group as $key => $prm_batch) {
            $principal = isset($prm_batch["principal"]) ? $prm_batch["principal"] : null;
            $interest = isset($prm_batch["interest"]) ? $prm_batch["interest"] : null;
            $cwt = isset($prm_batch["cwt"]) ? $prm_batch["cwt"] : null;
            $net_of_amount = isset($prm_batch["net_of_amount"]) ? $prm_batch["net_of_amount"] : null;
            $cheque_date = isset($prm_batch["cheque_date"]) ? $prm_batch["cheque_date"] : null;
            $temporary_request_id = $request_id + $key;

            $new_transaction = Transaction::create([
              "transaction_id" => $transaction_id,
              // "users_id" => $fields["requestor"]["id"],
              // "id_prefix" => $fields["requestor"]["id_prefix"],
              // "id_no" => $fields["requestor"]["id_no"],
              // "first_name" => $fields["requestor"]["first_name"],
              // "middle_name" => $fields["requestor"]["middle_name"],
              // "last_name" => $fields["requestor"]["last_name"],
              // "suffix" => $fields["requestor"]["suffix"],
              // "department_details" => $fields["requestor"]["department"],

              "users_id" => $requestor->id,
              "id_prefix" => $requestor->id_prefix,
              "id_no" => $requestor->id_no,
              "first_name" => $requestor->first_name,
              "middle_name" => $requestor->middle_name,
              "last_name" => $requestor->last_name,
              "suffix" => $requestor->suffix,
              "department_details" => $requestor->department[0]["name"],

              "document_id" => $fields["document"]["id"],
              "category_id" => $fields["document"]["category"]["id"],
              "category" => $fields["document"]["category"]["name"],
              "company_id" => $fields["document"]["company"]["id"],
              "company" => $fields["document"]["company"]["name"],
              "department_id" => $fields["document"]["department"]["id"],
              "department" => $fields["document"]["department"]["name"],
              "location_id" => $fields["document"]["location"]["id"],
              "location" => $fields["document"]["location"]["name"],
              "supplier_id" => $fields["document"]["supplier"]["id"],
              "supplier" => $fields["document"]["supplier"]["name"],
              "payment_type" => $fields["document"]["payment_type"],
              "document_no" => $fields["document"]["no"],
              "document_date" => isset($fields["document"]["date"]) ? $fields["document"]["date"] : null,
              "document_amount" => $fields["document"]["amount"],
              "remarks" => $fields["document"]["remarks"],
              "document_type" => $fields["document"]["name"],
              "po_total_amount" => $po_total_amount,
              "request_id" => $temporary_request_id ? $temporary_request_id : null,

              "date_requested" => $date_requested,
              "status" => "Pending",
              "principal" => $principal ? $principal : null,
              "interest" => $interest ? $interest : null,
              "cwt" => $cwt ? $cwt : null,
              "net_amount" => $net_of_amount ? $net_of_amount : null,
              "cheque_date" => $cheque_date ? $cheque_date : null,
              "release_date" => $fields["document"]["release_date"],
              "batch_no" => $fields["document"]["batch_no"],
                "business_unit_id" => $fields["document"]["business_unit"]["id"] ?? null,
                "business_unit" => $fields["document"]["business_unit"]["name"] ?? null,
                "sub_unit_id" => $fields["document"]["sub_unit"]["id"] ?? null,
                "sub_unit" => $fields["document"]["sub_unit"]["name"] ?? null,
            ]);
          }

          static::prmMultiplerequestUpdateID($new_transaction);

          break;
      }
    } elseif ($fields["document"]["id"] == 9) {
      //Auto Debit

      $new_transaction = Transaction::create([
        "transaction_id" => $transaction_id,
        // "users_id" => $fields["requestor"]["id"],
        // "id_prefix" => $fields["requestor"]["id_prefix"],
        // "id_no" => $fields["requestor"]["id_no"],
        // "first_name" => $fields["requestor"]["first_name"],
        // "middle_name" => $fields["requestor"]["middle_name"],
        // "last_name" => $fields["requestor"]["last_name"],
        // "suffix" => $fields["requestor"]["suffix"],
        // "department_details" => $fields["requestor"]["department"],

        "users_id" => $requestor->id,
        "id_prefix" => $requestor->id_prefix,
        "id_no" => $requestor->id_no,
        "first_name" => $requestor->first_name,
        "middle_name" => $requestor->middle_name,
        "last_name" => $requestor->last_name,
        "suffix" => $requestor->suffix,
        "department_details" => $requestor->department[0]["name"],

        "document_id" => $fields["document"]["id"],
        "category_id" => $fields["document"]["category"]["id"],
        "category" => $fields["document"]["category"]["name"],
        "company_id" => $fields["document"]["company"]["id"],
        "company" => $fields["document"]["company"]["name"],
        "department_id" => $fields["document"]["department"]["id"],
        "department" => $fields["document"]["department"]["name"],
        "location_id" => $fields["document"]["location"]["id"],
        "location" => $fields["document"]["location"]["name"],
        "supplier_id" => $fields["document"]["supplier"]["id"],
        "supplier" => $fields["document"]["supplier"]["name"],
        "payment_type" => $fields["document"]["payment_type"],
        "document_date" => $fields["document"]["date"],
        "document_amount" => $fields["document"]["amount"],
        "remarks" => $fields["document"]["remarks"],
        "document_type" => $fields["document"]["name"],
        "po_total_amount" => $po_total_amount,
        "request_id" => $request_id,

        "date_requested" => $date_requested,
        "status" => "Pending",
          "business_unit_id" => $fields["document"]["business_unit"]["id"] ?? null,
          "business_unit" => $fields["document"]["business_unit"]["name"] ?? null,
          "sub_unit_id" => $fields["document"]["sub_unit"]["id"] ?? null,
          "sub_unit" => $fields["document"]["sub_unit"]["name"] ?? null,
      ]);

      if ($new_transaction->id) {
        // GenericMethod::insert_debit_attachment($request_id, $fields["autoDebit_group"]);

          if (isset($fields["autoDebit_group"])) {
              GenericMethod::insert_debit_attachment($request_id, $fields["autoDebit_group"]);
          }
      }
    } elseif ($fields["document"]["id"] == 1 && $fields["document"]["payment_type"] == "Partial") {
      $new_transaction = Transaction::create([
        "transaction_id" => $transaction_id,

        "users_id" => $requestor->id,
        "id_prefix" => $requestor->id_prefix,
        "id_no" => $requestor->id_no,
        "first_name" => $requestor->first_name,
        "middle_name" => $requestor->middle_name,
        "last_name" => $requestor->last_name,
        "suffix" => $requestor->suffix,
        "department_details" => $requestor->department[0]["name"],

        "document_id" => $fields["document"]["id"],
        "category_id" => $fields["document"]["category"]["id"],
        "category" => $fields["document"]["category"]["name"],
        "company_id" => $fields["document"]["company"]["id"],
        "company" => $fields["document"]["company"]["name"],
        "department_id" => $fields["document"]["department"]["id"],
        "department" => $fields["document"]["department"]["name"],
        "location_id" => $fields["document"]["location"]["id"],
        "location" => $fields["document"]["location"]["name"],
        "supplier_id" => $fields["document"]["supplier"]["id"],
        "supplier" => $fields["document"]["supplier"]["name"],
        "payment_type" => $fields["document"]["payment_type"],
        "document_no" => $fields["document"]["no"],
        "document_date" => $fields["document"]["date"],
        "document_amount" => $fields["document"]["amount"],
        "remarks" => $fields["document"]["remarks"],
        "document_type" => $fields["document"]["name"],

        "po_total_amount" => $po_total_amount,
        "balance_po_ref_amount" => $balance_po_ref_amount,
        "request_id" => $request_id,

        "date_requested" => $date_requested,
        "status" => "Pending",
        "is_not_editable" => false,
          "business_unit_id" => $fields["document"]["business_unit"]["id"] ?? null,
          "business_unit" => $fields["document"]["business_unit"]["name"] ?? null,
          "sub_unit_id" => $fields["document"]["sub_unit"]["id"] ?? null,
          "sub_unit" => $fields["document"]["sub_unit"]["name"] ?? null,
      ]);
    } else {
      $new_transaction = Transaction::create([
        "transaction_id" => $transaction_id,
        // "users_id" => $fields["requestor"]["id"],
        // "id_prefix" => $fields["requestor"]["id_prefix"],
        // "id_no" => $fields["requestor"]["id_no"],
        // "first_name" => $fields["requestor"]["first_name"],
        // "middle_name" => $fields["requestor"]["middle_name"],
        // "last_name" => $fields["requestor"]["last_name"],
        // "suffix" => $fields["requestor"]["suffix"],
        // "department_details" => $fields["requestor"]["department"],

        "users_id" => $requestor->id,
        "id_prefix" => $requestor->id_prefix,
        "id_no" => $requestor->id_no,
        "first_name" => $requestor->first_name,
        "middle_name" => $requestor->middle_name,
        "last_name" => $requestor->last_name,
        "suffix" => $requestor->suffix,
        "department_details" => $requestor->department[0]["name"],

        "document_id" => $fields["document"]["id"],
        "category_id" => $fields["document"]["category"]["id"],
        "category" => $fields["document"]["category"]["name"],
        "company_id" => $fields["document"]["company"]["id"],
        "company" => $fields["document"]["company"]["name"],
        "department_id" => $fields["document"]["department"]["id"],
        "department" => $fields["document"]["department"]["name"],
        "location_id" => $fields["document"]["location"]["id"],
        "location" => $fields["document"]["location"]["name"],
        "supplier_id" => $fields["document"]["supplier"]["id"],
        "supplier" => $fields["document"]["supplier"]["name"],
        "payment_type" => $fields["document"]["payment_type"],
        "document_no" => $fields["document"]["no"],
        "document_date" => $fields["document"]["date"],
        "document_amount" => $fields["document"]["amount"],
        "remarks" => $fields["document"]["remarks"],
        "document_type" => $fields["document"]["name"],
        "po_total_amount" => $po_total_amount,
        "request_id" => $request_id,

        "date_requested" => $date_requested,
        "status" => "Pending",
          "business_unit_id" => $fields["document"]["business_unit"]["id"] ?? null,
          "business_unit" => $fields["document"]["business_unit"]["name"] ?? null,
          "sub_unit_id" => $fields["document"]["sub_unit"]["id"] ?? null,
          "sub_unit" => $fields["document"]["sub_unit"]["name"] ?? null,
      ]);
    }

    GenericMethod::updateRequestID($new_transaction->id);
    GenericMethod::insertRequestorLogs(
      $new_transaction->id,
      $transaction_id,
      $date_requested,
      $fields["document"]["remarks"],
      Auth::user()->id,
      $status,
      null,
      null,
      null
    );

    return $new_transaction;
  }

  public static function updateRequestID($id)
  {
    Transaction::where("id", $id)->update([
      "request_id" => $id,
    ]);
  }

  public static function updateTransaction(
    $transaction_id,
    $po_total_amount = 0,
    $request_id,
    $date_requested,
    $fields,
    $balance_po_ref_amount = 0,
    $changes
  ) {
    if ($fields["document"]["name"] == "PRM Multiple") {
      $transaction = GenericMethod::insertTransaction(
        $transaction_id,
        $po_total_amount = 0,
        $request_id,
        $date_requested,
        $fields,
        $balance_po_ref_amount = 0
      );
      return $transaction;
    }
    $currentTransaction = Transaction::with("po_details")
      ->where("id", $transaction_id)
      ->first();
    $currentTransaction->isClean();
    $status = "update";

    $capex_no = isset($fields["document"]["capex_no"]) ? $fields["document"]["capex_no"] : null;
    $document_no = isset($fields["document"]["no"]) ? $fields["document"]["no"] : null;
    $document_date = isset($fields["document"]["date"]) ? $fields["document"]["date"] : null;
    $category_id = isset($fields["document"]["category"]["id"]) ? $fields["document"]["category"]["id"] : null;
    $category_name = isset($fields["document"]["category"]["name"]) ? $fields["document"]["category"]["name"] : null;
    $document_from = isset($fields["document"]["from"]) ? $fields["document"]["from"] : null;
    $document_to = isset($fields["document"]["to"]) ? $fields["document"]["to"] : null;
    $amount = isset($fields["document"]["amount"])
      ? $fields["document"]["amount"]
      : $fields["document"]["reference"]["amount"];

    // Utilities
    $receipt_no = isset($fields["document"]["utility"]["receipt_no"])
      ? $fields["document"]["utility"]["receipt_no"]
      : null;
    $consumption = isset($fields["document"]["utility"]["consumption"])
      ? $fields["document"]["utility"]["consumption"]
      : null;
    $location_id = isset($fields["document"]["utility"]["location"]["id"])
      ? $fields["document"]["utility"]["location"]["id"]
      : null;
    $location_name = isset($fields["document"]["utility"]["location"]["name"])
      ? $fields["document"]["utility"]["location"]["name"]
      : null;
    $utility_category_id = isset($fields["document"]["utility"]["category"]["id"])
      ? $fields["document"]["utility"]["category"]["id"]
      : null;
    $utility_category_name = isset($fields["document"]["utility"]["category"]["name"])
      ? $fields["document"]["utility"]["category"]["name"]
      : null;
    $account_no_id = isset($fields["document"]["utility"]["account_no"]["id"])
      ? $fields["document"]["utility"]["account_no"]["id"]
      : null;
    $account_no = isset($fields["document"]["utility"]["account_no"]["no"])
      ? $fields["document"]["utility"]["account_no"]["no"]
      : null;

    // Payroll
    $payroll_type = isset($fields["document"]["payroll"]["type"]) ? $fields["document"]["payroll"]["type"] : null;
    $payroll_category_id = isset($fields["document"]["payroll"]["category"]["id"])
      ? $fields["document"]["payroll"]["category"]["id"]
      : null;
    $payroll_category_name = isset($fields["document"]["payroll"]["category"]["name"])
      ? $fields["document"]["payroll"]["category"]["name"]
      : null;
    $payroll_control_no = $fields->input("document.payroll.control_no", null);
    $clients = isset($fields["document"]["payroll"]["clients"]) ? $fields["document"]["payroll"]["clients"] : null;

    // PCF
    $pcf_name = isset($fields["document"]["pcf_batch"]["name"]) ? $fields["document"]["pcf_batch"]["name"] : null;
    $pcf_date = isset($fields["document"]["pcf_batch"]["date"]) ? $fields["document"]["pcf_batch"]["date"] : null;
    $pcf_letter = isset($fields["document"]["pcf_batch"]["letter"]) ? $fields["document"]["pcf_batch"]["letter"] : null;

    // Receipt
    $reference_id = isset($fields["document"]["reference"]["id"]) ? $fields["document"]["reference"]["id"] : null;
    $reference_type = isset($fields["document"]["reference"]["type"]) ? $fields["document"]["reference"]["type"] : null;
    $reference_no = isset($fields["document"]["reference"]["no"]) ? $fields["document"]["reference"]["no"] : null;
    $reference_amount = isset($fields["document"]["reference"]["amount"])
      ? $fields["document"]["reference"]["amount"]
      : null;
    $is_allowable = isset($fields["document"]["reference"]["allowable"])
      ? $fields["document"]["reference"]["allowable"]
      : 0;
    $balance_po_ref_amount = isset($balance_po_ref_amount) ? $balance_po_ref_amount : null;

    $currentTransaction->transaction_id = $fields["transaction"]["no"];
    // $currentTransaction->users_id = $fields["requestor"]["id"];
    // $currentTransaction->id_prefix = $fields["requestor"]["id_prefix"];
    // $currentTransaction->id_no = $fields["requestor"]["id_no"];
    // $currentTransaction->first_name = $fields["requestor"]["first_name"];
    // $currentTransaction->middle_name = $fields["requestor"]["middle_name"];
    // $currentTransaction->last_name = $fields["requestor"]["last_name"];
    // $currentTransaction->suffix = $fields["requestor"]["suffix"];
    // $currentTransaction->department_details = $fields["requestor"]["department"];

    $requestor = Auth::user();

    $currentTransaction->users_id = $requestor->id;
    $currentTransaction->id_prefix = $requestor->id_prefix;
    $currentTransaction->id_no = $requestor->id_no;
    $currentTransaction->first_name = $requestor->first_name;
    $currentTransaction->middle_name = $requestor->middle_name;
    $currentTransaction->last_name = $requestor->last_name;
    $currentTransaction->suffix = $requestor->suffix;
    $currentTransaction->department_details = $requestor->department[0]["name"];

    $currentTransaction->document_no = $document_no;
    $currentTransaction->document_date = $document_date;
    $currentTransaction->category_id = $category_id;
    $currentTransaction->category = $category_name;
    $currentTransaction->document_id = $fields["document"]["id"];
    $currentTransaction->document_type = $fields["document"]["name"];
    $currentTransaction->company_id = $fields["document"]["company"]["id"];
    $currentTransaction->company = $fields["document"]["company"]["name"];
    $currentTransaction->department_id = $fields["document"]["department"]["id"];
    $currentTransaction->department = $fields["document"]["department"]["name"];
    $currentTransaction->location_id = $fields["document"]["location"]["id"];
    $currentTransaction->location = $fields["document"]["location"]["name"];
    $currentTransaction->supplier_id = $fields["document"]["supplier"]["id"];
    $currentTransaction->supplier = $fields["document"]["supplier"]["name"];
    $currentTransaction->payment_type = $fields["document"]["payment_type"];
    $currentTransaction->document_amount = $amount;
    $currentTransaction->remarks = $fields["document"]["remarks"];
    $currentTransaction->po_total_amount = $po_total_amount;
    $currentTransaction->request_id = $request_id;
    $currentTransaction->status = "Pending";
    $currentTransaction->state = "pending";
    $currentTransaction->reason_id = null;
    $currentTransaction->reason = null;
    $currentTransaction->reason_remarks = null;

//    $currentTransaction->business_unit_id = $fields["document"]["business_unit"]["id"];
//    $currentTransaction->business_unit = $fields["document"]["business_unit"]["name"];
//    $currentTransaction->sub_unit_id = $fields["document"]["sub_unit"]["id"];
//    $currentTransaction->sub_unit = $fields["document"]["sub_unit"]["name"];

    // Contractor's Billing
    $currentTransaction->capex_no = $capex_no;

    // Utility
    $currentTransaction->utilities_from = $document_from;
    $currentTransaction->utilities_to = $document_to;
    $currentTransaction->utilities_receipt_no = $receipt_no;
    $currentTransaction->utilities_consumption = $consumption;
    $currentTransaction->utilities_location_id = $location_id;
    $currentTransaction->utilities_location = $location_name;
    $currentTransaction->utilities_category_id = $utility_category_id;
    $currentTransaction->utilities_category = $utility_category_name;
    $currentTransaction->utilities_account_no_id = $account_no_id;
    $currentTransaction->utilities_account_no = $account_no;

    // Payroll
    $currentTransaction->payroll_from = $document_from;
    $currentTransaction->payroll_to = $document_to;
    $currentTransaction->payroll_type = $payroll_type;
    $currentTransaction->payroll_category_id = $payroll_category_id;
    $currentTransaction->payroll_category = $payroll_category_name;
    $currentTransaction->payroll_control_no = $payroll_control_no;
    $currentTransaction->payroll_client = $clients;

    // PCF
    $currentTransaction->pcf_name = $pcf_name;
    $currentTransaction->pcf_date = $pcf_date;
    $currentTransaction->pcf_letter = $pcf_letter;

    // Receipt
    $currentTransaction->referrence_id = $reference_id;
    $currentTransaction->referrence_type = $reference_type;
    $currentTransaction->referrence_no = $reference_no;
    $currentTransaction->referrence_amount = $reference_amount;
    $currentTransaction->is_allowable = $is_allowable;
    $currentTransaction->balance_po_ref_amount = $balance_po_ref_amount;

    $currentTransaction->save();
    GenericMethod::insertRequestorLogs(
      $transaction_id,
      $fields["transaction"]["no"],
      $date_requested,
      $fields["document"]["remarks"],
      Auth::user()->id,
      $status,
      null,
      null,
      null,
      $changes
    );
    return $currentTransaction;
  }

  public static function validateIfNothingChangeThenSave($model, $modelName, $is_tagged_array_modified = 0)
  {
    // return $model->isClean().'&&'.$is_tagged_array_modified;
    if ($model->isClean() && $is_tagged_array_modified == 0) {
      return GenericMethod::resultResponse("nothing-has-changed", $modelName, []);
    } else {
      $model->save();
      return GenericMethod::resultResponse("update", $modelName, []);
    }
  }

  public static function insertRequestorLogs(
    $transaction_id,
    $transaction_no,
    $date_requested,
    $remarks,
    $user_id,
    $status,
    $reason_id,
    $reason_description,
    $reason_remarks,
    $changes = []
  ) {
    RequestorLogs::create([
      "transaction_id" => $transaction_id,
      "transaction_no" => $transaction_no,
      "description" => $remarks,
      "status" => $status,
      "date_status" => $date_requested,
      "user_id" => $user_id,
      "reason_id" => $reason_id,
      "reason_description" => $reason_description,
      "reason_remarks" => $reason_remarks,
      "changes" => $changes,
    ]);
  }

  public static function insertClient($request_id, $clients)
  {
    $client_count = count($clients);
    for ($i = 0; $i < $client_count; $i++) {
      $id = $clients[$i]["id"];
      $name = $clients[$i]["name"];
      $insert_po_batch = TransactionClient::create([
        "request_id" => $request_id,
        "client_id" => $id,
        "client_name" => $name,
      ]);
    }
  }

  public static function insert_debit_attachment($request_id, $autoDebit_group)
  {
    $autoDebit_count = count($autoDebit_group);
    for ($i = 0; $i < $autoDebit_count; $i++) {
      $insert_po_batch = DebitBatch::create([
        "request_id" => $request_id,
        "pn_no" => $autoDebit_group[$i]["pn_no"],
        "interest_from" => $autoDebit_group[$i]["interest_from"],
        "interest_to" => $autoDebit_group[$i]["interest_to"],
        "outstanding_amount" => $autoDebit_group[$i]["outstanding_amount"],
        "interest_rate" => $autoDebit_group[$i]["interest_rate"],
        "no_of_days" => $autoDebit_group[$i]["no_of_days"],
        "principal_amount" => $autoDebit_group[$i]["principal_amount"],
        "interest_due" => $autoDebit_group[$i]["interest_due"],
        "cwt" => $autoDebit_group[$i]["cwt"],
        "dst" => $autoDebit_group[$i]["dst"],
      ]);
    }
  }

  public static function update_debit_attachment($request_id, $autoDebit_group, $id)
  {
    DebitBatch::where("request_id", $request_id)->delete();
    $autoDebit_count = count($autoDebit_group);
    for ($i = 0; $i < $autoDebit_count; $i++) {
      $insert_po_batch = DebitBatch::create([
        "request_id" => $request_id,
        "pn_no" => $autoDebit_group[$i]["pn_no"],
        "interest_from" => $autoDebit_group[$i]["interest_from"],
        "interest_to" => $autoDebit_group[$i]["interest_to"],
        "outstanding_amount" => $autoDebit_group[$i]["outstanding_amount"],
        "interest_rate" => $autoDebit_group[$i]["interest_rate"],
        "no_of_days" => $autoDebit_group[$i]["no_of_days"],
        "principal_amount" => $autoDebit_group[$i]["principal_amount"],
        "interest_due" => $autoDebit_group[$i]["interest_due"],
        "cwt" => $autoDebit_group[$i]["cwt"],
        "dst" => $autoDebit_group[$i]["dst"],
      ]);
    }
  }

  public static function insertPO($request_id, $po_group, $po_total_amount, $payment_type)
  {
    $po_count = count($po_group);
    for ($i = 0; $i < $po_count; $i++) {
      $is_add = null;
      $is_editable = 1;
      $previous_balance = $po_group[$i]["amount"];
      if ($payment_type === "PARTIAL") {
        $is_add = $po_group[$i]["is_add"];
        $is_editable = $po_group[$i]["is_editable"];
        $previous_balance = $po_group[$i]["previous_balance"];
      }

      $po_no = $po_group[$i]["no"];
      $po_amount = $po_group[$i]["amount"];
      $rr_group = $po_group[$i]["rr_no"];

      $insert_po_batch = POBatch::create([
        "request_id" => $request_id,
        "is_add" => $is_add,
        "is_editable" => $is_editable,
        "previous_balance" => $previous_balance,
        "po_no" => $po_no,
        "po_amount" => $po_amount,
        "rr_group" => $rr_group,
        "po_total_amount" => $po_total_amount,
      ]);
    }
  }

  public static function updatePO($request_id, $po_group, $po_total_amount, $payment_type, $id)
  {
    $po_count = count($po_group);

    POBatch::where("request_id", $request_id)->delete();
    for ($i = 0; $i < $po_count; $i++) {
      $is_add = null;
      $is_editable = 1;
      $previous_balance = $po_group[$i]["amount"];
      if ($payment_type === "PARTIAL") {
        $is_add = $po_group[$i]["is_add"];
        $is_editable = $po_group[$i]["is_editable"];
        $previous_balance = $po_group[$i]["previous_balance"];
      }

      $po_no = $po_group[$i]["no"];
      $po_amount = $po_group[$i]["amount"];
      $rr_group = $po_group[$i]["rr_no"];

      $insert_po_batch = POBatch::create([
        "request_id" => $request_id,
        "is_add" => $is_add,
        "is_editable" => $is_editable,
        "previous_balance" => $previous_balance,
        "po_no" => $po_no,
        "po_amount" => $po_amount,
        "rr_group" => $rr_group,
        "po_total_amount" => $po_total_amount,
      ]);

      POBatch::where("request_id", $request_id)
        ->where("is_editable", true)
        ->update(["is_modifiable" => true]);

      // $isAdd = POBatch::where('request_id', $request_id)->get();

      // foreach ($isAdd as $record) {
      //   if ($record->is_editable) {
      //     $record->update([
      //         'is_modifiable' => true
      //     ]);
      //   }
      // }
    }
  }

  public static function updateClients($request_id, $client_groups, $id)
  {
    $client_group_count = count($client_groups);
    TransactionClient::where("request_id", $request_id)->delete();
    for ($i = 0; $i < $client_group_count; $i++) {
      $insert_po_batch = TransactionClient::create([
        "request_id" => $request_id,
        "client_id" => $client_groups[$i]["id"],
        "client_name" => $client_groups[$i]["name"],
      ]);
    }
  }

  public static function insertRef($request_id, $reference)
  {
    $insert_reference_batch = ReferrenceBatch::create([
      "request_id" => $request_id,
      "referrence_type" => $reference["document"]["reference"]["type"],
      "referrence_no" => $reference["document"]["no"],
      "referrence_amount" => $reference["document"]["amount"],
    ]);
  }

  public static function paginateme($items, $perPage, $page = null, $options = [])
  {
    if (!isset($perPage)) {
      $perPage = 10;
    }
    $page = $page ?: (Paginator::resolveCurrentPage() ?: 1);
    $items = $items instanceof Collection ? $items : Collection::make($items);
    return new LengthAwarePaginator(
      $items->forPage($page, $perPage)->values(),
      $items->count(),
      $perPage,
      $page,
      $options
    );
  }

  public static function updateTransactionStatus(
    $id,
    $transaction_id,
    $request_id,
    $receipt_type,
    $tag_no,
    $status,
    $state,
    $reason_id,
    $reason,
    $reason_remarks,
    $voucher_no,
    $voucher_month,
    $distributed_id,
    $distributed_name,
    $approver_id,
    $approver_name,
    $input_tax,
    $transaction_type = "cheque",
      $box_no = null,
      $is_cleared = null
  ) {
    // $voucher_no = isset($voucher_no) ? $voucher_no : null;
    // $voucher_month = isset($voucher_month) ? $voucher_month : null;

    if (
      in_array($status, ["reverse-receive-approver", "reverse-receive-requestor", "reverse-approve", "reverse-return"])
    ) {
      $reason_details = Reverse::where("tag_id", $tag_no)
        ->latest()
        ->get()
        ->first();
      $reason_id = $reason_details->reason_id;
      $reason = Reason::where("id", $reason_id)
        ->select("reason")
        ->first()->reason;
      $reason_remarks = $reason_details->remarks;
    }
    DB::table("transactions")
      ->when(
        in_array($status, [
          "tag-receive",
          "tag-hold",
          "tag-unhold",
          "tag-return",
          "tag-unreturn",
          "tag-void",
          "tag-tag",
        ]),
        function ($query) use ($id, $request_id) {
          $query->where("id", $id)->where("request_id", $request_id);
        },
        function ($query) use ($id, $tag_no) {
          $query->where("id", $id)->where("tag_no", $tag_no);
        }
      )
      ->when(
        $status == "reverse-request",
        function ($query) use (
          $status,
          $state,
          $tag_no,
            $receipt_type,
          $reason_id,
          $reason,
          $reason_remarks,
          $voucher_no,
          $voucher_month,
          $distributed_id,
          $distributed_name,
          $approver_id,
          $approver_name
        ) {
          $query->update([
            "status" => $status,
            "state" => $state,
            "receipt_type" => $receipt_type,
            "tag_no" => $tag_no,
            "reason_id" => $reason_id,
            "reason" => $reason,
            "reason_remarks" => $reason_remarks,
            "voucher_no" => $voucher_no,
            "voucher_month" => $voucher_month,
            "reverse_distributed_id" => $distributed_id,
            "reverse_distributed_name" => $distributed_name,
            "approver_id" => $approver_id,
            "approver_name" => $approver_name,
          ]);
        },
        function ($query) use (
          $status,
          $state,
          $receipt_type,
          $tag_no,
          $reason_id,
          $reason,
          $reason_remarks,
          $voucher_no,
          $voucher_month,
          $distributed_id,
          $distributed_name,
          $approver_id,
          $approver_name,
          $transaction_type,
            $box_no,
            $is_cleared,
            $input_tax
        ) {
          $query->update([
            "status" => $status,
            "state" => $state,
            "receipt_type" => $receipt_type,
            "tag_no" => $tag_no,
            "reason_id" => $reason_id,
            "reason" => $reason,
            "reason_remarks" => $reason_remarks,
            "voucher_no" => $voucher_no,
            "voucher_month" => $voucher_month,
            "distributed_id" => $distributed_id,
            "distributed_name" => $distributed_name,
            "approver_id" => $approver_id,
            "approver_name" => $approver_name,
            "transaction_type" => $transaction_type,
              'box_no' => $box_no,
              "is_cleared" => $is_cleared,
              'input_tax' => $input_tax
          ]);
        }
      );
  }

  public static function addAND($array)
  {
    $last = array_slice($array, -1);
    $first = join(", ", array_slice($array, 0, -1));
    $both = array_filter(array_merge([$first], $last), "strlen");
    return join(" and ", $both);
  }

  ##########################################################################################################
  #########################################      RETRIEVE             ######################################
  ##########################################################################################################

  public static function getFullname($fname, $mname = "", $lname, $suffix)
  {
    $fullname = $fname . " " . strtoupper($mname[0]) . ". " . $lname . " " . $suffix;
    return $fullname;
  }

  public static function getFullnameNoMiddle($fname, $lname, $suffix)
  {
    $fullname = $fname . " " . $lname . " " . $suffix;
    return $fullname;
  }

  public static function setGroup($group, $field1, $field2)
  {
    $list = collect();
    $total = 0;
    $group_details = collect();
    foreach ($group as $specific_group) {
      $list->push($specific_group->$field1);
      $total = $total + $specific_group->$field2;
    }

    $group_details->push([
      "" . $field1 . "_list" => $list,
      "total_" . $field2 . "" => $total,
    ]);

    return $group_details;
  }

  public static function generateTagNo($receipt_type, $id)
  {
//    return Transaction::max("tag_no") + 1;
      return static::generateTagNoTest($receipt_type, $id);
  }

  public static function countTableById($table, $id)
  {
    $table = DB::table($table)->where("id", $id);
    return $table->count();
  }

  public static function unique_values_in_array_based_on_key($array, $key)
  {
    $temp_array = [];
    $i = 0;
    $key_array = [];

    foreach ($array as $val) {
      if (!in_array($val->$key, $key_array)) {
        $key_array[$i] = $val->$key;
        $temp_array[$i] = $val;
      }
      $i++;
    }
    return array_values($temp_array);
  }

  public static function where($collection, $field, $id, $desired_field)
  {
    $new_request = collect();
    foreach ($collection as $specific_collection) {
      if ($specific_collection["" . $field . ""] == $id) {
        $new_request->push([
          "id" => $id,
          "categories" => $specific_collection["" . $desired_field . ""],
        ]);
      }
    }

    return $new_request;
  }

  public static function getBalanceAmountOfRefPO($payment_type, $company_id, $supplier_id, $po_no, $document_amount)
  {
    $transactions = DB::table("transactions")
      ->leftJoin("p_o_batches", "transactions.request_id", "=", "p_o_batches.request_id")
      ->where("transactions.company_id", $company_id)
      ->where("transactions.supplier_id", $supplier_id)
      ->where("p_o_batches.po_no", $po_no)
      ->orderBy("transactions.id", "desc")
      ->take(1)
      ->get("transactions.balance_po_ref_amount");

    return $transactions;
  }

  public static function getBalanceQtyOfRefPO($payment_type, $company_id, $supplier_id, $po_no, $ref_qty)
  {
    $transactions = DB::table("transactions")
      ->leftJoin("p_o_batches", "transactions.request_id", "=", "p_o_batches.request_id")
      ->where("transactions.company_id", $company_id)
      ->where("transactions.supplier_id", $supplier_id)
      ->where("p_o_batches.po_no", $po_no)
      ->orderBy("transactions.id", "desc")
      ->take(1)
      ->get("transactions.balance_po_ref_qty");

    return $transactions;
  }

  public static function getUsedPO($payment_type, $company_id, $supplier_id, $po_no, $document_amount)
  {
    $transactions = DB::table("transactions")
      ->leftJoin("p_o_batches", "transactions.request_id", "=", "p_o_batches.request_id")
      ->where("transactions.payment_type", $payment_type)
      ->where("transactions.company_id", $company_id)
      ->where("transactions.supplier_id", $supplier_id)
      ->where("p_o_batches.po_no", $po_no)
      ->where("transactions.balance_document_po_amount", "<", $document_amount);
    return $transactions->count();
  }

  public static function getPOWithInsufficientAmont($payment_type, $company_id, $supplier_id, $po_no, $document_amount)
  {
    $transactions = DB::table("transactions")
      ->leftJoin("p_o_batches", "transactions.request_id", "=", "p_o_batches.request_id")
      ->where("payment_type", $payment_type)
      ->where("company_id", $company_id)
      ->where("supplier_id", $supplier_id)
      ->where("po_no", $po_no)
      ->where("balance_document_po_amount", "<", $document_amount)
      ->get();
    return $transactions;
  }

  public static function getTagIDUsingPONo($payment_type, $company_id, $supplier_id, $po_no)
  {
    $transactions = DB::table("transactions")
      ->select("transactions.request_id")
      ->leftJoin("p_o_batches", "transactions.request_id", "=", "p_o_batches.request_id")
      ->where("transactions.company_id", $company_id)
      ->where("transactions.supplier_id", $supplier_id)
      ->where("p_o_batches.po_no", $po_no)
      ->where(function ($query) {
        $query
          ->whereNotNull("transactions.balance_po_ref_amount")
          ->orWhere("transactions.balance_po_ref_amount", ">", 0);
      })
      ->orderBy("transactions.request_id", "desc")
      ->get();
    return $transactions;
  }

  public static function getUsedPOFromDB($payment_type, $company_id, $supplier_id, $po_no, $document_amount)
  {
    $transactions = DB::table("p_o_batches")
      ->where("request_id", "=", function ($query) use ($po_no) {
        $query
          ->selectRaw("request_id")
          ->from("p_o_batches")
          ->where("po_no", $po_no)
          ->orderByDesc("id")
          ->limit(1);
      })
      ->get("po_no");

    return $transactions;
  }

  public static function getUserDetailsById($id)
  {
    $user_details = User::find($id);

    $document_details = DB::select(
      DB::raw('SELECT documents.id AS "masterlist_id",
            documents.document_type AS "document_name",
            IFNULL(categories.id,"no category")  AS "masterlist_category_id",
            categories.name AS "category_name",
            user_document_category.user_id AS "user_id" ,
            user_document_category.id AS "user_id",
            user_document_category.category_id AS "user_category_id"
            FROM documents
            LEFT JOIN document_categories
            ON documents.id = document_categories.id
            LEFT JOIN categories
            ON document_categories.category_id = categories.id
            LEFT JOIN user_document_category
            ON document_categories.id = user_document_category.id AND document_categories.category_id = user_document_category.category_id
            LEFT JOIN users
            ON user_document_category.user_id = users.id
            ORDER by documents.id,categories.id')
    );

    $user_document_details = collect();
    $document_types = collect();
    $categories = collect();
    $categories_per_doc = [];

    foreach ($document_details as $specific_document_details) {
      if (
        $specific_document_details->masterlist_id == $specific_document_details->user_id and
        $specific_document_details->masterlist_category_id == $specific_document_details->user_category_id
      ) {
        $document_status = true;
        $category_status = true;
      } elseif (
        $specific_document_details->masterlist_id == $specific_document_details->user_id and
        $specific_document_details->masterlist_category_id != $specific_document_details->user_category_id
      ) {
        $document_status = true;
        $category_status = false;
      } elseif (
        $specific_document_details->masterlist_id != $specific_document_details->user_id and
        $specific_document_details->masterlist_category_id != $specific_document_details->user_category_id
      ) {
        $document_status = true;
        $category_status = false;
      } else {
        $document_status = false;
      }

      $categories->push([
        "user_id" => $specific_document_details->user_id,
        "user_category_id" => $specific_document_details->user_category_id,
        "id" => $specific_document_details->masterlist_id,
        "category_id" => $specific_document_details->masterlist_category_id,
        "category_name" => $specific_document_details->category_name,
        "category_status" => $category_status,
      ]);
    }

    $final_document_details = GenericMethod::unique_values_in_array_based_on_key($document_details, "masterlist_id");

    foreach ($final_document_details as $final_specific_document_details) {
      $ids[] = $final_specific_document_details->masterlist_id;
    }

    $get_id_with_no_categories = DB::table("user_document_category")
      ->where("category_id", 0)
      ->get();

    // return $get_id_with_no_categories;

    foreach ($final_document_details as $final_specific_document_details) {
      if ($final_specific_document_details->masterlist_id == $final_specific_document_details->user_id) {
        $document_status = true;
      } else {
        $document_status = false;
      }

      foreach ($categories as $specific_categories) {
        if ($specific_categories["id"] == $final_specific_document_details->masterlist_id) {
          array_push($categories_per_doc, [
            "category_id" => $specific_categories["category_id"],
            "category_name" => $specific_categories["category_name"],
            "category_status" => $specific_categories["category_status"],
          ]);
        } else {
        }
      }
      $document_types->push([
        "id" => $final_specific_document_details->masterlist_id,
        "document_name" => $final_specific_document_details->document_name,
        "document_status" => $document_status,
        "document_categories" => $categories_per_doc,
      ]);

      $categories_per_doc = [];
    }

    $user_document_details->push([
      "id" => $user_details->id,
      "id_prefix" => $user_details->id_prefix,
      "id_no" => $user_details->id_prefix,
      "role" => $user_details->role,
      "first_name" => $user_details->first_name,
      "middle_name" => $user_details->middle_name,
      "last_name" => $user_details->last_name,
      "suffix" => $user_details->suffix,
      "department" => $user_details->department,
      "position" => $user_details->position,
      "permissions" => $user_details->permissions,
      "document_types" => $document_types,
      "username" => $user_details->username,
    ]);

    $result = $user_document_details;

    if (!$result) {
      return [
        "error_message" => "Data Not Found",
      ];
    }

    return $result;
  }

  public static function getCategoriesByUserAndDocID($user_id, $id)
  {
    $get_category_ids = DB::select(
      DB::raw("SELECT user_id,document_id,category_id
            FROM `user_document_category`
            WHERE `user_id` = '$user_id' AND `document_id` = '$id'
            ORDER BY `id` ASC")
    );

    return $get_category_ids;
  }

  public static function getTransactionFormat($transaction, $table)
  {
    $result = collect();

    if ($table == "taggings") {
      $remarks = DB::table($table)
        ->select("remarks")
        ->where("transaction_id", $transaction->transaction_id)
        ->orderBy("id", "desc")
        ->first();
    } else {
      $remarks = DB::table($table)
        ->select("remarks")
        ->where("request_id", $transaction->tagging_request_id)
        ->orderBy("id", "desc")
        ->first();
    }

    if (!isset($transaction->created_at)) {
      $date_requested = DB::table("transactions")
        ->where("transaction_id", $transaction[0]["transaction_id"])
        ->select("created_at")
        ->latest();
      // $date_requested = date('Y-m-d',strtotime($date_requested->created_at));
    } else {
      $date_requested = date("Y-m-d", strtotime($transaction->created_at));
    }

    // PO & RR
    $po_group = collect();
    $get_po = DB::table("p_o_batches as PB")
      ->where("PB.request_id", $transaction->request_id)
      ->get();

    foreach ($get_po as $specific_po) {
      $id = $specific_po->id;

      $rr_group = collect();
      $get_rr = DB::table("r_r_batches as RB")
        ->where("RB.po_batch_no", $id)
        ->get();

      foreach ($get_rr as $specific_rr) {
        $rr_group->push([
          "rr_no" => $specific_rr->rr_code,
          "rr_qty" => $specific_rr->rr_qty,
        ]);
      }
      $po_group->push([
        "po_no" => $specific_po->po_no,
        "rr_group" => $rr_group,
        "po_amount" => $specific_po->po_amount,
        "po_qty" => $specific_po->po_qty,
      ]);
    }
    // REFERRENCE
    $referrence_group = collect();
    $get_referrence = DB::table("referrence_batches")
      ->where("request_id", "=", $transaction->request_id)
      ->get();

    foreach ($get_referrence as $specific_refference) {
      $referrence_group->push([
        "referrence_type" => $specific_refference->referrence_type,
        "referrence_no" => $specific_refference->referrence_no,
        "referrence_amount" => $specific_refference->referrence_amount,
        "referrence_qty" => $specific_refference->referrence_qty,
      ]);
    }

    //    DOCUMENT CATEGORY

    //    return $transaction;
    $result->push([
      "users_id" => $transaction->users_id,
      "id_prefix" => $transaction->id_prefix,
      "first_name" => $transaction->first_name,
      "middle_name" => $transaction->middle_name,
      "last_name" => $transaction->last_name,
      "suffix" => $transaction->suffix,
      "id_no" => $transaction->id_no,
      "department" => $transaction->department,
      "document_date" => $transaction->document_date,
      "reason_id" => $transaction->reason_id,
      "reason" => $transaction->reason,
      "utilities_from" => $transaction->utilities_from,
      "utilities_to" => $transaction->utilities_to,
      "created_at" => $transaction->created_at,
      "updated_at" => $transaction->updated_at,
      "id" => $transaction->id,
      "date_requested" => $date_requested,
      "transaction_id" => $transaction->transaction_id,
      "request_id" => $transaction->request_id,
      "tagging_request_id" => $transaction->tagging_request_id,
      "document_id" => $transaction->document_id,
      "document_type" => $transaction->document_type,
      "category_id" => $transaction->category_id,
      "category" => $transaction->category,
      "document_no" => $transaction->document_no,
      "document_amount" => $transaction->document_amount,
      "company_id" => $transaction->company_id,
      "company" => $transaction->company,
      "supplier_id" => $transaction->supplier_id,
      "supplier" => $transaction->supplier,
      "po_group" => $po_group,
      "po_total_amount" => $transaction->po_total_amount,
      "po_total_qty" => $transaction->po_total_qty,
      "rr_total_qty" => $transaction->rr_total_qty,
      "referrence_group" => $referrence_group,
      "referrence_total_amount" => $transaction->referrence_total_amount,
      "referrence_total_qty" => $transaction->referrence_total_qty,
      "payment_type" => $transaction->payment_type,
      "status" => $transaction->status,
      "remarks" => $remarks,
      "status_group_id" => null,
      "pcf_date" => $transaction->pcf_date,
      "pcf_letter" => $transaction->pcf_letter,
      "date_from" => $transaction->utilities_from,
      "date_to" => $transaction->utilities_to,
      "balance_document_po_amount" => $transaction->balance_document_po_amount,
      "balance_document_ref_amount" => $transaction->balance_document_ref_amount,
      "balance_po_ref_amount" => $transaction->balance_po_ref_amount,
    ]);

    $resultTransaction = $result->sortDesc();
    // $resultTransaction = $resultTransaction->values();
    // return GenericMethod::paginateme($resultTransaction);
    return $result->paginateme(5);
    return $resultTransaction;
  }

  public static function getPOTotalAmount($request_id, $po_group)
  {
    $po_count = count($po_group);
    $po_total_amount = 0;
    for ($i = 0; $i < $po_count; $i++) {
      $po_amount = $po_group[$i]["amount"];
      $po_total_amount = $po_total_amount + $po_amount;
    }
    return $po_total_amount;
  }

  public static function getBalance($po_total_amount, $balance, $reference_amount)
  {
    $balance = $po_total_amount + $balance - $reference_amount;
    return $balance;
  }

  public static function getRequestID()
  {
    $transactions = DB::table("transactions")
      ->select("request_id")
      ->orderBy("id", "desc")
      ->first();
    return empty($transactions) ? 0 : $transactions->request_id + 1;
  }

  public static function getTransactionNo($str)
  {
    $dep_initials = "";
    foreach (explode(" ", $str) as $word) {
      $dep_initials .= strtoupper($word[0]);
    }

    $transactions = DB::table("transactions")
      ->where("transaction_id", "like", "%" . $dep_initials . "%")
      ->select("transaction_id")
      ->orderBy("id", "DESC")
      ->first();
    if (empty($transactions)) {
      $transaction_id = 0;
    } else {
      $transaction_id = preg_replace("/[^0-9.]+/", "", $transactions->transaction_id);
    }
    return $transaction_id;
  }

  public static function getTransactionCode($str, $transaction_id)
  {
    $dep_initials = "";
    $transaction_no = "";
    if ($str == trim($str) && strpos($str, " ") !== false) {
      // IF MORE THAN 1 WORD AND DEPARTMENT NAME (MANAGEMENT INFORMATION SYSTEMS)
      foreach (explode(" ", $str) as $word) {
        $dep_initials .= strtoupper($word[0]);
      }

      return $dep_initials . sprintf("%03d", $transaction_id + 1);
    } else {
      // IF 1 WORD AND DEPARTMENT NAME (FINANCE)
      $dep_initials = strtoupper(mb_substr($str, 0, 3));

      $transactions = DB::table("transactions")
        ->where("transaction_id", "like", "%" . $dep_initials . "%")
        ->select("transaction_id")
        ->orderBy("id", "desc")
        ->first();

      if (empty($transactions)) {
        // IF WALANG LAMAN ANG KEYWORD DITO IREREGISTER ANG KEYWORD (FIN,MIS,AUD...)
        $transaction_id = 0;
        return $dep_initials . sprintf("%03d", $transaction_id + 1);
      } else {
        // IF MAY LAMAN ANG EXISTING NA ANG KEYWORD DOON SA TRANSACTION (FIN,MIS,AUD...)
        $transaction_code = preg_replace("/[^0-9.]+/", "", $transactions->transaction_id);

        if (empty($transaction_code)) {
          return $dep_initials . sprintf("%03d", $transaction_code + 1);
        } else {
          $transaction_id = preg_replace("/[^0-9.]+/", "", $transaction_code + 1);
        }
        return $dep_initials . sprintf("%03d", $transaction_id);
      }
    }
  }

  public static function getTransactionID($department)
  {
    $transaction_no = GenericMethod::getTransactionNo($department);
    return GenericMethod::getTransactionCode($department, $transaction_no);
  }

  public static function convert_string_to_float($value)
  {
    if (gettype($value) == "array") {
      return array_map(function ($val) {
        return floatval(str_replace(",", "", $val));
      }, $value);
    } elseif (gettype($value) == "string") {
      return floatval(str_replace(",", "", $value));
    }
  }

  ##########################################################################################################
  #########################################      VALIDATION           ######################################
  ##########################################################################################################
  public static function validate_debit_amount($document_amount, $autoDebit_group, $message)
  {
    $total_principal = array_sum(
      GenericMethod::convert_string_to_float(array_column($autoDebit_group, "principal_amount"))
    );
    $total_interest = array_sum(GenericMethod::convert_string_to_float(array_column($autoDebit_group, "interest_due")));
    $total_cwt = array_sum(GenericMethod::convert_string_to_float(array_column($autoDebit_group, "cwt")));
    $total_dst = array_sum(GenericMethod::convert_string_to_float(array_column($autoDebit_group, "dst")));
    $total_net = $total_principal + $total_interest + $total_dst - $total_cwt;

    $is_greater_than_piso_balance = !(
      abs($document_amount - $total_net) >= 0.0 && abs($document_amount - $total_net) < 1.0
    );

    if ($is_greater_than_piso_balance) {
      throw new FistoLaravelException(
        "The given data was invalid.",
        422,
        null,
        collect(["document.amount" => [$message]])
      );
    }
  }

  public static function is_duplicate_auto_debit($company_id, $supplier_id, $document_date, $id = 0)
  {
    return $transaction = Transaction::where("company_id", $company_id)
      ->where("supplier_id", $supplier_id)
      ->where("document_date", $document_date)
      ->where("document_type", "Auto Debit")
      ->where("state", "!=", "void")
      ->when($id, function ($query) use ($id) {
        $query->where("id", "<>", $id);
      })
      ->exists();
  }
  public static function validate_document_amount($document_amount, $compared_amount, $message)
  {
    if (round($compared_amount, 2) != round($document_amount, 2)) {
      throw new FistoLaravelException(
        "The given data was invalid.",
        422,
        null,
        collect(["document.amount" => [$message]])
      );
    }
  }

  public static function checkIfValidDateFormat($date, $original_date)
  {
    if ($date == "1970-01-01") {
      return "$original_date";
    }
  }

  public static function validate_prm_date_covered($prm_date_range, $line_no, $errors)
  {
    $period_covered_array = explode("-", $prm_date_range);
    $prm_from = $period_covered_array[0];
    $prm_to = $period_covered_array[1];
  }

  public static function validate_if_date_within_date_range(
    $date,
    $from_dates,
    $to_dates,
    $line_no,
    $errors = [],
    $date_type
  ) {
    $error_type = "invalid";
    foreach ($from_dates as $k => $v) {
      if ($line_no == $k) {
        // return $line_no." == ".$k;
        unset($from_dates[$k]);
        unset($to_dates[$k]);

        $from_date_removal_of_date_input = array_values($from_dates);
        $to_date_removal_of_date_input = array_values($to_dates);

        foreach ($from_date_removal_of_date_input as $j => $h) {
          if (isset($from_date_removal_of_date_input[$j]) and isset($to_date_removal_of_date_input[$j])) {
            if ($date >= $from_date_removal_of_date_input[$j] and $date <= $to_date_removal_of_date_input[$j]) {
              $error_details = [
                "error_type" => $error_type,
                "line" => $line_no + 1,
                "description" => $date_type . " Date conflicted with other dates.",
              ];
              array_push($errors, $error_details);
              // echo 'Line: '.($line_no+1) .' (From Date),  '.$date.' conflicted to Line: '.($j+2).', '.$from_dates[$j+1].' - '.$to_dates[$j+1];
            }
          }
        }
      }
    }

    return $errors;
  }

  public static function validate_amount_per_line($prm_group, $errors)
  {
    $error_summary = [];
    foreach ($prm_group as $k => $prm_batch) {
      $error_type = "invalid";
      if ($prm_batch["gross_amount"] != $prm_batch["wht"] + $prm_batch["net_of_amount"]) {
        $error_details = [
          "error_type" => $error_type,
          "line" => $k + 1,
          "description" => "Gross and computed withholding & net of amount not equal.",
        ];
        array_push($error_summary, $error_details);
      }
    }
    return $error_summary;
  }

  public static function validate_amount_per_line_leasing($prm_group, $errors)
  {
    $error_summary = [];
    foreach ($prm_group as $k => $prm_batch) {
      $error_type = "invalid";
      if ($prm_batch["amortization"] != $prm_batch["interest"] + $prm_batch["principal"]) {
        $error_details = [
          "error_type" => $error_type,
          "line" => $k + 1,
          "description" => "Amortization and interest & principal amount not equal.",
        ];
        array_push($error_summary, $error_details);
      }
    }
    return $error_summary;
  }

  public static function validate_amount_per_line_loans($prm_group, $errors)
  {
    $error_summary = [];
    foreach ($prm_group as $k => $prm_batch) {
      $error_type = "invalid";
      if (
        round($prm_batch["net_of_amount"], 2) !=
        round($prm_batch["interest"], 2) + round($prm_batch["principal"], 2) - round($prm_batch["cwt"], 2)
      ) {
        // if((floor($prm_batch['net_of_amount']) != ((floor($prm_batch['interest'])+floor($prm_batch['principal']))-floor($prm_batch['cwt'])))){
        $error_details = [
          "error_type" => $error_type,
          "line" => $k + 1,
          "description" =>
            "Net of amount " .
            round($prm_batch["net_of_amount"]) .
            " and interest, principal & cwt amount " .
            (round($prm_batch["interest"]) + round($prm_batch["principal"]) - round($prm_batch["cwt"])) .
            " not equal.",
        ];
        array_push($error_summary, $error_details);
      }
    }
    return $error_summary;
  }

  public static function is_duplicate_prm_multiple(
    $company_id,
    $supplier_id,
    $category_id,
    $period_covered,
    $cheque_date
  ) {
    return $transaction = Transaction::where("company_id", $company_id)
      ->where("supplier_id", $supplier_id)
      ->where("category_id", $category_id)
      ->where("period_covered", $period_covered)
      ->where("cheque_date", $cheque_date)
      ->where("state", "!=", "void")
      ->exists();
  }

  public static function is_duplicate_prm_multiple_leasing_and_loans(
    $company_id,
    $supplier_id,
    $category,
    $release_date,
    $batch_no,
    $cheque_date
  ) {
    return $transaction = Transaction::where("company_id", $company_id)
      ->where("supplier_id", $supplier_id)
      ->where("category", $category)
      ->where("release_date", $release_date)
      ->where("batch_no", $batch_no)
      ->where("cheque_date", $cheque_date)
      ->where("state", "!=", "void")
      ->exists();
  }

  public static function validate_duplicate_prm_multiple_transaction($prm_group, $fields)
  {
    $error_summary = [];
    $company_id = $fields["document"]["company"]["id"];
    $supplier_id = $fields["document"]["supplier"]["id"];
    $category_id = $fields["document"]["category"]["id"];
    foreach ($prm_group as $k => $prm_group_detail) {
      $period_covered = $prm_group_detail["period_covered"];
      $cheque_date = $prm_group_detail["cheque_date"];

      $duplicate_transaction = GenericMethod::is_duplicate_prm_multiple(
        $company_id,
        $supplier_id,
        $category_id,
        $period_covered,
        $cheque_date
      );

      if ($duplicate_transaction) {
        $error_details = [
          "error_type" => "existing",
          "line" => $k + 1,
          "description" => "Transaction details already exist.",
        ];
        array_push($error_summary, $error_details);
      }
    }
    return $error_summary;
  }

  public static function validate_duplicate_prm_multiple_transaction_leasing_and_loans($prm_group, $fields)
  {
    $error_summary = [];
    $category = $fields["document"]["category"]["name"];
    $release_date = $fields["document"]["release_date"];
    $batch_no = $fields["document"]["batch_no"];

    foreach ($prm_group as $k => $prm_group_detail) {
      $company_id = $fields["document"]["company"]["id"];
      $supplier_id = $fields["document"]["supplier"]["id"];
      $cheque_date = $prm_group_detail["cheque_date"];

      $duplicate_transaction = GenericMethod::is_duplicate_prm_multiple_leasing_and_loans(
        $company_id,
        $supplier_id,
        $category,
        $release_date,
        $batch_no,
        $cheque_date
      );

      if ($duplicate_transaction) {
        $error_details = [
          "error_type" => "existing",
          "line" => $k + 1,
          "description" => "Transaction details already exist.",
        ];
        array_push($error_summary, $error_details);
      }
    }
    return $error_summary;
  }

  public static function validate_multiple_cheque_dates($cheque_dates, $errors)
  {
    $error_type = "duplicate";

    foreach ($cheque_dates as $k => $v) {
      $date = $v;
      foreach ($cheque_dates as $j => $u) {
        if ($k == $j) {
          unset($cheque_dates[$j]);

          $cheque_dates_removal_of_date_input = $cheque_dates;

          foreach ($cheque_dates_removal_of_date_input as $l => $w) {
            if ($date == $cheque_dates_removal_of_date_input[$l]) {
              $error_details = [
                "error_type" => $error_type,
                "line" => $k + 1 . " & " . ($l + 1),
                "description" => "Cheque date has a duplicate in your excel file",
              ];
              array_push($errors, $error_details);
            }
          }
        }
      }
    }
    return $errors;
  }

  public static function validate_period_covered($period_covered_array, $errors)
  {
    $period_covered_array = array_map(function ($values) {
      $batch = explode("-", $values);
      $from = date("Y-m-d", strtotime(trim(isset($batch[0]) ? $batch[0] : "0")));
      $to = date("Y-m-d", strtotime(trim(isset($batch[1]) ? $batch[1] : "0")));
      return ["from" => $from, "to" => $to];
    }, $period_covered_array);

    $from_array = array_column($period_covered_array, "from");
    $to_array = array_column($period_covered_array, "to");

    foreach ($period_covered_array as $k => $value) {
      $errors = GenericMethod::validate_if_date_within_date_range(
        $from_array[$k],
        $from_array,
        $to_array,
        $k,
        $errors,
        "From"
      );
      $errors = GenericMethod::validate_if_date_within_date_range(
        $to_array[$k],
        $from_array,
        $to_array,
        $k,
        $errors,
        "To"
      );
    }
    return $errors;
  }

  public static function validate_prm_date_range_format($prm_group, $errors)
  {
    $error_summary = [];
    $error_invalid_prm_from = [];
    $error_invalid_prm_to = [];

    foreach ($prm_group as $k => $value) {
      $period_covered_array = explode("-", $value["period_covered"]);

      $prm_from = isset($period_covered_array[0])
        ? ($period_covered_array[0] == ""
          ? "1970-01-01"
          : $period_covered_array[0])
        : "1970-01-01";
      $prm_to = isset($period_covered_array[1])
        ? ($period_covered_array[1] == ""
          ? "1970-01-01"
          : $period_covered_array[1])
        : "1970-01-01";

      $invalid_prm_from = GenericMethod::checkIfValidDateFormat(date("Y-m-d", strtotime($prm_from)), $prm_from);
      $invalid_prm_to = GenericMethod::checkIfValidDateFormat(date("Y-m-d", strtotime($prm_to)), $prm_to);
      $error_type = "invalid";

      if ($invalid_prm_from) {
        $error_details = [
          "error_type" => $error_type,
          "line" => $k + 1,
          "description" => "Invalid from date format",
        ];
        array_push($error_invalid_prm_from, $error_details);
      }

      if ($invalid_prm_to) {
        $error_details = [
          "error_type" => $error_type,
          "line" => $k + 1,
          "description" => "Invalid to date format",
        ];
        array_push($error_invalid_prm_to, $error_details);
      }
    }
    $error_summary = array_merge($error_invalid_prm_from, $error_invalid_prm_to);
    return $error_summary;
  }

  public static function voucherNoValidationUponSaving($voucher_no, $id)
  {
    $transaction = Transaction::where("voucher_no", $voucher_no)
      ->when($id, function ($query) use ($id) {
        $query->where("id", "<>", $id);
      })
      ->where("state", "!=", "void")
      ->count();

    if ($transaction > 0) {
      return GenericMethod::resultResponse("voucher-no-exist", "", []);
    }
    return GenericMethod::resultResponse("success-no-content", "", []);
  }

  public static function voucherNoValidation($voucher_no, $id)
  {
    $transaction = Transaction::where("voucher_no", $voucher_no)
      ->when($id, function ($query) use ($id) {
        $query->where("id", "<>", $id);
      })
      ->where("state", "!=", "void")
      ->exists();

    if ($transaction) {
      $errorMessage = GenericMethod::resultLaravelFormat("voucher.no", ["Voucher number already exist."]);
      return GenericMethod::resultResponse("invalid", "", $errorMessage);
    }
    return GenericMethod::resultResponse("success-no-content", "", []);
  }

  public static function documentNoValidation($doc_no)
  {
    if (!isset($doc_no)) {
      throw new FistoLaravelException("Document number is empty.", 404, null, []);
    }

    if (TransactionValidationMethod::validateIfDocumentNoExist($doc_no) > 0) {
      throw new FistoLaravelException(
        "The given data was invalid.",
        422,
        null,
        collect(["document.no" => ["The Document number has already been taken."]])
      );
    }
  }

  public static function documentNoValidationUpdate($doc_no, $id, $transaction_id = null)
  {
    if (!isset($doc_no)) {
      throw new FistoException("Document number is empty.", 404, null, []);
    }

    if (TransactionValidationMethod::validateIfDocumentNoExistUpdate($doc_no, $id, $transaction_id) > 0) {
      throw new FistoLaravelException(
        "The given data was invalid.",
        422,
        null,
        collect(["document.no" => ["The Document number has already been taken."]])
      );
    }
  }

  public static function billingValidation(
    $company_id,
    $department_id,
    $location_id,
    $supplier_id,
    $category_id,
    $capex_no,
    $transaction_id = null
  ) {
    if (
      Transaction::where("id", "<>", $transaction_id)
        ->where("company_id", $company_id)
        ->where("department_id", $company_id)
        ->where("location_id", $location_id)
        ->where("supplier_id", $supplier_id)
        ->where("category_id", $category_id)
        ->where("capex_no", $capex_no)
        ->exists()
    ) {
      throw new FistoLaravelException(
        "The given data was invalid.",
        422,
        null,
        collect([
          "document.company.id" => ["The Company has already been taken."],
          "document.department.id" => ["The Department has already been taken."],
          "document.location.id" => ["The Location has already been taken."],
          "document.supplier.id" => ["The Supplier has already been taken."],
          "document.category.id" => ["The Category has already been taken."],
          "document.capex_no" => ["The Capex number has already been taken."],
        ])
      );
    }
  }

  public static function getEmptyErrorBag($tableName, $index, $errorBag)
  {
    foreach ($tableName as $key => $value) {
      if (empty($value)) {
        $errorBag[] = [
          "error_type" => "empty",
          "line" => $index,
          "description" => $key . " is empty.",
        ];
      }
    }
    return $errorBag;
  }

  public static function validateTransactionByDateRange(
    $from,
    $to,
    $company_id,
    $department_id,
    $supplier_id,
    $location_id,
    $category,
    $account_no,
    $receipt_no,
    $id = 0
  ) {
    $transactions = DB::table("transactions")
      ->where(function ($query) use ($from, $to) {
        $query
          ->where(function ($query) use ($from, $to) {
            $query
              ->where(function ($query1) use ($from) {
                $query1->where("utilities_from", "<", $from)->where("utilities_to", ">", $from);
              })
              ->orWhere(function ($query2) use ($to) {
                $query2->where("utilities_from", "<", $to)->where("utilities_to", ">", $to);
              });
          })
          ->orWhere(function ($query) use ($from, $to) {
            $query->where(function ($query1) use ($from, $to) {
              $query1->where("utilities_from", ">=", $from)->where("utilities_to", "<=", $to);
            });
          });
      })
      ->where("utilities_account_no", $account_no)
      ->where("company_id", $company_id)
      ->where("department_id", $department_id)
      ->where("supplier_id", $supplier_id)
      ->where("state", "!=", "void")
      ->where("utilities_location_id", $location_id)
      ->where("utilities_category", $category)
      ->where("utilities_receipt_no", $receipt_no)
      ->when($id, function ($query, $id) {
        $query->where("id", "<>", $id);
      })
      ->get();

    if (count($transactions) > 0) {
      return GenericMethod::resultLaravelFormat(
        [
          "document.from",
          "document.to",
          "document.company.id",
          "document.department.id",
          "document.utility.location.id",
          "document.utility.category.id",
          "document.utility.receipt_no",
          "document.supplier.id",
          "document.utility.account_no.no",
        ],
        [
          ["from has already been taken."],
          ["to has already been taken."],
          ["Company has already been taken."],
          ["Department has already been taken."],
          ["Utility Location has already been taken."],
          ["Utility Category has already been taken."],
          ["SOA Number has already been taken."],
          ["Supplier has already been taken."],
          ["Account Number has already been taken."],
        ]
      );
    }

    if ($receipt_no) {
      $receiptNo = Transaction::where("utilities_receipt_no", $receipt_no)
        ->where("supplier_id", $supplier_id)
        ->where("state", "!=", "void")
        ->when($id, function ($query, $id) {
          $query->where("id", "<>", $id);
        })
        ->count();

      if ($receiptNo > 0) {
        return GenericMethod::resultLaravelFormat(
          [
            // "document.from",
            // "document.to",
            // "document.company.id",
            // "document.department.id",
            // "document.utility.location.id",
            // "document.utility.category.id",
            "document.utility.receipt_no",
            "document.supplier.id",
          ],
          [
            // ["from has already been taken."],
            // ["to has already been taken."],
            // ["Company has already been taken."],
            // ["Department has already been taken."],
            // ["Utility Location has already been taken."],
            // ["Utility Category has already been taken."],
            ["SOA Number has already been taken."],
            ["Supplier has already been taken."],
          ]
        );
      }
    }
  }

  public static function validateSOANumber($receipt_no, $supplier_id, $id = 0)
  {
    $transaction = Transaction::where("utilities_receipt_no", $receipt_no)
      ->where("supplier_id", $supplier_id)
      ->when($id, function ($query, $id) {
        $query->where("id", "<>", $id);
      })
      ->get();

    if (count($transaction) > 0) {
      return GenericMethod::resultLaravelFormat("document.utility.receipt_no", ["SOA number already exist."]);
    }
  }

  // public static function validatePayroll(
  //   $payroll_from,
  //   $payroll_to,
  //   $company_id,
  //   $department_id,
  //   $location_id,
  //   $supplier_id,
  //   $payroll_client,
  //   $payroll_type,
  //   $payroll_category,
  //   $payroll_control_no,
  //   $id = 0
  // ) {

  //   $duplicate_client = [];
  //   foreach ($payroll_client as $specific_client) {
  //     $client_id = $specific_client["id"];
  //     $client_name = $specific_client["name"];
  //     $transactions = DB::table("transactions")
  //       ->leftJoin("transaction_client", "transactions.request_id", "=", "transaction_client.request_id")
  //       ->select("client_name")
  //       ->where("company_id", $company_id)
  //       ->where("department_id", $department_id)
  //       ->where("location_id", $location_id)
  //       ->where("supplier_id", $supplier_id)
  //       ->where("payroll_category", "$payroll_category")
  //       ->where("payroll_type", $payroll_type)
  //       ->where("client_name", $client_name)
  //       ->where("state", "!=", "void")
  //       ->when($id, function ($query, $id) {
  //         $query->where("transactions.id", "<>", $id);
  //       })
  //       ->where(function ($query) use ($payroll_from, $payroll_to) {
  //         $query
  //           ->where(function ($query) use ($payroll_from, $payroll_to) {
  //             $query
  //               ->where(function ($query1) use ($payroll_from) {
  //                 $query1->where("payroll_from", "<=", $payroll_from)->where("payroll_to", ">=", $payroll_from);
  //               })
  //               ->orWhere(function ($query2) use ($payroll_to) {
  //                 $query2->where("payroll_from", "<=", $payroll_to)->where("payroll_to", ">=", $payroll_to);
  //               });
  //           })
  //           ->orWhere(function ($query) use ($payroll_from, $payroll_to) {
  //             $query->where(function ($query1) use ($payroll_from, $payroll_to) {
  //               $query1->where("payroll_from", ">=", $payroll_from)->where("payroll_to", "<=", $payroll_to);
  //             });
  //           });
  //       })
  //       ->count();

  //     if ($transactions > 0) {
  //       array_push($duplicate_client, $client_name);
  //     }
  //   }

  //   $duplicate_clients = GenericMethod::addAnd($duplicate_client);
  //   if (!empty($duplicate_client)) {
  //     return GenericMethod::resultLaravelFormat(
  //       [
  //         "document.payroll.type",
  //         "document.payroll.clients",
  //         "document.payroll.category",
  //         "document.from",
  //         "document.to",
  //         "document.company.id",
  //         "document.department.id",
  //         "document.location.id",
  //         "document.supplier.id"
  //       ],
  //       [
  //         ["Payroll type has already been taken."],
  //         ["Payroll client has already been taken."],
  //         ["Payroll category has already been taken."],
  //         ["From has already been taken."],
  //         ["To date has already been taken."],
  //         ["Company has already been taken."],
  //         ["Department has already been taken."],
  //         ["Location has already been taken."],
  //         ["Supplier has already been taken."]
  //       ]
  //     );
  //   }
  // }

  public static function validatePayroll(
    $payroll_from,
    $payroll_to,
    $company_id,
    $department_id,
    $location_id,
    $supplier_id,
    $payroll_client,
    $payroll_type,
    $payroll_category,
    $payroll_control_no,
    $id = 0
  ) {
    $duplicate_client = [];
    foreach ($payroll_client as $specific_client) {
      $client_id = $specific_client["id"];
      $client_name = $specific_client["name"];
      $transactions = DB::table("transactions")
        ->leftJoin("transaction_client", "transactions.request_id", "=", "transaction_client.request_id")
        ->select("client_name")
        ->where("company_id", $company_id)
        ->where("department_id", $department_id)
        ->where("location_id", $location_id)
        ->where("supplier_id", $supplier_id)
        ->where("payroll_category", "$payroll_category")
        ->where("payroll_type", $payroll_type)
        ->where("client_name", $client_name)
        ->whereNull("payroll_control_no")
        ->where("state", "!=", "void")
        ->when($id, function ($query, $id) {
          $query->where("transactions.id", "<>", $id);
        })
        ->where(function ($query) use ($payroll_from, $payroll_to) {
          $query
            ->where(function ($query) use ($payroll_from, $payroll_to) {
              $query
                ->where(function ($query1) use ($payroll_from) {
                  $query1->where("payroll_from", "<=", $payroll_from)->where("payroll_to", ">=", $payroll_from);
                })
                ->orWhere(function ($query2) use ($payroll_to) {
                  $query2->where("payroll_from", "<=", $payroll_to)->where("payroll_to", ">=", $payroll_to);
                });
            })
            ->orWhere(function ($query) use ($payroll_from, $payroll_to) {
              $query->where(function ($query1) use ($payroll_from, $payroll_to) {
                $query1->where("payroll_from", ">=", $payroll_from)->where("payroll_to", "<=", $payroll_to);
              });
            });
        })
        ->count();

      if (!is_null($payroll_control_no)) {
        // $transactions = DB::table("transactions")
        //   ->leftJoin("transaction_client", "transactions.request_id", "=", "transaction_client.request_id")
        //   ->select("client_name")
        //   ->where("company_id", $company_id)
        //   ->where("department_id", $department_id)
        //   ->where("location_id", $location_id)
        //   ->where("supplier_id", $supplier_id)
        //   ->where("payroll_category", "$payroll_category")
        //   ->where("payroll_type", $payroll_type)
        //   ->where("client_name", $client_name)
        //   ->whereNotNull("payroll_control_no")
        //   ->where("state", "!=", "void")
        //   ->when($id, function ($query, $id) {
        //     $query->where("transactions.id", "<>", $id);
        //   })
        //   ->where(function ($query) use ($payroll_from, $payroll_to) {
        //     $query
        //       ->where(function ($query) use ($payroll_from, $payroll_to) {
        //         $query
        //           ->where(function ($query1) use ($payroll_from) {
        //             $query1->where("payroll_from", "<=", $payroll_from)->where("payroll_to", ">=", $payroll_from);
        //           })
        //           ->orWhere(function ($query2) use ($payroll_to) {
        //             $query2->where("payroll_from", "<=", $payroll_to)->where("payroll_to", ">=", $payroll_to);
        //           });
        //       })
        //       ->orWhere(function ($query) use ($payroll_from, $payroll_to) {
        //         $query->where(function ($query1) use ($payroll_from, $payroll_to) {
        //           $query1->where("payroll_from", ">=", $payroll_from)->where("payroll_to", "<=", $payroll_to);
        //         });
        //       });
        //   })
        //   ->count();

        $controlNoTransactions = DB::table("transactions")
          ->leftJoin("transaction_client", "transactions.request_id", "=", "transaction_client.request_id")
          ->select("payroll_control_no")
          ->where("company_id", $company_id)
          ->where("department_id", $department_id)
          ->where("location_id", $location_id)
          ->where("supplier_id", $supplier_id)
          ->where("payroll_category", "$payroll_category")
          ->where("payroll_type", $payroll_type)
          ->where("client_name", $client_name)
          ->where("payroll_control_no", $payroll_control_no)
          ->where("state", "!=", "void")
          ->when($id, function ($query, $id) {
            $query->where("transactions.id", "<>", $id);
          })
          ->where(function ($query) use ($payroll_from, $payroll_to) {
            $query
              ->where(function ($query) use ($payroll_from, $payroll_to) {
                $query
                  ->where(function ($query1) use ($payroll_from) {
                    $query1->where("payroll_from", "<=", $payroll_from)->where("payroll_to", ">=", $payroll_from);
                  })
                  ->orWhere(function ($query2) use ($payroll_to) {
                    $query2->where("payroll_from", "<=", $payroll_to)->where("payroll_to", ">=", $payroll_to);
                  });
              })
              ->orWhere(function ($query) use ($payroll_from, $payroll_to) {
                $query->where(function ($query1) use ($payroll_from, $payroll_to) {
                  $query1->where("payroll_from", ">=", $payroll_from)->where("payroll_to", "<=", $payroll_to);
                });
              });
          })
          ->count();

        if ($controlNoTransactions >= 1) {
          array_push($duplicate_client, "Payroll control number");
        } else {
          return;
        }

        // if ($controlNoTransactions >= 1) {
        //   array_push($duplicate_client, "Payroll control number");
        // } elseif ($transactions > 0) {
        //   array_push($duplicate_client, $client_name);
        // } else {
        //   return;
        // }
      }

      if ($transactions > 0) {
        array_push($duplicate_client, $client_name);
      }
    }

    if (in_array("Payroll control number", $duplicate_client)) {
      if (!empty($duplicate_client)) {
        return GenericMethod::resultLaravelFormat(
          [
            "document.payroll.type",
            "document.payroll.clients",
            "document.payroll.category",
            "document.from",
            "document.to",
            "document.company.id",
            "document.department.id",
            "document.location.id",
            "document.supplier.id",
            "document.payroll.control_no",
          ],
          [
            ["Payroll type has already been taken."],
            ["Payroll client has already been taken."],
            ["Payroll category has already been taken."],
            ["From has already been taken."],
            ["To date has already been taken."],
            ["Company has already been taken."],
            ["Department has already been taken."],
            ["Location has already been taken."],
            ["Supplier has already been taken."],
            ["Payroll control number has already been taken."],
          ]
        );
      }
    }

    $duplicate_clients = GenericMethod::addAnd($duplicate_client);
    if (!empty($duplicate_clients)) {
      return GenericMethod::resultLaravelFormat(
        [
          "document.payroll.type",
          "document.payroll.clients",
          "document.payroll.category",
          "document.from",
          "document.to",
          "document.company.id",
          "document.department.id",
          "document.location.id",
          "document.supplier.id",
        ],
        [
          ["Payroll type has already been taken."],
          ["Payroll client has already been taken."],
          ["Payroll category has already been taken."],
          ["From has already been taken."],
          ["To date has already been taken."],
          ["Company has already been taken."],
          ["Department has already been taken."],
          ["Location has already been taken."],
          ["Supplier has already been taken."],
        ]
      );
    } else {
      return;
    }
  }

  public static function validateAutoDebit($company_id, $supplier_id, $document_date, $id = 0)
  {
    $is_duplicate = GenericMethod::is_duplicate_auto_debit($company_id, $supplier_id, $document_date, $id);
    if ($is_duplicate) {
      return GenericMethod::resultLaravelFormat(
        ["document.date", "document.company.id", "document.supplier.id"],
        [
          ["Document date has already been taken."],
          ["Company has already been taken."],
          ["Supplier has already been taken."],
        ]
      );
    }
  }
  public static function validateReferenceNo($fields, $id = 0)
  {
    $validateTransactionCount = Transaction::where("company_id", $fields["document"]["company"]["id"])
      ->where("referrence_no", $fields["document"]["reference"]["no"])
      ->where("supplier_id", $fields["document"]["supplier"]["id"])
      ->when($id, function ($query, $id) {
        $query->where("id", "<>", $id);
      })
      ->where("state", "!=", "void")
      ->get();
    if (count($validateTransactionCount) > 0) {
      return GenericMethod::resultLaravelFormat("document.reference.no", ["Reference number already exist."]);
    }
  }

  public static function getAndValidatePOBalance(
    $fields,
    $company_id,
    $po_no,
    float $reference_amount,
    $po_group,
    $id = 0
  ) {
    $balance_po_ref_amount = Transaction::leftJoin(
      "p_o_batches",
      "transactions.request_id",
      "=",
      "p_o_batches.request_id"
    )
      ->where("transactions.company_id", $company_id)
      ->when($id, function ($query, $id) {
        $query->where("transactions.id", "<>", $id);
      })
      ->where("transactions.state", "!=", "void")
      ->where("p_o_batches.po_no", $po_no)
      ->orderBy("transactions.id", "desc")
      ->get("balance_po_ref_amount")
      ->first();

    if (empty($balance_po_ref_amount)) {
      return;
    }
    $balance_po_ref_amount = $balance_po_ref_amount->balance_po_ref_amount;

    if ($balance_po_ref_amount == 0) {
      if (!$id) {
        return GenericMethod::resultLaravelFormat("po_group.no", ["PO already exist."]);
      }
    }
    // Additional PO
    $additional_po_group = [];
    $po_total_amount = 0;

    foreach ($po_group as $k => $v) {
      if (
        !POBatch::leftJoin("transactions", "p_o_batches.request_id", "=", "transactions.request_id")
          ->where("company_id", "=", $company_id)
          ->when($id, function ($query, $id) {
            $query->where("transactions.id", "<>", $id);
          })
          ->where("p_o_batches.po_no", "=", $po_group[$k]["no"])
          ->where("state", "!=", "void")
          ->exists()
      ) {
        $additional_po_group[$k]["no"] = $po_group[$k]["no"];
        $additional_po_group[$k]["amount"] = $po_group[$k]["amount"];
        $additional_po_group[$k]["rr_no"] = $po_group[$k]["rr_no"];
      }
      $po_total_amount = $po_total_amount + $po_group[$k]["amount"];
    }
    $additional_po_group = array_values($additional_po_group);

    if (count($additional_po_group) > 0) {
      $new_po_total_amount = GenericMethod::getPOTotalAmount($request_id = 0, $additional_po_group);
      $additional_plust_balance_amount = $new_po_total_amount + $balance_po_ref_amount;

      // if ($additional_plust_balance_amount < $reference_amount) {
      //   return GenericMethod::resultLaravelFormat("document.reference.no", ["Insufficient PO balance."]);
      // }

      // if (!$fields["document"]["reference"]["allowable"]) {
      //   if ($additional_plust_balance_amount < $reference_amount) {
      //     return GenericMethod::resultLaravelFormat("document.reference.no", ["Insufficient PO balance."]);
      //   }
      // }

      // if (isset($fields["document"]["reference"]["allowable"])) {
      //   if ($additional_plust_balance_amount < $reference_amount) {
      //     return GenericMethod::resultLaravelFormat("document.reference.no", ["Insufficient PO balance."]);
      //   }
      // }

      if (isset($fields["document"]["reference"]) && !$fields["document"]["reference"]["allowable"]) {
        if ($additional_plust_balance_amount < $reference_amount) {
          return GenericMethod::resultLaravelFormat("document.reference.no", ["Insufficient PO balance."]);
        }
      }

      $balance = GenericMethod::getBalance($new_po_total_amount, $balance_po_ref_amount, $reference_amount);

      return [
        "po_total_amount" => $po_total_amount,
        "new_po_total_amount" => $new_po_total_amount,
        "balance" => $balance,
        "new_po_group" => $additional_po_group,
      ];
    }

    // if (!$fields["document"]["reference"]["allowable"]) {
    //   if ($balance_po_ref_amount < $reference_amount) {
    //     return GenericMethod::resultLaravelFormat("document.reference.no", ["Insufficient PO balance."]);
    //   }
    // }

    if (isset($fields["document"]["reference"]) && !$fields["document"]["reference"]["allowable"]) {
      if ($balance_po_ref_amount < $reference_amount) {
        return GenericMethod::resultLaravelFormat("document.reference.no", ["Insufficient PO balance."]);
      }
    }

    $balance = $balance_po_ref_amount - $reference_amount;
    return $balance;
  }

  public static function getBalancePORefAmount($company_id, $reference_no)
  {
    return Transaction::where("company_id", $company_id)
      ->where("referrence_no", $reference_no)
      ->get("balance_po_ref_amount")
      ->first()->balance_po_ref_amount;
  }

  // public static function validateReceiptPartial($fields){

  //    return $transaction = DB::table('transactions')
  //     ->leftJoin('p_o_batches','transactions.request_id','=','p_o_batches.request_id')
  //     ->where('transactions.company_id',$fields['document']['company']['id'])
  //     ->where('transactions.supplier_id',$fields['document']['supplier']['id'])
  //     ->where('transactions.balance_po_ref_amount','>',0)
  //     ->where('p_o_batches.po_no',$fields['po_group']['no'])
  //     ->orderBy('p_o_batches.id','desc');
  //     $validateTransactionCount = $transaction->get();

  //     if(count($validateTransactionCount)>0){
  //         return GenericMethod::resultLaravelFormat('document.no',["Reference number already exist."]);
  //     }
  // }

  public static function validatePCF(
    $pcf_name,
    $pcf_date,
    $pcf_letter,
    $company_id,
    $supplier_id,
    $department_id,
    $location_id,
    $id = 0
  ) {
    $transactions = DB::table("transactions")
      ->where("pcf_name", $pcf_name)
      ->where("pcf_date", $pcf_date)
      ->where("pcf_letter", $pcf_letter)
      ->where("company_id", $company_id)
      ->where("supplier_id", $supplier_id)
      ->where("department_id", $department_id)
      ->where("location_id", $location_id)
      ->where("state", "!=", "void")
      ->when($id, function ($query, $id) {
        $query->where("id", "<>", $id);
      })
      ->get();

    if (count($transactions) > 0) {
      return GenericMethod::resultLaravelFormat(
        [
          "document.pcf_batch.letter",
          "document.pcf_batch.date",
          "document.company.id",
          "document.department.id",
          "document.location.id",
        ],
        [
          ["PCF letter has already been taken."],
          ["PCF date has already been taken."],
          ["PCF company has already been taken."],
          ["PCF department has already been taken."],
          ["PCF location has already been taken."],
        ]
      );
    }
  }

  public static function validatePOFull($company_id, $po_group)
  {
    $po_nos = array_column($po_group, "no");

    $transactions = DB::table("transactions")
      ->leftJoin("p_o_batches", "transactions.request_id", "=", "p_o_batches.request_id")
      ->where("company_id", $company_id)
      ->where("transactions.state", "!=", "void")
      ->whereIn("po_no", $po_nos);
    $validateTransactionCount = $transactions->get();
    $unique_po = array_unique($validateTransactionCount->pluck("po_no")->toArray());
    $duplicate_po_nos = GenericMethod::addAnd($unique_po);

    if (count($validateTransactionCount) > 0) {
      return GenericMethod::resultLaravelFormat("po_group.no", [
        "PO " . $duplicate_po_nos . " has already been taken.",
      ]);
    }
  }

  public static function validatePOFullUpdate($company_id, $po_group, $id)
  {
    $po_nos = array_column($po_group, "no");

    $transactions = DB::table("transactions")
      ->leftJoin("p_o_batches", "transactions.request_id", "=", "p_o_batches.request_id")
      ->where("transactions.company_id", $company_id)
      ->where("transactions.id", "<>", $id)
      ->where("transactions.state", "<>", "void")
      ->whereIn("p_o_batches.po_no", $po_nos);

    $validateTransactionCount = $transactions->get();

    $unique_po = array_unique($validateTransactionCount->pluck("po_no")->toArray());
    $duplicate_po_nos = GenericMethod::addAnd($unique_po);

    if (count($validateTransactionCount) > 0) {
      return GenericMethod::resultLaravelFormat("po_group.no", [
        "PO " . $duplicate_po_nos . " has already been taken.",
      ]);
    }
  }

  public static function validateIfDocumentNoExist($doc_no)
  {
    $transactions = DB::table("transactions")
      ->where("document_no", $doc_no)
      ->whereNotNull("document_no");
    return $transactions->count();
  }

  public static function validateIfPONoExist($payment_type, $company_id, $supplier_id, $po_no)
  {
    $transactions = DB::table("transactions")
      ->leftJoin("p_o_batches", "transactions.request_id", "=", "p_o_batches.request_id")
      // ->where('payment_type',$payment_type)
      ->where("company_id", $company_id)
      ->where("supplier_id", $supplier_id)
      ->where("po_no", $po_no);
    return $transactions->count();
  }

  public static function validateIfUtilityExist(
    $payment_type,
    $company_id,
    $supplier_id,
    $utilities_from,
    $utilities_to,
    $utilities_category,
    $utilities_account_no,
    $utilities_consumption,
    $utilities_uom,
    $utilities_receipt_no
  ) {
    $transactions = DB::select(
      DB::raw("SELECT id FROM `transactions`
                    WHERE `company_id` = $company_id AND
                    `supplier_id` = $supplier_id AND
                    `utilities_category` = '$utilities_category' AND
                    `utilities_account_no` = '$utilities_account_no' AND
                    (
                        (`utilities_from` >= '$utilities_from' AND `utilities_from` <= '$utilities_to')
                            OR
                        (`utilities_to` >= '$utilities_from' AND `utilities_to` <= '$utilities_to')
                    )")
    );
    return count($transactions);
  }

  public static function validateIfPayrollExist(
    $payment_type,
    $company_id,
    $supplier_id,
    $payroll_from,
    $payroll_to,
    $payroll_client,
    $payroll_category,
    $payroll_type
  ) {
    $duplicate_client = 0;

    foreach ($payroll_client as $specific_client) {
      $transactions = DB::table("transactions")
        ->select("id")
        ->where("company_id", $company_id)
        ->where("supplier_id", $supplier_id)
        ->where("payroll_category", $payroll_category)
        ->where("payroll_type", $payroll_type)
        ->whereJsonContains("payroll_client", $specific_client)
        ->whereBetween("payroll_from", [$payroll_from, $payroll_to])
        ->orWhereBetween("payroll_to", [$payroll_from, $payroll_to])
        ->where(function ($query) use ($payroll_from, $payroll_to) {
          $query
            ->where(function ($query2) use ($payroll_from, $payroll_to) {
              $query2->where("payroll_from", ">=", $payroll_from)->where("payroll_from", "<=", $payroll_to);
            })
            ->orWhere(function ($query3) use ($payroll_from, $payroll_to) {
              $query3->where("payroll_to", ">=", $payroll_from)->where("payroll_to", "<=", $payroll_to);
            });
        })
        ->get();

      if (count($transactions) > 0) {
        $duplicate_client = $duplicate_client + 1;
      }
    }
    return $duplicate_client;
  }

  public static function validateIfPCFExist($payment_type, $company_id, $supplier_id, $pcf_date, $pcf_letter)
  {
    $duplicate_client = 0;

    $transactions = DB::table("transactions")
      ->where("company_id", $company_id)
      ->where("supplier_id", $supplier_id)
      ->where("pcf_date", $pcf_date)
      ->where("pcf_letter", $pcf_letter)
      ->get();

    return $transactions->count();
  }

  public static function validateIfPONoExistInDifferentSupplierReceiptPartial(
    $payment_type,
    $company_id,
    $supplier_id,
    $po_no
  ) {
    $transactions = DB::table("transactions")
      ->leftJoin("p_o_batches", "transactions.request_id", "=", "p_o_batches.request_id")
      ->where("transactions.company_id", $company_id)
      ->where("transactions.supplier_id", $supplier_id)
      ->where("transactions.balance_po_ref_amount", ">", 0)
      ->orWhere("transactions.balance_po_ref_qty", ">", 0)
      ->where("p_o_batches.po_no", $po_no)
      ->orderBy("p_o_batches.id", "desc")
      ->get();
    return $transactions;
  }

  public static function validateIfPONoExistInDifferentSupplier($payment_type, $company_id, $supplier_id, $po_no)
  {
    $transactions = DB::table("transactions")
      ->leftJoin("p_o_batches", "transactions.request_id", "=", "p_o_batches.request_id")
      ->where("transactions.company_id", $company_id)
      ->where("transactions.supplier_id", $supplier_id)
      ->where("p_o_batches.po_no", $po_no);
    return $transactions->count();
  }

  public static function validateIfRefNoExist($payment_type, $company_id, $supplier_id, $ref_no)
  {
    $transactions = DB::table("transactions")
      ->leftJoin("p_o_batches", "transactions.request_id", "=", "p_o_batches.request_id")
      ->leftJoin("referrence_batches", "transactions.request_id", "=", "referrence_batches.request_id")
      // ->where('payment_type',$payment_type)
      ->where("company_id", $company_id)
      ->where("supplier_id", $supplier_id)
      ->where("referrence_no", $ref_no)
      // ->where(function ($query){
      //     $query->where('transactions.balance_po_ref_amount','=',0)
      //     ->orWhereNull('transactions.balance_po_ref_amount');
      // })
      ->get();
    return count($transactions);
  }

  public static function validateIfDocumentAmountIsGreaterThanPO($po_total_amount, $document_amount, $po_additional_pos)
  {
    if ($po_total_amount < $document_amount) {
      $response = [
        "code" => 403,
        "message" => "Document amount is higher than the old balance and total amount of additional POs ",
        "data" => $po_additional_pos,
      ];
    } else {
      $response = "Insert Additional PO";
    }
    return $response;
  }

  public static function validateIfPOExistInOtherDocNo(
    $payment_type,
    $company_id,
    $supplier_id,
    $po_no,
    $used_request_id
  ) {
    $transactions = DB::table("transactions")
      ->leftJoin("p_o_batches", "transactions.request_id", "=", "p_o_batches.request_id")
      ->where("transactions.company_id", $company_id)
      ->where("transactions.supplier_id", $supplier_id)
      ->where("p_o_batches.po_no", $po_no)
      ->whereIn("transactions.request_id", $used_request_id);
    return $transactions->count();
  }

  public static function validateDuplicateDocumentType($type)
  {
    $documentType = DB::table("documents")
      ->where("type", "=", $type)
      ->get();

    return $documentType;
  }

  public static function validateDuplicateDocumentTypeInUpdate($type, $id)
  {
    $documentType = DB::table("documents")
      ->where("id", "!=", $id)
      ->where("type", "=", $type)
      ->get();

    return $documentType;
  }

  public static function validateDuplicateByIdAndTable($value, $field, $table)
  {
    $result = DB::table("" . $table . "")
      ->where("" . $field . "", "=", $value)
      ->get();

    return $result;
  }

  public static function validateDuplicateInUpdate($value, $field, $table, $id)
  {
    $result = DB::table("" . $table . "")
      ->where("" . $field . "", "=", $value)
      ->where("id", "!=", $id)
      ->get();

    return $result;
  }

  public static function validateIfPOExists($po_group, $company_id, $id = 0)
  {
    $po_total_amount = 0;
    $existingTransaction = [];
    foreach ($po_group as $k => $v) {
      $po_no = $po_group[$k]["no"];

      $existingTransaction = Transaction::with("po_details")
        ->where("company_id", $company_id)
        ->where("state", "!=", "void")
        ->when($id, function ($query, $id) {
          $query->where("id", "<>", $id);
        })
        ->whereHas("po_details", function ($q) use ($po_no) {
          $q->where("po_no", $po_no);
        })
        ->exists();
    }

    foreach ($po_group as $k => $v) {
      $po_no = $po_group[$k]["no"];

      $transaction = Transaction::with("po_details")
        ->where("company_id", $company_id)
        ->where("state", "!=", "void")
        ->when($id, function ($query, $id) {
          $query->where("id", "<>", $id);
        })
        ->whereHas("po_details", function ($q) use ($po_no) {
          $q->where("po_no", $po_no);
        })
        ->get();
      if ($transaction->count() > 0) {
        $po_group[$k]["is_add"] = 0;
        $po_group[$k]["is_editable"] = 0;
        $po_group[$k]["previous_balance"] = Transaction::with("po_details")
          ->where("company_id", $company_id)
          ->where("state", "!=", "void")
          ->when($id, function ($query, $id) {
            $query->where("id", "<>", $id);
          })
          ->without("po_details")
          ->whereHas("po_details", function ($q) use ($po_no) {
            $q->where("po_no", $po_no);
          })
          ->whereHas("po_details", function ($q) {
            $q->where("is_add", 0);
          })
          ->get("balance_po_ref_amount")
          ->last()->balance_po_ref_amount;
      } else {
        $po_group[$k]["is_editable"] = 1;
        $po_group[$k]["is_add"] = 1;
        if (!$existingTransaction) {
          $po_group[$k]["is_add"] = 0;
        }

        $po_group[$k]["previous_balance"] = $v["amount"];
      }
    }
    return $po_group;
  }

  ##########################################################################################################
  #########################################      RESPONSE             ######################################
  ##########################################################################################################
  public static function result($code, $message, $data)
  {
    $arrayResponse = [
      "code" => $code,
      "message" => $message,
      "result" => $data,
    ];
    return response($arrayResponse, $code);
  }
  public static function error($code, $message, $data)
  {
    $arrayResponse = [
      "code" => $code,
      "message" => $message,
      "errors" => $data,
    ];
    return response($arrayResponse, $code);
  }

  public static function resultResponse($action, $modelName, $data = [])
  {
    $modelName = ucfirst(strtolower($modelName));
    switch ($action) {
      case "not-equal":
        return GenericMethod::error(422, $modelName . " amount not equal.", []);
        break;
      case "receive":
        return GenericMethod::result(200, "Transaction has been received.", []);
        break;
      case "hold":
        return GenericMethod::result(200, "Transaction has been hold.", []);
        break;
      case "unhold":
        return GenericMethod::result(200, "Transaction has been unhold.", []);
        break;
      case "return":
        return GenericMethod::result(200, "Transaction has been returned.", []);
        break;
      case "unreturn":
        return GenericMethod::result(200, "Transaction has been unreturned.", []);
        break;
      case "void":
        return GenericMethod::result(200, "Transaction has been voided.", []);
        break;
        case "gas":
            return GenericMethod::result(200, "Transaction has been saved.", []);
            break;
        case "discharge":
            return GenericMethod::result(200, "Transaction has been saved.", []);
            break;
      case "tag":
        return GenericMethod::result(200, "Transaction has been saved.", []);
        break;
      case "voucher":
        return GenericMethod::result(200, "Transaction has been saved.", []);
        break;
      case "approve":
        return GenericMethod::result(200, "Transaction has been saved.", []);
        break;
      case "transmit":
        return GenericMethod::result(200, "Transaction has been saved.", []);
        break;
      case "inspect":
        return GenericMethod::result(200, "Transaction has been saved.", []);
        break;
      case "audit":
        return GenericMethod::result(200, "Transaction has been saved.", []);
        break;
      case "cheque":
        return GenericMethod::result(200, "Transaction has been saved.", []);
        break;
      case "executive":
        return GenericMethod::result(200, "Transaction has been saved.", []);
        break;
      case "issue":
        return GenericMethod::result(200, "Transaction has been saved.", []);
        break;
      case "release":
        return GenericMethod::result(200, "Transaction has been saved.", []);
        break;
      case "reverse":
        return GenericMethod::result(200, "Transaction has been saved.", []);
        break;
      case "file":
        return GenericMethod::result(200, "Transaction has been saved.", []);
        break;
      case "clear":
        return GenericMethod::result(200, "Transaction has been saved.", []);
        break;
      case "request":
        return GenericMethod::result(200, "Transaction has been saved.", []);
        break;
      case "accept":
        return GenericMethod::result(200, "Transaction has been saved.", []);
        break;
      case "receive-approver":
        return GenericMethod::result(200, "Transaction has been saved.", []);
        break;
      case "receive-requestor":
        return GenericMethod::result(200, "Transaction has been saved.", []);
        break;
      case "transfer":
        return GenericMethod::result(200, "Transaction has been transferred.", []);
        break;
      case "fetch":
        return GenericMethod::result(200, Str::plural($modelName) . " has been fetched.", $data);
        break;

      case "save":
        return GenericMethod::result(201, "New " . strtolower($modelName) . " has been saved.", $data);
        break;

      case "counter-save":
        return GenericMethod::result(201, $modelName . " has been saved.", $data);
        break;

      case "import":
        return GenericMethod::result(201, Str::plural($modelName) . " has been imported.", $data);
        break;

      case "update":
        return GenericMethod::result(200, $modelName . " has been updated.", $data);
        break;

      case "archive":
        return GenericMethod::result(200, $modelName . " has been archived.", $data);
        break;

      case "restore":
        return GenericMethod::result(200, $modelName . " has been restored.", $data);
        break;

      case "registered":
        throw new FistoException($modelName . " already registered.", 409, null, $data);
        break;

      case "not-registered":
        throw new FistoException($modelName . " not registered.", 409, null, $data);
        break;

      case "registered-inactive":
        throw new FistoException($modelName . " already registered but inactive.", 409, null, $data);
        break;

      case "exist":
        throw new FistoException($modelName . " already exist.", 409, null, $data);
        break;

      case "transfer-invalid-process":
        throw new FistoException(
          $modelName . " Invalid, process inputted is not allowed to transfer.",
          422,
          null,
          $data
        );
        break;

      case "transfer-invalid-subprocess":
        throw new FistoException($modelName . " Invalid, subprocess must be transfer.", 422, null, $data);
        break;

      case "exist-flow":
        throw new FistoException("Transaction already " . strtolower($modelName) . ".", 409, null, $data);
        break;

      case "import-error":
        throw new FistoException(
          "No " . Str::plural(strtolower($modelName)) . " were imported. Kindly check the errors.",
          409,
          null,
          $data
        );
        break;

      case "ongoing":
        return GenericMethod::result(422, "On-going Transaction encountered.", []);
        break;

      case "upload-error":
        return GenericMethod::result(422, "The given data was invalid..", $data);
        break;

      case "import-format":
        throw new FistoException("Invalid excel template, it should be " . $modelName . ".", 406, null, []);
        break;

      case "nothing-has-changed":
        return GenericMethod::result(200, "Nothing has changed.", $data);
        break;

      case "not-found":
        throw new FistoException("No records found.", 404, null, $data);
        break;

      case "password-changed":
        return GenericMethod::result(200, "Password has been changed.", $data);
        break;

      case "password-incorrect":
        throw new FistoException("The password you entered is incorrect.", 409, null, $data);
        break;

      case "password-error-cred":
        throw new FistoException("You don't have the proper credentials to perform this action.", 401, null, $data);
        break;

      case "login":
        return GenericMethod::result(200, "Succesfully login.", $data);
        break;

      case "logout":
        return GenericMethod::result(200, "User has been logged out.", $data);
        break;

      case "logout-again":
        throw new FistoException("User is already logged out.", 401, null, []);
        break;

      case "login-error":
        throw new FistoException("Invalid username or password.", 409, null, $data);
        break;

      case "available":
        return GenericMethod::result(200, $modelName . " is available.", $data);
        break;

      case "password-reset":
        return GenericMethod::result(200, "User's default password has been restored.", $data);
        break;

      case "invalid-access":
        throw new FistoLaravelException("API cannot access by this user.", 422, null, $data);
        break;

      case "invalid":
        throw new FistoLaravelException("The given data was invalid.", 422, null, $data);
        break;

      case "voucher-no-exist":
        throw new FistoLaravelException("Voucher number already exist.", 422, null, $data);
        break;

      case "cheque-no-exist":
        throw new FistoLaravelException("Cheque number already exist.", 422, null, $data);
        break;

      case "success-no-content":
        return GenericMethod::result(204, "Success.", []);
        break;
    }
  }

  public static function resultLaravelFormat($column, $message)
  {
    if (gettype($column) == "string") {
      return collect(["$column" => $message]);
    }

    $result = collect();
    $column_count = count($column);
    $message_count = count($message);

    if ($column_count === $message_count) {
      foreach ($column as $key => $value) {
        $result->put("$column[$key]", $message[$key]);
      }
    }

    return $result;
  }

  ##########################################################################################################
  #########################################      OTHERS               ######################################
  ##########################################################################################################

  public static function viewRequestLogs($request)
  {
    $rows = empty($request["rows"]) ? 10 : (int) $request["rows"];
    $search = $request["search"];
    $paginate = isset($request["paginate"]) ? $request["paginate"] : ($paginate = 1);

    $requestor_logs = RequestorLogs::with("transaction")
      ->where(function ($query) use ($search) {
        $query
          ->where("transaction_id", "like", "%" . $search . "%")
          ->orWhere("transaction_no", "like", "%" . $search . "%")
          ->orWhere("description", "like", "%" . $search . "%")
          ->orWhere("status", "like", "%" . $search . "%")
          ->orWhere("date_status", "like", "%" . $search . "%")
          ->orWhere("user_id", "like", "%" . $search . "%")
          ->orWhere("reason_description", "like", "%" . $search . "%")
          ->orWhere("reason_remarks", "like", "%" . $search . "%");
      })
      ->latest("updated_at");

    if ($paginate == 1) {
      $requestor_logs = $requestor_logs
        ->select(["id", "status as type", "created_at as date", "transaction_id"])
        ->paginate($rows);
    } elseif ($paginate == 0) {
      $requestor_logs = $requestor_logs->get(["id", "status as type", "created_at as date", "transaction_id"]);
      if (count($requestor_logs) == true) {
        $requestor_logs = ["requestor_logs" => $requestor_logs];
      }
    }
    return $requestor_logs;
  }

  public static function addToUserDocumentCategory($user_id, $document_id, $category_id)
  {
    $new_user_document_category = UserDocumentCategory::create([
      "user_id" => $user_id,
      "document_id" => $document_id,
      "category_id" => $category_id,
    ]);
  }
  ##########################################################################################################

  public function generateVoucherNo($id, $code)
  {
    $existingVoucher = Transaction::whereNotNull("voucher_no")
      ->where("id", $id)
      ->first();

    if ($existingVoucher) {
      return $existingVoucher->voucher_no;
    }

    $series = 1;
    $code = Department::where('id', $code)->first()->voucherCode->code;
    $date = Carbon::now("Asia/Manila")->format("ym");

    do {
      $formattedSeries = str_pad($series, 3, "0", STR_PAD_LEFT);
       $generatedVoucher = $code . " " . $date . "-" . $formattedSeries;
//      $generatedVoucher = $date . "-" . $formattedSeries;

      $series++;
    } while ($this->checkDuplicateGeneratedVoucher($generatedVoucher));

    return $generatedVoucher;
  }

  function checkDuplicateGeneratedVoucher($voucher)
  {
    return Transaction::where("voucher_no", $voucher)->exists();
  }

  public static function generateTagNoTest($receipt_type, $id) {
      $existingTag = Transaction::whereNotNull("tag_no")
          ->where("id", $id)->where("receipt_type", $receipt_type)
          ->first();

      if ($existingTag) {
          return $existingTag->tag_no;
      }

      $series = 0;
      do {
          $series++;
      } while (static::checkDuplicateGeneratedTagNo($series, $receipt_type));

      return $series;
  }

  public static function checkDuplicateGeneratedTagNo($tag_no, $receipt_type) {
      return Transaction::where('tag_no', $tag_no)->where('receipt_type', $receipt_type)->exists();
  }


  public static function prmMultiplerequestUpdateID($new_transaction) {
      $newTransactionId = $new_transaction->transaction_id;
      $requestIds = Transaction::where('transaction_id', $newTransactionId)->pluck('id');
      Transaction::whereIn('id', $requestIds)->update(['request_id' => DB::raw('id')]);
  }
}
