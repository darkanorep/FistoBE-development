<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\POBatch;
use App\Models\Tagging;
use App\Models\Transaction;

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
    $this->state = $this->stateChange($this->state);
    $is_editable_prm = Tagging::where("transaction_id", $this->transaction_id)
      ->whereNotIn("status", ["tag-return", "tag-void"])
      ->exists()
      ? 1
      : 0;

    $is_latest = 0;

    $latestAudit = $this->audit ? $this->audit : $this->auditVoucher;

    $auditValues = [
      $latestAudit ? $latestAudit->transaction_id : null,
      $latestAudit ? $latestAudit->date_received : null,
      $latestAudit ? $latestAudit->status : null,
      $this->audit ? $this->audit->reason_id : null,
      $this->audit ? $this->audit->remarks : null,
      $this->auditVoucher ? optional($this->auditVoucher->auditedBy)->id : null,
      $this->auditVoucher ? optional($this->auditVoucher->auditedBy)->first_name : null,
      $this->audit ? optional($this->audit->auditedBy)->id : null,
      $this->audit ? optional($this->audit->auditedBy)->first_name : null,
      $this->audit ? $this->audit->date_audited : null,
      $this->auditVoucher ? $this->auditVoucher->date_audited : null,
    ];

    if (
      array_filter($auditValues, function ($value) {
        return $value !== null;
      }) === []
    ) {
      $auditData = [];
    } else {
      $auditData = [
        "transaction_id" => $latestAudit ? $latestAudit->transaction_id : null,
        "date_received" => $latestAudit ? $latestAudit->date_received : null,
        "status" => $latestAudit ? $latestAudit->status : null,
        "reason_id" => $this->audit ? $this->audit->reason_id : null,
        "remarks" => $this->audit ? $this->audit->remarks : null,
        "audit_by" => [
          "voucher" => $this->auditVoucher
            ? [
              "id" => optional($this->auditVoucher->auditedBy)->id,
              "name" => optional($this->auditVoucher->auditedBy)->name,
              "date_audit" => $this->auditVoucher->date_audited,
            ]
            : [],
          "cheque" => $this->audit
            ? [
              "id" => optional($this->audit->auditedBy)->id,
              "name" => optional($this->audit->auditedBy)->name,
              "date_audit" => $this->audit->date_audited,
            ]
            : [],
        ],
      ];
    }

    $executiveValues = [
      $this->executive ? $this->executive->transaction_id : null,
      $this->executive ? $this->executive->date_received : null,
      $this->executive ? $this->executive->status : null,
      $this->executive ? $this->executive->reason_id : null,
      $this->executive ? $this->executive->remarks : null,
      $this->executive ? optional($this->executive->executiveSignedBy)->id : null,
      $this->executive ? optional($this->executive->executiveSignedBy)->first_name : null,
      $this->executive ? $this->executive->date_signed : null,
    ];

    if (!empty($this->po_details)) {
      if ($this->po_details->last() != null) {
        $po_no = $this->po_details->last()->po_no;

        $transactions_ids = POBatch::with("transaction_ids")
          ->where("p_o_batches.po_no", $po_no)
          ->select(["request_id", "po_no"])
          ->get();

        $transactions_ids->filter(function ($value, $key) use ($transactions_ids) {
          if ($value->transaction_ids) {
            $$transactions_ids[$key] = $transactions_ids[$key];
          }
        });

        $transaction_obj = $transactions_ids->pluck(["transaction_ids"]);
        $transaction_obj = $transaction_obj->filter();

        if (!empty($transaction_obj->last())) {
          if ($transaction_obj->last()) {
            if ($this->id == $transaction_obj->last()->id) {
              $is_latest = 1;
            }
            $transactions_details = [
              "id" => $this->id,
              "tag_no" => $this->tag_no,
              "is_latest_transaction" => $is_latest,
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
              "document_amount" => $this->document_amount,
              "referrence_no" => $this->referrence_no,
              "referrence_amount" => $this->referrence_amount,
              "status" => $this->state,
              "state" => $this->status,
              "users" => $this->users,
              "po_details" => in_array($this->document_id, [1, 4, 5]) ? $this->po_details : [],
              // "audit" => $auditData,
              // "executive" => [
              //   "transaction_id" => $this->executive ? $this->executive->transaction_id : null,
              //   "date_received" => $this->executive ? $this->executive->date_received : null,
              //   "status" => $this->executive ? $this->executive->status : null,
              //   "reason_id" => $this->executive ? $this->executive->reason_id : null,
              //   "remarks" => $this->executive ? $this->executive->remarks : null,
              //   "signed_by" => [
              //     "id" => $this->executive ? optional($this->executive->executiveSignedBy)->id : null,
              //     "name" => $this->executive ? optional($this->executive->executiveSignedBy)->first_name : null,
              //   ],
              //   "date_signed" => $this->executive ? $this->executive->date_signed : null,
              // ],
            ];
          }
        }
      }

      $transactions_details = [
        "id" => $this->id,
        "tag_no" => $this->tag_no,
        "is_latest_transaction" => $is_latest,
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
        "document_amount" => $this->document_id == 3 ? $this->net_amount : $this->document_amount,
        "referrence_no" => $this->referrence_no,
        "referrence_amount" => $this->referrence_amount,
        "status" => $this->state,
        "state" => $this->status,
        "users" => $this->users,
        "po_details" => in_array($this->document_id, [1, 4, 5]) ? $this->po_details : [],
        // "audit" => $auditData,
        // "executive" => [
        //   "transaction_id" => $this->executive ? $this->executive->transaction_id : null,
        //   "date_received" => $this->executive ? $this->executive->date_received : null,
        //   "status" => $this->executive ? $this->executive->status : null,
        //   "reason_id" => $this->executive ? $this->executive->reason_id : null,
        //   "remarks" => $this->executive ? $this->executive->remarks : null,
        //   "signed_by" => [
        //     "id" => $this->executive ? optional($this->executive->executiveSignedBy)->id : null,
        //     "name" => $this->executive ? optional($this->executive->executiveSignedBy)->first_name : null,
        //   ],
        //   "date_signed" => $this->executive ? $this->executive->date_signed : null,
        // ],
      ];
    }

    return $transactions_details;
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
}
