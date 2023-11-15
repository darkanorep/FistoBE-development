<?php

namespace App\Http\Resources;

use App\Models\Associate;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\POBatch;
use App\Models\Tagging;
use App\Models\Transaction;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\TransactionResource;

class TransactionIndex extends JsonResource
{
  /**
   * Transform the resource into an array.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
   */

    public function toArray($request)
  {
//      if (Auth::user()->role == 'Treasury Associate' && (request()->state == 'pending' || $this->status == 'cheque-receive')) {
//
//          return [
//              'id' => $this->id,
//              'transaction_no' => $this->transaction_id,
//              'receipt_type' => $this->receipt_type,
//              'payment_type' => $this->payment_type,
//              'document' => [
//                  'id' => $this->document_id,
//                  'name' => $this->document_type
//              ],
//              'supplier' => [
//                    'id' => $this->supplier_id,
//                    'name' => $this->supplier->name,
//                    'type' => $this->supplier->supplier_type->name,
//              ],
//              'voucher' => [
//                  'no' => $this->voucher_no,
//                  'month' => $this->voucher_month,
//                  'date' => $this->get_transaction_dates(Associate::class, $this->id, 'voucher', ["voucher"])
//              ],
//              'company' => [
//                  'id' => $this->company_id,
//                  'name' => $this->company,
//              ],
//              'department' => [
//                  'id' => $this->department_id,
//                  'name' => $this->department,
//              ],
//              'location' => [
//                    'id' => $this->location_id,
//                    'name' => $this->location,
//              ],
//              'remarks' => $this->remarks,
//              'document_no' => $this->document_no,
//              'document_amount' => $this->document_amount,
//              'referrence_no' => $this->referrence_no,
//              'referrence_amount' => $this->referrence_amount,
//              'status' => $this->stateChange($this->state),
//              'state' => $this->status,
//          ];
//      }

      $this->state = $this->stateChange($this->state);

    $is_editable_prm = 0;
    if ($this->document_id == 3) {
      $is_editable_prm = Tagging::where("transaction_id", $this->transaction_id)
        ->whereNotIn("status", ["tag-return", "tag-void"])
        ->exists();
    }


    $is_latest_transaction = 0;
    if ($this->po_details->isNotEmpty() && strtoupper($this->payment_type) === "PARTIAL") {
      $po_no = $this->po_details->last()->po_no;

      $trxns_id = POBatch::with("transaction_ids")
        ->where("p_o_batches.po_no", $po_no)
        ->select(["request_id", "po_no"])
        ->get();

      $latest_trxn_id = $trxns_id->pluck("transaction_ids.id")->last();

      if ($latest_trxn_id == $this->id) {
        $is_latest_transaction = 1;
      }
    }

    return [
      "id" => $this->id,
      "tag_no" => $this->tag_no,
      "is_latest_transaction" => $is_latest_transaction,
      "is_editable_prm" => $is_editable_prm,
      "users_id" => $this->users_id,
      "request_id" => $this->request_id,
      "supplier_id" => $this->supplier_id,
      "document_id" => $this->document_id,
      "transaction_id" => $this->transaction_id,
      "document_type" => $this->document_type,
      "payment_type" => $this->payment_type,
      "supplier" => $this->supplier,
      "remarks" => $this->remarks,
      "date_requested" => $this->date_requested,
      "company_id" => $this->company_id,
      "company" => $this->company,
      "department" => $this->department,
      "location" => $this->location,
      "document_no" => $this->document_no,
        'document_amount' => ($this->document_id == 3)
            ? ($this->category == 'rental' ? $this->gross_amount : (($this->principal + $this->interest)))
            : $this->document_amount,
         "cheque_date" => $this->document_id == 3 ? $this->cheque_date : null,
      "referrence_no" => $this->referrence_no,
      "referrence_amount" => $this->referrence_amount,
      "status" => $this->state,
      "state" => $this->status,
      "users" => $this->users,
      "po_details" => in_array($this->document_id, [1, 4, 5]) ? $this->po_details : [],
        'receipt_type' => $this->receipt_type,
        "is_cleared" => $this->is_cleared
    ];
  }

  public function stateChange($state)
  {
    switch ($state) {
      case "tag":
        $state = "Tagged";
        break;
      case "request":
      case "pending":
        $state = "Pending";
        break;
      case "hold":
        $state = "Held";
        break;
      case "transmit":
        $state = "Transmitted";
        break;
      case "receive-approver":
        $state = "Received";
        break;
      case "receive-requestor":
        $state = "Received";
        break;

      default:
        if (str_ends_with($state, "e")) {
          $state = ucfirst($state . "d");
        } elseif (str_ends_with($state, "g")) {
          $state = ucfirst($state);
        } else {
          $state = ucfirst($state . "ed");
        }
    }

    return $state;
  }

    public function get_transaction_dates($model, $id, $process, $subprocesses)
    {
        $flow_details = $model::where('transaction_id', $id)->latest()->get();

        $details = [];
        foreach ($subprocesses as $k => $subprocess) {
            $status = $process . "-" . $subprocess;
            $details[$k]["subprocess"] = strtolower($this->stateChange($subprocess));

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

//        return array_reduce(
//            $details,
//            function ($result, $item) {
//                $result[$item["subprocess"]] = $item["date"];
//                return $result;
//            },
//            []
//        );
        return $details[0]["date"];
    }
}
