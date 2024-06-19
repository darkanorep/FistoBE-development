<?php

namespace App\Http\Resources;

use App\Models\Associate;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\POBatch;
use App\Models\Tagging;
use App\Models\Transaction;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\TransactionResource1;

class TransactionIndex extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */

    public function toArray($request)
    {
        $resource = new TransactionResource1($this);

        $rental = $resource->getRental();

        $accounts = $this->account_titles->filter(function ($item) {
            return $item->account_title_name == 'Accounts Payable' || $item->account_title_name == 'Accounts Payable - RHL';
        });

//        $this->state = $this->stateChange($this->state);
        $this->state = $resource->stateChange($this->state);

        $is_editable_prm = 0;
        if ($this->document_id == 3) {
            $is_editable_prm = Tagging::where("transaction_id", $this->transaction_id)
                ->whereNotIn("status", ["tag-return", "tag-void"])
                ->exists();
        }

        $is_latest_transaction = 0;
        if ($this->po_details->isNotEmpty() && strtoupper($this->payment_type) === "PARTIAL") {
            $po_no = $this->po_details->last()->po_no;

//            $trxns_id = POBatch::with("transaction_ids")
//                ->where("p_o_batches.po_no", $po_no)
//                ->select(["request_id", "po_no"])
//                ->get();
//
//            $latest_trxn_id = $trxns_id->pluck("transaction_ids.id")->last();
//
//            if ($latest_trxn_id == $this->id) {
//                $is_latest_transaction = 1;
//            }

            $trxns_id = POBatch::with([
                'request' => function ($query) {
                $query->where('state', '!=', 'void')
                    ->select(['request_id']);
                }
            ])
                ->where('po_no', $po_no)->select(['request_id', 'po_no'])->get();

            $trxns_id = $trxns_id->filter(function ($query) {
                return $query['request'] != null;
            })->pluck('request.request_id')->last();

            if ($trxns_id == $this->id) {
                $is_latest_transaction = 1;
            }
        }

        $is_cheque = $this->treasuryCheque()->exists() ? 1 : 0;

//        $collect = $this->treasuryCheque->pluck('is_cleared');
//        $is_cleared = $collect->isEmpty() ? 0 : ($collect->contains(0 || null) ? 0 : 1);
        $is_cleared = $this->treasuryCheque->pluck('is_cleared')->isEmpty()
            ? 0 : ($this->treasuryCheque->pluck('is_cleared')->contains(0 || null)
                ? 0
                : 1);

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
            'company_code' => $this->company_info->code,
            "company" => $this->company,
            'department_id' => $this->department_id,
            'department_code' => $this->department_info->code,
            "department" => $this->department,
            'location_id' => $this->location_id,
            'location_code' => $this->location_info->code,
            "location" => $this->location,
            "document_no" => $this->document_no,
            'document_amount' => ($this->document_id == 3)
                ? ($this->category == in_array($this->category, $rental) ? $this->gross_amount : (($this->principal + $this->interest)))
                : $this->document_amount,
            "cheque_date" => $this->document_id == 3 ? $this->cheque_date : null,
            "period_covered" => $this->document_id == 3 ? $this->period_covered : null,
            "referrence_no" => $this->referrence_no,
            "referrence_amount" => $this->referrence_amount,
            "status" => $this->state,
            "state" => $this->status == 'cheque-cheque' ? 'cheque-create' : $this->status,
            "users" => [
                "id" => $this->users->id,
                "first_name" => $this->users->first_name,
                "middle_name" => $this->users->middle_name,
                "last_name" => $this->users->last_name,
                "department" => $this->users->department,
                "position" => $this->users->position,
            ],
            "po_details" => in_array($this->document_id, [1,  2, 4, 5])
                ? $this->po_details->map(function ($po) {
                    return [
                        "id" => $po->id,
                        "request_id" => $po->request_id,
                        "po_no" => $po->po_no,
                        "po_total_amount" => $po->po_total_amount
                    ];
                })
                : [],
            'receipt_type' => $this->receipt_type,
            'input_tax' => $this->input_tax,
            'is_cleared' => $is_cleared,
            'cheques' => $this->treasuryCheque->map(function ($item) {
                return [
                    'bank' => $item->bank_name,
                    'cheque_no' => $item->cheque_no,
                    'amount' => $item->cheque_amount,
                    'is_cleared' => $item->is_cleared,
                ];
            }),
            'accounts' => $accounts->map(function ($item) {
                return [
                    'account_title' => [
                        'name' => $item->account_title_name
                    ],
                    'amount' => $item->amount,
                ];
            })->values(),
            'voucher' => [
                'no' => $this->voucher_no,
            ],
            'is_cheque' => $is_cheque,
            'is_confidential' => $this->is_confidential,
            'is_mc' => $this->is_mc
        ];
    }


//    public function get_transaction_dates($model, $id, $process, $subprocesses)
//    {
//        $flow_details = $model::where('transaction_id', $id)->latest()->get();
//
//        $details = [];
//        foreach ($subprocesses as $k => $subprocess) {
//            $status = $process . "-" . $subprocess;
//            $details[$k]["subprocess"] = strtolower($this->stateChange($subprocess));
//
//            if ($process == "tag") {
//                $details[$k]["date"] = isset($flow_details->where("status", $status)->first()->created_at)
//                    ? $flow_details->where("status", $status)->first()->created_at
//                    : null;
//            } else {
//                $details[$k]["date"] = isset($flow_details->where("status", $status)->first()["created_at"])
//                    ? $flow_details->where("status", $status)->first()["created_at"]
//                    : null;
//            }
//        }
//
////        return array_reduce(
////            $details,
////            function ($result, $item) {
////                $result[$item["subprocess"]] = $item["date"];
////                return $result;
////            },
////            []
////        );
//        return $details[0]["date"];
//    }
}
