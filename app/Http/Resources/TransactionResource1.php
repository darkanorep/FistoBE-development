<?php

namespace App\Http\Resources;

use App\Models\Approver;
use App\Models\Associate;
use App\Models\Audit;
use App\Models\Executive;
use App\Models\File;
use App\Models\Gas;
use App\Models\Issue;
use App\Models\POBatch;
use App\Models\Reason;
use App\Models\Release;
use App\Models\Tagging;
use App\Models\Transaction;
use App\Models\Transmit;
use App\Models\Treasury;
use App\Models\User;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource1 extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {

        $autoDebit_group = [];
        $document = null;
        $prm_group = [];
        $po_details = [];
        $tag = null;
        $gas = null;
        $voucher = null;
        $inspect = null;
        $approve = null;
        $transmit = null;
        $cheque = null;
        $audit = null;
        $executive = null;
        $issue = null;
        $release = null;
        $discharge = null;
        $file = null;

        $requestor = [
            'id' => $this->users->id,
            'id_prefix' => $this->id_prefix,
            'id_no' => $this->id_no,
            'role' => $this->users->role,
            'position' => $this->users->position,
            'first_name' => $this->users->first_name,
            'middle_name' => $this->users->middle_name,
            'last_name' => $this->users->last_name,
            'suffix' => $this->users->suffix,
            'department' => $this->department_details,
        ];

        $transaction = [
            'id' => $this->id,
            'request_id' => $this->request_id,
            'no' => $this->transaction_id,
            'date_requested' => $this->date_requested,
            'status' => $this->status,
            'state' => $this->state
        ];

        $reason = [
            'id' => $this->reason_id ?? null,
            'description' => Reason::where('id', $this->reason_id)->first()->remarks ?? null,
            'remarks' => $this->reason_remarks ?? null,
//            'date' => $this->reason_id ? $this->updated_at : null,
        ];

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
                    $prm_group = Transaction::rental($this->transaction_id)->get();
                    break;
                case "official store leasing":
                case "unofficial store leasing":
                case "leasing":
                    $prm_group = Transaction::leasing($this->transaction_id)->get();
                    break;
                case "loans":
                    $prm_group = Transaction::loans($this->transaction_id)->get();
                    break;
            }
        }


        $sub_unit = [
            'id' => $this->sub_unit_id,
            'name' => $this->sub_unit_name,
        ];

        $bussiness_unit = [
            'id' => $this->bussiness_unit_id,
            'name' => $this->bussiness_unit_name,
        ];

        $autoDebit_group = $this->auto_debit->map(function ($autoDebit) {
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

        $condition = $this->state == "void" ? "=" : "!=";
//        $document_amount = Transaction::where("request_id", $this->request_id)
//            ->where("state", $condition, "void")
//            ->first()->document_amount;
        $payment_type = strtoupper($this->payment_type);
//        $user = User::where("id", $this->users_id)
//            ->get()
//            ->first();
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
            }
            // $po_details->first()->balance = $po_details->pluck("previous_balance")->sum() - $this->referrence_amount;
            switch ($this->document_id) {
                case 1:
                    $po_details->first()->balance = $po_details->pluck("previous_balance")->sum() - $this->document_amount;
                    break;

                default:
                    $po_details->first()->balance = $po_details->pluck("previous_balance")->sum() - $this->referrence_amount;
                    break;
            }
        }

        //TAG
//        if ($this->tag()->count() > 0) {
        if ($this->withCount('tag')) {
            $tag_transaction = $this->tag->first();

            if (isset($tag_transaction->status)) {
                $tag = [
                    'status' => $tag_transaction->status,
                    'receipt_type' => $this->receipt_type,
                    'no' => $this->tag_no,
                    'dates' => $this->get_transaction_dates(Tagging::class, $this->id, 'tag', ["transfer", "receive", "tag"]),
                    'distributed_to' => [
                        'id' => $tag_transaction->distributed_id,
                        'name' => $tag_transaction->distributed_name,
                    ],
                    'reason' => $this->reason($tag_transaction, $tag_transaction->reason_id)
                ];
            }
        }

        //GAS
        if ($this->gas1()->count() > 0) {
//            $gas = $this->test(Gas::class, $this->receiveGas, $this->gas, $this->reasonGas, $this->statusGas, 'gas', ["receive", "gas"]);
            $gas_transaction = $this->gas1->first();

            if (isset($gas_transaction->status)) {
                $gas = [
                    'status' => $gas_transaction->status,
                    'dates' => $this->get_transaction_dates(Gas::class, $this->id, 'gas', ["receive", "gas"]),
                    'reason' => $this->reason($gas_transaction, $gas_transaction->reason_id)
                ];
            }
        }

        //VOUCHER
//        if ($this->voucher()->count() > 0) {
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

        //INSPECT
        if ($this->inspect()->count() > 0) {
            $inspect_transaction = $this->inspect->first();

            if (isset($inspect_transaction->status)) {
                $inspect = [
                    'status' => $inspect_transaction->status,
                    'dates' => $this->get_transaction_dates(Audit::class, $this->id, 'inspect', ["receive", "inspect"]),
                    'reason' => $this->reason($inspect_transaction, $inspect_transaction->reason_id)
                ];
            }
        }

        //APPROVE
//        if ($this->approve()->count() > 0) {
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

        //TRANSMIT
//        if ($this->transmit()->count() > 0) {
        if ($this->withCount('transmit')) {
            $transmit_transaction = $this->transmit->first();

            if (isset($transmit_transaction->status)) {
                $transmit = [
                    'dates' => $this->get_transaction_dates(Transmit::class, $this->id, 'transmit', ["transfer", "receive", "transmit"]),
                    'status' => $transmit_transaction->status,
                ];
            }
        }

        //CHEQUE
        if ($this->cheques()->count() > 0) {
//        if ($this->withCount('cheques')) {
            $cheque_transaction = $this->cheques->first();
            $clear_transaction = $this->accountTitleClear;

            ///=== ISSUE CHEQUE/ACCOUNT TITLE ===///
            $cheque = $this->treasuryChequeTrashed();
            $issue_cheque = $this->chequeIssue;

            $merged = $cheque->merge($issue_cheque);

            $distinct = $merged->filter(function ($item) {
                return $item->deleted_at == null;
            })->values();
            ///=== END ISSUE CHEQUE/ACCOUNT TITLE === ///


            ///=== CLEAR ACCOUNT TITLE ===///
//            $test = $clear_transaction->merge($cheque_transaction->account_title);
            /// === END CLEAR ACCOUNT TITLE ===///

            if (empty($cheque_transaction->cheques)) {
                $cheques = null;
                $accounts = null;
            } else {

//                $cheque = $this->treasuryChequeTrashed()->count() === $this->chequeIssue()->count()
//                    ? $this->chequeIssue
//                        ? $this->chequeIssue
//                        : $cheque_transaction->cheques
//                    : $distinct;

                $chequeIssue = $this->chequeIssue;
                $cheque = $this->treasuryChequeTrashed()->count() === $chequeIssue->count()
                    ? ($chequeIssue ?: $cheque_transaction->cheques)
                    : $distinct;

                $cheques = $cheque->map(function ($item) {
                    return [
                        'type' => $item->entry_type,
                        'bank' => [
                            'id' => (int)$item->bank_id,
                            'name' => $item->bank_name,
                        ],
                        'no' => $item->cheque_no,
                        'date' => $item->cheque_date,
                        'amount' => $item->cheque_amount,
                        'date_cleared' => $item->date_cleared,
                    ];
                });

                $account_titles = $clear_transaction->isEmpty()
                    ? $cheque_transaction->account_title
                    : $clear_transaction;

                $accounts = $account_titles->map(function ($item) {
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

            if (isset($cheque_transaction->status)) {
                $cheque = [
                    'dates' => $this->get_transaction_dates(Treasury::class, $this->id, 'cheque', ["receive", "cheque", "release"]),
                    'status' => $cheque_transaction->status,
                    'cheques' => $cheques,
                    'accounts' => $accounts,
                    'reason' => $this->reason($cheque_transaction, $cheque_transaction->reason_id)
                ];
            }
        }

        //AUDIT CHEQUE
        if ($this->audit1()->count() > 0) {
            $audit_transaction = $this->audit1->first();

            if (isset($audit_transaction->status)) {
                $audit = [
                    'dates' => $this->get_transaction_dates(Audit::class, $this->id, 'audit', ["receive", "audit"]),
                    'status' => $audit_transaction->status,
                    'reason' => $this->reason($audit_transaction, $audit_transaction->reason_id)
                ];
            }
        }

//        if ($this->audit1()->count() > 0) {
//            $audit_transaction = collect($this->audit1()->pluck('status')->toArray());
//
//            if ($audit_transaction->contains('audit-receive')) {
//                $audit_transaction = $this->audit1->first();
//
//                if (isset($audit_transaction->status)) {
//                    $audit = [
//                        'dates' => $this->get_transaction_dates(Audit::class, $this->id, 'audit', ["receive", "audit"]),
//                        'status' => $audit_transaction->status,
//                        'reason' => $this->reason($audit_transaction, $audit_transaction->reason_id)
//                    ];
//                }
//            }
//        }

        //EXECUTIVE
        if ($this->executive1()->count() > 0) {
            $executive_transaction = $this->executive1->first();

            if (isset($executive_transaction->status)) {
                $executive = [
                    'dates' => $this->get_transaction_dates(Executive::class, $this->id, 'executive', ["receive", "executive"]),
                    'status' => $executive_transaction->status,
//                    'reason' => $this->reason($executive_transaction, $executive_transaction->reason_id)
                ];
            }
        }

        //ISSUE
        if ($this->issue()->count() > 0) {
            $issue_transaction = $this->issue->first();

            if (isset($issue_transaction->status)) {
                $issue = [
                    'dates' => $this->get_transaction_dates(Issue::class, $this->id, 'issue', ["receive", "issue"]),
                    'status' => $issue_transaction->status,
                    'reason' => $this->reason($issue_transaction, $issue_transaction->reason_id)
                ];
            }
        }

        //RELEASE
        if ($this->release()->count() > 0 ) {
            $release_transaction = $this->release->first();

            if (empty($release_transaction->distributed_id)) {
                $distributed = null;
            } else {
                $distributed =[
                    'id' => $release_transaction->distributed_id,
                    'name' => $release_transaction->distributed_name,
                ];
            }

            if (isset($release_transaction->status)) {
                $release = [
                    'dates' => $this->get_transaction_dates(Release::class, $this->id, 'release', ["receive", "release"]),
                    'status' => $release_transaction->status,
                    'distributed_to' => $distributed,
                    'reason' => $this->reason($release_transaction, $release_transaction->reason_id)
                ];
            }
        }

        //DISCHARGE
        if ($this->discharge1()->count() > 0) {
            $discharge_transaction = $this->discharge1->first();

            if (isset($discharge_transaction->status)) {
                $discharge = [
                    'dates' => $this->get_transaction_dates(Gas::class, $this->id, 'discharge', ["receive", "discharge"]),
                    'status' => $discharge_transaction->status,
                    'reason' => $this->reason($discharge_transaction, $discharge_transaction->reason_id)
                ];
            }

        }

        //FILE
        if ($this->file()->count() > 0) {
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
            'requestor' => $requestor,
            'transaction' => $transaction,
            'reason' => $reason,
            'document' => $document,
            'po_group' => $po_details,
            'prm_group' => $prm_group,
            'autoDebit_group' => $autoDebit_group,
            'tag' => $tag,
            'gas' => $gas,
            'voucher' => $voucher,
            'inspect' => $inspect,
            'approve' => $approve,
            'transmit' => $transmit,
            'cheque' => $cheque,
            'audit' => $audit,
            'executive' => $executive,
            'issue' => $issue,
            'release' => $release,
            'discharge' => $discharge,
            'file' => $file,
        ];
        $transaction_result['document']['sub_unit'] = $sub_unit;
        $transaction_result['document']['bussiness_unit'] = $bussiness_unit;

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
