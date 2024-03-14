<?php

namespace App\Http\Resources;

use App\Models\Cheque;
use App\Models\Supplier;
use Illuminate\Http\Resources\Json\JsonResource;

class ChequeIndex extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
//        $cheques = $this->cheques->first()->cheques ?? $this->cheques;
        $cheques = $this->cheques->first()
            ? $this->cheques->first()->chequeViaTransaction
                ? $this->cheques->first()->chequeViaTransaction
                : $this->cheques
            : $this->cheques;

        $account_title = $this->voucher->first()->account_title;
        return [
            "id" => $this->id,
            "tag_no" => $this->tag_no,
            "transaction_no" => $this->transaction_id,
            "receipt_type" => $this->receipt_type,
            "payment_type" => $this->payment_type,

            "document" => [
                "id" => $this->document_id,
                "name" => $this->document_type,
            ],
            "document_no" => $this->document_no,
//                "document_amount" => $this->document_amount,
            'document_amount' => ($this->document_id == 3)
                ? ($this->category == 'rental' ? $this->gross_amount : (($this->principal + $this->interest)))
                : $this->document_amount ?? $this->referrence_amount,
            "reference_no" => $this->referrence_no,
            "input_tax" => $this->input_tax,
//            "reference_amount" => $this->referrence_amount,
            "date_requested" => $this->date_requested,

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
                "id" => $this->supplier->id,
                "name" => $this->supplier->name,
                "type" => $this->supplier->supplier_type->name,
            ],

            "voucher" => [
                "no" => $this->voucher_no,
                "month" => $this->voucher_month,
            ],
            "cheques" => $cheques->map(function ($item) {
                return [
                    "type" => $item->entry_type,
                    "no" => $item->cheque_no,
                    "bank" => [
                        "id" => $item->bank_id,
                        "name" => $item->bank_name
                    ],
                    "amount" => $item->cheque_amount,
                    "date" => $item->cheque_date,
                ];
            }),
            "accounts" => $account_title->map(function ($item) {
                return [
                    "entry" => $item->entry,
                    "account_title" => [
                        "id" => $item->account_title_id,
                        "code" => $item->account_title_code,
                        "name" => $item->account_title_name,
                    ],
                    "company" => [
                        "id" => $item->company_id,
                        "code" => $item->company_code,
                        "name" => $item->company_name,
                    ],
                    "department" => [
                        "id" => $item->department_id,
                        "code" => $item->department_code,
                        "name" => $item->department_name,
                    ],
                    "location" => [
                        "id" => $item->location_id,
                        "code" => $item->location_code,
                        "name" => $item->location_name,
                    ],
                    "business_unit" => [
                        "id" => $item->business_unit_id,
                        "code" => $item->business_unit_code,
                        "name" => $item->business_unit_name,
                    ],
                    "sub_unit" => [
                        "id" => $item->sub_unit_id,
                        "code" => $item->sub_unit_code,
                        "name" => $item->sub_unit_name,
                    ],
                    "amount" => $item->amount,
                    "remarks" => $item->remarks,
                ];
            }),
            "remarks" => $this->remarks,
            "status" => $this->state,
            "state" => $this->status,
        ];
    }
}
