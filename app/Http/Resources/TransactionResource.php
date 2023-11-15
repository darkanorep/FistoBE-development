<?php

namespace App\Http\Resources;
use App\Models\BusinessUnit;
use App\Models\Charging;
use App\Models\Company;
use App\Models\Department;
use App\Models\Location;
use App\Models\SubUnit;
use App\Models\User;
use App\Models\POBatch;
use App\Models\Transaction;
use App\Models\Tagging;
use App\Models\Associate;
use App\Models\Approver;
use App\Models\Transmit;
use App\Models\Treasury;
use App\Models\Cheque;
use App\Models\Release;
use App\Models\File;
use App\Models\Clear;
use App\Models\Transfer;
use App\Models\Reason;
use App\Models\Reverse;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
  /**
   * Transform the resource into an array.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
   */
  public function toArray($request)
  {

    $document = [];
    $tag = null;
    $voucher = null;
    $approve = null;
    $transmit = null;
    $cheque_description = null;
    $release_description = null;
    $file_description = null;
    $reverse_description = null;
    $po_details = [];
    $prm_group = [];
    $reference = [];
    $po_no = [];
    $previous_balance = 0;
    $first_transaction_keys = [];
    $keys = [];
    $reverse_distributor = [];
    $autoDebit_group = [];
    $clear_description = [];

    $counter_receipt_status = $this->counter_receipt_status ? $this->counter_receipt_status : null;
    $counter_receipt_no = $this->counter_receipt_no ? $this->counter_receipt_no : null;

    $transaction = Transaction::
    with("tag")
        ->with("voucher.account_title")
      ->with("approve")
      ->with("transmit")
      ->with("cheques.cheques")
      ->with("cheques.account_title")
      ->with("audit")
      ->with("release")
      ->with("file")
      ->with("reverse")
      ->with("clear")
      ->with("clear.account_title")
//        with([
//            'tag',
//            'voucher.account_title',
////            'account_titles',
//            'approve',
//            'transmit',
//            'cheques.cheques',
//            'cheques.account_title',
//            'audit',
//            'release',
//            'file',
//            'reverse',
//            'clear',
//            'clear.account_title'
//        ])
      ->when($this->document_type == "Auto Debit", function ($query) {
        $query->with("auto_debit");
      })
      ->where("id", $this->id)
      ->get()
      ->first();
    $transaction_tag_no = isset($transaction->tag_no) ? $transaction->tag_no : null;
    $transaction_voucher_no = isset($transaction->voucher_no) ? $transaction->voucher_no : null;
    $transaction_voucher_month = isset($transaction->voucher_month) ? $transaction->voucher_month : null;
    $transaction_with_debit = $transaction;
    $transaction_request_id = $transaction["request_id"];
    $reason_date = $this->reason_id ? $transaction->updated_at : null;

    // TAG PROCESS
    if (count($transaction->tag) > 0) {
      $transaction_tag = $transaction->tag->first();
      isset($transaction["document"]["capex_no"]) ? $transaction["document"]["capex_no"] : null;
      $transaction_tag_date = isset($transaction_tag->date) ? $transaction_tag->date : null;
      $transaction_tag_status = isset($transaction_tag->status) ? $transaction_tag->status : null;
      $transaction_tag_distributed_id = isset($transaction->distributed_id) ? $this->distributed_id : null;
      $transaction_tag_distributed_name = isset($transaction->distributed_name) ? $transaction->distributed_name : null;

      $reason_id = isset($transaction_tag->reason_id) ? $transaction_tag->reason_id : null;
      $reason = isset($transaction_tag->reason_id) ? Reason::find($transaction_tag->reason_id)->reason : null;
      $reason_remarks = isset($transaction_tag->remarks) ? $transaction_tag->remarks : null;
    }
    // END TAG PROCESS

    // VOUCHER PROCESS
    if (count($transaction->voucher) > 0) {
      $voucher = $transaction->voucher->first();
      $voucher_receipt_type = isset($voucher->receipt_type) ? $voucher->receipt_type : null;
      $voucher_percentage_tax = isset($voucher->percentage_tax) ? $voucher->percentage_tax : null;
      $vouocher_witholding_tax = isset($voucher->witholding_tax) ? $voucher->witholding_tax : null;
      $voucher_net_amount = isset($voucher->net_amount) ? $voucher->net_amount : null;
      $voucher_approver_id = isset($voucher->approver_id) ? $voucher->approver_id : null;
      $voucher_approver_name = isset($voucher->approver_name) ? $voucher->approver_name : null;
      $voucher_date = isset($voucher->date) ? $voucher->date : null;
      $voucher_status = isset($voucher->status) ? $voucher->status : null;
      $voucher_reason_id = isset($voucher->reason_id) ? $voucher->reason_id : null;
      $voucher_reason = isset($voucher->reason_id) ? Reason::find($voucher->reason_id)->reason : null;
      $voucher_reason_remarks = isset($voucher->remarks) ? $voucher->remarks : null;
    }
    // END VOUCHER PROCESS

    // APPROVE PROCESS
    if (count($transaction->approve) > 0) {
      $approve = $transaction->approve->first();

      $approve_id = isset($approve->id) ? $approve->id : null;
      $approve_distributed_id = isset($transaction->distributed_id) ? $transaction->distributed_id : null;
      $approve_distributed_name = isset($transaction->distributed_name) ? $transaction->distributed_name : null;
      $approve_date = isset($approve->date) ? $approve->date : null;
      $approve_status = isset($approve->status) ? $approve->status : null;
      $approve_reason_id = isset($approve->reason_id) ? $approve->reason_id : null;
      $approve_reason = isset($approve->reason_id) ? Reason::find($approve->reason_id)->reason : null;
      $approve_reason_remarks = isset($approve->remarks) ? $approve->remarks : null;
    }
    // END APPROVE PROCESS

    // TRANSMITAL PROCESS
    if (count($transaction->transmit) > 0) {
      $transmit = $transaction->transmit->first();

      $transmit_id = isset($transmit->id) ? $transmit->id : null;
      $transmit_date = isset($transmit->date) ? $transmit->date : null;
      $transmit_status = isset($transmit->status) ? $transmit->status : null;
    }
    // END TRANSMITAL PROCESS

    // CHEQUE PROCESS
    if (count($transaction->cheques) > 0) {
      $cheque = $transaction->cheques->first();
      $cheque_status = isset($cheque->status) ? $cheque->status : null;
      $cheque_date_status = isset($cheque->date) ? $cheque->date : null;
      $cheque_reason_id = isset($cheque->reason_id) ? $cheque->reason_id : null;
      $cheque_reason = isset($cheque->reason_id) ? Reason::find($cheque->reason_id)->reason : null;
      $cheque_reason_remarks = isset($cheque->remarks) ? $cheque->remarks : null;
    }
    // END CHEQUE PROCESS

    // RELEASE PROCESS
    if (count($transaction->release) > 0) {
      $release = $transaction->release->first();

      $release_id = isset($release->id) ? $release->id : null;
      $release_date = isset($release->date) ? $release->date : null;
      $release_reason_id = isset($release->reason_id) ? $release->reason_id : null;
      $release_reason = isset($release->reason_id) ? Reason::find($release->reason_id)->reason : null;
      $release_reason_remarks = isset($release->remarks) ? $release->remarks : null;
      $release_status = isset($release->status) ? $release->status : null;
      $release_distributed_id = isset($release->distributed_id) ? $release->distributed_id : null;
      $release_distributed_name = isset($release->distributed_name) ? $release->distributed_name : null;
    }
    // END RELEASE PROCESS

    // FILE PROCESS
    if (count($transaction->file) > 0) {
      $file = $transaction->file->first();

      $file_id = isset($file->id) ? $file->id : null;
      $file_date = isset($file->date) ? $file->date : null;
      $file_status = isset($file->status) ? $file->status : null;
      $file_reason_id = isset($file->reason_id) ? $file->reason_id : null;
      $file_reason = isset($file->reason_id) ? Reason::find($file->reason_id)->reason : null;
      $file_reason_remarks = isset($file->remarks) ? $file->remarks : null;
    }
    // END FILE PROCESS

    // REVERSE PROCESS
    if (count($transaction->reverse) > 0) {
      $reverse = $transaction->reverse->first();

      $reverse_id = isset($reverse->id) ? $reverse->id : null;
      $reverse_date = isset($reverse->date) ? $reverse->date : null;
      $reverse_status = isset($reverse->status) ? $reverse->status : null;
      $reverse_reason_id = isset($reverse->reason_id) ? $reverse->reason_id : null;
      $reverse_reason = isset($reverse->reason_id) ? Reason::find($reverse->reason_id)->reason : null;
      $reverse_reason_remarks = isset($reverse->remarks) ? $reverse->remarks : null;
      $reverse_user_role = isset($reverse->user_role) ? $reverse->user_role : null;
      $reverse_user_id = isset($reverse->user_id) ? $reverse->user_id : null;
      $reverse_user_name = isset($reverse->user_name) ? $reverse->user_name : null;
      $reverse_distributed_id = isset($reverse->distributed_id) ? $reverse->distributed_id : null;
      $reverse_distributed_name = isset($reverse->distributed_name) ? $reverse->distributed_name : null;
    }
    // END REVERSE PROCESS

    // CLEAR PROCESS
    if (count($transaction->clear) > 0) {
      $clear = $transaction->clear->first();
      $clear_status = isset($clear->status) ? $clear->status : null;
      $clear_date_status = isset($clear->date) ? $clear->date : null;
      $clear_date_cleared = isset($clear->date_cleared) ? $clear->date_cleared : null;
    }

    $condition = $this->state == "void" ? "=" : "!=";
    $document_amount = Transaction::where("request_id", $this->request_id)
      ->where("state", $condition, "void")
      ->first()->document_amount;
    $payment_type = strtoupper($this->payment_type);
    $user = User::where("id", $this->users_id)
      ->get()
      ->first();
    $po_transaction = POBatch::leftJoin("transactions", "p_o_batches.request_id", "=", "transactions.request_id")
      ->where("transactions.state", $condition, "void")
      ->get();
    $po_details = POBatch::leftJoin("transactions", "p_o_batches.request_id", "=", "transactions.request_id")
      ->where("transactions.state", $condition, "void")
      ->where("transactions.id", $this->id)
      ->where("transactions.request_id", $this->request_id)
      ->whereIn("transactions.document_id", [1, 4, 5])
      ->when(
        $payment_type === "PARTIAL",
        function ($q) {
          $q->select([
            "is_add",
            "is_editable",
            "is_modifiable",
            "p_o_batches.id as id",
            "po_no as no",
            "po_amount as amount",
            "previous_balance",
            "balance_po_ref_amount as balance",
            "rr_group as rr_no",
          ]);
        },
        function ($q) {
          $q->select([
            "p_o_batches.id as id",
            "po_no as no",
            "po_amount as amount",
            "rr_group as rr_no",
            "p_o_batches.request_id",
          ]);
        }
      )
      ->get();

    foreach ($po_details as $j => $u) {
      $rr_no = json_decode($po_details[$j]["rr_no"]);
      $po_details[$j]["rr_no"] = $rr_no;
      $po_details[$j]["is_editable"] = 1;
      $po_details[$j]["previous_balance"] = $po_details[$j]["amount"];
    }

    $is_latest_transaction = 1;
    if (strtoupper($this->payment_type) == "PARTIAL") {
      $is_latest_transaction = 0;

      $first_po_no = $po_details->where("is_add", 0)->last()->no;
      $with_linked_transactions = $po_transaction
        ->where("po_no", $first_po_no)
        ->where("id", "<", $this->id)
        ->pluck("id");
      $balance = $po_details->where("is_add", 0)->first()->balance;
      $previous_balance = $po_details->where("is_add", 0)->first()->previous_balance;

      foreach ($po_details as $k => $v) {
        $po_no = $po_details[$k]["no"];
        if ($po_details[$k]["is_add"] == 0 and count($with_linked_transactions) == 0) {
          $first_transaction_keys[] = $k;
          $po_details[$k]["previous_balance"] = $po_details[$k]["amount"];
          $po_details[$k]["balance"] = 0;
        } elseif ($po_details[$k]["is_add"] == 0 and count($with_linked_transactions) > 0) {
          $old_po_with_linked_transaction_keys[] = $k;
          $po_details[$k]["previous_balance"] = 0;
          $po_details[$k]["balance"] = 0;
        }
        //     else if($po_details[$k]['is_add']==0 ){
        //         $keys[] = $k;
        //         $po_details[$k]['previous_balance'] = 0;
        //         $po_details[$k]['balance'] = 0;
        //     }
        //     // unset($po_details[$k]->is_add);
        $last_transaction_id = $po_transaction
          ->where("po_no", $po_no)
          ->where("state", $condition, "void")
          ->pluck("id")
          ->last();
        if ($last_transaction_id == $this->id) {
          $is_latest_transaction = 1;
        }
      }

      // return current($old_po_with_linked_transaction_keys);

      if (!empty($first_transaction_keys)) {
        $key = current($first_transaction_keys);
        $po_details[$key]["balance"] = $balance;
      } elseif (!empty($old_po_with_linked_transaction_keys)) {
        $last_transaction_no = $with_linked_transactions->last();
        $previous_balance = $po_transaction->firstWhere("id", $last_transaction_no)->balance_po_ref_amount;
        $key = current($old_po_with_linked_transaction_keys);
        $po_details[$key]["previous_balance"] = $previous_balance;
      } else {
      }
      // $po_details->first()->balance = $po_details->pluck("previous_balance")->sum() - $this->referrence_amount;
      switch ($transaction->document_id) {
        case 1:
          $po_details->first()->balance = $po_details->pluck("previous_balance")->sum() - $this->document_amount;
          break;

        default:
          $po_details->first()->balance = $po_details->pluck("previous_balance")->sum() - $this->referrence_amount;
          break;
      }
    }
    $transaction = $po_transaction->where("request_id", $this->request_id);

    switch ($this->document_id) {
      case 1: //PAD
      case 2: //PRM Common
        $document = [
          "id" => $this->document_id,
          "name" => $this->document_type,
          "no" => $this->document_no,
          "date" => $this->document_date,
          "payment_type" => $this->payment_type,
          "amount" => $this->document_amount,
          "remarks" => $this->remarks,
          "category" => [
            "id" => $this->category_id,
            "name" => $this->category,
          ],
          "company" => [
            "id" => $this->company_id,
            "name" => $this->company,
          ],
          "department" => [
            "id" => $this->department_id,
            "name" => $this->department,
          ],
          "location" => [
            "id" => $this->location_id,
            "name" => $this->location,
          ],
          "supplier" => [
            "id" => $this->supplier_id,
            "name" => $this->supplier,
          ],
        ];
        break;

      case 3: //PRM Multiple
        $document = [
          "id" => $this->document_id,
          "name" => $this->document_type,
          "no" => $this->document_no,
          "date" => $this->document_date,
          "payment_type" => $this->payment_type,
//          "amount" => $this->document_amount,
            'amount' => ($this->document_id == 3)
                ? ($this->category == 'rental' ? $this->gross_amount : floatval((number_format(($this->principal + $this->interest), 2, '.', ''))))
                : $this->document_amount,
          "net_amount" => $this->net_amount,
          "release_date" => $this->release_date,
          "batch_no" => $this->batch_no,
          "remarks" => $this->remarks,
          "category" => [
            "id" => $this->category_id,
            "name" => $this->category,
          ],
          "company" => [
            "id" => $this->company_id,
            "name" => $this->company,
          ],
          "department" => [
            "id" => $this->department_id,
            "name" => $this->department,
          ],
          "location" => [
            "id" => $this->location_id,
            "name" => $this->location,
          ],
          "supplier" => [
            "id" => $this->supplier_id,
            "name" => $this->supplier,
          ],
        ];
        switch ($this->category) {
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
            $document["period_covered"] = $this->period_covered;
            $document["prm_multiple_from"] = $this->prm_multiple_from;
            $document["prm_multiple_to"] = $this->prm_multiple_to;
            $document["gross_amount"] = $this->gross_amount;
            $document["witholding_tax"] = $this->witholding_tax;
            $document["net_of_amount"] = $this->net_amount;
            $document["cheque_date"] = $this->cheque_date;
            break;
          case "official store leasing":
          case "unofficial store leasing":
          case "leasing":
            $document["amortization"] = $this->amortization;
            $document["principal"] = $this->principal;
            $document["interest"] = $this->interest;
            $document["cwt"] = $this->cwt;
            $document["net_of_amount"] = $this->net_amount;
            $document["cheque_date"] = $this->cheque_date;
            break;
          case "loans":
            $document["principal"] = $this->principal;
            $document["interest"] = $this->interest;
            $document["cwt"] = $this->cwt;
            $document["net_of_amount"] = $this->net_amount;
            $document["cheque_date"] = $this->cheque_date;
            break;
        }

        break;

      case 5: //Contractor's Billing
        $document = [
          "id" => $this->document_id,
          "name" => $this->document_type,
          "no" => $this->document_no,
          "capex_no" => $this->capex_no,
          "date" => $this->document_date,
          "payment_type" => $this->payment_type,
          "amount" => $this->document_amount,
          "remarks" => $this->remarks,
          "category" => [
            "id" => $this->category_id,
            "name" => $this->category,
          ],
          "company" => [
            "id" => $this->company_id,
            "name" => $this->company,
          ],
          "department" => [
            "id" => $this->department_id,
            "name" => $this->department,
          ],
          "location" => [
            "id" => $this->location_id,
            "name" => $this->location,
          ],
          "supplier" => [
            "id" => $this->supplier_id,
            "name" => $this->supplier,
          ],
        ];
        break;

      case 6: //Utilities
        $document = [
          "id" => $this->document_id,
          "name" => $this->document_type,
          "payment_type" => $this->payment_type,
          "amount" => $this->document_amount,
          "from" => $this->utilities_from,
          "to" => $this->utilities_to,
          "remarks" => $this->remarks,
          "company" => [
            "id" => $this->company_id,
            "name" => $this->company,
          ],
          "department" => [
            "id" => $this->department_id,
            "name" => $this->department,
          ],
          "location" => [
            "id" => $this->location_id,
            "name" => $this->location,
          ],
          "supplier" => [
            "id" => $this->supplier_id,
            "name" => $this->supplier,
          ],
          "utility" => [
            "receipt_no" => $this->utilities_receipt_no,
            "consumption" => $this->utilities_consumption,
            "location" => [
              "id" => $this->utilities_location_id,
              "name" => $this->utilities_location,
            ],
            "category" => [
              "id" => $this->utilities_category_id,
              "name" => $this->utilities_category,
            ],
            "account_no" => [
              "id" => $this->utilities_account_no_id,
              "no" => $this->utilities_account_no,
            ],
          ],
        ];
        break;

      case 8: //PCF
        $document = [
          "id" => $this->document_id,
          "name" => $this->document_type,
          "date" => $this->document_date,
          "amount" => $this->document_amount,
          "payment_type" => $this->payment_type,
          "remarks" => $this->remarks,
          "company" => [
            "id" => $this->company_id,
            "name" => $this->company,
          ],
          "department" => [
            "id" => $this->department_id,
            "name" => $this->department,
          ],
          "location" => [
            "id" => $this->location_id,
            "name" => $this->location,
          ],
          "supplier" => [
            "id" => $this->supplier_id,
            "name" => $this->supplier,
          ],
          "pcf_batch" => [
            "name" => $this->pcf_name,
            "letter" => $this->pcf_letter,
            "date" => $this->pcf_date,
          ],
        ];

        break;

      case 7: //Payroll
        $document = [
          "id" => $this->document_id,
          "name" => $this->document_type,
          "payment_type" => $this->payment_type,
          "amount" => $this->document_amount,
          "from" => $this->payroll_from,
          "to" => $this->payroll_to,
          "remarks" => $this->remarks,
          "company" => [
            "id" => $this->company_id,
            "name" => $this->company,
          ],
          "department" => [
            "id" => $this->department_id,
            "name" => $this->department,
          ],
          "location" => [
            "id" => $this->location_id,
            "name" => $this->location,
          ],
          "supplier" => [
            "id" => $this->supplier_id,
            "name" => $this->supplier,
          ],
          "payroll" => [
            "type" => $this->payroll_type,
            "clients" => $this->payroll_client,
            "category" => [
              "id" => $this->payroll_category_id,
              "name" => $this->payroll_category,
            ],
            "control_no" => $this->payroll_control_no,
          ],
        ];
        break;

      case 4: //Receipt
        $document = [
          "id" => $this->document_id,
          "name" => $this->document_type,
          "date" => $this->document_date,
          "payment_type" => $this->payment_type,
          "remarks" => $this->remarks,
          "category" => [
            "id" => $this->category_id,
            "name" => $this->category,
          ],
          "company" => [
            "id" => $this->company_id,
            "name" => $this->company,
          ],
          "department" => [
            "id" => $this->department_id,
            "name" => $this->department,
          ],
          "location" => [
            "id" => $this->location_id,
            "name" => $this->location,
          ],
          "supplier" => [
            "id" => $this->supplier_id,
            "name" => $this->supplier,
          ],
          "reference" => [
            "id" => $this->referrence_id,
            "type" => $this->referrence_type,
            "no" => $this->referrence_no,
            "amount" => $this->referrence_amount,
            "allowable" => $this->is_allowable,
          ],
        ];
        break;
      case 9: //Auto Debit
        $document = [
          "id" => $this->document_id,
          "name" => $this->document_type,
          "date" => $this->document_date,
          "payment_type" => $this->payment_type,
          "amount" => $this->document_amount,
          "remarks" => $this->remarks,
          "category" => [
            "id" => $this->category_id,
            "name" => $this->category,
          ],
          "company" => [
            "id" => $this->company_id,
            "name" => $this->company,
          ],
          "department" => [
            "id" => $this->department_id,
            "name" => $this->department,
          ],
          "location" => [
            "id" => $this->location_id,
            "name" => $this->location,
          ],
          "supplier" => [
            "id" => $this->supplier_id,
            "name" => $this->supplier,
          ],
        ];
        break;
    }

    // TAG
    if (isset($transaction_tag_status)) {
      $reason = null;
      $distributed_to = null;
      $dates = null;

      $model = new Tagging();
      $process = "tag";
      $subprocess = ["receive", "tag"];
      $dates = $this->get_transaction_dates($model, $this->id, $process, $subprocess);

      if (isset($transaction_tag_distributed_id)) {
        $distributed_to = [
          "id" => $transaction_tag_distributed_id,
          "name" => $transaction_tag_distributed_name,
        ];
      }
      if (isset($reason_id)) {
        $reason = [
          "id" => $reason_id,
          "reason" => $reason,
          "remarks" => $reason_remarks,
        ];
      }

      $tag = [
        "status" => $transaction_tag_status,
          "receipt_type" => $this->receipt_type,
        "no" => $transaction_tag_no,
        "dates" => $dates,
        "distributed_to" => $distributed_to,
        "reason" => $reason,
      ];
    }

    // VOUCHER
    if (isset($voucher_status)) {
      $reason = null;
      $approver = null;
      $account_title = null;
      $transaction_type = null;
      $dates = null;
      $input_tax = null;
      $model = new Associate();
      $process = "voucher";
      $subprocess = ["transfer", "receive", "voucher"];
      $dates = $this->get_transaction_dates($model, $this->id, $process, $subprocess);

      if (isset($voucher->account_title)) {
        $voucher_account_title = $voucher->account_title;
        $voucher_account_title = $voucher_account_title->mapToGroups(function ($item, $key) {
          return [
            $item["associate_id"] => [
              "id" => $item["associate_id"],
              "entry" => $item["entry"],
              "account_title" => [
                "id" => $item["account_title_id"],
                  "code" => $item["account_title_code"],
                "name" => $item["account_title_name"]
              ],
              "amount" => $item["amount"],
              "remarks" => $item["remarks"],
                "company" => [
                    'id' => $item['company_id'],
                    'name' => isset($item['company_id']) ? $item["company_name"] : null,
                    'code' => isset($item['company_id']) ? $item["company_code"] : null
                ],
                "department" => [
                    'id' => $item['department_id'],
                    'name' => isset($item['department_id']) ? $item["department_name"] : null,
                    'code' => isset($item['department_id']) ? $item["department_code"] : null
                ],
                "location" => [
                    'id' => $item['location_id'],
                    'name' => isset($item['location_id']) ? $item["location_name"] : null,
                    'code' => isset($item['location_id']) ? $item["location_code"] : null
                ],
                "business_unit" => [
                    'id' => $item['business_unit_id'],
                    'name' => isset($item['business_unit_id']) ? $item["business_unit_name"] : null,
                    'code' => isset($item['business_unit_id']) ? $item["business_unit_code"] : null
                ],
                "sub_unit" => [
                    'id' => $item['sub_unit_id'],
                    'name' => isset($item['sub_unit_id']) ? $item["sub_unit_name"] : null,
                    'code' => isset($item['sub_unit_id']) ? $item["sub_unit_code"] : null
                ],
                "is_default" => $item["is_default"]
            ],
          ];
        });
        $account_title = $voucher_account_title->values();

        if (!$account_title->isEmpty()) {
          $account_title = $account_title->first();
        } else {
          $account_title = [];
        }
      }

      if (isset($transaction_tag_distributed_id)) {
        if ($voucher_approver_id) {
          $approver = [
            "id" => $voucher_approver_id,
            "name" => $voucher_approver_name,
          ];

          $transaction_type = [
              'id' => $voucher->transaction_type_id,
              'name' => $voucher->transaction_type_name
          ];

          $input_tax = $this->input_tax;
        }
      }

      if (isset($voucher_reason_id)) {
        $reason = [
          "id" => $voucher_reason_id,
          "reason" => $voucher_reason,
          "remarks" => $voucher_reason_remarks,
        ];
      }

      $voucher = [
        "status" => $voucher_status,
        "no" => $transaction_voucher_no,
        "dates" => $dates,
        "month" => $transaction_voucher_month,
//        "receipt_type" => $voucher_receipt_type,
        "transaction_type" => $transaction_type,
        "input_tax" => $input_tax,
        "accounts" => $account_title,
        "approver" => $approver,
        "reason" => $reason,
      ];
    }

    // APPROVE
    if (isset($approve_status)) {
      $reason = null;
      $distributed_to = null;
      $dates = null;
      $model = new Approver();
      $process = "approve";
      $subprocess = ["receive", "approve"];
      $dates = $this->get_transaction_dates($model, $this->id, $process, $subprocess);

      if (isset($approve_distributed_id)) {
        $distributed_to = [
          "id" => $approve_distributed_id,
          "name" => $approve_distributed_name,
        ];
      }
      if (isset($approve_reason_id)) {
        $reason = [
          "id" => $approve_reason_id,
          "reason" => $approve_reason,
          "remarks" => $approve_reason_remarks,
        ];
      }

      $approve = [
        "dates" => $dates,
        "status" => $approve_status,
        "distributed_to" => $distributed_to,
        "reason" => $reason,
      ];
    }

    // TRANSMIT
    if (isset($transmit_status)) {
      $dates = null;
      $model = new Transmit();
      $process = "transmit";
      $subprocess = ["transfer", "receive", "transmit"];
      $dates = $this->get_transaction_dates($model, $this->id, $process, $subprocess);

      $transmit = [
        "dates" => $dates,
        "status" => $transmit_status,
      ];
    }

    // CHEQUE
    if (isset($cheque_status)) {
      $reason = null;
      $account_title = null;
      $dates = null;
      $model = new Treasury();
      $process = "cheque";
      $subprocess = ["receive", "cheque", "release"];
      $dates = $this->get_transaction_dates($model, $this->id, $process, $subprocess);

      if (isset($cheque->cheques)) {
        $cheque_cheques = $cheque->cheques;
        $cheque_cheques = $cheque_cheques->filter(function ($value, $key) {
          return $value["transaction_type"] == "new";
        });

        $cheque_details = $cheque_cheques->mapToGroups(function ($item, $key) {
          return [
            $item["treasury_id"] => [
              // "id"=>$item['treasury_id']
              "type" => $item["entry_type"],
              "bank" => [
                "id" => intval($item["bank_id"]),
                "name" => $item["bank_name"],
              ],
              "no" => $item["cheque_no"],
              "date" => $item["cheque_date"],
              "amount" => $item["cheque_amount"],
            ],
          ];
        });
        $cheque_details = $cheque_details->values();
      }

      if (isset($cheque->account_title)) {
        $cheque_account_title = $cheque->account_title;
        $cheque_account_title = $cheque_account_title->filter(function ($value, $key) {
          return $value["transaction_type"] == "new";
        });
        $cheque_account_title = $cheque_account_title->mapToGroups(function ($item, $key) {
          return [
            $item["treasury_id"] => [
              "id" => $item["treasury_id"],
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
        $account_title = $cheque_account_title->values();
      }

      if (!$cheque_details->isEmpty()) {
        $cheque_details = $cheque_details->first();
      } else {
        $cheque_details = [];
      }

      if (!$account_title->isEmpty()) {
        $account_title = $account_title->first();
      } else {
        $account_title = [];
      }

      if (isset($cheque_reason_id)) {
        $reason = [
          "id" => $cheque_reason_id,
          "reason" => $cheque_reason,
          "remarks" => $cheque_reason_remarks,
        ];
      }

      $cheque_description = [
        "dates" => $dates,
        "status" => $cheque_status,
        "cheques" => $cheque_details,
        "accounts" => $account_title,
        "reason" => $reason,
      ];
    }

    // RELEASE
    if (isset($release_status)) {
      $reason = null;
      $distributed_to = null;
      $dates = null;
      $model = new Release();
      $process = "release";
      $subprocess = ["receive", "release"];
      $dates = $this->get_transaction_dates($model, $this->id, $process, $subprocess);

      if (isset($release_distributed_id)) {
        $distributed_to = [
          "id" => $release_distributed_id,
          "name" => $release_distributed_name,
        ];
      }

      if (isset($release_reason_id)) {
        $reason = [
          "id" => $release_reason_id,
          "reason" => $release_reason,
          "remarks" => $release_reason_remarks,
        ];
      }

      $release_description = [
        "dates" => $dates,
        "status" => $release_status,
        "distributed_to" => $distributed_to,
        "reason" => $reason,
      ];
    }

    // FILE
    if (isset($file_status)) {
      $reason = null;
      $dates = null;
      $model = new File();
      $process = "file";
      $subprocess = ["transfer", "receive", "file"];
      $box_no = $this->box_no;
      $dates = $this->get_transaction_dates($model, $this->id, $process, $subprocess);

      if (isset($file_reason_id)) {
        $reason = [
          "id" => $file_reason_id,
          "reason" => $file_reason,
          "remarks" => $file_reason_remarks,
        ];
      }

      $file_description = [
        "dates" => $dates,
        "status" => $file_status,
        "reason" => $reason,
        "box_no" => $box_no,
      ];
    }

    // REVERSE
    if (isset($reverse_status)) {
      $reason = null;
      $modified_by = null;

      if (isset($reverse_reason_id)) {
        $reason = [
          "id" => $reverse_reason_id,
          "reason" => $reverse_reason,
          "remarks" => $reverse_reason_remarks,
        ];
      }

      if (isset($reverse_user_id)) {
        $modified_by = [
          "role" => $reverse_user_role,
          "id" => $reverse_user_id,
          "name" => $reverse_user_name,
        ];
      }

      if (isset($reverse_distributed_id)) {
        $reverse_distributor = [
          "id" => $reverse_distributed_id,
          "name" => $reverse_distributed_name,
        ];
      }

      $reverse_description = [
        "status" => $reverse_status,
        "date" => $reverse_date,
        "modified_by" => $modified_by,
        "reason" => $reason,
        "distributed_to" => $reverse_distributor,
      ];

      if ($reverse_status != "reverse-request") {
        $reverse_description = [
          "status" => $reverse_status,
          "date" => $reverse_date,
          "modified_by" => $modified_by,
          "reason" => $reason,
        ];
      }
    }

    // CLEARING
    if (isset($clear_status)) {
      $account_title = null;
      $dates = null;
      $model = new Clear();
      $process = "clear";
      $subprocess = ["receive", "clear"];
      $dates = $this->get_transaction_dates($model, $this->id, $process, $subprocess);

      if (isset($clear->account_title)) {
        $clear_account_title = $clear->account_title;
        $clear_account_title = $clear_account_title->filter(function ($value, $key) {
          return $value["transaction_type"] == "new";
        });
        $clear_account_title = $clear_account_title->mapToGroups(function ($item, $key) {
          return [
            $item["clear_id"] => [
              "id" => $item["clear_id"],
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
        $account_title = $clear_account_title->values();
      }

      if (!$account_title->isEmpty()) {
        $account_title = $account_title->first();
      } else {
        $account_title = [];
      }

      $clear_description = [
        "dates" => $dates,
        "status" => $clear_status,
        "date" => $clear_date_status,
        "date_cleared" => $clear_date_cleared,
        "accounts" => $account_title,
      ];
    }

    // PRM GROUP
    if ($this->document_type == "PRM Multiple") {
      switch ($this->category) {
        case "stall a rental":
        case "stall b rental":
        case "stall c rental":
        case "stall d rental":
        case "cusa rental":
        case "dorm rental":
        case "additional rental":
        case "lounge rental":
        case "corporate special program - education":
        case "official store rental":
        case "unofficial store rental":
        case "rental":
          $prm_fields = Transaction::where("transaction_id", $this->transaction_id)
            // ->where("state", "!=", "void")
            ->select([
              "status",
              "period_covered",
              "gross_amount",
              "witholding_tax as wht",
              "net_amount as net_of_amount",
              "cheque_date",
            ])
            ->get();
          break;
        case "official store leasing":
        case "unofficial store leasing":
        case "leasing":
          $prm_fields = Transaction::where("transaction_id", $this->transaction_id)
            // ->where("state", "!=", "void")
            ->select([
              "status",
              "amortization",
              "principal",
              "interest",
              "cwt",
              "net_amount as net_of_amount",
              "cheque_date",
            ])
            ->get();
          break;
        case "loans":
          $prm_fields = Transaction::where("transaction_id", $this->transaction_id)
            // ->where("state", "!=", "void")
            ->select(["status", "principal", "interest", "cwt", "net_amount as net_of_amount", "cheque_date"])
            ->get();
          break;
      }
      $prm_group = $prm_fields;
    }

    // COUNTER RECEIPT
    $counter_receipt = [];
    if ($counter_receipt_status) {
      $counter_receipt = [
        "status" => $counter_receipt_status,
        "no" => $counter_receipt_no,
      ];
    }

    $transaction_result = [
      "counter_receipt" => $counter_receipt,
      "transaction" => [
        "id" => $this->id,
        "is_latest_transaction" => $is_latest_transaction,
        "request_id" => $this->request_id,
        "no" => $this->transaction_id,
        "date_requested" => $this->date_requested,
        "status" => $this->status,
        "state" => $this->state,
      ],
      "reason" => [
        "id" => $this->reason_id,
        "description" => $this->reason,
        "remarks" => $this->reason_remarks,
        "date" => $reason_date,
      ],
      "requestor" => [
        "id" => $this->users_id,
        "id_prefix" => $this->id_prefix,
        "id_no" => $this->id_no,
        "role" => $user->role,
        "position" => $user->position,
        "first_name" => $this->first_name,
        "middle_name" => $this->middle_name,
        "last_name" => $this->last_name,
        "suffix" => $this->suffix,
        "department" => $this->department_details,
      ],
      "document" => $document,
    ];

      $transaction_result["autoDebit_group"] = $this->auto_debit->map(function ($autoDebit) {
        return [
          "request_id" => $autoDebit->request_id,
          "pn_no" => $autoDebit->pn_no,
          "interest_from" => $autoDebit->interest_from,
          "interest_to" => $autoDebit->interest_to,
          "outstanding_amount" => floatVal($autoDebit->outstanding_amount),
          "interest_rate" => floatVal($autoDebit->interest_rate),
          "no_of_days" => floatVal($autoDebit->no_of_days),
          "principal_amount" => floatVal($autoDebit->principal_amount),
          "interest_due" => floatVal($autoDebit->interest_due),
          "cwt" => floatVal($autoDebit->cwt),
          "dst" => floatVal($autoDebit->dst),
        ];
      });
    $transaction_result["po_group"] = $po_details;
    $transaction_result["prm_group"] = $prm_group;
    $transaction_result["tag"] = $tag;
    $transaction_result["voucher"] = $voucher;
    $transaction_result["approve"] = $approve;
    $transaction_result["transmit"] = $transmit;
    $transaction_result["cheque"] = $cheque_description;

    // GAS
      $receiveGas = $this->receiveGas;
      $gas = $this->gas;
      $reasonGas = $this->reasonGas;
      $statusGas = null;
      if ($this->statusGas) {
        $statusGas = $this->statusGas->status;
      }

      $gasValues = [
        "date_received" => $receiveGas ? ($receiveGas->created_at ?: null) : null,
        "date_gas" => $gas ? ($gas->created_at ?: null) : null,
        "status" => $statusGas,
      ];

      $reasonGasValues = [
        "id" => $reasonGas ? ($reasonGas->reason_id ?: null) : null,
        "reason" => $reasonGas && $reasonGas->reason ? $reasonGas->reason->reason : null,
        "remarks" => $reasonGas ? ($reasonGas->remarks ?: null) : null,
      ];

      if (
          array_filter($gasValues, function ($value) {
          return $value !== null;
        }) === []
      ) {
        $transaction_result["gas"] = [];
      } else {
        if ($gasValues) {
          $transaction_result["gas"] = [
            "dates" => [
              "received" => $gasValues["date_received"],
              "gas" => $gasValues["date_gas"],
            ],
            "status" => $gasValues["status"],
          ];

          if ($reasonGasValues["id"] !== null || $reasonGasValues["remarks"] !== null) {
            $transaction_result["gas"]["reason"] = [
              "id" => $reasonGasValues["id"],
              "reason" => $reasonGasValues["reason"],
              "remarks" => $reasonGasValues["remarks"],
            ];
          } else {
            $transaction_result["gas"]["reason"] = null;
          }
        } else {
          $transaction_result["gas"] = [];
        }
      }

      //Discharge
        $receiveDischarge = $this->receiveDischarge;
        $discharge = $this->discharge;
        $reasonDischarge = $this->reasonDischarge;
        $statusDischarge = null;

        if ($this->statusDischarge) {
          $statusDischarge = $this->statusDischarge->status;
        }

        $dischargeValues = [
          "date_received" => $receiveDischarge ? ($receiveDischarge->created_at ?: null) : null,
          "date_discharge" => $discharge ? ($discharge->created_at ?: null) : null,
          "status" => $statusDischarge,
        ];

        $reasonDischargeValues = [
          "id" => $reasonDischarge ? ($reasonDischarge->reason_id ?: null) : null,
          "reason" => $reasonDischarge && $reasonDischarge->reason ? $reasonDischarge->reason->reason : null,
          "remarks" => $reasonDischarge ? ($reasonDischarge->remarks ?: null) : null,
        ];

        if (
          array_filter($dischargeValues, function ($value) {
            return $value !== null;
          }) === []
        ) {
          $transaction_result["discharge"] = [];
        } else {
          if ($dischargeValues) {
            $transaction_result["discharge"] = [
              "dates" => [
                "received" => $dischargeValues["date_received"],
                "discharged" => $dischargeValues["date_discharge"],
              ],
              "status" => $dischargeValues["status"],
            ];

            if ($reasonDischargeValues["id"] !== null || $reasonDischargeValues["remarks"] !== null) {
              $transaction_result["discharge"]["reason"] = [
                "id" => $reasonDischargeValues["id"],
                "reason" => $reasonDischargeValues["reason"],
                "remarks" => $reasonDischargeValues["remarks"],
              ];
            } else {
              $transaction_result["discharge"]["reason"] = null;
            }
          } else {
            $transaction_result["discharge"] = [];
          }
        }

    //Inspect Voucher
    $receive = $this->receiveVoucher;
    $inspect = $this->auditVoucher;
    $reasonVoucher = $this->reasonVoucher;
    $status = null; // Default value
    if ($this->statusVoucher) {
      $status = $this->statusVoucher->status;
    }

    $inspectValues = [
      "date_received" => $receive ? ($receive->created_at ?: null) : null,
      "date_inspected" => $inspect ? ($inspect->created_at ?: null) : null,
      "status" => $status,
    ];

    $reasonValues = [
      "id" => $reasonVoucher ? ($reasonVoucher->reason_id ?: null) : null,
      "reason" => $reasonVoucher && $reasonVoucher->reason ? $reasonVoucher->reason->reason : null,
      "remarks" => $reasonVoucher ? ($reasonVoucher->remarks ?: null) : null,
    ];

    if (
      array_filter($inspectValues, function ($value) {
        return $value !== null;
      }) === []
    ) {
      $transaction_result["inspect"] = [];
    } else {
      if ($inspectValues) {
        $transaction_result["inspect"] = [
          "dates" => [
            "received" => $inspectValues["date_received"],
            "inspected" => $inspectValues["date_inspected"],
          ],
          "status" => $inspectValues["status"],
        ];

        if ($reasonValues["id"] !== null || $reasonValues["remarks"] !== null) {
          $transaction_result["inspect"]["reason"] = [
            "id" => $reasonValues["id"],
            "reason" => $reasonValues["reason"],
            "remarks" => $reasonValues["remarks"],
          ];
        } else {
          $transaction_result["inspect"]["reason"] = null;
        }
      } else {
        $transaction_result["inspect"] = [];
      }
    }

    //Audit Cheque
    $receiveCheque = $this->receive;
    $auditCheque = $this->audit;
    $reasonAudit = $this->reasonAudit;
    $statusAudit = null;
    if ($this->statusAudit) {
      $statusAudit = $this->statusAudit->status;
    }

    $auditValues = [
      "date_received" => $receiveCheque ? ($receiveCheque->created_at ?: null) : null,
      "date_audited" => $auditCheque ? ($auditCheque->created_at ?: null) : null,
      "status" => $statusAudit,
    ];

    $reasonAuditValues = [
      "id" => $reasonAudit ? ($reasonAudit->reason_id ?: null) : null,
      "reason" => $reasonAudit && $reasonAudit->reason ? $reasonAudit->reason->reason : null,
      "remarks" => $reasonAudit ? ($reasonAudit->remarks ?: null) : null,
    ];

    if (
      array_filter($auditValues, function ($value) {
        return $value !== null;
      }) === []
    ) {
      $transaction_result["audit"] = [];
    } else {
      if ($auditValues) {
        $transaction_result["audit"] = [
          "dates" => [
            "received" => $auditValues["date_received"],
            "audited" => $auditValues["date_audited"],
          ],
          "status" => $auditValues["status"],
        ];

        if ($reasonAuditValues["id"] !== null || $reasonAuditValues["remarks"] !== null) {
          $transaction_result["audit"]["reason"] = [
            "id" => $reasonAuditValues["id"],
            "reason" => $reasonAuditValues["reason"],
            "remarks" => $reasonAuditValues["remarks"],
          ];
        } else {
          $transaction_result["audit"]["reason"] = null;
        }
      } else {
        $transaction_result["audit"] = [];
      }
    }

    //Executive
    $receiveExecutive = $this->receiveExecutive;
    $executiveSign = $this->executive;
    $reasonExecutive = $this->reasonExecutive;
    $statusExecutive = null;
    if ($this->statusExecutive) {
      $statusExecutive = $this->statusExecutive->status;
    }

    $executiveValues = [
      "date_received" => $receiveExecutive ? ($receiveExecutive->created_at ?: null) : null,
      "date_signed" => $executiveSign ? ($executiveSign->created_at ?: null) : null,
      "status" => $statusExecutive,
    ];

    // $reasonExecutiveValues = [
    //   "id" => $reasonExecutive ? ($reasonExecutive->reason_id ?: null) : null,
    //   "reason" => $reasonExecutive && $reasonExecutive->reason ? $reasonExecutive->reason->reason : null,
    //   "remarks" => $reasonExecutive ? ($reasonExecutive->remarks ?: null) : null,
    // ];

    if (
      array_filter($executiveValues, function ($value) {
        return $value !== null;
      }) === []
    ) {
      $transaction_result["executive"] = [];
    } else {
      if ($executiveValues) {
        $transaction_result["executive"] = [
          "dates" => [
            "received" => $executiveValues["date_received"],
            "signed" => $executiveValues["date_signed"],
          ],
          "status" => $executiveValues["status"],
        ];

        // if ($reasonExecutiveValues["id"] !== null || $reasonExecutiveValues["remarks"] !== null) {
        //   $transaction_result["executive"]["reason"] = [
        //     "id" => $reasonExecutiveValues["id"],
        //     "reason" => $reasonExecutiveValues["reason"],
        //     "remarks" => $reasonExecutiveValues["remarks"],
        //   ];
        // } else {
        //   $transaction_result["executive"]["reason"] = null;
        // }
      } else {
        $transaction_result["executive"] = [];
      }
    }

    //Issue
    $issueReceive = $this->issueReceive;
    $issueIssue = $this->issueIssue;

    $issueReason = $this->issueReason;
    $issueStatus = null;
    if ($this->issueStatus) {
      $issueStatus = $this->issueStatus->status;
    }

    $issueValues = [
      "date_received" => $issueReceive ? ($issueReceive->created_at ?: null) : null,
      "date_issued" => $issueIssue ? ($issueIssue->created_at ?: null) : null,
      "status" => $issueStatus,
    ];

    $reasonIssueValues = [
      "id" => $issueReason ? ($issueReason->reason_id ?: null) : null,
      "reason" => $issueReason && $issueReason->reason ? $issueReason->reason->reason : null,
      "remarks" => $issueReason ? ($issueReason->remarks ?: null) : null,
    ];

    if (
      array_filter($issueValues, function ($value) {
        return $value !== null;
      }) === []
    ) {
      $transaction_result["issue"] = [];
    } else {
      if ($issueValues) {
        $transaction_result["issue"] = [
          "dates" => [
            "received" => $issueValues["date_received"],
            "issued" => $issueValues["date_issued"],
          ],
          "status" => $issueValues["status"],
        ];

        if ($reasonIssueValues["id"] !== null || $reasonIssueValues["remarks"] !== null) {
          $transaction_result["issue"]["reason"] = [
            "id" => $reasonIssueValues["id"],
            "reason" => $reasonIssueValues["reason"],
            "remarks" => $reasonIssueValues["remarks"],
          ];
        } else {
          $transaction_result["issue"]["reason"] = null;
        }
      } else {
        $transaction_result["issue"] = [];
      }
    }

    //for Auto Debit
    $debitReceive = $this->debitReceive;
    $debitFile = $this->debitFile;
    $debitReason = $this->debitReason;
    $debitStatus = null;
    if ($this->debitStatus) {
      $debitStatus = $this->debitStatus->status;
    }

    $debitValues = [
      "date_received" => $debitReceive ? ($debitReceive->created_at ?: null) : null,
      "date_filed" => $debitFile ? ($debitFile->created_at ?: null) : null,
      "status" => $debitStatus,
    ];

    $reasondebitValues = [
      "id" => $debitReason ? ($debitReason->reason_id ?: null) : null,
      "reason" => $debitReason && $debitReason->reason ? $debitReason->reason->reason : null,
      "remarks" => $debitReason ? ($debitReason->remarks ?: null) : null,
    ];

    if (
      array_filter($debitValues, function ($value) {
        return $value !== null;
      }) === []
    ) {
      $transaction_result["debit"] = [];
    } else {
      if ($debitValues) {
        $transaction_result["debit"] = [
          "dates" => [
            "received" => $debitValues["date_received"],
            "filed" => $debitValues["date_filed"],
          ],
          "status" => $debitValues["status"],
            "accounts" => $this->debit_file->map(function ($debitFile) {
                return [
                    "entry" => $debitFile->entry,
                    "accounts" => [
                        "id" => $debitFile->account_title_id,
                        "name" => $debitFile->account_title_name
                    ],
                ];
            }),
        ];

        if ($reasondebitValues["id"] !== null || $reasondebitValues["remarks"] !== null) {
          $transaction_result["debit"]["reason"] = [
            "id" => $reasondebitValues["id"],
            "reason" => $reasondebitValues["reason"],
            "remarks" => $reasondebitValues["remarks"],
          ];
        } else {
          $transaction_result["debit"]["reason"] = null;
        }
      } else {
        $transaction_result["debit"] = [];
      }
    }

    $transaction_result["release"] = $release_description;
    $transaction_result["file"] = $file_description;
    $transaction_result["reverse"] = $reverse_description;
    $transaction_result["clear"] = $clear_description;
    $transaction_result["document"]["business_unit"] = [
        "id" => $this->business_unit_id,
        "name" => $this->business_unit,
    ];
    $transaction_result["document"]["sub_unit"] = [
        "id" => $this->sub_unit_id,
        "name" => $this->sub_unit,
    ];


    //---------------------------------------------------------------------------------------------------------------------------//
//      $test = Transaction::where('id', $this->id)->select(['company_id', 'department_id'])->first()->toArray();
//      $test2 = Charging::where('transaction_id', $this->id)->select(['company_id', 'department_id'])->first()->toArray();
//
//      $test_array = array_diff($test2, $test);
//
//      if ($test_array) {
//          $changedData = [
//              'company' => [
//                  'id' => isset($test_array['company_id']) ? $test_array['company_id'] : null,
//                  'name' => isset($test_array['company_id']) ? Company::where('id', $test_array['company_id'])->select('company')->first()->company : null,
//              ],
//              'department' => [
//                  'id' => isset($test_array['department_id']) ? $test_array['department_id'] : null,
//                  'name' => isset($test_array['department_id']) ? Department::where('id', $test_array['department_id'])->select('department')->first()->department : null,
//              ]
//          ];
//          $transaction_result["change"] =  $changedData;
//      }
      //---------------------------------------------------------------------------------------------------------------------------//
    // return $transaction_result;
    $result = [];
    foreach ($transaction_result as $k => $v) {
      if ($transaction_result[$k] != null) {
        $result[$k] = $transaction_result[$k];
      }
    }
    return $result;
  }

  public function get_transaction_dates($model, $id, $process, $subprocesses)
  {
//    $flow_details = $model
//      ::when(
//        $process == "tag",
////        function ($query) use ($id) {
////          $query->where("request_id", $id);
////        },
//          function ($query) use ($id) {
//              $query->where("transaction_id", $id);
//          },
////        function ($query) use ($id) {
////          $query->where("tag_id", $id);
////        }
//          function ($query) use ($id) {
//              $query->where("tag_id", $id);
//          }
//      )
//      ->latest()
//      ->get();

    $flow_details = $model::where('transaction_id', $id)->latest()->get();

    $details = [];
    foreach ($subprocesses as $k => $subprocess) {
      $status = $process . "-" . $subprocess;
      $details[$k]["subprocess"] = $this->stateChange($subprocess);

      if ($process == "tag") {
        $details[$k]["date"] = isset($flow_details->where("status", $status)->first()->created_at)
          ? $flow_details->where("status", $status)->first()->created_at
          : null;
      } else {
        $details[$k]["date"] = isset($flow_details->where("status", $status)->first()["created_at"])
          ? $flow_details->where("status", $status)->first()["created_at"]
          : null;
      }
    }

    return array_reduce(
      $details,
      function ($result, $item) {
        $result[$item["subprocess"]] = $item["date"];
        return $result;
      },
      []
    );
  }

  public function stateChange($state)
  {
    switch ($state) {
      case "tag":
        $state = "tagged";
        break;
      case "request":
      case "pending":
        $state = "pending";
        break;
      case "cheque":
        $state = "created";
        break;
      case "hold":
        $state = "held";
        break;
      case "transmit":
        $state = "transmitted";
        break;
      case "receive-approver":
        $state = "received";
        break;
      case "receive-requestor":
        $state = "received";
        break;

      default:
        if (str_ends_with($state, "e")) {
          $state = strtolower($state . "d");
        } elseif (str_ends_with($state, "g")) {
          $state = strtolower($state);
        } else {
          $state = strtolower($state . "ed");
        }
    }

    return $state;
  }
}
