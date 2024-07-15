<?php

namespace App\Http\Resources;

use App\Models\Associate;
use Illuminate\Http\Resources\Json\JsonResource;

class APReportResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        if ($this->has('voucher')) {
            $voucher_transaction = $this->voucher->first();

            if (empty($voucher_transaction->account_title)) {
                $voucher_account_title = [];
            } else {
                $voucher_account_title = $voucher_transaction->account_title->map(function ($item) {
                    return [
                        'entry' => $item->entry,
                        'amount' => $item->amount,
                        'account_title_code' => $item->account_title_code,
                        'account_title' => $item->account_title_name,
                        'company_code' => $item->company_code,
                        'company' => $item->company_name,
                        'department_code' => $item->department_code,
                        'department' => $item->department_name,
                        'location_code' => $item->location_code,
                        'location' => $item->location_name,
                        'description' => $item->remarks,
//                        'account_types' => $item->accountType
                    ];
                });
            }
        }

        return [
            'account_tag' => $this->tag_no,
            'transaction_date' => $this->date_requested,
            'supplier' => $this->supplier,
            'voucher_month' => $this->voucher_month,
            'voucher_no' => $this->voucher_no,
            'reference_no' => $this->referrence_no ?? $this->utilities_receipt_no,
            'vouchers' => $voucher_account_title,
            'po_details' => $this->po_details->map(function ($item) {
                return [
                    'po_no' => $item->po_no,
                ];
            }),
        ];
    }
}
