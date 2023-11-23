<?php

namespace App\Http\Resources;

use App\Models\Supplier;
use Illuminate\Http\Resources\Json\JsonResource;

class ChequeClearIndex extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $account_title = $this->transaction->voucher->first()->account_title;
        return [
            'id' => optional($this->transaction)->id,
            'tag_no' => optional($this->transaction)->tag_no,
            'transaction_no' => $this->transaction->transaction_id,
            'receipt_type' => $this->transaction->receipt_type,
            'payment_type' => $this->transaction->payment_type,
            'document' => [
                'id' => $this->transaction->document_id,
                'name' => $this->transaction->document_type,
            ],
            'document_no' => $this->transaction->document_no,
//                'document_amount' => $this->transaction->document_amount,
            'document_amount' => ($this->document_id == 3)
                ? ($this->category == 'rental' ? $this->gross_amount : (($this->principal + $this->interest)))
                : $this->transaction->document_amount,
            'reference_no' => $this->transaction->referrence_no,
            'reference_amount' => $this->transaction->referrence_amount,
            'date_requested' => $this->transaction->date_requested,
            'company' => [
                'id' => $this->transaction->company_id,
                'name' => $this->transaction->company,
            ],
            'department' => [
                'id' => $this->transaction->department_id,
                'name' => $this->transaction->department,
            ],
            'location' => [
                'id' => $this->transaction->location_id,
                'name' => $this->transaction->location,
            ],
            'supplier' => [
                'id' => $this->transaction->supplier_id,
                'name' => $this->transaction->supplier,
                'type' => Supplier::where('id', $this->transaction->supplier_id)->first()->supplier_type->type,
            ],
            'voucher' => [
                'no' => $this->transaction->voucher_no,
                'month' => $this->transaction->voucher_month,
            ],
            'cheques' => [
                [
                    'type' => $this->entry_type,
                    'no' => $this->cheque_no,
                    'bank' => [
                        'id' => $this->bank_id,
                        'name' => $this->bank_name,
                    ],
                    'amount' => $this->cheque_amount,
                    'date' => $this->cheque_date
                ]
            ],
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
            'remarks' => $this->transaction->remarks,
            'status' => $this->transaction->status,
            'state' => $this->transaction->state,
            'is_cleared' => $this->is_cleared,
            'date_cleared' => $this->date_cleared,
        ];
    }
}
