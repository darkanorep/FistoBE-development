<?php

namespace App\Http\Resources;

use App\Models\Approver;
use App\Models\Associate;
use App\Models\Audit;
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

        $voucher = null;
        $inspect = null;
        $approve = null;
        $transmit = null;

        if ($this->withCount('voucher')) {
            $voucher_transaction = $this->voucher->first();

            if (empty($voucher_transaction->account_title)) {
                $voucher_account_title = [];
            } else {
                $voucher_account_title = $voucher_transaction->account_title->map(function ($item) {
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
                        'is_default' => $item->is_default,
                    ];
                });
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

            if (isset($voucher_transaction->status)) {
                $voucher = [
                    'status' => $voucher_transaction->status,
                    'no' => $this->voucher_no,
                    'dates' => $this->get_transaction_dates(Associate::class, $this->id, 'voucher', ["transfer", "receive", "voucher"]),
                    'month' => $this->voucher_month,
                    'transaction_type' => $transaction_type,
                    'input_tax' => $this->input_tax,
                    'accounts' => $voucher_account_title,
                    'approver' => $approver,
                    'reason' => $this->reason($voucher_transaction, $voucher_transaction->reason_id)
                ];
            }
        }

        if ($this->withCount('inspect')) {
            $inspect_transaction = $this->inspect->first();

            if (isset($inspect_transaction->status)) {
                $inspect = [
                    'status' => $inspect_transaction->status,
                    'dates' => $this->get_transaction_dates(Audit::class, $this->id, 'inspect', ["receive", "inspect"]),
                    'reason' => $this->reason($inspect_transaction, $inspect_transaction->reason_id)
                ];
            }
        }

        if ($this->withCount('approve')) {
            $approve_transaction = $this->approve->first();

            if (isset($approve_transaction->status)) {
                $approve = [
                    'status' => $approve_transaction->status,
                    'dates' => $this->get_transaction_dates(Approver::class, $this->id, 'approve', ["receive", "approve"]),
                    'distributed_to' => [
                        'id' => $this->distributed_id,
                        'name' => $this->distributed_name,
                    ],
                    'reason' => $this->reason($approve_transaction, $approve_transaction->reason_id)
                ];
            }
        }

        if ($this->withCount('transmit')) {
            $transmit_transaction = $this->transmit->first();

            if (isset($transmit_transaction->status)) {
                $transmit = [
                    'dates' => $this->get_transaction_dates(Transmit::class, $this->id, 'transmit', ["transfer", "receive", "transmit"]),
                    'status' => $transmit_transaction->status,
                ];
            }
        }

        $transaction_result = [
            'voucher' => $voucher,
            'inspect' => $inspect,
            'approve' => $approve,
            'transmit' => $transmit,
        ];

        $result = [];
        foreach ($transaction_result as $k => $v) {
            if ($v != null) {
                $result[$k] = $v;
            }
        }
        return $result;

    }

    function get_transaction_dates($model, $id, $process, $subprocesses)
    {
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
    function stateChange($state): string
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
            case 'executive';
                $state = 'signed';
                break;
            case 'gas':
                $state = 'gas';
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

    function reason($model, $reason_id): ?array
    {
        if (isset($reason_id)) {
            return [
                'id' => $model->reason_id,
                'reason' => Reason::where('id', $model->reason_id)->first()->reason,
                'remarks' => $model->remarks
            ];
        } else {
            return null;
        }
    }
}
