<?php

namespace App\Http\Resources;

use App\Models\Audit;
use App\Models\Cheque;
use App\Models\Executive;
use App\Models\Issue;
use App\Models\Release;
use App\Models\Transaction;
use App\Models\Treasury;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionChequeResource extends JsonResource
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
        $rental = $transactionResource->getRental();
        $cheque = null;
        $audit = null;
        $executive = null;
        $issue = null;
        $release = null;

        //CHEQUE
        if ($this->has('cheques')->exists()){
            $cheque_transaction = $this->cheques->first();
            $clear_transaction = $this->accountTitleClear;

            ///=== ISSUE CHEQUE/ACCOUNT TITLE ===///
            $trashedCheques = $this->treasuryChequeTrashed();
            $issuedCheques = $this->chequeIssue;

            $mergedCheques = $trashedCheques->merge($issuedCheques);

            $distinctCheques = $mergedCheques->filter(function ($item) {
                return $item->deleted_at == null;
            })->values();
            ///=== END ISSUE CHEQUE/ACCOUNT TITLE === ///

            if (empty($cheque_transaction->cheques)) {
                $cheques = null;
                $accounts = null;
            } else {

                $trashedChequesCount = $trashedCheques->count();
                $issuedChequesCount = $this->withCount('chequeIssue');

                $cheque = $trashedChequesCount === $issuedChequesCount && !$issuedCheques->isEmpty()
                    ? $issuedCheques
                    : $distinctCheques;

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

                $chequeHistory = null;

                if ($this->treasuryChequeHistory()->count() > 0) {
                    $chequeHistoryMapper = function ($item) {
                        return [
                            'bank_name' => $item->bank_name,
                            'cheque_no' => $item->cheque_no,
                            'cheque_amount' => $item->cheque_amount,
                            'reason_id' => $item->reason_id,
                            'reason' => $item->reason,
                        ];
                    };

                    [$valid, $void] = $this->treasuryChequeHistory()->partition(function ($item) {
                        return $item->reason_id == null;
                    });

                    $chequeHistory = [
                        'valid' => $valid->map($chequeHistoryMapper)->values(),
                        'invalid' => $void->map($chequeHistoryMapper)->values(),
                    ];
                }

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

                $related = $this->cheque->map(function ($item) {
                    return [
                        'bank_name' => $item->bank_name,
                        'cheque_no' => $item->cheque_no,
                        'cheque_amount' => $item->cheque_amount,
                    ];
                })->unique()->first();

                if (empty($related)) {
                    $relatedVouchers = null;
                } else {

                    $relatedVouchers = Cheque::where('cheque_no', $related['cheque_no'])
                        ->where('cheque_amount', $related['cheque_amount'])
                        ->where('bank_name', $related['bank_name'])
                        ->get()
                        ->pluck('transaction_id');

                    $relatedVouchers = Transaction::whereIn('id', $relatedVouchers)->get()->map(function ($item) use ($rental) {
                        $voucher_account_title = collect($item->voucher->first()->account_title->map(function ($item) {
                            return [
                                'account_title' => $item->account_title_name,
                                'amount' => $item->amount,
                            ];
                        }));

                        return [
                            'id' => $item->id,
                            'document_amount' => ($item->document_id == 3)
                                ? ($item->category == in_array($item->category, $rental) ? $item->gross_amount : floatval((number_format(($item->principal + $item->interest), 2, '.', ''))))
                                : $item->document_amount,
                            'voucher_no' => $item->voucher_no,
                            'input_tax' => $item->input_tax ?? 0,
                            'voucher_account_title' => $voucher_account_title->filter(function ($item) {
                                return $item['account_title'] == 'Accounts Payable' || $item['account_title'] == 'Accounts Payable - RHL';
                            })->values(),
                        ];
                    });

                    $relatedVouchers = $relatedVouchers->filter(function ($item) {
                        return $item['id'] != $this->id;
                    })->values();
                }
            }

            if (isset($cheque_transaction->status)) {
                $cheque = [
                    'dates' => $transactionResource->get_transaction_dates(Treasury::class, $this->id, 'cheque', ["receive", "cheque", "release"]),
                    'status' => $cheque_transaction->status,
                    'cheques' => $cheques,
                    'accounts' => $accounts,
                    'cheque_history' => $chequeHistory,
                    'vouchers' => $relatedVouchers,
                    'reason' => $transactionResource->reason($cheque_transaction, $cheque_transaction->reason_id),
                ];
            }
        }

        //AUDIT
        if ($this->has('audit')->exists()) {
            $audit_transaction = $this->audit->first();

            if (isset($audit_transaction->status)) {
                $audit = [
                    'dates' => $transactionResource->get_transaction_dates(Audit::class, $this->id, 'audit', ["receive", "audit"]),
                    'status' => $audit_transaction->status,
                    'reason' => $transactionResource->reason($audit_transaction, $audit_transaction->reason_id)
                ];
            }
        }

        //EXECUTIVE
        if ($this->has('executive')->exists()) {
            $executive_transaction = $this->executive->first();

            if (isset($executive_transaction->status)) {
                $executive = [
                    'dates' => $transactionResource->get_transaction_dates(Executive::class, $this->id, 'executive', ["receive", "executive"]),
                    'status' => $executive_transaction->status,
                ];
            }
        }

        //ISSUE
        if ($this->has('issue')) {
            $issue_transaction = $this->issue->first();

            if (isset($issue_transaction->status)) {
                $issue = [
                    'dates' => $transactionResource->get_transaction_dates(Issue::class, $this->id, 'issue', ["receive", "issue"]),
                    'status' => $issue_transaction->status,
                    'reason' => $transactionResource->reason($issue_transaction, $issue_transaction->reason_id)
                ];
            }
        }

        //RELEASE
        if ($this->has('release') ) {
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
                    'dates' => $transactionResource->get_transaction_dates(Release::class, $this->id, 'release', ["receive", "release"]),
                    'status' => $release_transaction->status,
                    'distributed_to' => $distributed,
                    'reason' => $transactionResource->reason($release_transaction, $release_transaction->reason_id)
                ];
            }
        }

        $transaction_result = [
            'cheque' => $cheque,
            'audit' => $audit,
            'executive' => $executive,
            'issue' => $issue,
            'release' => $release,
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
