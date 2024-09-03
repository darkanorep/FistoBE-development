<?php

namespace App\Http\Resources;

use App\Models\Approver;
use App\Models\Associate;
use App\Models\Audit;
use App\Models\File;
use App\Models\Gas;
use App\Models\Transmit;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionVoucherResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {

        $transactionResource = new TransactionResource1($this);
        $voucher = null;
        $inspect = null;
        $approve = null;
        $transmit = null;
        $discharge = null;
        $file = null;
        $cheque_account_title = null;
        $account_title = null;
        $voucher_account_title = null;

        //VOUCHER
        if ($this->has('voucher')->exists()) {
            $voucher_transaction = $this->voucher->first();

            if (empty($voucher_transaction->account_title)) {
                $voucher_account_title = [];
            } else {
                $voucher_account_title = $voucher_transaction->account_title;
            }

            if (empty($voucher_transaction->transaction_type_id)) {
                $transaction_type = null;
            } else {
                $transaction_type = [
                    'id' => $voucher_transaction->transaction_type_id,
                    'name' => $voucher_transaction->transaction_type_name,
                ];
            }

            if (empty($voucher_transaction->approver_id)) {
                $approver = null;
            } else {
                $approver = [
                    'id' => $voucher_transaction->approver_id,
                    'name' => $voucher_transaction->approver_name,
                ];
            }

            if ($this->has('cheques')->exists()) {

                $cheque_transaction = $this->cheques->first();
                $clear_transaction = $this->accountTitleClear;

                $cheque_account_title = $clear_transaction->isEmpty()
                    ? ($cheque_transaction ? ($cheque_transaction->account_title ?: []) : [])
                    : ($clear_transaction ?: []);
            }

            $account_title = empty($cheque_account_title)
                ? ($voucher_account_title ?: [])
                : ($cheque_account_title);

            if (!empty($account_title)) {
                $account_title = $account_title->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'entry' => $item->entry,
                        'account_title' => [
                            'id' => $item->account_title_id,
                            'code' => $item->account_title_code,
                            'name' => $item->account_title_name,
                        ],
                        'amount' => $item->amount,
                        'remarks' => $item->remarks,
                        'company' => [
                            'id' => $item->company_id,
                            'code' => $item->company_code,
                            'name' => $item->company_name,
                        ],
                        'department' => [
                            'id' => $item->department_id,
                            'code' => $item->department_code,
                            'name' => $item->department_name,
                        ],
                        'location' => [
                            'id' => $item->location_id,
                            'code' => $item->location_code,
                            'name' => $item->location_name,
                        ],
                        'business_unit' => [
                            'id' => $item->business_unit_id,
                            'code' => $item->business_unit_code,
                            'name' => $item->business_unit_name,
                        ],
                        'sub_unit' => [
                            'id' => $item->sub_unit_id,
                            'code' => $item->sub_unit_code,
                            'name' => $item->sub_unit_name,
                        ],
                        'is_default' => $item->is_default
                    ];
                });
            }

            if (isset($voucher_transaction->status)) {
                $voucher = [
                    'status' => $voucher_transaction->status,
                    'no' => $this->voucher_no,
                    'dates' => $transactionResource->get_transaction_dates(Associate::class, $this->id, 'voucher', ["transfer", "receive", "voucher"]),
                    'month' => $this->voucher_month,
                    'transaction_type' => $transaction_type,
                    'input_tax' => $this->input_tax,
                    'accounts' => $account_title,
                    'approve' => $approver,
                    'reason' => $transactionResource->reason($voucher_transaction, $voucher_transaction->reason_id)
                ];
            }
        }

        //INSPECT
        if ($this->has('inspect')->exists()) {
            $inspect_transaction = $this->inspect->first();

            if (isset($inspect_transaction->status)) {
                $inspect = [
                    'status' => $inspect_transaction->status,
                    'dates' => $transactionResource->get_transaction_dates(Audit::class, $this->id, 'inspect', ["receive", "inspect"]),
                    'reason' => $transactionResource->reason($inspect_transaction, $inspect_transaction->reason_id)
                ];
            }
        }

        //APPROVE
        if ($this->has('approve')->exists()) {
            $approve_transaction = $this->approve->first();

            if (isset($approve_transaction->status)) {
                $approve = [
                    'status' => $approve_transaction->status,
                    'dates' => $transactionResource->get_transaction_dates(Approver::class, $this->id, 'approve', ["receive", "approve"]),
                    'distributed_to' => [
                        'id' => $this->distributed_id,
                        'name' => $this->distributed_name,
                    ],
                    'reason' => $transactionResource->reason($approve_transaction, $approve_transaction->reason_id)
                ];
            }
        }

        //TRANSMIT
        if ($this->has('transmit')->exists()) {
            $transmit_transaction = $this->transmit->first();

            if (isset($transmit_transaction->status)) {
                $transmit = [
                    'dates' => $transactionResource->get_transaction_dates(Transmit::class, $this->id, 'transmit', ["transfer", "receive", "transmit"]),
                    'status' => $transmit_transaction->status,
                ];
            }
        }

        //DISCHARGE
        if ($this->has('discharge')->exists()) {
            $discharge_transaction = $this->discharge->first();

            if (isset($discharge_transaction->status)) {
                $discharge = [
                    'dates' => $this->get_transaction_dates(Gas::class, $this->id, 'discharge', ["receive", "discharge"]),
                    'status' => $discharge_transaction->status,
                    'reason' => $this->reason($discharge_transaction, $discharge_transaction->reason_id)
                ];
            }

        }

        //FILE
        if ($this->has('file')->exists()) {
            $file_transaction = $this->file->first();

            if (isset($file_transaction->status)) {
                $file = [
                    'dates' => $this->get_transaction_dates(File::class, $this->id, 'file', ["transfer", "receive", "file"]),
                    'status' => $file_transaction->status,
                    'reason' => $this->reason($file_transaction, $file_transaction->reason_id),
                    'box_no' => $this->box_no
                ];
            }
        }

        $transaction_result = [
            'voucher' => $voucher,
            'inspect' => $inspect,
            'approve' => $approve,
            'transmit' => $transmit,
            'discharge' => $discharge,
            'file' => $file,
        ];

        $result = [];
        foreach ($transaction_result as $k => $v) {
            if ($v != null) {
                $result[$k] = $v;
            }
        }
        return $result;
    }
}
