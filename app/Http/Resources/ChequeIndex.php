<?php

namespace App\Http\Resources;

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
        $cheques = $this->cheques->first()->cheques ?? $this->cheques;

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
          "document_amount" => $this->document_amount,
          "reference_no" => $this->referrence_no,
          "reference_amount" => $this->referrence_amount,
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

          "remarks" => $this->remarks,
          "status" => $this->state,
          "state" => $this->status,
        ];
    }
}
