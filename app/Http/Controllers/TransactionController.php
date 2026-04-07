<?php

namespace App\Http\Controllers;

use App\Exports\AccountServicesExport;
use App\Http\Requests\TransactionRequest;
use App\Http\Resources\APReportResource;
use App\Http\Resources\ChequeClearIndex;
use App\Http\Resources\ChequeIndex;
use App\Http\Resources\TransactionChequeResource;
use App\Http\Resources\TransactionResource1;
use App\Http\Resources\TransactionVoucherResource;
use App\Jobs\AccountServicesJob;
use App\Models\Accruals;
use App\Models\Audit;
use App\Models\BankSeries;
use App\Models\Cheque;
use App\Models\Clear;
use App\Models\ClearingAccountTitle;
use App\Models\Executive;
use App\Models\GeneralJournal;
use App\Models\Issue;
use App\Models\Permission;
use App\Models\POBalance;
use App\Models\PurchaseOrders;
use App\Models\Release;
use App\Models\Supplier;
use App\Models\Treasury;
use App\Models\User;
use App\Models\VoucherAccountTitle;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\PODetailsRequest;
use App\Methods\PADValidationMethod;
use App\Methods\GenericMethod;
use App\Methods\CounterReceiptMethod;
use App\Models\Transaction;
use App\Models\POBatch;
use App\Models\Tagging;
use App\Models\RequestorLogs;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\TransactionResource;
use App\Http\Resources\TransactionIndex;
use App\Http\Resources\RequestLog;
use App\Exceptions\FistoException;
use App\Exports\ChequeExport;
use App\Exports\TransactionExport;
use Carbon\Carbon;

use App\Http\Requests\TransactionPostRequest;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use mysql_xdevapi\Collection;

class TransactionController extends Controller
{

    public function showUserDepartment()
    {
        $departments = Auth::user()->department;
        if (count($departments)) {
            return $this->resultResponse("fetch", "Departments", ["departments" => $departments]);
        }
        return $this->resultResponse("not-found", "Transaction", []);
    }
    public function index(Request $request)
    {
        $status = $request->input('state', 'request');
        $rows = (int)$request->input('rows', 10);
        $suppliers = $this->getRequestData($request, 'suppliers');
        $document_ids = $this->getRequestData($request, 'document_ids');
        $companies = $this->getRequestData($request, 'companies');
        $business_units = $this->getRequestData($request, 'business_units');
        $transaction_from = $this->getTransactionDate($request, 'transaction_from', Carbon::now()->startOfMonth()->format('Y-m-d H:i:s'));
        $transaction_to = $this->getTransactionDate($request, 'transaction_to', Carbon::now()->endOfMonth()->format('Y-m-d H:i:s'));
        $cheque_from = $this->getTransactionDate($request, 'cheque_from', Carbon::now()->startOfMonth()->format('Y-m-d H:i:s'));
        $cheque_to = $this->getTransactionDate($request, 'cheque_to', Carbon::now()->endOfMonth()->format('Y-m-d H:i:s'));
        $search = $request->input('search');
//        $department = $request->input('department', [auth()->user()->department[0]['name']]);
        $user_id = null;
//        $my_request = $request->input('my_request', 0);
        $is_confidential = $request->input('is_confidential', 0);
        $is_mc = $request->input('is_mc', 0);
        $is_mcl = $request->input('is_mcl', 1);
        $voucher_numbers = $this->getRequestData($request, 'voucher_numbers');

        $userRole = auth()->user()->role;
        $position = auth()->user()->position;
        if (in_array($userRole, ['AP Associate', 'AP Specialist', 'Approver'])) {
            $user_id = auth()->user()->id;
        }

        $declaredStatus = [
            //Requesting of Documents
            'pending',
            'return-request',
            'requestor-void',

            //Tagging of Documents
            'pending-tag',
            'tag-receive',
            'return-tag',
            'hold-tag',

            //Transmittal of Official Receipt
            'pending-gas',
            'gas-receive',

            //Transmittal of GAS Documents
            'pending-extract',

            //Creation of Voucher
            'pending-voucher',
            'return-voucher',
            'hold-voucher',

            //Transaction Approval
            'pending-approve',
            'approve-receive',
            'approve-approve',
            'approve-hold',
            'approve-return',

            //Transmittal of Voucher
            'pending-transmit',
            'transmit-receive',
            'transmit-transmit',

            //Auditing of Voucher
            'pending-inspect',

            //Filing of Official Receipt
            'pending-discharge',

            //Transmittal for Filing of Voucher
            'pending-pass',

            //Filing of Voucher
            'pending-file',

            //Application of Loan
            'pending-mcloan',
            'loan-loan'
        ];

        $transactions = Transaction::with([
            "users:id,first_name,middle_name,last_name,department,position",
            "supplier.supplier_type:id,type as name,transaction_days",
            "po_details:id,request_id,po_no,po_total_amount",
            "company_info:id,code",
            "department_info:id,code",
            "location_info:id,code",
            "treasuryCheque",
            "account_titles",
            "voucher",
//            "purchaseOrders"
//            "purchaseOrders.receivedReceipts"
        ])
            //Requesting of Documents
//
            //Confidential
            ->when($is_confidential == null, function ($query) {
                $query->whereIn('is_confidential', [1, 0]);
            })->when($is_confidential == 1, function ($query) {
                $query->where('is_confidential', 1);
            })->when($is_confidential == 0, function ($query) {
                $query->where('is_confidential', 0);
            })

            //Managers Cheque
            ->when($is_mc == null, function ($query) {
                $query->whereIn('is_mc', [1, 0]);
            })->when($is_mc == 1, function ($query) {
                $query->where('is_mc', 1);
            })->when($is_mc == 0, function ($query) {
                $query->whereIn('is_mc', [1, 0]);
            })
            ->when(!empty($document_ids), function ($query) use ($document_ids) {
                $query->whereIn("document_id", $document_ids);
            })
            ->when(!empty($suppliers), function ($query) use ($suppliers) {
                $query->whereIn("supplier_id", $suppliers);
            })
            ->when(!empty($business_units), function ($query) use ($business_units) {
                $query->whereIn("business_unit_id", $business_units);
            })
//            ->when(!empty($voucher_numbers), function ($query) use ($voucher_numbers) {
//                $query->whereIn('id', $voucher_numbers);
//            })
//            ->when(
//                isset($request["cheque_from"]) || isset($request["cheque_to"]),
//                function ($query) use ($cheque_from, $cheque_to) {
//                    $query->whereHas("cheques.cheques", function ($query) use ($cheque_from, $cheque_to) {
//                        $query->where("cheque_date", ">=", $cheque_from)->where("cheque_date", "<=", $cheque_to);
//                    });
//                },
//                function ($query) use ($document_ids, $suppliers, $transaction_from, $transaction_to) {
//                    $query->when(!empty($document_ids) || !empty($suppliers), function ($query) use (
//                        $transaction_from,
//                        $transaction_to
//                    ) {
//                        $query->where("date_requested", ">=", $transaction_from)->where("date_requested", "<=", $transaction_to);
//                    });
//                }
//            )
            ->when($status == 'pending', function ($query) use ($is_mcl) {
                $query->whereNotIn('status', ['requestor-void', 'tag-return'])
//                    ->whereIn('department_details', $department)
                    ->where('users_id', auth()->user()->id)
                    ->withTrashed(function ($query) {
                        $query->where('deleted_at', '=', '2024-08-28 00:00:00');
                    });
//                    ->when($is_mcl == 1, function ($query) {
//                        $query->orWhere([
//                            'is_mcl' => 1
//                        ]);
//                    }, function ($query) {
//                        $query->where('is_mc', 1)->where('is_mcl', '!=', 1);
//                    })

            })
            ->when($status == 'return-request', function ($query) {
                $query->where('status', 'tag-return')
                    ->where('users_id', auth()->user()->id);
//                    ->whereIn('department_details', $department);
            })
            ->when($status == 'requestor-void', function ($query) {
                $query->where('state', 'void')
                    ->where('users_id', auth()->user()->id);
//                    ->whereIn('department_details', $department);
            })

            //Tagging of Documents
            ->when($status == 'pending-tag', function ($query) {
                $query->where('status', ['pending']);
            })
            ->when($status == 'tag-receive', function ($query) {
                $query->whereIn('status', ['tag-receive', 'tag-unhold', 'tag-unreturn']);
            })
            ->when($status == 'return-tag', function ($query) {
                $query->whereIn('status', ['voucher-return', 'gas-return']);
            })
            ->when($status == 'hold-tag', function ($query) {
                $query->whereIn('status', ['voucher-hold', 'gas-hold']);
            })

            //Transmittal of Official Receipt
            ->when($status == 'pending-gas', function ($query) {
//                $query->where('status', 'tag-tag')
//                    ->where('receipt_type', 'official');
                $query->where([
                    'status' => 'tag-tag',
                    'receipt_type' => 'official'
                ]);
            })
            ->when($status == 'gas-receive', function ($query) {
                $query->whereIn('status', ['gas-receive', 'gas-unhold', 'gas-unreturn']);
            })

            //Transmittal of GAS Documents
            ->when($status == 'pending-extract', function ($query) {
                $query->where('status', 'gas-gas');
            })

            //Creation of Voucher
            ->when($status == 'pending-voucher', function ($query) use ($user_id, $is_confidential) {
                $query->where('distributed_id', $user_id, $is_confidential)
                    ->when($is_confidential == 1, function ($query) {
                        $query->where('is_confidential', 1)
                            ->whereIn("status", ['tag-tag', 'tag-unhold', 'tag-unreturn']);
                    }, function ($query) {
                        $query->where(function ($query) {
                            $query->whereIn("status", ["tag-tag", "voucher-transfer"])
                                ->where("receipt_type", "unofficial")
                                ->orWhere(function ($query) {
                                    $query->where('status', 'extract-extract');
                                });
                        });
                    });
            })
            ->when($status == 'return-voucher', function ($query) use ($user_id) {
                $query->where('distributed_id', $user_id)
                    ->whereIn("status", [
                        "cheque-return",
                        "approve-return",
                        "inspect-return",
                        "issue-return",
                        "debit-return",
                    ]);
            })
            ->when($status == 'hold-voucher', function ($query) use ($user_id) {
                $query->where('distributed_id', $user_id)
                    ->whereIn("status", [
                        "cheque-hold",
                        "approve-hold",
                        "inspect-hold",
                        "issue-hold",
                        "debit-hold",
                    ]);
            })

            //Transaction Approval
            ->when($status == 'pending-approve', function ($query) use ($user_id, $position, $userRole) {
                $query->where(function ($query) use ($user_id, $position, $userRole) {
                    $query->where([
                        'approver_id' => $user_id,
                        'status' => 'voucher-voucher'
                    ])
                        ->orWhere(function ($query) use ($position, $userRole) {
                            if ($position == 'SUPERVISOR' && $userRole == 'Approver') {
                                $query->where('status', 'voucher-voucher')
                                    ->whereIn('approver_id', User::where('role', 'AP Specialist')->pluck('id')->toArray());
                            }
                        });
                });
            })
            ->when($status == 'approve-receive', function ($query) use ($user_id, $position, $userRole) {
//                $query->where('approver_id', $user_id)
//                    ->whereIn('status', ['approve-receive']);
                $query->where(function ($query) use ($user_id) {
                    $query->where('approver_id', $user_id)
                        ->whereIn('status', ['approve-receive']);
                })
                    ->orWhere(function ($query) use ($position, $userRole) {
                        if ($position == 'SUPERVISOR' && $userRole == 'Approver') {
                            $query->where('status', 'approve-receive')
                                ->whereIn('approver_id', User::where('role', 'AP Specialist')->pluck('id')->toArray());
                        }
                    });
            })
            ->when($status == 'approve-approve', function ($query) use ($user_id, $position, $userRole) {
//                $query->where([
//                    'approver_id' => $user_id,
//                    'status' => 'approve-approve'
//                ]);
                $query->where(function ($query) use ($user_id, $position, $userRole) {
                    $query->where([
                        'approver_id' => $user_id,
                        'status' => 'approve-approve'
                    ]);
                })
                    ->orWhere(function ($query) use ($position, $userRole) {
                        if ($position == 'SUPERVISOR' && $userRole == 'Approver') {
                            $query->where('status', 'voucher-voucher')
                                ->whereIn('approver_id', User::where('role', 'AP Specialist')->pluck('id')->toArray());
                        }
                    });

            })
            ->when($status == 'approve-hold', function ($query) use ($user_id) {
//                $query->where('approver_id', $user_id)
//                    ->where('status', 'approve-hold');
                $query->where([
                    'approver_id' => $user_id,
                    'status' => 'approve-hold'
                ]);

            })
            ->when($status == 'approve-return', function ($query) use ($user_id) {
//                $query->where('approver_id', $user_id)
//                    ->where('status', 'approve-return');
                $query->where([
                    'approver_id' => $user_id,
                    'status' => 'approve-return'
                ]);
            })

            //Transmittal of Voucher
            ->when($status == 'pending-transmit', function ($query) use ($user_id) {
                $query->where('distributed_id', $user_id)
                    ->whereIn('status', ['approve-approve', 'transmit-transfer'])
                    ->whereNull('is_for_releasing')
                    ->whereIn('is_mc', [0,1]);
            })
            ->when($status == 'transmit-receive', function ($query) use ($user_id) {
                $query->where('distributed_id', $user_id)
                    ->whereIn('status', ['transmit-receive']);
            })
            ->when($status == 'transmit-transmit', function ($query) use ($user_id) {
//                $query->where('distributed_id', $user_id)
//                    ->where('status', 'transmit-transmit');
                $query->where([
                    'distributed_id' => $user_id,
                    'status' => 'transmit-transmit'
                ]);
            })

            //Auditing of Voucher
            ->when($status == 'pending-inspect', function ($query) {
                $query->whereIn('status', ['transmit-transmit'])
                    ->where('is_for_voucher_audit', true);
            })

            //Transmittal for Filing of Voucher
            ->when($status == 'pending-pass', function ($query) {
                $query->whereIn("status", ["release-release"]);
            })

            //Filing of Official Receipt (GAS)
            ->when($status == 'pending-discharge', function ($query) {
//                $query->whereIn("status", ["release-release"])->where("receipt_type", "official");
                $query->whereIn("status", ["pass-pass"])->where("receipt_type", "official");
            })

            //Filing of Voucher
            ->when($status == 'pending-file', function ($query) use ($user_id) {
                $query->where("distributed_id", $user_id)
                    ->where(function ($query) {
                        $query->whereIn("status", ["pass-pass", "discharge-discharge"])
                            ->whereIn("is_mc", [1, 0])
                            ->orWhere(function ($query) {
                                $query->whereIn("status", ["release-release"])->whereNull('receipt_type');
                            });
                    });

//                ->whereIn("status", ["pass-pass", "discharge-discharge"])
//                    ->where(function ($query) {
//                        $query->where(function ($query) {
//                            $query->whereIn("receipt_type", ["unofficial", "official"])
//                                ->whereIn("is_mc", [1, 0]);
//                        });
//                    })
//                    ->orWhere(function ($query) {
//                        $query->whereIn("status", ["release-release"])->whereNull('receipt_type');
//                    });
            })

            //Application for Loan
            ->when($status == 'pending-mcloan', function ($query) {
                $query->whereIn("status", ["approve-approve"])
                    ->where([
                        'is_mc' => 0,
                        'is_confidential' => 0
                    ]);
            })
            ->when($status == 'loan-loan', function ($query) {
                $query->where([
                    'is_mcl' => 1
                ]);
            })
            ->when(!in_array($status, $declaredStatus), function ($query) use ($status, $user_id, $userRole) {
                $query->where('status', preg_replace('/\s+/', '', $status))
                    ->when(isset($user_id), function ($query) use ($user_id, $userRole) {
                        $query->where(function ($query) use ($user_id, $userRole) {
                            if (in_array($userRole, ['AP Associate', 'AP Specialist'])) {
                                $query->where('distributed_id', $user_id);
                            } else if ($userRole == 'Approver') {
                                $query->where('approver_id', $user_id)
                                    ->orWhere('distributed_id', $user_id);
                            }
                        });
                    });
            })
            ->select([
                "id",
                "users_id",
                "request_id",
                "supplier_id",
                "document_id",
                "tag_no",
                "transaction_id",
                "document_type",
                "payment_type",
                "remarks",
                "date_requested",
                "document_date",
                "company_id",
                "company",
                "business_unit_id",
                "business_unit",
                "department",
                "unit_id",
                "unit",
                "sub_unit_id",
                "sub_unit",
                "location",
                "document_no",
                "document_type",
                "document_amount",
                "referrence_no",
                "referrence_amount",
                "net_amount",
                "cheque_date",
                "receipt_type",
                "is_not_editable",
                "approver_id",
                "approver_name",
                "assigned_id",
                "charge_id",
                "status",
                "state",
                "principal",
                "interest",
                "gross_amount",
                "category",
                "department_id",
                "location_id",
                "input_tax",
                "voucher_no",
                "voucher_month",
                "distributed_id",
                "distributed_name",
                'is_confidential',
                'is_mc',
                'is_new',
                'transaction_type'
            ])
            ->whereLike([
                "date_requested",
                "voucher_no",
                "remarks",
                "tag_no",
                "transaction_id",
                "document_amount",
                "document_type",
                "payment_type",
                "company",
                "department",
                "location",
                "supplier",
                "document_no",
                "referrence_no",
                "po_total_amount",
                "referrence_total_amount",
                "po_details.po_no",
                "users.first_name",
                "users.middle_name",
                "users.last_name",
            ], $search)
            ->latest('updated_at')
            ->paginate($rows);


        $transactions = $this->transactionIndexFormatter($transactions);
        $entryEnabled = DB::table('settings')->where('key', 'entry_enabled')->first();


        if (count($transactions)) {
            return $this->resultResponse("fetch", "Transaction", $transactions);
        }
        return $this->resultResponse("not-found", "Transaction", []);

    }

    private function transactionIndexFormatter($transactions)
    {
        $transactions->transform(function ($transaction) {
            $resource = new TransactionResource1($transaction);
            $rental = $resource->getRental();
            $state = $resource->stateChange($transaction->state);
            $is_editable_prm = 0;
//            if ($transaction->document_id == 3) {
//                $is_editable_prm = Tagging::where("transaction_id", $transaction->transaction_id)
//                    ->whereNotIn("status", ["tag-return", "tag-void"])
//                    ->exists();
//            }

            $is_latest_transaction = 0;
            if ($transaction->po_details->isNotEmpty() && strtoupper($transaction->payment_type) === "PARTIAL") {
                $po_no = $transaction->po_details->last()->po_no;
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

                if ($trxns_id == $transaction->id) {
                    $is_latest_transaction = 1;
                }
            }

            $accounts = $transaction->account_titles->filter(function ($item) {
//                return $item->account_title_name == 'Accounts Payable' || $item->account_title_name == 'Accounts Payable - RHL';
                return strpos($item->account_title_name, "Accounts Payable") !== false;
            });

//            $is_cheque = $transaction->treasuryCheque()->exists() ? 1 : 0;
//            $is_cleared = $transaction->treasuryCheque->pluck('is_cleared')->isEmpty()
//                ? 0 : ($transaction->treasuryCheque->pluck('is_cleared')->contains(0 || null)
//                    ? 0
//                    : 1);

            return [
                "id" => $transaction->id,
                "tag_no" => $transaction->tag_no,
                "users_id" => $transaction->users_id,
                "is_latest_transaction" => $is_latest_transaction,
                "is_editable_prm" => $is_editable_prm,
                "request_id" => $transaction->request_id,
                "document_id" => $transaction->document_id,
                "transaction_id" => $transaction->transaction_id,
                "document_type" => $transaction->document_type,
                "payment_type" => $transaction->payment_type,
                "supplier" => $transaction->supplier,
                "remarks" => $transaction->remarks,
                "date_requested" => $transaction->date_requested,
                "company_id" => $transaction->company_info->id ?? null,
                "company_code" => $transaction->company_info->code ?? null,
                "company" => $transaction->company,
                "business_unit_id" => $transaction->business_unit_id,
                "business_unit_code" => $transaction->business_unit_info->code ?? null,
                "business_unit" => $transaction->business_unit,
                'department_id' => $transaction->department_id,
                'department_code' => $transaction->department_info->code ?? null,
                "department" => $transaction->department,
                "unit_id" => $transaction->unit_id,
                "unit_code" => $transaction->unit_info->code ?? null,
                "unit" => $transaction->unit,
                "sub_unit_id" => $transaction->sub_unit_id,
                "sub_unit_code" => $transaction->sub_unit_info->code ?? null,
                "sub_unit" => $transaction->sub_unit,
                "location_id" => $transaction->location_id,
                "location_code" => $transaction->location_info->code ?? null,
                "location" => $transaction->location,
                "document_no" => $transaction->document_no,
                "document_amount" => ($transaction->document_id == 3)
                    ? ($transaction->category == in_array($transaction->category, $rental) ? $transaction->gross_amount : (($transaction->principal + $transaction->interest)))
                    : $transaction->document_amount ?? $transaction->referrence_amount,
                "cheque_date" => $transaction->document_id == 3 ? $transaction->cheque_date : null,
                "period_covered" => $transaction->document_id == 3 ? $transaction->period_covered : null,
                "referrence_no" => $transaction->referrence_no,
                "referrence_amount" => $transaction->referrence_amount,
                "status" => $state,
                "state" => $transaction->status == 'cheque-cheque' ? 'cheque-create' : $transaction->status,
                "users" => $transaction->users,
                "po_details" => in_array($transaction->document_id, [1, 2, 4, 5])
                    ? $transaction->po_details->map(function ($po) {
                        return [
                            "id" => $po->id,
                            "request_id" => $po->request_id,
                            "po_no" => $po->po_no,
                            "po_total_amount" => $po->po_total_amount
                        ];
                    })
                    : [],
//                "purchased_orders" => $transaction->purchaseOrders->map(function ($po) {
//                    return [
//                        "po_number" => $po->po_number,
//                        'po_description' => $po->po_description,
////                        'received_receipts' => $po->receivedReceipts->map(function ($rr) {
////                            return [
////                                'rr_no' => $rr->rr_number,
////                            ];
////                        })
//                    ];
//                }) ?? [],
                'receipt_type' => $transaction->receipt_type,
                'input_tax' => $transaction->input_tax,
                'cheques' => $transaction->treasuryCheque->map(function ($item) {
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
                    'no' => $transaction->voucher_no,
                    'month' => $transaction->voucher_month,
                    'accounts' => $transaction->account_titles->map(function ($item) {
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
                    }),
                    'distributed' => [
                        'id' => $transaction->distributed_id,
                        'name' => $transaction->distributed_name,
                    ],
                    'approved' => [
                        'id' => $transaction->approver_id,
                        'name' => $transaction->approver_name,
                    ],
                    'dates' => [
                        'vouchered' => $this->getDateEveryStatus($transaction->voucher, 'voucher-voucher')
                    ],
                ],
//                'is_cheque' => $is_cheque,
                'is_confidential' => $transaction->is_confidential,
                'is_mc' => $transaction->is_mc,
                "is_new" => $transaction->is_new ? 1 : 0,
                'document_date' => $transaction->document_date ?? $transaction->date_requested,
                'assigned_id' => $transaction->assigned_id,
                'charge_id' => $transaction->charge_id,
                'transaction_type' => $transaction->transaction_type,
            ];

        });

        return $transactions;
    }

    public function show($id)
    {
        $transaction = Transaction::withTrashed()
            ->with([
                "supplier",
                "supplier.supplier_type:id,type as name,transaction_days",
                "receivedReceipts.purchaseOrders",
                "receivedReceipts.jobOrders"
            ])
            ->where('id', $id)
            ->select(
                "id",
                "users_id",
                "id_prefix",
                "id_no",
                "first_name",
                "middle_name",
                "last_name",
                "department_details",
                "receipt_type",
                "tag_no",
                "voucher_no",
                "voucher_month",
                "transaction_id",
                "request_id",
                "date_requested",
                "capex_no",
                "document_id",
                "document_type",
                "document_no",
                "document_type",
                "document_amount",
                "document_date",
                "payment_type",
                "category_id",
                "category",
                "company_id",
                "company",
                "department_id",
                "department",
                "location_id",
                "location",
                "supplier_id",
//               "supplier",
                "po_total_amount",
                "balance_po_ref_amount",
                "referrence_id",
                "referrence_type",
                "referrence_no",
                "referrence_amount",
                "pcf_name",
                "pcf_date",
                "pcf_letter",
                "utilities_from",
                "utilities_to",
                "utilities_category_id",
                "utilities_category",
                "utilities_account_no_id",
                "utilities_account_no",
                "utilities_consumption",
                "utilities_location_id",
                "utilities_location",
                "utilities_receipt_no",
                "payroll_from",
                "payroll_to",
                "payroll_client",
                "payroll_category_id",
                "payroll_category",
                "payroll_type",
                "payroll_control_no",
                "remarks",
                "state",
                "status",
                "reason_id",
                "reason",
                "reason_remarks",
                "distributed_id",
                "distributed_name",
                "approver_id",
                "approver_name",
                "total_gross",
                "total_cwt",
                "total_net",
                "period_covered",
                "prm_multiple_from",
                "prm_multiple_to",
                "cheque_date",
                "gross_amount",
                "witholding_tax",
                "net_amount",
                "release_date",
                "batch_no",
                "amortization",
                "interest",
                "cwt",
                "dst",
                "principal",
                "transaction_type",
                "is_allowable",
                "business_unit_id",
                "business_unit",
                "unit_id",
                "unit",
                "sub_unit_id",
                "sub_unit",
                "input_tax",
                "box_no",
                "transaction_type",
                "assigned_id",
                "charge_id"
            )
            ->first();

        $type = $transaction->transaction_type;
        $rental = [
            'stall a rental',
            'stall b rental',
            'stall c rental',
            'stall d rental',
            'cusa rental',
            'dorm rental',
            'additional rental',
            'lounge rental',
            'corporate special program - education',
            'official store rental',
            'unofficial store rental',
            'rental'
        ];
        $company = [
            "id" => $transaction->company_id,
            "name" => $transaction->company,
        ];

        $department = [
            "id" => $transaction->department_id,
            "name" => $transaction->department,
        ];

        $location = [
            "id" => $transaction->location_id,
            "name" => $transaction->location,
        ];

        $unit = [
            "id" => $transaction->unit_id,
            "name" => $transaction->unit,
        ];

        $sub_unit = [
            'id' => $transaction->sub_unit_id,
            'name' => $transaction->sub_unit
        ];

        $business_unit = [
            'id' => $transaction->business_unit_id,
            'name' => $transaction->business_unit,
        ];

        $supplier = $transaction->supplier;

        $tag = null;
        $inspect = null;
        $extract = null;
        $gas = null;
        $voucher = null;
        $approve = null;
        $transmit = null;
        $cheque = null;
        $audit = null;
        $executive = null;
        $discharge = null;
        $issue = null;
        $pass = null;
        $file = null;
        $release = null;

        switch ($transaction->document_id) {
            case 1: //PAD
            case 2: //PRM Common
                $document = [
                    "id" => $transaction->document_id,
                    "is_confidential" => $transaction->is_confidential,
                    "is_mc" => $transaction->is_mc,
                    "is_new" => $transaction->is_new,
                    "name" => $transaction->document_type,
                    "no" => $transaction->document_no,
                    "date" => $transaction->document_date,
                    "payment_type" => $transaction->payment_type,
                    "amount" => $transaction->document_amount,
                    "remarks" => $transaction->remarks,
                    "category" => [
                        "id" => $transaction->category_id,
                        "name" => $transaction->category,
                    ]
                ];
                break;

            case 3: //PRM Multiple
                $document = [
                    "id" => $transaction->document_id,
                    "is_confidential" => $transaction->is_confidential,
                    "is_mc" => $transaction->is_mc,
                    "name" => $transaction->document_type,
                    "no" => $transaction->document_no,
//                    "date" => $this->document_date ?? $this->date_requested,
                    "payment_type" => $transaction->payment_type,
                    'amount' => ($transaction->document_id == 3)
                        ? ($transaction->category == in_array($transaction->category, [
                            'stall a rental',
                            'stall b rental',
                            'stall c rental',
                            'stall d rental',
                            'cusa rental',
                            'dorm rental',
                            'additional rental',
                            'lounge rental',
                            'corporate special program - education',
                            'official store rental',
                            'unofficial store rental',
                            'rental'
                        ]) ? $transaction->gross_amount : floatval((number_format(($transaction->principal + $transaction->interest), 2, '.', ''))))
                        : $transaction->document_amount,
                    "net_amount" => $transaction->net_amount,
                    "release_date" => $transaction->release_date,
                    "batch_no" => $transaction->batch_no,
                    "remarks" => $transaction->remarks,
                    "category" => [
                        "id" => $transaction->category_id,
                        "name" => $transaction->category,
                    ]
                ];
                switch ($transaction->category) {
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
                        $document["period_covered"] = $transaction->period_covered;
                        $document["prm_multiple_from"] = $transaction->prm_multiple_from;
                        $document["prm_multiple_to"] = $transaction->prm_multiple_to;
                        $document["gross_amount"] = $transaction->gross_amount;
                        $document["witholding_tax"] = $transaction->witholding_tax;
                        $document["net_of_amount"] = $transaction->net_amount;
                        $document["cheque_date"] = $transaction->cheque_date;
                        $prm_group = Transaction::rental($transaction->transaction_id)->get();

                        break;
                    case "official store leasing":
                    case "unofficial store leasing":
                    case "leasing":
                        $document["amortization"] = $transaction->amortization;
                        $document["principal"] = $transaction->principal;
                        $document["interest"] = $transaction->interest;
                        $document["cwt"] = $transaction->cwt;
                        $document["net_of_amount"] = $transaction->net_amount;
                        $document["cheque_date"] = $transaction->cheque_date;
                        $prm_group = Transaction::leasing($transaction->transaction_id)->get();
                        break;

                    case "loans":
                        $document["principal"] = $transaction->principal;
                        $document["interest"] = $transaction->interest;
                        $document["cwt"] = $transaction->cwt;
                        $document["net_of_amount"] = $transaction->net_amount;
                        $document["cheque_date"] = $transaction->cheque_date;
                        $prm_group = Transaction::loans($transaction->transaction_id)->get();
                        break;
                }

                break;

            case 5: //Contractor's Billing
                $document = [
                    "id" => $transaction->document_id,
                    "is_confidential" => $transaction->is_confidential,
                    "is_mc" => $transaction->is_mc,
                    "name" => $transaction->document_type,
                    "no" => $transaction->document_no,
                    "capex_no" => $transaction->capex_no,
                    "date" => $transaction->document_date,
                    "payment_type" => $transaction->payment_type,
                    "amount" => $transaction->document_amount,
                    "remarks" => $transaction->remarks,
                    "category" => [
                        "id" => $transaction->category_id,
                        "name" => $transaction->category,
                    ]
                ];
                break;

            case 6: //Utilities
                $document = [
                    "id" => $transaction->document_id,
//                    "date" => $this->document_date ?? $this->date_requested,
                    "is_confidential" => $transaction->is_confidential,
                    "is_mc" => $transaction->is_mc,
                    "name" => $transaction->document_type,
                    "payment_type" => $transaction->payment_type,
                    "amount" => $transaction->document_amount,
                    "from" => $transaction->utilities_from,
                    "to" => $transaction->utilities_to,
                    "remarks" => $transaction->remarks,
                    "utility" => [
                        "receipt_no" => $transaction->utilities_receipt_no,
                        "consumption" => $transaction->utilities_consumption,
                        "location" => [
                            "id" => $transaction->utilities_location_id,
                            "name" => $transaction->utilities_location,
                        ],
                        "category" => [
                            "id" => $transaction->utilities_category_id,
                            "name" => $transaction->utilities_category,
                        ],
                        "account_no" => [
                            "id" => $transaction->utilities_account_no_id,
                            "no" => $transaction->utilities_account_no,
                        ],
                    ],
                ];
                break;

            case 8: //PCF
                $document = [
                    "id" => $transaction->document_id,
                    "is_confidential" => $transaction->is_confidential,
                    "is_mc" => $transaction->is_mc,
                    "name" => $transaction->document_type,
                    "date" => $transaction->document_date,
                    "amount" => $transaction->document_amount,
                    "payment_type" => $transaction->payment_type,
                    "remarks" => $transaction->remarks,
                    "pcf_batch" => [
                        "name" => $transaction->pcf_name,
                        "letter" => $transaction->pcf_letter,
                        "date" => $transaction->pcf_date,
                    ],
                ];

                break;

            case 7: //Payroll
                $document = [
                    "id" => $transaction->document_id,
//                    "date" => $this->document_date ?? $this->date_requested,
                    "is_confidential" => $transaction->is_confidential,
                    "is_mc" => $transaction->is_mc,
                    "name" => $transaction->document_type,
                    "payment_type" => $transaction->payment_type,
                    "amount" => $transaction->document_amount,
                    "from" => $transaction->payroll_from,
                    "to" => $transaction->payroll_to,
                    "remarks" => $transaction->remarks,
                    "payroll" => [
                        "type" => $transaction->payroll_type,
                        "clients" => $transaction->payroll_client,
                        "category" => [
                            "id" => $transaction->payroll_category_id,
                            "name" => $transaction->payroll_category,
                        ],
                        "control_no" => $transaction->payroll_control_no,
                    ],
                ];
                break;

            case 4: //Receipt
                $document = [
                    "id" => $transaction->document_id,
                    "is_confidential" => $transaction->is_confidential,
                    "is_mc" => $transaction->is_mc,
                    "is_new" => $transaction->is_new,
                    "name" => $transaction->document_type,
                    "date" => $transaction->document_date,
                    "payment_type" => $transaction->payment_type,
                    "remarks" => $transaction->remarks,
                    "category" => [
                        "id" => $transaction->category_id,
                        "name" => $transaction->category,
                    ],
                    "reference" => [
                        "id" => $transaction->referrence_id,
                        "type" => $transaction->referrence_type,
                        "no" => $transaction->referrence_no,
                        "amount" => $transaction->referrence_amount,
                        "allowable" => $transaction->is_allowable,
                    ]
                ];
                break;

            case 9: //Auto Debit
                $document = [
                    "id" => $transaction->document_id,
                    "is_confidential" => $transaction->is_confidential,
                    "is_mc" => $transaction->is_mc,
                    "name" => $transaction->document_type,
                    "date" => $transaction->document_date,
                    "payment_type" => $transaction->payment_type,
                    "amount" => $transaction->document_amount,
                    "remarks" => $transaction->remarks,
                    "category" => [
                        "id" => $transaction->category_id,
                        "name" => $transaction->category,
                    ]
                ];
                break;
        }

        $requestor = [
            'id' => $transaction->users_id,
            'id_prefix' => $transaction->id_prefix,
            'id_no' => $transaction->id_no,
            'role' => $transaction->users->role,
            'position' => $transaction->users->position,
            'first_name' => $transaction->users->first_name,
            'middle_name' => $transaction->users->middle_name,
            'last_name' => $transaction->users->last_name,
            'suffix' => $transaction->users->suffix,
            'department' => $transaction->users->department[0]['name'],
        ];

        $transact = [
            'id' => $transaction->id,
            'request_id' => $transaction->request_id,
            'no' => $transaction->transaction_id,
            'date_requested' => $transaction->date_requested,
            'status' => $transaction->status,
            'state' => $transaction->state,
            "is_latest_transaction" => "-"
        ];

        $document = array_merge($document, [
            'company' => $company,
            'business_unit' => $business_unit,
            'department' => $department,
            'unit' => $unit,
            'sub_unit' => $sub_unit,
            'location' => $location,
            'supplier' => $supplier,
        ]);

        $po_group = $transaction->po_details->map(function ($po, $index) use ($transaction) {
            return [
                'is_add' => $po->is_add,
                'is_editable' => $po->is_editable,
                'is_modifiable' => $po->is_modifiable,
                'id' => $po->id,
                'no' => $po->po_no,
                'amount' => floatVal($po->po_amount),
//                    'previous_balance' => $index === $transaction->po_details->count() - 1 ? floatval($po->po_total_amount - $totalDeduction) : 0,
                'previous_balance' => $index === $transaction->po_details->count() - 1 ? floatval($po->previous_balance) : 0,
//                    'balance' => 0,
                'balance' => $index === $transaction->po_details->count() - 1 ? floatval($po->previous_balance) : 0,
                'rr_no' => $po->rr_group,
            ];
        }) ?? [];

        $receivedReceipts = $transaction->receivedReceipts()->first();
        $receivedReceiptsCount = $transaction->receivedReceipts()->pluck('rr_id')->unique()->count();

        $purchase_order = [];
        if ($receivedReceipts && !$receivedReceipts->purchaseOrders->isEmpty()) {
            $purchase_order = $transaction->receivedReceipts->map(function ($item) use ($transaction) {
                return [
                    'is_new_po' => true,
                    'id' => $item->rr_id,
                    'rr_year_number_id' => $item->rr_number,
                    'rr_orders' => $transaction->receivedReceipts->filter(function ($rr) use ($item) {
                        return $rr->rr_id == $item->rr_id;
                    })->map(function ($rr) {
                        return [
                            'item_code' => $rr->item_code,
                            'item_name' => $rr->item_name,
                            'quantity_receive' => $rr->quantity,
                            'order' => [
                                'item_code' => $rr->item_code,
                                'item_name' => $rr->item_name,
                                'price' => $rr->price,
                                'reference_no' => $rr->reference_no,
                                'uom' => [
                                    'code' => $rr->uom_code,
                                    'name' => $rr->uom_name,
                                ],
                                'po_transaction' => $rr->purchaseOrders
                                    ->map(function ($po) {
                                    return [
                                        'purchase_order_id' => $po->id,
                                        'po_year_number_id' => $po->po_number,
                                        'po_description' => $po->po_description,
                                        'type_name' => $po->type_name,
                                        'po_amount' => $po->po_amount,
                                        'company' => [
                                            'id' => $po->company_id,
                                            'code' => $po->company_code,
                                            'name' => $po->company_name,
                                        ],
                                        'business_unit' => [
                                            'id' => $po->business_unit_id,
                                            'code' => $po->business_unit_code,
                                            'name' => $po->business_unit_name,
                                        ],
                                        'department' => [
                                            'id' => $po->department_id,
                                            'code' => $po->department_code,
                                            'name' => $po->department_name,
                                        ],
                                        'unit' => [
                                            'id' => $po->unit_id,
                                            'code' => $po->unit_code,
                                            'name' => $po->unit_name,
                                        ],
                                        'sub_unit' => [
                                            'id' => $po->sub_unit_id,
                                            'code' => $po->sub_unit_code,
                                            'name' => $po->sub_unit_name,
                                        ],
                                        'location' => [
                                            'id' => $po->location_id,
                                            'code' => $po->location_code,
                                            'name' => $po->location_name,
                                        ],
                                        'account_title' => [
                                            'id' => $po->account_title_id,
                                            'code' => $po->account_title_code,
                                            'name' => $po->account_title_name,
                                        ],
                                    ];
                                })
                            ]
                        ];
                    })->values()
                ];
            })->values();

            if ($receivedReceiptsCount == 1) {
                $purchase_order = $purchase_order->unique()->values();
            }
        }


        $job_order = $receivedReceiptsCount != 1
            ? ($receivedReceipts && !$receivedReceipts->jobOrders->isEmpty()
                ? $transaction->receivedReceipts->map(function ($item) use ($transaction) {
                    return [
                        'is_new_po' => true,
                        'id' => $item->rr_id,
                        'rr_year_number_id' => $item->rr_number,
                        'rr_orders' => $transaction->receivedReceipts->filter(function ($rr) use ($item) {
                            return $rr->rr_id == $item->rr_id;
                        })->map(function ($rr) {
                            return [
                                'description' => $rr->item_name,
                                'quantity_receive' => $rr->quantity,
                                'order' => [
                                    'description' => $rr->item_name,
                                    'price' => $rr->price,
                                    'reference_no' => $rr->reference_no,
                                    'uom' => [
                                        'code' => $rr->uom_code,
                                        'name' => $rr->uom_name,
                                    ],
                                ],
                                'jo_transaction' => $rr->jobOrders->map(function ($jo) {
                                    return [
                                        'job_order_id' => $jo->id,
                                        'jo_year_number_id' => $jo->jo_number,
                                        'jo_description' => $jo->jo_description,
                                        'type_name' => $jo->type_name,
                                        'jo_amount' => $jo->jo_amount,
                                        'company' => [
                                            'id' => $jo->company_id,
                                            'code' => $jo->company_code,
                                            'name' => $jo->company_name,
                                        ],
                                        'business_unit' => [
                                            'id' => $jo->business_unit_id,
                                            'code' => $jo->business_unit_code,
                                            'name' => $jo->business_unit_name,
                                        ],
                                        'department' => [
                                            'id' => $jo->department_id,
                                            'code' => $jo->department_code,
                                            'name' => $jo->department_name,
                                        ],
                                        'unit' => [
                                            'id' => $jo->unit_id,
                                            'code' => $jo->unit_code,
                                            'name' => $jo->unit_name,
                                        ],
                                        'sub_unit' => [
                                            'id' => $jo->sub_unit_id,
                                            'code' => $jo->sub_unit_code,
                                            'name' => $jo->sub_unit_name,
                                        ],
                                        'location' => [
                                            'id' => $jo->location_id,
                                            'code' => $jo->location_code,
                                            'name' => $jo->location_name,
                                        ],
                                        'account_title' => [
                                            'id' => $jo->account_title_id,
                                            'code' => $jo->account_title_code,
                                            'name' => $jo->account_title_name,
                                        ],
                                    ];
                                })
                            ];
                        })->values()
                    ];
                })
                : [])
            : ($receivedReceipts && !$receivedReceipts->jobOrders->isEmpty()
                ? $transaction->receivedReceipts->map(function ($item) use ($transaction) {
                    return [
                        'is_new_po' => true,
                        'id' => $item->rr_id,
                        'rr_year_number_id' => $item->rr_number,
                        'rr_orders' => $transaction->receivedReceipts->filter(function ($rr) use ($item) {
                            return $rr->rr_id == $item->rr_id;
                        })->map(function ($rr) {
                            return [
                                'description' => $rr->item_name,
                                'quantity_receive' => $rr->quantity,
                                'order' => [
                                    'description' => $rr->item_name,
                                    'price' => $rr->price,
                                    'reference_no' => $rr->reference_no,
                                    'uom' => [
                                        'code' => $rr->uom_code,
                                        'name' => $rr->uom_name,
                                    ],
                                ],
                                'jo_transaction' => $rr->jobOrders->map(function ($jo) {
                                    return [
                                        'job_order_id' => $jo->id,
                                        'jo_year_number_id' => $jo->jo_number,
                                        'jo_description' => $jo->jo_description,
                                        'type_name' => $jo->type_name,
                                        'jo_amount' => $jo->jo_amount,
                                        'company' => [
                                            'id' => $jo->company_id,
                                            'code' => $jo->company_code,
                                            'name' => $jo->company_name,
                                        ],
                                        'business_unit' => [
                                            'id' => $jo->business_unit_id,
                                            'code' => $jo->business_unit_code,
                                            'name' => $jo->business_unit_name,
                                        ],
                                        'department' => [
                                            'id' => $jo->department_id,
                                            'code' => $jo->department_code,
                                            'name' => $jo->department_name,
                                        ],
                                        'unit' => [
                                            'id' => $jo->unit_id,
                                            'code' => $jo->unit_code,
                                            'name' => $jo->unit_name,
                                        ],
                                        'sub_unit' => [
                                            'id' => $jo->sub_unit_id,
                                            'code' => $jo->sub_unit_code,
                                            'name' => $jo->sub_unit_name,
                                        ],
                                        'location' => [
                                            'id' => $jo->location_id,
                                            'code' => $jo->location_code,
                                            'name' => $jo->location_name,
                                        ],
                                        'account_title' => [
                                            'id' => $jo->account_title_id,
                                            'code' => $jo->account_title_code,
                                            'name' => $jo->account_title_name,
                                        ],
                                    ];
                                })
                            ];
                        })->values()
                    ];
                })->unique()->values()
                : []);

        $prm_group = $prm_group ?? [];

        $autoDebit_group = $transaction->auto_debit->map(function ($autoDebit) {
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
        }) ?? [];
        $service_group = $transaction->service_batches->map(function ($service) {
            return [
                "company" => $service->company_name,
                "business_unit" => $service->business_unit_name,
                "department" => $service->department_name,
                "sub_unit" => $service->sub_unit_name,
                "location" => $service->location_name,
                "amount" => floatVal($service->amount),
            ];
        }) ?? [];

        //REASON
        $reason = [
            'id' => $transaction->reason_id,
            'description' => $transaction->reason,
            'remarks' => $transaction->reason_remarks
        ];

        //TAG
        if (isset($transaction->tag->first()->status)) {
            $tag = [
                'status' => $transaction->tag->first()->status ?? null,
                'receipt_type' => $transaction->receipt_type,
                'no' => $transaction->tag_no,
                'dates' => [
                    'transfered' => null,
                    'received' => $this->getDateEveryStatus($transaction->tag, 'tag-receive'),
                    'tagged' => $this->getDateEveryStatus($transaction->tag, 'tag-tag')
                ],
                'distributed_to' => [
                    'id' => $transaction->distributed_id,
                    'name' => $transaction->distributed_name,
                ]
            ];
        }

        //INSPECT
        if (isset($transaction->inspect->first()->status)) {
            $inspect = [
                'status' => $transaction->inspect->first()->status,
                'dates' => [
                    'received' => $this->getDateEveryStatus($transaction->inspect, 'inspect-receive'),
                    'inspected' => $this->getDateEveryStatus($transaction->inspect, 'inspect-inspect')
                ]
            ];
        }

        //EXTRACT
        if (isset($transaction->extract->first()->status)) {
            $extract = [
                'status' => $transaction->extract->first()->status ?? null,
                'dates' => [
                    'received' => $this->getDateEveryStatus($transaction->extract, 'extract-receive') ?? null,
                    'extracted' => $this->getDateEveryStatus($transaction->extract, 'extract-extract') ?? null
                ]
            ];
        }

        //GAS
        if (isset($transaction->gas->first()->status)) {
            $gas = [
                'status' => $transaction->gas->first()->status ?? null,
                'dates' => [
                    'received' => $this->getDateEveryStatus($transaction->gas, 'gas-receive'),
                    'gas' => $this->getDateEveryStatus($transaction->gas, 'gas-gas')
                ]
            ];
        }

        //VOUCHER
        if (isset($transaction->voucher->first()->status)) {
            $voucher = [
                'status' => $transaction->voucher->first()->status ?? null,
                'no' => $transaction->voucher_no,
                'month' => $transaction->voucher_month,
                'dates' => [
                    'transfered' => null,
                    'received' => $this->getDateEveryStatus($transaction->voucher, 'voucher-receive'),
                    'vouchered' => $this->getDateEveryStatus($transaction->voucher, 'voucher-voucher')
                ],
                'transaction_type' => [
                    'id' => $transaction->document_id,
                    'name' => $transaction->document_type,
                ],
                'input_tax' => $transaction->input_tax,
                'accounts' => $transaction->account_titles->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'entry' => $item->entry,
                        'purchase_order_id' => $item->purchase_order_id,
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
                        'unit' => [
                            'id' => $item->unit_id,
                            'code' => $item->unit_code,
                            'name' => $item->unit_name,
                        ],
                        'sub_unit' => [
                            'id' => $item->sub_unit_id,
                            'code' => $item->sub_unit_code,
                            'name' => $item->sub_unit_name,
                        ],
                        'is_default' => $item->is_default,
                    ];
                }),
                'approver' => [
                    'id' => $transaction->approver_id,
                    'name' => $transaction->approver_name,
                ]
            ];
        }

        //APPROVE
        if (isset($transaction->approve->first()->status)) {
            $approve = [
//                'status' => $transaction->approve->first()->status ?? null,
//                    'dates' => [
//                        'received' => $this->getDateEveryStatus($transaction->approve, 'approve-receive'),
//                        'approved' => $this->getDateEveryStatus($transaction->approve, 'approve-approve')
//                    ],
                'distributed_to' => [
                    'id' => $transaction->distributed_id,
                    'name' => $transaction->distributed_name,
                ]
            ];
        }

        //TRANSMIT
        if (isset($transaction->transmit->first()->status)) {
            $transmit = [
                'status' => $transaction->transmit->first()->status ?? null,
                'dates' => [
                    'received' => $this->getDateEveryStatus($transaction->transmit, 'transmit-receive'),
                    'transmitted' => $this->getDateEveryStatus($transaction->transmit, 'transmit-transmit')
                ]
            ];
        }

        //CHEQUE
        if (isset($transaction->cheques->first()->status)) {
            $cheque_transaction = $transaction->cheques->first();
            $clear_transaction = $transaction->accountTitleClear;

            $cheque = $transaction->treasuryChequeTrashed();
            $issue_cheque = $transaction->chequeIssue;

            $merged = $cheque->merge($issue_cheque);

            $distinct = $merged->filter(function ($item) {
                return $item->deleted_at == null;
            })->values();

            if (empty($cheque_transaction->cheques)) {
                $cheques = null;
                $accounts = null;
            } else {
                $chequeIssue = $transaction->chequeIssue;
                $cheque = $transaction->treasuryChequeTrashed()->count() === $chequeIssue->count()
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

                $chequeHistory = null;

                if ($transaction->treasuryChequeHistory()->count() > 0) {
                    $chequeHistoryMapper = function ($item) {
                        return [
                            'bank_name' => $item->bank_name,
                            'cheque_no' => $item->cheque_no,
                            'cheque_amount' => $item->cheque_amount,
                            'reason_id' => $item->reason_id,
                            'reason' => $item->reason,
                        ];
                    };

                    [$valid, $void] = $transaction->treasuryChequeHistory()->partition(function ($item) {
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

                $related = $transaction->cheque->map(function ($item) {
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

                    $relatedVouchers = $relatedVouchers->filter(function ($item) use ($transaction) {
                        return $item['id'] != $transaction->id;
                    })->values();
                }
            }

            $cheque = [
                'status' => $cheque_transaction->status,
                'dates' => [
                    'received' => $this->getDateEveryStatus($transaction->cheques, 'cheque-receive'),
                    'chequed' => $this->getDateEveryStatus($transaction->cheques, 'cheque-cheque')
                ],
                'cheques' => $cheques,
                'accounts' => $accounts,
                'cheque_history' => $chequeHistory,
                'vouchers' => $relatedVouchers,
            ];
        }

        //AUDIT
//        if (isset($transaction->audit->first()->status)) {
//            $audit = [
//                'status' => $transaction->audit->first()->status ?? null,
//                'dates' => [
//                    'received' => $this->getDateEveryStatus($transaction->audit, 'audit-receive'),
//                    'audited' => $this->getDateEveryStatus($transaction->audit, 'audit-audit')
//                ],
//                'reason' => null
//            ];
//        }

        //EXECUTIVE
//        if (isset($transaction->executive->first()->status)) {
//            $executive = [
//                'status' => $transaction->executive->first()->status ?? null,
//                    'dates' => [
//                        'received' => $this->getDateEveryStatus($transaction->executive, 'executive-receive'),
//                        'signed' => $this->getDateEveryStatus($transaction->executive, 'executive-executive')
//                    ],
//            ];
//        }

        //DISCHARGE
        if (isset($transaction->discharge->first()->status)) {
            $discharge = [
                'status' => $transaction->discharge->first()->status ?? null,
                'dates' => [
                    'received' => $this->getDateEveryStatus($transaction->discharge, 'discharge-receive'),
                    'discharged' => $this->getDateEveryStatus($transaction->discharge, 'discharge-discharge')
                ]
            ];
        }

        //FILE
//        if (isset($transaction->file->first()->status)) {
//            $file = [
//                'status' => $transaction->file->first()->status ?? null,
//                'dates' => [
//                    'received' => $this->getDateEveryStatus($transaction->file, 'file-receive'),
//                    'filed' => $this->getDateEveryStatus($transaction->file, 'file-file')
//                ],
//                'box_no' => $transaction->box_no
//            ];
//        }

        //ISSUE
//        if (isset($transaction->issue->first()->status)) {
//            $issue = [
//                'status' => $transaction->issue->first()->status ?? null,
//                'dates' => [
//                    'received' => $this->getDateEveryStatus($transaction->issue, 'issue-receive'),
//                    'issued' => $this->getDateEveryStatus($transaction->issue, 'issue-issue')
//                ]
//            ];
//        }

        //RELEASE
//        if (isset($transaction->release->first()->status)) {
//            $release = [
//                'status' => $transaction->release->first()->status ?? null,
//                'dates' => [
//                    'received' => $this->getDateEveryStatus($transaction->release, 'release-receive'),
//                    'released' => $this->getDateEveryStatus($transaction->release, 'release-release')
//                ]
//            ];
//        }

        $transaction = [
            'type' => $type,
            "assigned_id" => $transaction->assigned_id,
            'charge_id' => $transaction->charge_id,
            'requestor' => $requestor,
            'transaction' => $transact,
            'document' => $document,
            'reason' => $reason,
            'po_group' => $po_group,
            'prm_group' => $prm_group,
            'purchase_order' => $purchase_order,
            'job_order' => $job_order,
            'autoDebit_group' => $autoDebit_group,
            'service_group' => $service_group,
            'tag' => $tag,
            'inspect' => $inspect,
            'extract' => $extract,
            'gas' => $gas,
            'voucher' => $voucher,
            'approve' => $approve,
            'transmit' => $transmit,
//            'cheque' => $cheque,
//            'audit' => $audit,
//            'executive' => $executive,
            'discharge' => $discharge,
//            'file' => $file,
//            'issue' => $issue,
//            'release' => $release,
        ];

        $result = [];
        foreach ($transaction as $key => $value) {
            if ($value != null) {
                $result[$key] = $value;
            }
        }

        return $this->resultResponse("fetch", "Transaction details", $result);
    }

    public function getDateEveryStatus($transaction, $status)
    {
        return $transaction->filter(function ($item) use ($status) {
            return $item->status == $status;
        })->first()->created_at ?? null;
    }

    public function store(TransactionPostRequest $request)
    {
        $fields = $request->validated();
        $date_requested = date("Y-m-d H:i:s");
        $transaction_id = GenericMethod::getTransactionID(Auth::user()->department[0]["name"]);
        $request_id = GenericMethod::getRequestID();
        $isConfidential = $request->input("document.is_confidential", 0);
        $isMc = $request->input("document.is_mc", 0);
        $isNew = null;
        $po_total_amount = 0;

        if (!empty($fields["po_group"]) ||
            !empty($fields['purchase_order']) ||
            !empty($fields['job_order'])
        ) {
            $check_po = POBatch::whereIn("po_no", collect($fields["po_group"])->pluck("no"))
                ->select("created_at")
                ->get()
                ->pluck('created_at');

            $isAllDatesNotLessThanToday = $check_po->every(function ($date) {
                $date = \Carbon\Carbon::parse($date);
                return $date->startOfDay()->greaterThanOrEqualTo('July 13, 2024');
            });

            if ($isAllDatesNotLessThanToday) {
                $isNew = 1;
            }
        }

        switch ($fields["document"]["id"]) {
            case 1: //PAD
//                if (empty($fields["po_group"])) {
//                    $errorMessage = GenericMethod::resultLaravelFormat("po_group", ["PO group required"]);
//
//                    return $this->resultResponse("invalid", "", $errorMessage);
//                }

                switch ($request->input("document.payment_type")) {
                    case "Partial":

                        if (!$isNew) {
                            $fields["po_group"] = GenericMethod::ValidateIfPOExists(
                                $fields["po_group"],
                                $fields["document"]["company"]["id"]
                            );

                            $getAndValidatePOBalance = GenericMethod::getAndValidatePOBalance(
                                $fields,
                                $fields["document"]["company"]["id"],
                                last($fields["po_group"])["no"],
                                $fields["document"]["amount"],
                                $fields["po_group"]
                            );

                            $existTransaction = Transaction::where("company_id", $fields["document"]["company"]["id"])
                                ->exists();

                            if ($existTransaction) {
                                $currentRequestids = POBatch::where("po_no", last($fields["po_group"])["no"])->pluck("request_id")->toArray();

                                // Enable new transaction
                                Transaction::where("request_id", last($currentRequestids))->update([
                                    "is_not_editable" => true,
                                    "updated_at" => DB::raw("updated_at"),
                                ]);
                            }

                            if (gettype($getAndValidatePOBalance) == "object") {
                                return $this->resultResponse("invalid", "", $getAndValidatePOBalance);
                            }

                            if (gettype($getAndValidatePOBalance) == "array") {
                                //for new po
                                //Additional PO Validation
                                $new_po = $getAndValidatePOBalance["new_po_group"];
                                $po_total_amount = $getAndValidatePOBalance["po_total_amount"];
                                $balance_with_additional_total_po_amount = $getAndValidatePOBalance["balance"];

                                $transaction = GenericMethod::insertTransaction(
                                    $transaction_id,
                                    $po_total_amount,
                                    $request_id,
                                    $date_requested,
                                    $fields,
                                    $balance_with_additional_total_po_amount,
                                    $isConfidential,
                                    $isMc,
                                    $isNew
                                );

                                $request_id = $transaction->id;

                                GenericMethod::insertPO(
                                    $request_id,
                                    $fields["po_group"],
                                    $po_total_amount,
                                    strtoupper($fields["document"]["payment_type"])
                                );

                                POBatch::where("request_id", $request_id)
                                    ->where("po_no", reset($fields["po_group"])["no"])
                                    ->update([
                                        "is_modifiable" => true,
                                    ]);

                                $isAdd = POBatch::where("request_id", $request_id)->get();

                                foreach ($isAdd as $record) {
                                    if ($record->is_add == true && $record->is_editable == true) {
                                        $record->update([
                                            "is_modifiable" => true,
                                        ]);
                                    }
                                }

                                if (isset($transaction->transaction_id)) {
                                    return $this->resultResponse("save", "Transaction", []);
                                }
                            }

                            $po_total_amount = GenericMethod::getPOTotalAmount($request_id, $fields["po_group"]);
                            $balance_po_ref_amount = $po_total_amount - $fields["document"]["amount"];

                            if ($po_total_amount < $fields["document"]["amount"]) {
                                $amountValdiation = GenericMethod::resultLaravelFormat("document.amount", ["Insufficient PO balance."]);

                                return $this->resultResponse("invalid", "", $amountValdiation);
                            }

                            if (isset($getAndValidatePOBalance)) {
                                $balance_po_ref_amount = $getAndValidatePOBalance;
                            }

                            $transaction = GenericMethod::insertTransaction(
                                $transaction_id,
                                $po_total_amount,
                                $request_id,
                                $date_requested,
                                $fields,
                                $balance_po_ref_amount,
                                $isConfidential,
                                $isMc,
                                $isNew
                            );

                            $request_id = $transaction->id;

                            GenericMethod::insertPO(
                                $request_id,
                                $fields["po_group"],
                                $po_total_amount,
                                strtoupper($fields["document"]["payment_type"])
                            );

                            // POBatch::where('request_id', $request_id)->update([
                            //   'is_modifiable' => true
                            // ]);

                            // $currentPO = POBatch::where('po_no', last($fields["po_group"])["no"])->pluck('request_id');

                            // if ($currentPO) {
                            //   POBatch::where('request_id', reset($currentPO))->update([
                            //     'is_modifiable' => true
                            //   ]);
                            // }

                            // $isAdd = POBatch::where('request_id', $request_id)->get();

                            // foreach ($isAdd as $record) {
                            //   if ($record->is_add == false && $record->is_editable == true) {
                            //       $record->update([
                            //           'is_modifiable' => true
                            //       ]);
                            //   }
                            // }

                            POBatch::where("request_id", $request_id)
                                ->where("is_add", false)
                                ->where("is_editable", true)
                                ->update(["is_modifiable" => true]);

                            if (isset($transaction->transaction_id)) {
                                return $this->resultResponse("save", "Transaction", []);
                            }
                        } else {
                            $po_total_amount = GenericMethod::getPOTotalAmount($request_id, $fields["po_group"]);

                            $newTransaction = Transaction::create([
                                "transaction_id" => $transaction_id,
                                "users_id" => auth()->user()->id,
                                "id_prefix" => auth()->user()->id_prefix,
                                "id_no" => auth()->user()->id_no,
                                "first_name" => auth()->user()->first_name,
                                "middle_name" => auth()->user()->middle_name,
                                "last_name" => auth()->user()->last_name,
                                "suffix" => auth()->user()->suffix,
                                "department_details" => auth()->user()->department[0]["name"],

                                "document_id" => $fields["document"]["id"],
                                "category_id" => $fields["document"]["category"]["id"],
                                "category" => $fields["document"]["category"]["name"],
                                "company_id" => $fields["document"]["company"]["id"],
                                "company" => $fields["document"]["company"]["name"],
                                "department_id" => $fields["document"]["department"]["id"],
                                "department" => $fields["document"]["department"]["name"],
                                "location_id" => $fields["document"]["location"]["id"],
                                "location" => $fields["document"]["location"]["name"],
                                "supplier_id" => $fields["document"]["supplier"]["id"],
                                "supplier" => $fields["document"]["supplier"]["name"],
                                "payment_type" => $fields["document"]["payment_type"],
                                "document_no" => $fields["document"]["no"],
                                "document_date" => $fields["document"]["date"],
                                "document_amount" => $fields["document"]["amount"],
                                "remarks" => $fields["document"]["remarks"],
                                "document_type" => $fields["document"]["name"],
                                "po_total_amount" => $po_total_amount,
                                "request_id" => $request_id,

                                "date_requested" => $date_requested,
                                "status" => "Pending",
                                "business_unit_id" => $fields["document"]["business_unit"]["id"] ?? null,
                                "business_unit" => $fields["document"]["business_unit"]["name"] ?? null,
                                "unit_id" => $fields["document"]["unit_id"]["id"] ?? null,
                                "unit" => $fields["document"]["unit"]["name"] ?? null,
                                "sub_unit_id" => $fields["document"]["sub_unit"]["id"] ?? null,
                                "sub_unit" => $fields["document"]["sub_unit"]["name"] ?? null,
                                "is_confidential" => $isConfidential,
                                "is_mc" => $isMc,
                                "is_new" => $isNew,
                                "transaction_type" => $fields["type"],
                                "assigned_id" => $fields["assigned_id"],
                                "charge_id" => $fields["charge_id"]
                            ]);

                            $newTransaction->update([
                                "request_id" => $newTransaction->id,
                            ]);

                            $fields["po_group"] = GenericMethod::ValidateIfPOExists(
                                $fields["po_group"],
                                $fields["document"]["company"]["id"]
                            );

                            if (!empty($fields["po_group"])) {
                                foreach ($fields["po_group"] as $po) {
                                    $newTransaction->po_details()->create([
                                        "request_id" => $newTransaction->id,
                                        "po_no" => $po["no"],
                                        "po_amount" => $po["amount"],
//                                        "is_add" => $po["is_add"],
//                                        "is_editable" => $po["is_editable"],
                                        "po_total_amount" => $po_total_amount,
                                        "previous_balance" => $request->po_balance,
                                        "rr_group" => $po["rr_no"],
                                    ]);
                                }
                            }

                            if (!empty($fields["po_group"])) {
                                $lastRequestId = POBatch::where("po_no", last($fields["po_group"])["no"])
                                    ->pluck("request_id")
                                    ->last();

                                if ($lastRequestId) {
                                    Transaction::where("company_id", $fields["document"]["company"]["id"])
                                        ->where("request_id", $lastRequestId)
                                        ->update([
                                            "is_not_editable" => true,
                                            "updated_at" => DB::raw("updated_at"),
                                        ]);
                                }
                            }

                            if (!empty($fields['purchase_order'])) {
                                foreach ($fields['purchase_order'] as $rr) {
                                    foreach ($rr['rr_orders'] as $order) {
                                        $rrTransactions = $newTransaction->receivedReceipts()->create([
                                            'rr_id' => $rr['rr_id'] ?? null,
                                            'rr_number' => $rr['rr_number'] ?? null,
                                            'item_code' => $order['item_code'] ?? null,
                                            'item_name' => $order['item_name'] ?? null,
                                            'price' => $order['price'] ?? 0,
                                            'reference_no' => $order['reference_no'] ?? null,
                                            'quantity' => $order['quantity_receive'] ?? 0,
                                            'uom_code' => $order['uom_code'] ?? null,
                                            'uom_name' => $order['uom_name'] ?? null,
                                        ]);


                                        foreach ($rr['purchase_orders'] as $purchaseOrder) {
                                            $rrTransactions->purchaseOrders()->create([
                                                'po_number' => $purchaseOrder['po_number'] ?? null,
                                                'po_amount' => $purchaseOrder['po_amount'] ?? 0,
                                                'consumed_amount' => $purchaseOrder['consumed_amount'] ?? 0,
                                                'remaining_amount' => $purchaseOrder['remaining_amount'] ?? 0,
                                                'po_description' => $purchaseOrder['po_description'] ?? null,
                                                'type_name' => $purchaseOrder['type_name'] ?? null,
                                                'company_id' => $purchaseOrder['company']['id'] ?? null,
                                                'company_code' => $purchaseOrder['company']['code'] ?? null,
                                                'company_name' => $purchaseOrder['company']['name'] ?? null,
                                                'business_unit_id' => $purchaseOrder['business_unit']['id'] ?? null,
                                                'business_unit_code' => $purchaseOrder['business_unit']['code'] ?? null,
                                                'business_unit_name' => $purchaseOrder['business_unit']['name'] ?? null,
                                                'department_id' => $purchaseOrder['department']['id'] ?? null,
                                                'department_code' => $purchaseOrder['department']['code'] ?? null,
                                                'department_name' => $purchaseOrder['department']['name'] ?? null,
                                                'unit_id' => $purchaseOrder['unit']['id'] ?? null,
                                                'unit_code' => $purchaseOrder['unit']['code'] ?? null,
                                                'unit_name' => $purchaseOrder['unit']['name'] ?? null,
                                                'sub_unit_id' => $purchaseOrder['sub_unit']['id'] ?? null,
                                                'sub_unit_code' => $purchaseOrder['sub_unit']['code'] ?? null,
                                                'sub_unit_name' => $purchaseOrder['sub_unit']['name'] ?? null,
                                                'location_id' => $purchaseOrder['location']['id'] ?? null,
                                                'location_code' => $purchaseOrder['location']['code'] ?? null,
                                                'location_name' => $purchaseOrder['location']['name'] ?? null,
                                                'account_title_id' => $purchaseOrder['account_title']['id'] ?? null,
                                                'account_title_code' => $purchaseOrder['account_title']['code'] ?? null,
                                                'account_title_name' => $purchaseOrder['account_title']['name'] ?? null,
                                            ]);
                                        }
                                    }
                                }
                            }

                            if (!empty($fields['job_order'])) {
                                foreach ($fields['job_order'] as $rr) {
                                    foreach ($rr['rr_orders'] as $order) {
                                        $rrTransactions = $newTransaction->receivedReceipts()->create([
                                            'rr_id' => $rr['rr_id'] ?? null,
                                            'rr_number' => $rr['rr_number'] ?? null,
                                            'item_code' => $order['item_code'] ?? null,
                                            'item_name' => $order['description'] ?? null,
                                            'price' => $order['price'] ?? 0,
                                            'reference_no' => $order['reference_no'] ?? null,
                                            'quantity' => $order['quantity_receive'] ?? 0,
                                            'uom_code' => $order['uom_code'] ?? null,
                                            'uom_name' => $order['uom_name'] ?? null,
                                        ]);


                                        foreach ($rr['job_orders'] as $jobOrder) {
                                            $rrTransactions->jobOrders()->create([
                                                'jo_number' => $jobOrder['jo_number'] ?? null,
                                                'jo_amount' => $jobOrder['jo_amount'] ?? 0,
                                                'consumed_amount' => $jobOrder['consumed_amount'] ?? 0,
                                                'remaining_amount' => $jobOrder['remaining_amount'] ?? 0,
                                                'jo_description' => $jobOrder['jo_description'] ?? null,
                                                'type_name' => $jobOrder['type_name'] ?? null,
                                                'company_id' => $jobOrder['company']['id'] ?? null,
                                                'company_code' => $jobOrder['company']['code'] ?? null,
                                                'company_name' => $jobOrder['company']['name'] ?? null,
                                                'business_unit_id' => $jobOrder['business_unit']['id'] ?? null,
                                                'business_unit_code' => $jobOrder['business_unit']['code'] ?? null,
                                                'business_unit_name' => $jobOrder['business_unit']['name'] ?? null,
                                                'department_id' => $jobOrder['department']['id'] ?? null,
                                                'department_code' => $jobOrder['department']['code'] ?? null,
                                                'department_name' => $jobOrder['department']['name'] ?? null,
                                                'unit_id' => $jobOrder['unit']['id'] ?? null,
                                                'unit_code' => $jobOrder['unit']['code'] ?? null,
                                                'unit_name' => $jobOrder['unit']['name'] ?? null,
                                                'sub_unit_id' => $jobOrder['sub_unit']['id'] ?? null,
                                                'sub_unit_code' => $jobOrder['sub_unit']['code'] ?? null,
                                                'sub_unit_name' => $jobOrder['sub_unit']['name'] ?? null,
                                                'location_id' => $jobOrder['location']['id'] ?? null,
                                                'location_code' => $jobOrder['location']['code'] ?? null,
                                                'location_name' => $jobOrder['location']['name'] ?? null,
                                                'account_title_id' => $jobOrder['account_title']['id'] ?? null,
                                                'account_title_code' => $jobOrder['account_title']['code'] ?? null,
                                                'account_title_name' => $jobOrder['account_title']['name'] ?? null,
                                            ]);
                                        }
                                    }
                                }
                            }

                            return $this->resultResponse("save", "Transaction", []);
                        }

                        //---------------------------------------------------------------------------------------------------
                        break;

                    default:
                        GenericMethod::documentNoValidation($request["document"]["no"]);

                        if (!empty($fields['po_group'])) {
                            $duplicatePO = GenericMethod::validatePOFull($fields["document"]["company"]["id"], $fields["po_group"]);

                            if (isset($duplicatePO)) {
                                return $this->resultResponse("invalid", "", $duplicatePO);
                            }

                            $po_total_amount = GenericMethod::getPOTotalAmount($request_id, $fields["po_group"]);

                            $errorMessage = GenericMethod::validateWith1PesoDifference(
                                "po_group.amount",
                                "Document",
                                $fields["document"]["amount"],
                                $po_total_amount
                            );

                            if (!empty($errorMessage)) {
                                return GenericMethod::resultResponse("invalid", "", $errorMessage);
                            }
                        }

                        $transaction = GenericMethod::insertTransaction(
                            $transaction_id,
                            $po_total_amount,
                            $request_id,
                            $date_requested,
                            $fields,
                            null,
                            $isConfidential,
                            $isMc,
                            $isNew
                        );

                        $request_id = $transaction->id;

                        if (!empty($fields['po_group'])) {
                            GenericMethod::insertPO(
                                $request_id,
                                $fields["po_group"],
                                $po_total_amount,
                                strtoupper($fields["document"]["payment_type"])
                            );
                        }

                        if (!empty($fields['purchase_order'])) {
                            foreach ($fields['purchase_order'] as $rr) {
                                foreach ($rr['rr_orders'] as $order) {
                                    $rrTransactions = $transaction->receivedReceipts()->create([
                                        'rr_id' => $rr['rr_id'] ?? null,
                                        'rr_number' => $rr['rr_number'] ?? null,
                                        'item_code' => $order['item_code'] ?? null,
                                        'item_name' => $order['item_name'] ?? null,
                                        'price' => $order['price'] ?? 0,
                                        'reference_no' => $order['reference_no'] ?? null,
                                        'quantity' => $order['quantity_receive'] ?? 0,
                                        'uom_code' => $order['uom_code'] ?? null,
                                        'uom_name' => $order['uom_name'] ?? null,
                                    ]);


                                    foreach ($rr['purchase_orders'] as $purchaseOrder) {
                                        $rrTransactions->purchaseOrders()->create([
                                            'po_number' => $purchaseOrder['po_number'] ?? null,
                                            'po_amount' => $purchaseOrder['po_amount'] ?? 0,
                                            'consumed_amount' => $purchaseOrder['consumed_amount'] ?? 0,
                                            'remaining_amount' => $purchaseOrder['remaining_amount'] ?? 0,
                                            'po_description' => $purchaseOrder['po_description'] ?? null,
                                            'type_name' => $purchaseOrder['type_name'] ?? null,
                                            'company_id' => $purchaseOrder['company']['id'] ?? null,
                                            'company_code' => $purchaseOrder['company']['code'] ?? null,
                                            'company_name' => $purchaseOrder['company']['name'] ?? null,
                                            'business_unit_id' => $purchaseOrder['business_unit']['id'] ?? null,
                                            'business_unit_code' => $purchaseOrder['business_unit']['code'] ?? null,
                                            'business_unit_name' => $purchaseOrder['business_unit']['name'] ?? null,
                                            'department_id' => $purchaseOrder['department']['id'] ?? null,
                                            'department_code' => $purchaseOrder['department']['code'] ?? null,
                                            'department_name' => $purchaseOrder['department']['name'] ?? null,
                                            'unit_id' => $purchaseOrder['unit']['id'] ?? null,
                                            'unit_code' => $purchaseOrder['unit']['code'] ?? null,
                                            'unit_name' => $purchaseOrder['unit']['name'] ?? null,
                                            'sub_unit_id' => $purchaseOrder['sub_unit']['id'] ?? null,
                                            'sub_unit_code' => $purchaseOrder['sub_unit']['code'] ?? null,
                                            'sub_unit_name' => $purchaseOrder['sub_unit']['name'] ?? null,
                                            'location_id' => $purchaseOrder['location']['id'] ?? null,
                                            'location_code' => $purchaseOrder['location']['code'] ?? null,
                                            'location_name' => $purchaseOrder['location']['name'] ?? null,
                                            'account_title_id' => $purchaseOrder['account_title']['id'] ?? null,
                                            'account_title_code' => $purchaseOrder['account_title']['code'] ?? null,
                                            'account_title_name' => $purchaseOrder['account_title']['name'] ?? null,
                                        ]);
                                    }
                                }
                            }
                        }

                        if (!empty($fields['job_order'])) {
                            foreach ($fields['job_order'] as $rr) {
                                foreach ($rr['rr_orders'] as $order) {
                                    $rrTransactions = $transaction->receivedReceipts()->create([
                                        'rr_id' => $rr['rr_id'] ?? null,
                                        'rr_number' => $rr['rr_number'] ?? null,
                                        'item_code' => $order['item_code'] ?? null,
                                        'item_name' => $order['description'] ?? null,
                                        'price' => $order['price'] ?? 0,
                                        'reference_no' => $order['reference_no'] ?? null,
                                        'quantity' => $order['quantity_receive'] ?? 0,
                                        'uom_code' => $order['uom_code'] ?? null,
                                        'uom_name' => $order['uom_name'] ?? null,
                                    ]);


                                    foreach ($rr['job_orders'] as $jobOrder) {
                                        $rrTransactions->jobOrders()->create([
                                            'jo_number' => $jobOrder['jo_number'] ?? null,
                                            'jo_amount' => $jobOrder['jo_amount'] ?? 0,
                                            'consumed_amount' => $jobOrder['consumed_amount'] ?? 0,
                                            'remaining_amount' => $jobOrder['remaining_amount'] ?? 0,
                                            'jo_description' => $jobOrder['jo_description'] ?? null,
                                            'type_name' => $jobOrder['type_name'] ?? null,
                                            'company_id' => $jobOrder['company']['id'] ?? null,
                                            'company_code' => $jobOrder['company']['code'] ?? null,
                                            'company_name' => $jobOrder['company']['name'] ?? null,
                                            'business_unit_id' => $jobOrder['business_unit']['id'] ?? null,
                                            'business_unit_code' => $jobOrder['business_unit']['code'] ?? null,
                                            'business_unit_name' => $jobOrder['business_unit']['name'] ?? null,
                                            'department_id' => $jobOrder['department']['id'] ?? null,
                                            'department_code' => $jobOrder['department']['code'] ?? null,
                                            'department_name' => $jobOrder['department']['name'] ?? null,
                                            'unit_id' => $jobOrder['unit']['id'] ?? null,
                                            'unit_code' => $jobOrder['unit']['code'] ?? null,
                                            'unit_name' => $jobOrder['unit']['name'] ?? null,
                                            'sub_unit_id' => $jobOrder['sub_unit']['id'] ?? null,
                                            'sub_unit_code' => $jobOrder['sub_unit']['code'] ?? null,
                                            'sub_unit_name' => $jobOrder['sub_unit']['name'] ?? null,
                                            'location_id' => $jobOrder['location']['id'] ?? null,
                                            'location_code' => $jobOrder['location']['code'] ?? null,
                                            'location_name' => $jobOrder['location']['name'] ?? null,
                                            'account_title_id' => $jobOrder['account_title']['id'] ?? null,
                                            'account_title_code' => $jobOrder['account_title']['code'] ?? null,
                                            'account_title_name' => $jobOrder['account_title']['name'] ?? null,
                                        ]);
                                    }
                                }
                            }
                        }

                        if (isset($transaction->transaction_id)) {
                            return $this->resultResponse("save", "Transaction", []);
                        }

                        break;
                }

                break;

            case 5: //Contractor's Billing
//                if (empty($fields["po_group"])) {
//                    $errorMessage = GenericMethod::resultLaravelFormat("po_group", ["PO group required"]);
//
//                    return $this->resultResponse("invalid", "", $errorMessage);
//                }

                $transaction_id = isset($transaction_id) ? $transaction_id : null;

                GenericMethod::billingValidation(
                    $fields["document"]["company"]["id"],
                    $fields["document"]["department"]["id"],
                    $fields["document"]["location"]["id"],
                    $fields["document"]["supplier"]["id"],
                    $fields["document"]["category"]["id"],
                    $fields["document"]["capex_no"],
                    $transaction_id
                );

                $duplicatePO = GenericMethod::validatePOFull($fields["document"]["company"]["id"], $fields["po_group"]);

                if (isset($duplicatePO)) {
                    return $this->resultResponse("invalid", "", $duplicatePO);
                }

                $po_total_amount = GenericMethod::getPOTotalAmount($request_id, $fields["po_group"]);

//                $errorMessage = GenericMethod::validateWith1PesoDifference(
//                    "po_group.amount",
//                    "Document",
//                    $fields["document"]["amount"],
//                    $po_total_amount
//                );
//
//                if (!empty($errorMessage)) {
//                    return GenericMethod::resultResponse("invalid", "", $errorMessage);
//                }

                $transaction = GenericMethod::insertTransaction(
                    $transaction_id,
                    $po_total_amount,
                    $request_id,
                    $date_requested,
                    $fields,
                    null,
                    $isConfidential,
                    $isMc,
                    $isNew
                );

                $request_id = $transaction->id;

                GenericMethod::insertPO(
                    $request_id,
                    $fields["po_group"],
                    $po_total_amount,
                    strtoupper($fields["document"]["payment_type"])
                );

                if (!empty($fields['purchase_order'])) {
                    foreach ($fields['purchase_order'] as $rr) {
                        foreach ($rr['rr_orders'] as $order) {
                            $rrTransactions = $transaction->receivedReceipts()->create([
                                'rr_id' => $rr['rr_id'] ?? null,
                                'rr_number' => $rr['rr_number'] ?? null,
                                'item_code' => $order['item_code'] ?? null,
                                'item_name' => $order['item_name'] ?? null,
                                'price' => $order['price'] ?? 0,
                                'reference_no' => $order['reference_no'] ?? null,
                                'quantity' => $order['quantity_receive'] ?? 0,
                                'uom_code' => $order['uom_code'] ?? null,
                                'uom_name' => $order['uom_name'] ?? null,
                            ]);


                            foreach ($rr['purchase_orders'] as $purchaseOrder) {
                                $rrTransactions->purchaseOrders()->create([
                                    'po_number' => $purchaseOrder['po_number'] ?? null,
                                    'po_amount' => $purchaseOrder['po_amount'] ?? 0,
                                    'consumed_amount' => $purchaseOrder['consumed_amount'] ?? 0,
                                    'remaining_amount' => $purchaseOrder['remaining_amount'] ?? 0,
                                    'po_description' => $purchaseOrder['po_description'] ?? null,
                                    'type_name' => $purchaseOrder['type_name'] ?? null,
                                    'company_id' => $purchaseOrder['company']['id'] ?? null,
                                    'company_code' => $purchaseOrder['company']['code'] ?? null,
                                    'company_name' => $purchaseOrder['company']['name'] ?? null,
                                    'business_unit_id' => $purchaseOrder['business_unit']['id'] ?? null,
                                    'business_unit_code' => $purchaseOrder['business_unit']['code'] ?? null,
                                    'business_unit_name' => $purchaseOrder['business_unit']['name'] ?? null,
                                    'department_id' => $purchaseOrder['department']['id'] ?? null,
                                    'department_code' => $purchaseOrder['department']['code'] ?? null,
                                    'department_name' => $purchaseOrder['department']['name'] ?? null,
                                    'unit_id' => $purchaseOrder['unit']['id'] ?? null,
                                    'unit_code' => $purchaseOrder['unit']['code'] ?? null,
                                    'unit_name' => $purchaseOrder['unit']['name'] ?? null,
                                    'sub_unit_id' => $purchaseOrder['sub_unit']['id'] ?? null,
                                    'sub_unit_code' => $purchaseOrder['sub_unit']['code'] ?? null,
                                    'sub_unit_name' => $purchaseOrder['sub_unit']['name'] ?? null,
                                    'location_id' => $purchaseOrder['location']['id'] ?? null,
                                    'location_code' => $purchaseOrder['location']['code'] ?? null,
                                    'location_name' => $purchaseOrder['location']['name'] ?? null,
                                    'account_title_id' => $purchaseOrder['account_title']['id'] ?? null,
                                    'account_title_code' => $purchaseOrder['account_title']['code'] ?? null,
                                    'account_title_name' => $purchaseOrder['account_title']['name'] ?? null,
                                ]);
                            }
                        }
                    }
                }

                if (!empty($fields['job_order'])) {
                    foreach ($fields['job_order'] as $rr) {
                        foreach ($rr['rr_orders'] as $order) {
                            $rrTransactions = $transaction->receivedReceipts()->create([
                                'rr_id' => $rr['rr_id'] ?? null,
                                'rr_number' => $rr['rr_number'] ?? null,
                                'item_code' => $order['item_code'] ?? null,
                                'item_name' => $order['description'] ?? null,
                                'price' => $order['price'] ?? 0,
                                'reference_no' => $order['reference_no'] ?? null,
                                'quantity' => $order['quantity_receive'] ?? 0,
                                'uom_code' => $order['uom_code'] ?? null,
                                'uom_name' => $order['uom_name'] ?? null,
                            ]);


                            foreach ($rr['job_orders'] as $jobOrder) {
                                $rrTransactions->jobOrders()->create([
                                    'jo_number' => $jobOrder['jo_number'] ?? null,
                                    'jo_amount' => $jobOrder['jo_amount'] ?? 0,
                                    'consumed_amount' => $jobOrder['consumed_amount'] ?? 0,
                                    'remaining_amount' => $jobOrder['remaining_amount'] ?? 0,
                                    'jo_description' => $jobOrder['jo_description'] ?? null,
                                    'type_name' => $jobOrder['type_name'] ?? null,
                                    'company_id' => $jobOrder['company']['id'] ?? null,
                                    'company_code' => $jobOrder['company']['code'] ?? null,
                                    'company_name' => $jobOrder['company']['name'] ?? null,
                                    'business_unit_id' => $jobOrder['business_unit']['id'] ?? null,
                                    'business_unit_code' => $jobOrder['business_unit']['code'] ?? null,
                                    'business_unit_name' => $jobOrder['business_unit']['name'] ?? null,
                                    'department_id' => $jobOrder['department']['id'] ?? null,
                                    'department_code' => $jobOrder['department']['code'] ?? null,
                                    'department_name' => $jobOrder['department']['name'] ?? null,
                                    'unit_id' => $jobOrder['unit']['id'] ?? null,
                                    'unit_code' => $jobOrder['unit']['code'] ?? null,
                                    'unit_name' => $jobOrder['unit']['name'] ?? null,
                                    'sub_unit_id' => $jobOrder['sub_unit']['id'] ?? null,
                                    'sub_unit_code' => $jobOrder['sub_unit']['code'] ?? null,
                                    'sub_unit_name' => $jobOrder['sub_unit']['name'] ?? null,
                                    'location_id' => $jobOrder['location']['id'] ?? null,
                                    'location_code' => $jobOrder['location']['code'] ?? null,
                                    'location_name' => $jobOrder['location']['name'] ?? null,
                                    'account_title_id' => $jobOrder['account_title']['id'] ?? null,
                                    'account_title_code' => $jobOrder['account_title']['code'] ?? null,
                                    'account_title_name' => $jobOrder['account_title']['name'] ?? null,
                                ]);
                            }
                        }
                    }
                }

                if (isset($transaction->transaction_id)) {
                    return $this->resultResponse("save", "Transaction", []);
                }

                break;

            case 2: //PRM Common

                switch ($request->input("document.payment_type")) {
                    case 'Partial':

                        if (!$isNew) {
                            $fields["po_group"] = GenericMethod::ValidateIfPOExists(
                                $fields["po_group"],
                                $fields["document"]["company"]["id"]
                            );

                            $getAndValidatePOBalance = GenericMethod::getAndValidatePOBalance(
                                $fields,
                                $fields["document"]["company"]["id"],
                                last($fields["po_group"])["no"],
                                $fields["document"]["amount"],
                                $fields["po_group"]
                            );

                            $existTransaction = Transaction::where("company_id", $fields["document"]["company"]["id"])
                                ->exists();

                            if ($existTransaction) {
                                $currentRequestids = POBatch::where("po_no", last($fields["po_group"])["no"])->pluck("request_id")->toArray();

                                // Enable new transaction
                                Transaction::where("request_id", last($currentRequestids))->update([
                                    "is_not_editable" => true,
                                    "updated_at" => DB::raw("updated_at"),
                                ]);
                            }

                            if (gettype($getAndValidatePOBalance) == "object") {
                                return $this->resultResponse("invalid", "", $getAndValidatePOBalance);
                            }

                            if (gettype($getAndValidatePOBalance) == "array") {
                                //for new po
                                //Additional PO Validation
                                $new_po = $getAndValidatePOBalance["new_po_group"];
                                $po_total_amount = $getAndValidatePOBalance["po_total_amount"];
                                $balance_with_additional_total_po_amount = $getAndValidatePOBalance["balance"];

                                $transaction = GenericMethod::insertTransaction(
                                    $transaction_id,
                                    $po_total_amount,
                                    $request_id,
                                    $date_requested,
                                    $fields,
                                    $balance_with_additional_total_po_amount,
                                    $isConfidential,
                                    $isMc,
                                    $isNew
                                );

                                $request_id = $transaction->id;

                                GenericMethod::insertPO(
                                    $request_id,
                                    $fields["po_group"],
                                    $po_total_amount,
                                    strtoupper($fields["document"]["payment_type"])
                                );

                                POBatch::where("request_id", $request_id)
                                    ->where("po_no", reset($fields["po_group"])["no"])
                                    ->update([
                                        "is_modifiable" => true,
                                    ]);

                                $isAdd = POBatch::where("request_id", $request_id)->get();

                                foreach ($isAdd as $record) {
                                    if ($record->is_add == true && $record->is_editable == true) {
                                        $record->update([
                                            "is_modifiable" => true,
                                        ]);
                                    }
                                }

                                if (isset($transaction->transaction_id)) {
                                    return $this->resultResponse("save", "Transaction", []);
                                }
                            }

                            $po_total_amount = GenericMethod::getPOTotalAmount($request_id, $fields["po_group"]);
                            $balance_po_ref_amount = $po_total_amount - $fields["document"]["amount"];

                            if ($po_total_amount < $fields["document"]["amount"]) {
                                $amountValdiation = GenericMethod::resultLaravelFormat("document.amount", ["Insufficient PO balance."]);

                                return $this->resultResponse("invalid", "", $amountValdiation);
                            }

                            if (isset($getAndValidatePOBalance)) {
                                $balance_po_ref_amount = $getAndValidatePOBalance;
                            }

                            $transaction = GenericMethod::insertTransaction(
                                $transaction_id,
                                $po_total_amount,
                                $request_id,
                                $date_requested,
                                $fields,
                                $balance_po_ref_amount,
                                $isConfidential,
                                $isMc,
                                $isNew
                            );

                            $request_id = $transaction->id;

                            GenericMethod::insertPO(
                                $request_id,
                                $fields["po_group"],
                                $po_total_amount,
                                strtoupper($fields["document"]["payment_type"])
                            );

                            POBatch::where("request_id", $request_id)
                                ->where("is_add", false)
                                ->where("is_editable", true)
                                ->update(["is_modifiable" => true]);

                            if (isset($transaction->transaction_id)) {
                                return $this->resultResponse("save", "Transaction", []);
                            }
                        } else {
                            $po_total_amount = GenericMethod::getPOTotalAmount($request_id, $fields["po_group"]);
                            $transaction = GenericMethod::insertTransaction(
                                $transaction_id,
                                $po_total_amount,
                                $request_id,
                                $date_requested,
                                $fields,
                                $balance_po_ref_amount = 0,
                                $isConfidential,
                                $isMc,
                                $isNew
                            );

                            if (!empty($fields["po_group"])) {
                                foreach ($fields["po_group"] as $po) {
                                    $transaction->po_details()->create([
                                        "request_id" => $transaction->id,
                                        "po_no" => $po["no"],
                                        "po_amount" => $po["amount"],
//                                        "is_add" => $po["is_add"],
//                                        "is_editable" => $po["is_editable"],
                                        "po_total_amount" => $po_total_amount,
                                        "previous_balance" => $request->po_balance,
                                        "rr_group" => $po["rr_no"],
                                    ]);
                                }

                                $lastRequestId = POBatch::where("po_no", last($fields["po_group"])["no"])
                                    ->pluck("request_id")
                                    ->last();

                                if ($lastRequestId) {
                                    Transaction::where("company_id", $fields["document"]["company"]["id"])
                                        ->where("request_id", $lastRequestId)
                                        ->update([
                                            "is_not_editable" => true,
                                            "updated_at" => DB::raw("updated_at"),
                                        ]);
                                }
                            }

                            if (!empty($fields['purchase_order'])) {
                                foreach ($fields['purchase_order'] as $rr) {
                                    foreach ($rr['rr_orders'] as $order) {
                                        $rrTransactions = $transaction->receivedReceipts()->create([
                                            'rr_id' => $rr['rr_id'] ?? null,
                                            'rr_number' => $rr['rr_number'] ?? null,
                                            'item_code' => $order['item_code'] ?? null,
                                            'item_name' => $order['item_name'] ?? null,
                                            'price' => $order['price'] ?? 0,
                                            'reference_no' => $order['reference_no'] ?? null,
                                            'quantity' => $order['quantity_receive'] ?? 0,
                                            'uom_code' => $order['uom_code'] ?? null,
                                            'uom_name' => $order['uom_name'] ?? null,
                                        ]);


                                        foreach ($rr['purchase_orders'] as $purchaseOrder) {
                                            $rrTransactions->purchaseOrders()->create([
                                                'po_number' => $purchaseOrder['po_number'] ?? null,
                                                'po_amount' => $purchaseOrder['po_amount'] ?? 0,
                                                'consumed_amount' => $purchaseOrder['consumed_amount'] ?? 0,
                                                'remaining_amount' => $purchaseOrder['remaining_amount'] ?? 0,
                                                'po_description' => $purchaseOrder['po_description'] ?? null,
                                                'type_name' => $purchaseOrder['type_name'] ?? null,
                                                'company_id' => $purchaseOrder['company']['id'] ?? null,
                                                'company_code' => $purchaseOrder['company']['code'] ?? null,
                                                'company_name' => $purchaseOrder['company']['name'] ?? null,
                                                'business_unit_id' => $purchaseOrder['business_unit']['id'] ?? null,
                                                'business_unit_code' => $purchaseOrder['business_unit']['code'] ?? null,
                                                'business_unit_name' => $purchaseOrder['business_unit']['name'] ?? null,
                                                'department_id' => $purchaseOrder['department']['id'] ?? null,
                                                'department_code' => $purchaseOrder['department']['code'] ?? null,
                                                'department_name' => $purchaseOrder['department']['name'] ?? null,
                                                'unit_id' => $purchaseOrder['unit']['id'] ?? null,
                                                'unit_code' => $purchaseOrder['unit']['code'] ?? null,
                                                'unit_name' => $purchaseOrder['unit']['name'] ?? null,
                                                'sub_unit_id' => $purchaseOrder['sub_unit']['id'] ?? null,
                                                'sub_unit_code' => $purchaseOrder['sub_unit']['code'] ?? null,
                                                'sub_unit_name' => $purchaseOrder['sub_unit']['name'] ?? null,
                                                'location_id' => $purchaseOrder['location']['id'] ?? null,
                                                'location_code' => $purchaseOrder['location']['code'] ?? null,
                                                'location_name' => $purchaseOrder['location']['name'] ?? null,
                                                'account_title_id' => $purchaseOrder['account_title']['id'] ?? null,
                                                'account_title_code' => $purchaseOrder['account_title']['code'] ?? null,
                                                'account_title_name' => $purchaseOrder['account_title']['name'] ?? null,
                                            ]);
                                        }
                                    }
                                }
                            }

                            if (!empty($fields['job_order'])) {
                                foreach ($fields['job_order'] as $rr) {
                                    foreach ($rr['rr_orders'] as $order) {
                                        $rrTransactions = $transaction->receivedReceipts()->create([
                                            'rr_id' => $rr['rr_id'] ?? null,
                                            'rr_number' => $rr['rr_number'] ?? null,
                                            'item_code' => $order['item_code'] ?? null,
                                            'item_name' => $order['description'] ?? null,
                                            'price' => $order['price'] ?? 0,
                                            'reference_no' => $order['reference_no'] ?? null,
                                            'quantity' => $order['quantity_receive'] ?? 0,
                                            'uom_code' => $order['uom_code'] ?? null,
                                            'uom_name' => $order['uom_name'] ?? null,
                                        ]);


                                        foreach ($rr['job_orders'] as $jobOrder) {
                                            $rrTransactions->jobOrders()->create([
                                                'jo_number' => $jobOrder['jo_number'] ?? null,
                                                'jo_amount' => $jobOrder['jo_amount'] ?? 0,
                                                'consumed_amount' => $jobOrder['consumed_amount'] ?? 0,
                                                'remaining_amount' => $jobOrder['remaining_amount'] ?? 0,
                                                'jo_description' => $jobOrder['jo_description'] ?? null,
                                                'type_name' => $jobOrder['type_name'] ?? null,
                                                'company_id' => $jobOrder['company']['id'] ?? null,
                                                'company_code' => $jobOrder['company']['code'] ?? null,
                                                'company_name' => $jobOrder['company']['name'] ?? null,
                                                'business_unit_id' => $jobOrder['business_unit']['id'] ?? null,
                                                'business_unit_code' => $jobOrder['business_unit']['code'] ?? null,
                                                'business_unit_name' => $jobOrder['business_unit']['name'] ?? null,
                                                'department_id' => $jobOrder['department']['id'] ?? null,
                                                'department_code' => $jobOrder['department']['code'] ?? null,
                                                'department_name' => $jobOrder['department']['name'] ?? null,
                                                'unit_id' => $jobOrder['unit']['id'] ?? null,
                                                'unit_code' => $jobOrder['unit']['code'] ?? null,
                                                'unit_name' => $jobOrder['unit']['name'] ?? null,
                                                'sub_unit_id' => $jobOrder['sub_unit']['id'] ?? null,
                                                'sub_unit_code' => $jobOrder['sub_unit']['code'] ?? null,
                                                'sub_unit_name' => $jobOrder['sub_unit']['name'] ?? null,
                                                'location_id' => $jobOrder['location']['id'] ?? null,
                                                'location_code' => $jobOrder['location']['code'] ?? null,
                                                'location_name' => $jobOrder['location']['name'] ?? null,
                                                'account_title_id' => $jobOrder['account_title']['id'] ?? null,
                                                'account_title_code' => $jobOrder['account_title']['code'] ?? null,
                                                'account_title_name' => $jobOrder['account_title']['name'] ?? null,
                                            ]);
                                        }
                                    }
                                }
                            }

                            if (isset($transaction->transaction_id)) {
                                return $this->resultResponse("save", "Transaction", []);
                            }

                        }

                        break;

                    default:
                        GenericMethod::documentNoValidation($request["document"]["no"]);
                        $transaction = GenericMethod::insertTransaction($transaction_id, null, $request_id, $date_requested, $fields, null, $isConfidential, $isMc, $isNew);
                        if (isset($transaction->transaction_id)) {
                            return $this->resultResponse("save", "Transaction", []);
                        }
                        break;
                }
                break;

            case 3: // PRM Multiple
                GenericMethod::documentNoValidation($request["document"]["no"]);
                $transaction = GenericMethod::insertTransaction($transaction_id, null, $request_id, $date_requested, $fields, null, $isConfidential, $isMc, $isNew);

                if (isset($transaction->transaction_id)) {
                    return $this->resultResponse("save", "Transaction", []);
                }
                return $transaction;
                break;

            case 6: //Utilities
                $duplicateUtilities = GenericMethod::validateTransactionByDateRange(
                    $fields["document"]["from"],
                    $fields["document"]["to"],
                    $fields["document"]["company"]["id"],
                    $fields["document"]["department"]["id"],
                    $fields["document"]["supplier"]["id"],
                    $fields["document"]["utility"]["location"]["id"],
                    $fields["document"]["utility"]["category"]["name"],
                    $fields["document"]["utility"]["account_no"]["no"],
                    data_get($fields, "document.utility.receipt_no")
                );

                // $request->validate([
                //   'document.utility.receipt_no' => [
                //     'required',
                //     Rule::unique('transactions', 'utilities_receipt_no')->where(function ($query) use ($fields){
                //       $query->where('supplier_id', $fields["document"]["supplier"]["id"])
                //       ->where('utilities_receipt_no', data_get($fields, "document.utility.receipt_no")
                //       );
                //     })
                //   ]
                // ]);

                if (isset($duplicateUtilities)) {
                    return $this->resultResponse("invalid", "", $duplicateUtilities);
                }

                $transaction = GenericMethod::insertTransaction($transaction_id, null, $request_id, $date_requested, $fields, null, $isConfidential, $isMc, $isNew);
                if (isset($transaction->transaction_id)) {
                    return $this->resultResponse("save", "Transaction", []);
                }
                break;

            case 8: //PCF
                $duplicatePCF = GenericMethod::validatePCF(
                    $fields["document"]["pcf_batch"]["name"],
                    $fields["document"]["pcf_batch"]["date"],
                    $fields["document"]["pcf_batch"]["letter"],
                    $fields["document"]["company"]["id"],
                    $fields["document"]["supplier"]["id"],
                    $fields["document"]["department"]["id"],
                    $fields["document"]["location"]["id"]
                );
                if (isset($duplicatePCF)) {
                    return $this->resultResponse("invalid", "", $duplicatePCF);
                }

                $transaction = GenericMethod::insertTransaction($transaction_id, null, $request_id, $date_requested, $fields, null, $isConfidential, $isMc, $isNew);
                if (isset($transaction->transaction_id)) {
                    return $this->resultResponse("save", "Transaction", []);
                }
                break;

            case 7: //Payroll
                $duplicatePayroll = GenericMethod::validatePayroll(
                    $fields["document"]["from"],
                    $fields["document"]["to"],
                    $fields["document"]["company"]["id"],
                    $fields["document"]["department"]["id"],
                    $fields["document"]["location"]["id"],
                    $fields["document"]["supplier"]["id"],
                    $fields["document"]["payroll"]["clients"],
                    $fields["document"]["payroll"]["type"],
                    $fields["document"]["payroll"]["category"]["name"],
                    $fields["document"]["payroll"]["control_no"]
                );

                if (isset($duplicatePayroll)) {
                    return $this->resultResponse("invalid", "", $duplicatePayroll);
                }

                $transaction = GenericMethod::insertTransaction($transaction_id, null, $request_id, $date_requested, $fields, null, $isConfidential, $isMc, $isNew);

                $request_id = $transaction->id;

//                GenericMethod::insertClient($request_id, $fields["document"]["payroll"]["clients"]);

                if (isset($transaction->transaction_id)) {
                    return $this->resultResponse("save", "Transaction", []);
                }

                break;

            case 4: //Receipt
                $isFull = strtoupper($fields["document"]["payment_type"]) === "FULL";
                $isQty = $fields["document"]["reference"]["type"] === "DR Qty";

//                if (empty($fields["po_group"])) {
//                    $errorMessage = GenericMethod::resultLaravelFormat("po_group", ["PO group required"]);
//                    return $this->resultResponse("invalid", "", $errorMessage);
//                }

                if (!$isQty && $isFull) {
                    //Full
                    $duplicateRef = GenericMethod::validateReferenceNo($fields);

                    if (isset($duplicateRef)) {
                        return $this->resultResponse("invalid", "", $duplicateRef);
                    }

                    if (!empty($fields["po_group"])) {
                        $duplicatePO = GenericMethod::validatePOFull($fields["document"]["company"]["id"], $fields["po_group"]);

                        if (isset($duplicatePO)) {
                            return $this->resultResponse("invalid", "", $duplicatePO);
                        }

                        $po_total_amount = GenericMethod::getPOTotalAmount($request_id, $fields["po_group"]);

                        if (!$fields["document"]["reference"]["allowable"]) {
                            $errorMessage = GenericMethod::validateWith1PesoDifference(
                                "document.amount",
                                "Reference",
                                $fields["document"]["reference"]["amount"],
                                $po_total_amount
                            );

                            if (!empty($errorMessage)) {
                                return GenericMethod::resultResponse("invalid", "", $errorMessage);
                            }
                        }
                    }

                    $transaction = GenericMethod::insertTransaction(
                        $transaction_id,
                        $po_total_amount,
                        $request_id,
                        $date_requested,
                        $fields,
                        null,
                        $isConfidential,
                        $isMc,
                        $isNew
                    );

                    $request_id = $transaction->id;

                    if (!empty($fields["po_group"])) {
                        GenericMethod::insertPO(
                            $request_id,
                            $fields["po_group"],
                            $po_total_amount,
                            strtoupper($fields["document"]["payment_type"])
                        );
                    }

                    if (!empty($fields['purchase_order'])) {
                        foreach ($fields['purchase_order'] as $rr) {
                            foreach ($rr['rr_orders'] as $order) {
                                $rrTransactions = $transaction->receivedReceipts()->create([
                                    'rr_id' => $rr['rr_id'] ?? null,
                                    'rr_number' => $rr['rr_number'] ?? null,
                                    'item_code' => $order['item_code'] ?? null,
                                    'item_name' => $order['item_name'] ?? null,
                                    'price' => $order['price'] ?? 0,
                                    'reference_no' => $order['reference_no'] ?? null,
                                    'quantity' => $order['quantity_receive'] ?? 0,
                                    'uom_code' => $order['uom_code'] ?? null,
                                    'uom_name' => $order['uom_name'] ?? null,
                                ]);


                                foreach ($rr['purchase_orders'] as $purchaseOrder) {
                                    $rrTransactions->purchaseOrders()->create([
                                        'po_number' => $purchaseOrder['po_number'] ?? null,
                                        'po_amount' => $purchaseOrder['po_amount'] ?? 0,
                                        'consumed_amount' => $purchaseOrder['consumed_amount'] ?? 0,
                                        'remaining_amount' => $purchaseOrder['remaining_amount'] ?? 0,
                                        'po_description' => $purchaseOrder['po_description'] ?? null,
                                        'type_name' => $purchaseOrder['type_name'] ?? null,
                                        'company_id' => $purchaseOrder['company']['id'] ?? null,
                                        'company_code' => $purchaseOrder['company']['code'] ?? null,
                                        'company_name' => $purchaseOrder['company']['name'] ?? null,
                                        'business_unit_id' => $purchaseOrder['business_unit']['id'] ?? null,
                                        'business_unit_code' => $purchaseOrder['business_unit']['code'] ?? null,
                                        'business_unit_name' => $purchaseOrder['business_unit']['name'] ?? null,
                                        'department_id' => $purchaseOrder['department']['id'] ?? null,
                                        'department_code' => $purchaseOrder['department']['code'] ?? null,
                                        'department_name' => $purchaseOrder['department']['name'] ?? null,
                                        'unit_id' => $purchaseOrder['unit']['id'] ?? null,
                                        'unit_code' => $purchaseOrder['unit']['code'] ?? null,
                                        'unit_name' => $purchaseOrder['unit']['name'] ?? null,
                                        'sub_unit_id' => $purchaseOrder['sub_unit']['id'] ?? null,
                                        'sub_unit_code' => $purchaseOrder['sub_unit']['code'] ?? null,
                                        'sub_unit_name' => $purchaseOrder['sub_unit']['name'] ?? null,
                                        'location_id' => $purchaseOrder['location']['id'] ?? null,
                                        'location_code' => $purchaseOrder['location']['code'] ?? null,
                                        'location_name' => $purchaseOrder['location']['name'] ?? null,
                                        'account_title_id' => $purchaseOrder['account_title']['id'] ?? null,
                                        'account_title_code' => $purchaseOrder['account_title']['code'] ?? null,
                                        'account_title_name' => $purchaseOrder['account_title']['name'] ?? null,
                                    ]);
                                }
                            }
                        }
                    }

                    if (!empty($fields['job_order'])) {
                        foreach ($fields['job_order'] as $rr) {
                            foreach ($rr['rr_orders'] as $order) {
                                $rrTransactions = $transaction->receivedReceipts()->create([
                                    'rr_id' => $rr['rr_id'] ?? null,
                                    'rr_number' => $rr['rr_number'] ?? null,
                                    'item_code' => $order['item_code'] ?? null,
                                    'item_name' => $order['description'] ?? null,
                                    'price' => $order['price'] ?? 0,
                                    'reference_no' => $order['reference_no'] ?? null,
                                    'quantity' => $order['quantity_receive'] ?? 0,
                                    'uom_code' => $order['uom_code'] ?? null,
                                    'uom_name' => $order['uom_name'] ?? null,
                                ]);


                                foreach ($rr['job_orders'] as $jobOrder) {
                                    $rrTransactions->jobOrders()->create([
                                        'jo_number' => $jobOrder['jo_number'] ?? null,
                                        'jo_amount' => $jobOrder['jo_amount'] ?? 0,
                                        'consumed_amount' => $jobOrder['consumed_amount'] ?? 0,
                                        'remaining_amount' => $jobOrder['remaining_amount'] ?? 0,
                                        'jo_description' => $jobOrder['jo_description'] ?? null,
                                        'type_name' => $jobOrder['type_name'] ?? null,
                                        'company_id' => $jobOrder['company']['id'] ?? null,
                                        'company_code' => $jobOrder['company']['code'] ?? null,
                                        'company_name' => $jobOrder['company']['name'] ?? null,
                                        'business_unit_id' => $jobOrder['business_unit']['id'] ?? null,
                                        'business_unit_code' => $jobOrder['business_unit']['code'] ?? null,
                                        'business_unit_name' => $jobOrder['business_unit']['name'] ?? null,
                                        'department_id' => $jobOrder['department']['id'] ?? null,
                                        'department_code' => $jobOrder['department']['code'] ?? null,
                                        'department_name' => $jobOrder['department']['name'] ?? null,
                                        'unit_id' => $jobOrder['unit']['id'] ?? null,
                                        'unit_code' => $jobOrder['unit']['code'] ?? null,
                                        'unit_name' => $jobOrder['unit']['name'] ?? null,
                                        'sub_unit_id' => $jobOrder['sub_unit']['id'] ?? null,
                                        'sub_unit_code' => $jobOrder['sub_unit']['code'] ?? null,
                                        'sub_unit_name' => $jobOrder['sub_unit']['name'] ?? null,
                                        'location_id' => $jobOrder['location']['id'] ?? null,
                                        'location_code' => $jobOrder['location']['code'] ?? null,
                                        'location_name' => $jobOrder['location']['name'] ?? null,
                                        'account_title_id' => $jobOrder['account_title']['id'] ?? null,
                                        'account_title_code' => $jobOrder['account_title']['code'] ?? null,
                                        'account_title_name' => $jobOrder['account_title']['name'] ?? null,
                                    ]);
                                }
                            }
                        }
                    }

                    if (isset($transaction->transaction_id)) {
                        return $this->resultResponse("save", "Transaction", []);
                    }
                }

                if (!$isQty && !$isFull) {
                    //Partial

                    if (!$isNew) {

                        $fields["po_group"] = GenericMethod::ValidateIfPOExists(
                            $fields["po_group"],
                            $fields["document"]["company"]["id"]
                        );


                        $getAndValidatePOBalance = GenericMethod::getAndValidatePOBalance(
                            $fields,
                            $fields["document"]["company"]["id"],
                            last($fields["po_group"])["no"],
                            $fields["document"]["reference"]["amount"],
                            $fields["po_group"]
                        );

                        //---------------------------------------------------------------------------------------------------

                        //If the PO's is not unique and consider different condition

                        $lastRequestId = POBatch::where("po_no", last($fields["po_group"])["no"])
                            ->pluck("request_id")
                            ->last();

                        if ($lastRequestId) {
                            Transaction::where("company_id", $fields["document"]["company"]["id"])
                                ->where("request_id", $lastRequestId)
                                ->update([
                                    "is_not_editable" => true,
                                    "updated_at" => DB::raw("updated_at"),
                                ]);
                        }

                        if (gettype($getAndValidatePOBalance) == "object") {
                            return $this->resultResponse("invalid", "", $getAndValidatePOBalance);
                        }

                        if (gettype($getAndValidatePOBalance) == "array") {
                            //for new po
                            //Additional PO Validation
                            $new_po = $getAndValidatePOBalance["new_po_group"];
                            $po_total_amount = $getAndValidatePOBalance["po_total_amount"];
                            $balance_with_additional_total_po_amount = $getAndValidatePOBalance["balance"];

                            $transaction = GenericMethod::insertTransaction(
                                $transaction_id,
                                $po_total_amount,
                                $request_id,
                                $date_requested,
                                $fields,
                                $balance_with_additional_total_po_amount,
                                $isConfidential,
                                $isMc,
                                $isNew
                            );

                            $request_id = $transaction->id;

                            GenericMethod::insertPO(
                                $request_id,
                                $fields["po_group"],
                                $po_total_amount,
                                strtoupper($fields["document"]["payment_type"])
                            );

//                        POBatch::where("request_id", $request_id)
//                            ->where("po_no", reset($fields["po_group"])["no"])
//                            ->update([
//                                "is_modifiable" => true,
//                            ]);
//
//                        POBatch::where("request_id", $request_id)
//                            ->where("is_add", true)
//                            ->where("is_editable", true)
//                            ->update([
//                                "is_modifiable" => true,
//                            ]);

                            POBatch::where("request_id", $request_id)
                                ->where(function ($query) use ($fields) {
                                    $query->where("po_no", reset($fields["po_group"])["no"])
                                        ->orWhere(function ($query) {
                                            $query->where("is_add", true)
                                                ->where("is_editable", true);
                                        });
                                })
                                ->update([
                                    "is_modifiable" => true,
                                ]);

                            if (isset($transaction->transaction_id)) {
                                return $this->resultResponse("save", "Transaction", []);
                            }
                        }

                        $po_total_amount = GenericMethod::getPOTotalAmount($request_id, $fields["po_group"]);
                        $balance_po_ref_amount = $po_total_amount - $fields["document"]["reference"]["amount"];

                        if ($po_total_amount < $fields["document"]["reference"]["amount"]) {
                            $amountValdiation = GenericMethod::resultLaravelFormat("document.reference.no", [
                                "Insufficient PO balance.",
                            ]);

                            return $this->resultResponse("invalid", "", $amountValdiation);
                        }

                        if (isset($getAndValidatePOBalance)) {
                            $balance_po_ref_amount = $getAndValidatePOBalance;
                        }

                        $transaction = GenericMethod::insertTransaction(
                            $transaction_id,
                            $po_total_amount,
                            $request_id,
                            $date_requested,
                            $fields,
                            $balance_po_ref_amount,
                            $isConfidential,
                            $isMc,
                            $isNew
                        );

                        $request_id = $transaction->id;

                        GenericMethod::insertPO(
                            $request_id,
                            $fields["po_group"],
                            $po_total_amount,
                            strtoupper($fields["document"]["payment_type"])
                        );


                        POBatch::where("request_id", $request_id)
                            ->where("is_add", false)
                            ->where("is_editable", true)
                            ->update(["is_modifiable" => true]);

                        if (isset($transaction->transaction_id)) {
                            return $this->resultResponse("save", "Transaction", []);
                        }
                    } else {
                        $po_total_amount = GenericMethod::getPOTotalAmount($request_id, $fields["po_group"]);

                        $newTransaction = Transaction::create([
                            "transaction_id" => $transaction_id,
                            "users_id" => auth()->user()->id,
                            "id_prefix" => auth()->user()->id_prefix,
                            "id_no" => auth()->user()->id_no,
                            "first_name" => auth()->user()->first_name,
                            "middle_name" => auth()->user()->middle_name,
                            "last_name" => auth()->user()->last_name,
                            "suffix" => auth()->user()->suffix,
                            "department_details" => auth()->user()->department[0]["name"],

                            "document_id" => $fields["document"]["id"],
                            "category_id" => $fields["document"]["category"]["id"],
                            "category" => $fields["document"]["category"]["name"],
                            "company_id" => $fields["document"]["company"]["id"],
                            "company" => $fields["document"]["company"]["name"],
                            "department_id" => $fields["document"]["department"]["id"],
                            "department" => $fields["document"]["department"]["name"],
                            "location_id" => $fields["document"]["location"]["id"],
                            "location" => $fields["document"]["location"]["name"],
                            "supplier_id" => $fields["document"]["supplier"]["id"],
                            "supplier" => $fields["document"]["supplier"]["name"],
                            "payment_type" => $fields["document"]["payment_type"],
                            "document_date" => $fields["document"]["date"],
                            "remarks" => $fields["document"]["remarks"],
                            "document_type" => $fields["document"]["name"],

                            "po_total_amount" => $po_total_amount,

                            "referrence_type" => $fields["document"]["reference"]["type"],
                            "referrence_no" => $fields["document"]["reference"]["no"],
                            "referrence_amount" => $fields["document"]["reference"]["amount"],
                            "referrence_id" => $fields["document"]["reference"]["id"],
                            "is_allowable" => $fields["document"]["reference"]["allowable"],

                            "request_id" => 0,

                            "date_requested" => $date_requested,
                            "status" => "Pending",
                            "is_not_editable" => false,
                            "business_unit_id" => $fields["document"]["business_unit"]["id"] ?? null,
                            "business_unit" => $fields["document"]["business_unit"]["name"] ?? null,
                            "unit_id" => $fields["document"]["unit_id"]["id"] ?? null,
                            "unit" => $fields["document"]["unit"]["name"] ?? null,
                            "sub_unit_id" => $fields["document"]["sub_unit"]["id"] ?? null,
                            "sub_unit" => $fields["document"]["sub_unit"]["name"] ?? null,
                            "is_confidential" => $isConfidential,
                            "is_mc" => $isMc,
                            "is_new" => $isNew
                        ]);

                        $newTransaction->update([
                            "request_id" => $newTransaction->id,
                        ]);

                        $fields["po_group"] = GenericMethod::ValidateIfPOExists(
                            $fields["po_group"],
                            $fields["document"]["company"]["id"]
                        );

                        if (!empty($fields["po_group"])) {
                            foreach ($fields["po_group"] as $po) {
                                $newTransaction->po_details()->create([
                                    "request_id" => $newTransaction->id,
                                    "po_no" => $po["no"],
                                    "po_amount" => $po["amount"],
                                    "is_add" => $po["is_add"],
                                    "is_editable" => $po["is_editable"],
                                    "po_total_amount" => $po_total_amount,
                                    "previous_balance" => $request->po_balance,
                                    "rr_group" => $po["rr_no"],
                                ]);
                            }

                            $lastRequestId = POBatch::where("po_no", last($fields["po_group"])["no"])
                                ->pluck("request_id")
                                ->last();

                            if ($lastRequestId) {
                                Transaction::where("request_id", $lastRequestId)
                                    ->update([
                                        "is_not_editable" => true,
                                        "updated_at" => DB::raw("updated_at"),
                                    ]);
                            }
                        }


                        if (!empty($fields['purchase_order'])) {
                            foreach ($fields['purchase_order'] as $rr) {
                                foreach ($rr['rr_orders'] as $order) {
                                    $rrTransactions = $newTransaction->receivedReceipts()->create([
                                        'rr_id' => $rr['rr_id'] ?? null,
                                        'rr_number' => $rr['rr_number'] ?? null,
                                        'item_code' => $order['item_code'] ?? null,
                                        'item_name' => $order['item_name'] ?? null,
                                        'price' => $order['price'] ?? 0,
                                        'reference_no' => $order['reference_no'] ?? null,
                                        'quantity' => $order['quantity_receive'] ?? 0,
                                        'uom_code' => $order['uom_code'] ?? null,
                                        'uom_name' => $order['uom_name'] ?? null,
                                    ]);


                                    foreach ($rr['purchase_orders'] as $purchaseOrder) {
                                        $rrTransactions->purchaseOrders()->create([
                                            'po_number' => $purchaseOrder['po_number'] ?? null,
                                            'po_amount' => $purchaseOrder['po_amount'] ?? 0,
                                            'consumed_amount' => $purchaseOrder['consumed_amount'] ?? 0,
                                            'remaining_amount' => $purchaseOrder['remaining_amount'] ?? 0,
                                            'po_description' => $purchaseOrder['po_description'] ?? null,
                                            'type_name' => $purchaseOrder['type_name'] ?? null,
                                            'company_id' => $purchaseOrder['company']['id'] ?? null,
                                            'company_code' => $purchaseOrder['company']['code'] ?? null,
                                            'company_name' => $purchaseOrder['company']['name'] ?? null,
                                            'business_unit_id' => $purchaseOrder['business_unit']['id'] ?? null,
                                            'business_unit_code' => $purchaseOrder['business_unit']['code'] ?? null,
                                            'business_unit_name' => $purchaseOrder['business_unit']['name'] ?? null,
                                            'department_id' => $purchaseOrder['department']['id'] ?? null,
                                            'department_code' => $purchaseOrder['department']['code'] ?? null,
                                            'department_name' => $purchaseOrder['department']['name'] ?? null,
                                            'unit_id' => $purchaseOrder['unit']['id'] ?? null,
                                            'unit_code' => $purchaseOrder['unit']['code'] ?? null,
                                            'unit_name' => $purchaseOrder['unit']['name'] ?? null,
                                            'sub_unit_id' => $purchaseOrder['sub_unit']['id'] ?? null,
                                            'sub_unit_code' => $purchaseOrder['sub_unit']['code'] ?? null,
                                            'sub_unit_name' => $purchaseOrder['sub_unit']['name'] ?? null,
                                            'location_id' => $purchaseOrder['location']['id'] ?? null,
                                            'location_code' => $purchaseOrder['location']['code'] ?? null,
                                            'location_name' => $purchaseOrder['location']['name'] ?? null,
                                            'account_title_id' => $purchaseOrder['account_title']['id'] ?? null,
                                            'account_title_code' => $purchaseOrder['account_title']['code'] ?? null,
                                            'account_title_name' => $purchaseOrder['account_title']['name'] ?? null,
                                        ]);
                                    }
                                }
                            }
                        }

                        if (!empty($fields['job_order'])) {
                            foreach ($fields['job_order'] as $rr) {
                                foreach ($rr['rr_orders'] as $order) {
                                    $rrTransactions = $newTransaction->receivedReceipts()->create([
                                        'rr_id' => $rr['rr_id'] ?? null,
                                        'rr_number' => $rr['rr_number'] ?? null,
                                        'item_code' => $order['item_code'] ?? null,
                                        'item_name' => $order['description'] ?? null,
                                        'price' => $order['price'] ?? 0,
                                        'reference_no' => $order['reference_no'] ?? null,
                                        'quantity' => $order['quantity_receive'] ?? 0,
                                        'uom_code' => $order['uom_code'] ?? null,
                                        'uom_name' => $order['uom_name'] ?? null,
                                    ]);


                                    foreach ($rr['job_orders'] as $jobOrder) {
                                        $rrTransactions->jobOrders()->create([
                                            'jo_number' => $jobOrder['jo_number'] ?? null,
                                            'jo_amount' => $jobOrder['jo_amount'] ?? 0,
                                            'consumed_amount' => $jobOrder['consumed_amount'] ?? 0,
                                            'remaining_amount' => $jobOrder['remaining_amount'] ?? 0,
                                            'jo_description' => $jobOrder['jo_description'] ?? null,
                                            'type_name' => $jobOrder['type_name'] ?? null,
                                            'company_id' => $jobOrder['company']['id'] ?? null,
                                            'company_code' => $jobOrder['company']['code'] ?? null,
                                            'company_name' => $jobOrder['company']['name'] ?? null,
                                            'business_unit_id' => $jobOrder['business_unit']['id'] ?? null,
                                            'business_unit_code' => $jobOrder['business_unit']['code'] ?? null,
                                            'business_unit_name' => $jobOrder['business_unit']['name'] ?? null,
                                            'department_id' => $jobOrder['department']['id'] ?? null,
                                            'department_code' => $jobOrder['department']['code'] ?? null,
                                            'department_name' => $jobOrder['department']['name'] ?? null,
                                            'unit_id' => $jobOrder['unit']['id'] ?? null,
                                            'unit_code' => $jobOrder['unit']['code'] ?? null,
                                            'unit_name' => $jobOrder['unit']['name'] ?? null,
                                            'sub_unit_id' => $jobOrder['sub_unit']['id'] ?? null,
                                            'sub_unit_code' => $jobOrder['sub_unit']['code'] ?? null,
                                            'sub_unit_name' => $jobOrder['sub_unit']['name'] ?? null,
                                            'location_id' => $jobOrder['location']['id'] ?? null,
                                            'location_code' => $jobOrder['location']['code'] ?? null,
                                            'location_name' => $jobOrder['location']['name'] ?? null,
                                            'account_title_id' => $jobOrder['account_title']['id'] ?? null,
                                            'account_title_code' => $jobOrder['account_title']['code'] ?? null,
                                            'account_title_name' => $jobOrder['account_title']['name'] ?? null,
                                        ]);
                                    }
                                }
                            }
                        }

                        return $this->resultResponse("save", "Transaction", []);
                    }
                }

                break;

            case 9: //Auto Debit
                $is_duplicate = GenericMethod::validateAutoDebit(
                    $fields["document"]["company"]["id"],
                    $fields["document"]["supplier"]["id"],
                    $fields["document"]["date"]
                );

                if ($is_duplicate) {
                    return $this->resultResponse("invalid", "", $is_duplicate);
                }
                // GenericMethod::validate_debit_amount(
                //   $fields["document"]["amount"],
                //   $fields["autoDebit_group"],
                //   "Document amount and net of cwt amount is not equal."
                // );

//                isset($fields["autoDebit_group"])
//                    ? GenericMethod::validate_debit_amount(
//                    $fields["document"]["amount"],
//                    $fields["autoDebit_group"],
//                    "Document amount and net of cwt amount is not equal.")
//                    : null;

                $transaction = GenericMethod::insertTransaction($transaction_id, null, $request_id, $date_requested, $fields, null, $isConfidential, $isMc, $isNew);
                if (isset($transaction->transaction_id)) {
                    return $this->resultResponse("save", "Transaction", []);
                }
                break;
        }

//        return $this->resultResponse("save", "Transaction", []);
    }

    public function update(TransactionPostRequest $request, $id)
    {
        $fields = $request->validated();
        $date_requested = date("Y-m-d H:i:s");
        $request_id = $id;
        $currentTransaction = Transaction::findOrFail($id);
        $po_total_amount = 0;
        $balance_po_ref_amount = 0;

        switch ($fields["document"]["id"]) {
            case 1: //PAD
                switch ($request->input("document.payment_type")) {
                    case "Partial":
                        GenericMethod::documentNoValidationUpdate($request["document"]["no"], $id);

//                        if (empty($fields["po_group"])) {
//                            $errorMessage = GenericMethod::resultLaravelFormat("po_group", ["PO group required"]);
//                            return $this->resultResponse("invalid", "", $errorMessage);
//                        }

                        if (!$currentTransaction->is_new) {
                            if ($currentTransaction->is_not_editable == 1 && $currentTransaction->is_new == null) {
                                $updateData = [
                                    "document_no" => data_get($fields, "document.no"),
                                    "document_date" => data_get($fields, "document.date"),
                                    "remarks" => data_get($fields, "document.remarks"),
                                    "company" => data_get($fields, "document.company.name"),
                                    "department_id" => data_get($fields, "document.department.id"),
                                    "department" => data_get($fields, "document.department.name"),
                                    "location_id" => data_get($fields, "document.location.id"),
                                    "location" => data_get($fields, "document.location.name"),
                                    // "referrence_id" => data_get($fields, "document.reference.id"),
                                    // "referrence_type" => data_get($fields, "document.reference.type"),
                                    // "referrence_no" => data_get($fields, "document.reference.no"),
                                    "category_id" => data_get($fields, "document.category.id"),
                                    "category" => data_get($fields, "document.category.name"),
                                    "business_unit_id" => data_get($fields, "document.business_unit.id"),
                                    "business_unit" => data_get($fields, "document.business_unit.name"),
                                    "sub_unit_id" => data_get($fields, "document.sub_unit.id"),
                                    "sub_unit" => data_get($fields, "document.sub_unit.name"),
                                ];

                                if ($currentTransaction->status == "tag-return") {
                                    $updateData["status"] = "Pending";
                                    $updateData["state"] = "pending";
                                }

                                $currentTransaction->update($updateData, ["timestamps" => false]);
                                $poGroups = data_get($fields, "po_group");

                                for ($i = 0; $i < count($poGroups); $i++) {
                                    POBatch::where("request_id", $currentTransaction->request_id)
                                        ->where("po_no", $poGroups[$i]["no"])
                                        ->update([
                                            "rr_group" => $poGroups[$i]["rr_no"],
                                        ]);
                                }

                                return $this->resultResponse("update", "Transaction", []);
                            }

                            $fields["po_group"] = GenericMethod::ValidateIfPOExists(
                                $fields["po_group"],
                                $fields["document"]["company"]["id"],
                                $id
                            );

                            $getAndValidatePOBalance = GenericMethod::getAndValidatePOBalance(
                                $fields,
                                $fields["document"]["company"]["id"],
                                last($fields["po_group"])["no"],
                                $fields["document"]["amount"],
                                $fields["po_group"],
                                $id
                            );

                            if (gettype($getAndValidatePOBalance) == "object") {
                                return $this->resultResponse("invalid", "", $getAndValidatePOBalance);
                            }

                            if (gettype($getAndValidatePOBalance) == "array") {
                                //Additional PO Validation
                                $new_po = $getAndValidatePOBalance["new_po_group"];
                                $po_total_amount = $getAndValidatePOBalance["po_total_amount"];
                                $balance_with_additional_total_po_amount = $getAndValidatePOBalance["balance"];

                                $changes = GenericMethod::getTransactionChanges($request_id, $request, $id);
                                GenericMethod::updatePO(
                                    $request_id,
                                    $fields["po_group"],
                                    $po_total_amount,
                                    strtoupper($fields["document"]["payment_type"]),
                                    $id
                                );

                                $transaction = GenericMethod::updateTransaction(
                                    $id,
                                    $po_total_amount,
                                    $request_id,
                                    $date_requested,
                                    $request,
                                    $balance_with_additional_total_po_amount,
                                    $changes
                                );
                                if (isset($transaction->transaction_id)) {
                                    return $this->resultResponse("update", "Transaction", []);
                                }
                            }

                            $po_total_amount = GenericMethod::getPOTotalAmount($request_id, $fields["po_group"]);
                            $balance_po_ref_amount = $po_total_amount - $fields["document"]["amount"];

                            if (isset($getAndValidatePOBalance)) {
                                $balance_po_ref_amount = $getAndValidatePOBalance;
                            }

                            $changes = GenericMethod::getTransactionChanges($request_id, $request, $id);

                            GenericMethod::updatePO(
                                $request_id,
                                $fields["po_group"],
                                $po_total_amount,
                                strtoupper($fields["document"]["payment_type"]),
                                $id
                            );

                            $transaction = GenericMethod::updateTransaction(
                                $id,
                                $po_total_amount,
                                $request_id,
                                $date_requested,
                                $request,
                                $balance_po_ref_amount,
                                $changes
                            );
                            if (isset($transaction->transaction_id)) {
                                return $this->resultResponse("update", "Transaction", []);
                            }
                        } else {
                            $po_total_amount = GenericMethod::getPOTotalAmount($request_id, $fields["po_group"]);
                            $balance_po_ref_amount = $po_total_amount - $fields["document"]["amount"];
                            $changes = GenericMethod::getTransactionChanges($request_id, $request, $id);

                            $transaction = GenericMethod::updateTransaction(
                                $id,
                                $po_total_amount,
                                $request_id,
                                $date_requested,
                                $request,
                                $balance_po_ref_amount,
                                $changes
                            );

                            if (!empty($fields["po_group"])) {
                                foreach ($fields["po_group"] as $po) {
                                    $transaction->po_details()->update([
                                        "request_id" => $transaction->id,
                                        "po_no" => $po["no"],
                                        "po_amount" => $po["amount"],
//                                    "is_add" => $po["is_add"],
//                                    "is_editable" => $po["is_editable"],
                                        "previous_balance" => $request->po_balance,
                                        "po_total_amount" => $po_total_amount,
                                        "rr_group" => $po["rr_no"],
                                    ]);
                                }
                            }

                        }
                        break;

                    default:
                        GenericMethod::documentNoValidationUpdate($request["document"]["no"], $id);

                        if (!empty($fields["po_group"])) {
//                            if (empty($fields["po_group"])) {
//                                $errorMessage = GenericMethod::resultLaravelFormat("po_group", ["PO group required"]);
//                                return $this->resultResponse("invalid", "", $errorMessage);
//                            }

                            $duplicatePO = GenericMethod::validatePOFullUpdate(
                                $fields["document"]["company"]["id"],
                                $fields["po_group"],
                                $id
                            );
                            if (isset($duplicatePO)) {
                                return $this->resultResponse("invalid", "", $duplicatePO);
                            }

                            $po_total_amount = GenericMethod::getPOTotalAmount($request_id, $fields["po_group"]);

                            $errorMessage = GenericMethod::validateWith1PesoDifference(
                                "po_group.amount",
                                "Document",
                                $fields["document"]["amount"],
                                $po_total_amount
                            );
                            if (!empty($errorMessage)) {
                                return GenericMethod::resultResponse("invalid", "", $errorMessage);
                            }
                        }

                        $changes = GenericMethod::getTransactionChanges($request_id, $request, $id);

                        if (!empty($fields["po_group"])) {
                            GenericMethod::updatePO(
                                $request_id,
                                $fields["po_group"],
                                $po_total_amount,
                                strtoupper($fields["document"]["payment_type"]),
                                $id
                            );
                        }

                        $transaction = GenericMethod::updateTransaction(
                            $id,
                            $po_total_amount,
                            $request_id,
                            $date_requested,
                            $request,
                            0,
                            $changes
                        );

                        if ($transaction == "Nothing Has Changed") {
                            return $this->resultResponse("nothing-has-changed", "Transaction", []);
                        }
//                        if (isset($transaction->transaction_id)) {
//                            return $this->resultResponse("update", "Transaction", []);
//                        }
                        break;
                }
                break;

            case 5: //Contractor's Billing
//                if (empty($fields["po_group"])) {
//                    $errorMessage = GenericMethod::resultLaravelFormat("po_group", ["PO group required"]);
//                    return $this->resultResponse("invalid", "", $errorMessage);
//                }

                $transaction_id = isset($transaction_id) ? $transaction_id : null;
                $capex_no = isset($fields["document"]["capex_no"]) ? $fields["document"]["capex_no"] : null;

                GenericMethod::billingValidation(
                    $fields["document"]["company"]["id"],
                    $fields["document"]["department"]["id"],
                    $fields["document"]["location"]["id"],
                    $fields["document"]["supplier"]["id"],
                    $fields["document"]["category"]["id"],
                    $capex_no,
                    $id
                );
                $duplicatePO = GenericMethod::validatePOFullUpdate(
                    $fields["document"]["company"]["id"],
                    $fields["po_group"],
                    $id
                );

                if (isset($duplicatePO)) {
                    return $this->resultResponse("invalid", "", $duplicatePO);
                }

                if (!empty($fields['po_group'])) {
                            $duplicatePO = GenericMethod::validatePOFull($fields["document"]["company"]["id"], $fields["po_group"]);

                            if (isset($duplicatePO)) {
                                return $this->resultResponse("invalid", "", $duplicatePO);
                            }

                            $po_total_amount = GenericMethod::getPOTotalAmount($request_id, $fields["po_group"]);

                            $errorMessage = GenericMethod::validateWith1PesoDifference(
                                "po_group.amount",
                                "Document",
                                $fields["document"]["amount"],
                                $po_total_amount
                            );

                            if (!empty($errorMessage)) {
                                return GenericMethod::resultResponse("invalid", "", $errorMessage);
                            }
                        }

                $changes = GenericMethod::getTransactionChanges($request_id, $request, $id);
//                GenericMethod::updatePO(
//                    $request_id,
//                    $fields["po_group"],
//                    $po_total_amount,
//                    strtoupper($fields["document"]["payment_type"]),
//                    $id
//                );

                if (!$currentTransaction->is_new) {
                    GenericMethod::updatePO(
                        $request_id,
                        $fields["po_group"],
                        $po_total_amount,
                        strtoupper($fields["document"]["payment_type"]),
                        $id
                    );
                }

                $transaction = GenericMethod::updateTransaction(
                    $id,
                    $po_total_amount,
                    $request_id,
                    $date_requested,
                    $request,
                    0,
                    $changes
                );

                if ($transaction == "Nothing Has Changed") {
                    return $this->resultResponse("nothing-has-changed", "Transaction", []);
                }
//                if (isset($transaction->transaction_id)) {
//                    return $this->resultResponse("update", "Transaction", []);
//                }
                break;

            case 2: //PRM Common
                $po_total_amount = null;
                GenericMethod::documentNoValidationUpdate($request["document"]["no"], $id);

                $changes = GenericMethod::getTransactionChanges($request_id, $request, $id);

                $transaction = GenericMethod::updateTransaction(
                    $id,
                    $po_total_amount,
                    $request_id,
                    $date_requested,
                    $request,
                    0,
                    $changes
                );

                if ($transaction == "Nothing Has Changed") {
                    return $this->resultResponse("nothing-has-changed", "Transaction", []);
                }
//                if (isset($transaction->transaction_id)) {
//                    return $this->resultResponse("update", "Transaction", []);
//                }
                break;

            case 3: //PRM Multiple
                $prms = Transaction::where('transaction_id', $request->input('transaction.no'))->pluck('tag_no')->toArray();
                $prms = collect(array_filter($prms));

                if ($prms->isEmpty()) {
                    $po_total_amount = null;
                    GenericMethod::documentNoValidationUpdate($request["document"]["no"], $id, $request["transaction"]["no"]);
                    $changes = GenericMethod::getTransactionChanges($request_id, $request, $id);
                    $transaction = GenericMethod::updateTransaction(
                        $id,
                        $po_total_amount,
                        $request_id,
                        $date_requested,
                        $request,
                        0,
                        $changes
                    );
                } else {
                    Transaction::where('transaction_id', $request->input('transaction.no'))->update([
                        'document_no' => data_get($request, 'document.no'),
                        'remarks' => data_get($request, 'document.remarks'),
                        'document_amount' => data_get($request, 'document.amount'),
                        'batch_no' => data_get($request, 'document.batch_no'),
                        'company_id' => data_get($request, 'document.company.id'),
                        'company' => data_get($request, 'document.company.name'),
                        'department_id' => data_get($request, 'document.department.id'),
                        'department' => data_get($request, 'document.department.name'),
                        'location_id' => data_get($request, 'document.location.id'),
                        'location' => data_get($request, 'document.location.name'),
                        'release_date' => data_get($request, 'document.release_date'),
                    ]);
                }

                // if ($transaction->status == "tag-return") {
                //   $prm_group = $fields["prm_group"];
                //   switch ($transaction->category) {
                //     case "rental":
                //       $updateDetails = [
                //         "total_gross" => null,
                //         "total_cwt" => null,
                //         "total_net" => null,
                //         "period_covered" => null,
                //         "prm_multiple_from" => null,
                //         "prm_multiple_to" => null,
                //         "cheque_date" => null,
                //         "gross_amount" => null,
                //         "witholding_tax" => null,
                //         "net_amount" => null,
                //       ];

                //       // Transaction::where("transaction_id", $transaction->transaction_id)
                //       //   ->where("state", "!=", "void")
                //       //   ->update($updateDetails);

                //       $errors = [];
                //       $error_date_format = [];
                //       $error_period_covered = [];
                //       $error_multiple_cheque = [];
                //       $error_amount_per_line = [];
                //       $total_gross = array_sum(array_column($prm_group, "gross_amount"));
                //       $total_cwt = array_sum(array_column($prm_group, "wht"));
                //       $total_net = array_sum(array_column($prm_group, "net_of_amount"));
                //       $total_witholding_and_net = $total_cwt + $total_net;
                //       $cheque_dates_array = array_column($prm_group, "cheque_date");
                //       $period_covered_array = array_column($prm_group, "period_covered");

                //       $message_if_error = "Document Amount and Total Gross amount not equal.";
                //       $validate_document_amount = GenericMethod::validate_document_amount(
                //         $fields["document"]["amount"],
                //         $total_gross,
                //         $message_if_error
                //       );
                //       if ($validate_document_amount) {
                //         return $validate_document_amount;
                //       }

                //       $error_date_format = GenericMethod::validate_prm_date_range_format($prm_group, $errors);
                //       $error_period_covered = GenericMethod::validate_period_covered($period_covered_array, $errors);
                //       $error_multiple_cheque = GenericMethod::validate_multiple_cheque_dates($cheque_dates_array, $errors);
                //       // $error_amount_per_line = GenericMethod::validate_amount_per_line($prm_group, $errors);
                //       $error_duplicate_transaction = GenericMethod::validate_duplicate_prm_multiple_transaction(
                //         $prm_group,
                //         $fields
                //       );
                //       $errors = array_merge(
                //         $error_date_format,
                //         $error_period_covered,
                //         $error_multiple_cheque,
                //         $error_amount_per_line,
                //         $error_duplicate_transaction
                //       );

                //       // if ($errors) {
                //       //   $errors = collect($errors)
                //       //     ->sortBy(["line", "description"])
                //       //     ->values();
                //       //   $error_list = $errors
                //       //     ->unique(function ($item) {
                //       //       return $item["line"] . $item["description"];
                //       //     })
                //       //     ->values();
                //       //   // $error_list =  collect($errors)->unique('description')->all();
                //       //   return GenericMethod::resultResponse("upload-error", "", $error_list);
                //       // }

                //       // PROCEED RENTAL
                //       foreach ($prm_group as $key => $prm_batch) {
                //         $period_covered = isset($prm_batch["period_covered"]) ? $prm_batch["period_covered"] : null;
                //         $period_covered_array = explode("-", $period_covered);
                //         $prm_multiple_from = date("Y-m-d", strtotime(trim($period_covered_array[0])));
                //         $prm_multiple_to = date("Y-m-d", strtotime(trim($period_covered_array[1])));
                //         $cheque_date = isset($prm_batch["cheque_date"]) ? $prm_batch["cheque_date"] : null;
                //         $gross_amount = isset($prm_batch["gross_amount"]) ? $prm_batch["gross_amount"] : null;
                //         $witholding_tax = isset($prm_batch["wht"]) ? $prm_batch["wht"] : null;
                //         $net_amount = isset($prm_batch["net_of_amount"]) ? $prm_batch["net_of_amount"] : null;
                //         // $temporary_request_id = $request_id + $key;

                //         Transaction::where("transaction_id", $transaction->transaction_id)
                //           ->where("state", "!=", "void")
                //           ->update([
                //             "document_id" => $fields["document"]["id"],
                //             "category_id" => $fields["document"]["category"]["id"],
                //             "category" => $fields["document"]["category"]["name"],
                //             "company_id" => $fields["document"]["company"]["id"],
                //             "company" => $fields["document"]["company"]["name"],
                //             "department_id" => $fields["document"]["department"]["id"],
                //             "department" => $fields["document"]["department"]["name"],
                //             "location_id" => $fields["document"]["location"]["id"],
                //             "location" => $fields["document"]["location"]["name"],
                //             "supplier_id" => $fields["document"]["supplier"]["id"],
                //             "supplier" => $fields["document"]["supplier"]["name"],
                //             "payment_type" => $fields["document"]["payment_type"],
                //             "document_no" => $fields["document"]["no"],
                //             "document_date" => isset($fields["document"]["date"]) ? $fields["document"]["date"] : null,
                //             "document_amount" => $fields["document"]["amount"],
                //             "remarks" => $fields["document"]["remarks"],
                //             "document_type" => $fields["document"]["name"],
                //             "po_total_amount" => $po_total_amount,
                //             // "request_id" => $temporary_request_id ? $temporary_request_id : null,
                //             // "request_id" => isset($temporary_request_id) ? $temporary_request_id : null,

                //             // "date_requested" => $date_requested,
                //             "status" => "Pending",
                //             // "period_covered" => $period_covered ? $period_covered : null,
                //             // "prm_multiple_from" => $prm_multiple_from ? $prm_multiple_from : null,
                //             // "prm_multiple_to" => $prm_multiple_to ? $prm_multiple_to : null,
                //             // "cheque_date" => $cheque_date ? $cheque_date : null,
                //             // "gross_amount" => $gross_amount ? $gross_amount : null,
                //             // "witholding_tax" => $witholding_tax ? $witholding_tax : null,
                //             // "net_amount" => $net_amount ? $net_amount : null,
                //             // "total_gross" => $total_gross ? $total_gross : null,
                //             // "total_cwt" => $total_cwt ? $total_cwt : null,
                //             // "total_net" => $total_net ? $total_net : null,
                //           ]);
                //       }
                //       break;
                //   }
                // } else {
                //   GenericMethod::documentNoValidationUpdate($request["document"]["no"], $id, $request["transaction"]["no"]);
                //   $changes = GenericMethod::getTransactionChanges($request_id, $request, $id);
                //   $transaction = GenericMethod::updateTransaction(
                //     $id,
                //     $po_total_amount,
                //     $request_id,
                //     $date_requested,
                //     $request,
                //     0,
                //     $changes
                //   );

                //   // return $transaction;

                //   if ($transaction == "Nothing Has Changed") {
                //     return $this->resultResponse("nothing-has-changed", "Transaction", []);
                //   } elseif ($transaction == "On Going Transaction") {
                //     return GenericMethod::resultResponse("ongoing", "", []);
                //   }

                //   if (isset($transaction->transaction_id)) {
                //     return $this->resultResponse("update", "Transaction", []);
                //   }
                // }

                break;

            case 6: //Utilities
                $po_total_amount = null;
                $duplicateUtilities = GenericMethod::validateTransactionByDateRange(
                    $fields["document"]["from"],
                    $fields["document"]["to"],
                    $fields["document"]["company"]["id"],
                    $fields["document"]["department"]["id"],
                    $fields["document"]["supplier"]["id"],
                    $fields["document"]["utility"]["location"]["id"],
                    $fields["document"]["utility"]["category"]["name"],
                    $fields["document"]["utility"]["account_no"]["no"],
                    data_get($fields, "document.utility.receipt_no"),
                    $id
                );

                if (isset($duplicateUtilities)) {
                    return $this->resultResponse("invalid", "", $duplicateUtilities);
                }

                $changes = GenericMethod::getTransactionChanges($request_id, $request, $id);

                $transaction = GenericMethod::updateTransaction(
                    $id,
                    $po_total_amount,
                    $request_id,
                    $date_requested,
                    $request,
                    0,
                    $changes
                );

                if (isset($fields["service_group"])) {
                    $transaction->service_batches()->delete();
                    foreach ($fields["service_group"] as $services_group) {
                        $transaction->service_batches()->create([
                            'company_name' => $services_group['company'],
                            'business_unit_name' => $services_group['business_unit'],
                            'department_name' => $services_group['department'],
                            'sub_unit_name' => $services_group['sub_unit'],
                            'location_name' => $services_group['location'],
                            'amount' => $services_group['amount'],
                        ]);
                    }
                }

//                if (isset($transaction->transaction_id)) {
//                    return $this->resultResponse("update", "Transaction", []);
//                }
                break;

            case 7: //Payroll
                $po_total_amount = null;
                $duplicatePayroll = GenericMethod::validatePayroll(
                    $fields["document"]["from"],
                    $fields["document"]["to"],
                    $fields["document"]["company"]["id"],
                    $fields["document"]["department"]["id"],
                    $fields["document"]["location"]["id"],
                    $fields["document"]["supplier"]["id"],
                    $fields["document"]["payroll"]["clients"],
                    $fields["document"]["payroll"]["type"],
                    $fields["document"]["payroll"]["category"]["name"],
                    $fields["document"]["payroll"]["control_no"],
                    $id
                );

                if (isset($duplicatePayroll)) {
                    return $this->resultResponse("invalid", "", $duplicatePayroll);
                }
                $changes = GenericMethod::getTransactionChanges($request_id, $request, $id);
                GenericMethod::updateClients($request_id, $fields["document"]["payroll"]["clients"], $id);
                $transaction = GenericMethod::updateTransaction(
                    $id,
                    $po_total_amount,
                    $request_id,
                    $date_requested,
                    $request,
                    0,
                    $changes
                );
//                if (isset($transaction->transaction_id)) {
//                    return $this->resultResponse("update", "Transaction", []);
//                }
                break;

            case 8: //PCF
                $po_total_amount = null;
                $duplicatePCF = GenericMethod::validatePCF(
                    $fields["document"]["pcf_batch"]["name"],
                    $fields["document"]["pcf_batch"]["date"],
                    $fields["document"]["pcf_batch"]["letter"],
                    $fields["document"]["company"]["id"],
                    $fields["document"]["supplier"]["id"],
                    $fields["document"]["department"]["id"],
                    $fields["document"]["location"]["id"],
                    $id
                );
                if (isset($duplicatePCF)) {
                    return $this->resultResponse("invalid", "", $duplicatePCF);
                }
                $changes = GenericMethod::getTransactionChanges($request_id, $request, $id);

                $transaction = GenericMethod::updateTransaction(
                    $id,
                    $po_total_amount,
                    $request_id,
                    $date_requested,
                    $request,
                    0,
                    $changes
                );
//                if (isset($transaction->transaction_id)) {
//                    return $this->resultResponse("update", "Transaction", []);
//                }
                break;

            case 4: //Receipt
                $isFull = strtoupper($fields["document"]["payment_type"]) === "FULL";

//                if (empty($fields["po_group"])) {
//                    $errorMessage = GenericMethod::resultLaravelFormat("po_group", ["PO group required"]);
//                    return $this->resultResponse("invalid", "", $errorMessage);
//                }

                $duplicateRef = GenericMethod::validateReferenceNo($fields, $id);
                if (isset($duplicateRef)) {
                    return $this->resultResponse("invalid", "", $duplicateRef);
                }

                if ($isFull) {
                    if (!empty($fields["po_group"])) {
                        $duplicatePO = GenericMethod::validatePOFullUpdate(
                            $fields["document"]["company"]["id"],
                            $fields["po_group"],
                            $id
                        );
                        if (isset($duplicatePO)) {
                            return $this->resultResponse("invalid", "", $duplicatePO);
                        }

                        $po_total_amount = GenericMethod::getPOTotalAmount($request_id, $fields["po_group"]);
                        if (!isset($fields["document"]["reference"]["allowable"])) {
                            $errorMessage = GenericMethod::validateWith1PesoDifference(
                                "document.amount",
                                "Reference",
                                $fields["document"]["reference"]["amount"],
                                $po_total_amount
                            );
                            if (!empty($errorMessage)) {
                                return GenericMethod::resultResponse("invalid", "", $errorMessage);
                            }
                        }
                    }

                    $changes = GenericMethod::getTransactionChanges($request_id, $request, $id);

                    if (!empty($fields["po_group"])) {
                        GenericMethod::updatePO(
                            $request_id,
                            $fields["po_group"],
                            $po_total_amount,
                            strtoupper($fields["document"]["payment_type"]),
                            $id
                        );
                    }

                    $transaction = GenericMethod::updateTransaction(
                        $id,
                        $po_total_amount,
                        $request_id,
                        $date_requested,
                        $request,
                        0,
                        $changes
                    );
//                    if (isset($transaction->transaction_id)) {
//                        return $this->resultResponse("update", "Transaction", []);
//                    }
                }

                $currentTransaction = Transaction::findOrFail($id);

                if ($currentTransaction->is_not_editable == 1 && $currentTransaction->is_new == null) {
                    $updateData = [
                        "document_date" => data_get($fields, "document.date"),
                        "remarks" => data_get($fields, "document.remarks"),
                        "company" => data_get($fields, "document.company.name"),
                        "department_id" => data_get($fields, "document.department.id"),
                        "department" => data_get($fields, "document.department.name"),
                        "location_id" => data_get($fields, "document.location.id"),
                        "location" => data_get($fields, "document.location.name"),
                        "referrence_id" => data_get($fields, "document.reference.id"),
                        "referrence_type" => data_get($fields, "document.reference.type"),
                        "referrence_no" => data_get($fields, "document.reference.no"),
                        "category_id" => data_get($fields, "document.category.id"),
                        "category" => data_get($fields, "document.category.name"),
                    ];

                    if ($currentTransaction->status == "tag-return") {
                        $updateData["status"] = "Pending";
                        $updateData["state"] = "pending";
                    }

                    $poGroups = data_get($fields, "po_group");

                    for ($i = 0; $i < count($poGroups); $i++) {
                        POBatch::where("request_id", $currentTransaction->request_id)
                            ->where("po_no", $poGroups[$i]["no"])
                            ->update([
                                "rr_group" => $poGroups[$i]["rr_no"],
                            ]);
                    }

                    $currentTransaction->update($updateData, ["timestamps" => false]);

                    return $this->resultResponse("update", "Transaction", []);
                }

                if (!$currentTransaction->is_new) {
                    if (isset($fields["po_group"])) {
                        $fields["po_group"] = GenericMethod::ValidateIfPOExists(
                            $fields["po_group"],
                            $fields["document"]["company"]["id"],
                            $id
                        );

                        $getAndValidatePOBalance = GenericMethod::getAndValidatePOBalance(
                            $fields,
                            $fields["document"]["company"]["id"],
                            last($fields["po_group"])["no"],
                            $fields["document"]["reference"]["amount"],
                            $fields["po_group"],
                            $id
                        );

                        if (gettype($getAndValidatePOBalance) == "object") {
                            return $this->resultResponse("invalid", "", $getAndValidatePOBalance);
                        }
                    }

                    if (gettype($getAndValidatePOBalance) == "array") {
                        //Additional PO Validation
                        $new_po = $getAndValidatePOBalance["new_po_group"];
                        $po_total_amount = $getAndValidatePOBalance["po_total_amount"];
                        $balance_with_additional_total_po_amount = $getAndValidatePOBalance["balance"];

                        $changes = GenericMethod::getTransactionChanges($request_id, $request, $id);
                        GenericMethod::updatePO(
                            $request_id,
                            $fields["po_group"],
                            $po_total_amount,
                            strtoupper($fields["document"]["payment_type"]),
                            $id
                        );

                        $transaction = GenericMethod::updateTransaction(
                            $id,
                            $po_total_amount,
                            $request_id,
                            $date_requested,
                            $request,
                            $balance_with_additional_total_po_amount,
                            $changes
                        );
                        if (isset($transaction->transaction_id)) {
                            return $this->resultResponse("update", "Transaction", []);
                        }
                    }

                    $po_total_amount = GenericMethod::getPOTotalAmount($request_id, $fields["po_group"]);
                    $balance_po_ref_amount = $po_total_amount - $fields["document"]["reference"]["amount"];

                    if ($po_total_amount < $fields["document"]["reference"]["amount"]) {
                        if (!$fields["document"]["reference"]["allowable"]) {
                            $amountValdiation = GenericMethod::resultLaravelFormat("document.reference.no", [
                                "Insufficient PO balance.",
                            ]);
                            return $this->resultResponse("invalid", "", $amountValdiation);
                        }
                    }

                    if (isset($getAndValidatePOBalance)) {
                        $balance_po_ref_amount = $getAndValidatePOBalance;
                    }

                    $changes = GenericMethod::getTransactionChanges($request_id, $request, $id);
                    if (!empty($fields['po_group'])) {
                        GenericMethod::updatePO(
                            $request_id,
                            $fields["po_group"],
                            $po_total_amount,
                            strtoupper($fields["document"]["payment_type"]),
                            $id
                        );
                    }

                    $transaction = GenericMethod::updateTransaction(
                        $id,
                        $po_total_amount,
                        $request_id,
                        $date_requested,
                        $request,
                        $balance_po_ref_amount,
                        $changes
                    );
                    if (isset($transaction->transaction_id)) {
                        return $this->resultResponse("update", "Transaction", []);
                    }
                } else {
                    if (!empty($fields['po_group'])) {
                        $po_total_amount = GenericMethod::getPOTotalAmount($request_id, $fields["po_group"]);
                        $balance_po_ref_amount = $po_total_amount - $fields["document"]["reference"]["amount"];
                    }

                    $changes = GenericMethod::getTransactionChanges($request_id, $request, $id);

                    $transaction = GenericMethod::updateTransaction(
                        $id,
                        $po_total_amount,
                        $request_id,
                        $date_requested,
                        $request,
                        $balance_po_ref_amount,
                        $changes
                    );

                    if (!empty($fields["po_group"])) {
                        foreach ($fields["po_group"] as $po) {
                            $transaction->po_details()->update([
                                "request_id" => $transaction->id,
                                "po_no" => $po["no"],
                                "po_amount" => $po["amount"],
//                            "is_add" => $po["is_add"],
//                            "is_editable" => $po["is_editable"],
                                "previous_balance" => $request->po_balance,
                                "po_total_amount" => $po_total_amount,
                                "rr_group" => $po["rr_no"],
                            ]);
                        }
                    }

//                    if (isset($transaction->transaction_id)) {
//                        return $this->resultResponse("update", "Transaction", []);
//                    }
                }

                break;

            case 9: //Auto Debit
                $po_total_amount = null;
                $is_duplicate = GenericMethod::validateAutoDebit(
                    $fields["document"]["company"]["id"],
                    $fields["document"]["supplier"]["id"],
                    $fields["document"]["date"],
                    $id
                );
                if ($is_duplicate) {
                    return $this->resultResponse("invalid", "", $is_duplicate);
                }

                $changes = GenericMethod::getTransactionChanges($request_id, $request, $id);

                if (isset($fields["autoDebit_group"])) {
//                    GenericMethod::update_debit_attachment($request_id, $fields["autoDebit_group"], $id);
                    $transaction = Transaction::find($id);
                    $transaction->auto_debit()->delete();

                    foreach ($fields["autoDebit_group"] as $autoDebit) {
                        $transaction->auto_debit()->create([
                            'pn_no' => $autoDebit['pn_no'],
                            'interest_from' => $autoDebit['interest_from'],
                            'interest_to' => $autoDebit['interest_to'],
                            'outstanding_amount' => $autoDebit['outstanding_amount'],
                            'interest_rate' => $autoDebit['interest_rate'],
                            'no_of_days' => $autoDebit['no_of_days'],
                            'principal_amount' => $autoDebit['principal_amount'],
                            'interest_due' => $autoDebit['interest_due'],
                            'cwt' => $autoDebit['cwt'],
                            'dst' => $autoDebit['dst']
                        ]);
                    }
                }

                $transaction = GenericMethod::updateTransaction(
                    $id,
                    $po_total_amount,
                    $request_id,
                    $date_requested,
                    $request,
                    0,
                    $changes
                );

                if ($transaction == "Nothing Has Changed") {
                    return $this->resultResponse("nothing-has-changed", "Transaction", []);
                }
//                if (isset($transaction->transaction_id)) {
//                    return $this->resultResponse("update", "Transaction", []);
//                }
                break;
        }

//        if (!empty($fields['purchase_order'])) {
//            $currentTransaction->receivedReceipts()->each(function ($purchaseOrder) {
//                $purchaseOrder->purchaseOrders()->forceDelete();
//            });
//            $currentTransaction->receivedReceipts()->forceDelete();
//
//            foreach ($fields['purchase_order'] as $rr) {
//                foreach ($rr['rr_orders'] as $order) {
//                    $rrTransactions = $currentTransaction->receivedReceipts()->create([
//                        'rr_id' => $rr['rr_id'] ?? null,
//                        'rr_number' => $rr['rr_number'] ?? null,
//                        'item_code' => $order['item_code'] ?? null,
//                        'item_name' => $order['item_name'] ?? null,
//                        'price' => $order['price'] ?? 0,
//                        'reference_no' => $order['reference_no'] ?? null,
//                        'quantity' => $order['quantity_receive'] ?? 0,
//                        'uom_code' => $order['uom_code'] ?? null,
//                        'uom_name' => $order['uom_name'] ?? null,
//                    ]);
//
//
//                    foreach ($rr['purchase_orders'] as $purchaseOrder) {
//                        $rrTransactions->purchaseOrders()->create([
//                            'po_number' => $purchaseOrder['po_number'] ?? null,
//                            'po_description' => $purchaseOrder['po_description'] ?? null,
//                            'type_name' => $purchaseOrder['type_name'] ?? null,
//                            'company_id' => $purchaseOrder['company']['id'] ?? null,
//                            'company_code' => $purchaseOrder['company']['code'] ?? null,
//                            'company_name' => $purchaseOrder['company']['name'] ?? null,
//                            'business_unit_id' => $purchaseOrder['business_unit']['id'] ?? null,
//                            'business_unit_code' => $purchaseOrder['business_unit']['code'] ?? null,
//                            'business_unit_name' => $purchaseOrder['business_unit']['name'] ?? null,
//                            'department_id' => $purchaseOrder['department']['id'] ?? null,
//                            'department_code' => $purchaseOrder['department']['code'] ?? null,
//                            'department_name' => $purchaseOrder['department']['name'] ?? null,
//                            'unit_id' => $purchaseOrder['unit']['id'] ?? null,
//                            'unit_code' => $purchaseOrder['unit']['code'] ?? null,
//                            'unit_name' => $purchaseOrder['unit']['name'] ?? null,
//                            'sub_unit_id' => $purchaseOrder['sub_unit']['id'] ?? null,
//                            'sub_unit_code' => $purchaseOrder['sub_unit']['code'] ?? null,
//                            'sub_unit_name' => $purchaseOrder['sub_unit']['name'] ?? null,
//                            'location_id' => $purchaseOrder['location']['id'] ?? null,
//                            'location_code' => $purchaseOrder['location']['code'] ?? null,
//                            'location_name' => $purchaseOrder['location']['name'] ?? null,
//                            'account_title_id' => $purchaseOrder['account_title']['id'] ?? null,
//                            'account_title_code' => $purchaseOrder['account_title']['code'] ?? null,
//                            'account_title_name' => $purchaseOrder['account_title']['name'] ?? null,
//                        ]);
//                    }
//                }
//            }
//        }

        return $this->resultResponse("update", "Transaction", []);
        // return $this->resultResponse("not-exist", "Document number", []);
    }

    public function newGetPODetails(Request $request)
    {
        $po_number = $request->input('po_no');
        $rr_number = $request->input('rr_no');
        $payment_type = $request->input('payment_type');
        $company_id = $request->input('company_id');
        $business_unit_id = $request->input('business_unit_id');

        $po_batch = POBatch::query();

        if ($payment_type == 'Full') {
            $po_batch->where('po_no', $po_number)
                ->whereHas('request', function ($query) use ($company_id, $business_unit_id) {
                    $query->where('state', '!=', 'void')
                        ->where('company_id', $company_id)
                        ->orWhere('business_unit_id', $business_unit_id)
                        ->withTrashed(function ($query) {
                            $query->where('deleted_at', '=', '2024-08-28 00:00:00');
                        });
                })
                ->exists();

            if ($po_batch->count() > 0) {
                $errorMessage = GenericMethod::resultLaravelFormat("po_group.no", ["PO number already exist."]);
                return $this->resultResponse("invalid", "", $errorMessage);
            } else {
//                return $this->resultResponse("success-no-content", "", []);
                return (new MasterlistController())->projectYmir($request);
            }
        } else {
            $requestIds = $po_batch->select('p_o_batches.po_no','p_o_batches.request_id')->leftJoin('transactions', 'p_o_batches.request_id', '=', 'transactions.request_id')
                ->where('transactions.state', '!=', 'void')
//                ->where('transactions.company_id', $company_id)
                ->where(function ($query) use ($company_id, $business_unit_id) {
                    $query->where('transactions.company_id', $company_id)
                        ->orWhere('transactions.business_unit_id', $business_unit_id);
                })
                ->where('p_o_batches.po_no', $po_number)
                ->select('transactions.request_id')
                ->pluck('transactions.request_id');

            $po_no = POBatch::whereIn("request_id", $requestIds)
                ->whereNull("deleted_at")
                ->select("po_no", "po_amount", "po_total_amount", "rr_group")
                ->get()
                ->collect();

            $sums = Transaction::whereIn('request_id', $requestIds)
                ->withTrashed(function ($query) {
                    $query->where('deleted_at', '=', '2024-08-28 00:00:00');
                })
                ->where('state', '!=', 'void')
//                ->where('company_id', $company_id)
                ->select(['document_amount', 'referrence_amount'])
                ->get()
                ->reduce(function ($carry, $item) {
                    $carry['document_amount_sum'] += $item->document_amount;
                    $carry['referrence_amount_sum'] += $item->referrence_amount;
                    return $carry;
                }, ['document_amount_sum' => 0, 'referrence_amount_sum' => 0]);
            $totalDeduction = $sums['document_amount_sum'] + $sums['referrence_amount_sum'];
//            $po_total_amount = POBatch::whereIn('request_id', $requestIds)->pluck('po_total_amount')->collect()->unique()->max();
            $po_total_amount = $po_no->pluck('po_total_amount')->unique()->max();
            $transform_po = $po_no->transform(function ($item, $key) {
                return [
                    'no' => $item->po_no,
                    'amount' => $item->po_amount,
                    'rr_no' => $item->rr_group
                ];
            })->reverse()->groupBy('no')->map(function ($group) {
                return $group->sortByDesc(function ($item) {
                    return count($item['rr_no']);
                })->first();
            })->values();

            $filtered_po = $transform_po->transform(function ($item, $key) use ($po_total_amount, $totalDeduction, $po_no) {
                $balance = $key === 0 ? $po_total_amount - $totalDeduction : 0;
                return [
                    'no' => $item['no'],
                    'amount' => $item['amount'],
                    'rr_no' => $item['rr_no'],
                    'balance' => $balance,
                ];
            });

            if ($filtered_po->count() > 0) {
                if ($filtered_po[0]['balance'] >= 0 && $filtered_po[0]['balance'] != null) {
                    return response()->json([
                        'code' => 200,
                        'message' => 'Po numbers has been fetched.',
                        'result' => [
                            'is_new_po' => false,
                            'po_group' => $filtered_po,
                        ]
                    ]);
                } else {
                    $errorMessage = GenericMethod::resultLaravelFormat("po_group.no", ["No available balance."]);
                    return $this->resultResponse("invalid", "", $errorMessage);
                }
            } else {
                return $this->resultResponse("success-no-content", "", []);
            }
        }
    }

    public function getPODetails(PODetailsRequest $request)
    {
        $transaction_id = $request->transaction_id;
        $fields = $request->validated();
        $po_details = DB::table("transactions")
            ->leftJoin("p_o_batches", "transactions.request_id", "=", "p_o_batches.request_id")
//            ->where("transactions.company_id", $fields["company_id"])
                ->where(function ($query) use ($fields) {
                    $query->where("transactions.company_id", $fields["business_unit_id"])
                        ->orWhere("transactions.bussiness_unit_id", $fields["business_unit_id"]);
                })
            ->where("p_o_batches.po_no", $fields["po_no"])
            ->where("transactions.state", "!=", "void")
            ->when(isset($transaction_id), function ($query) use ($transaction_id) {
                $query->where("transactions.id", "<>", $transaction_id);
            })
            ->whereNull('p_o_batches.deleted_at')
            ->get(["balance_po_ref_amount as po_balance", "transactions.request_id", "p_o_batches.created_at"]);

        $isAllDatesNotLessThanToday = $po_details->pluck('created_at')->every(function ($date) {
            $date = \Carbon\Carbon::parse($date);
            return $date->startOfDay()->greaterThanOrEqualTo('July 13, 2024');
        });

        if ($isAllDatesNotLessThanToday) {
            return $this->newGetPODetails($request);
        }

        if (strtoupper($fields["payment_type"]) == "FULL") {
            $errorMessage = GenericMethod::resultLaravelFormat("po_group.no", ["PO number already exist."]);
            return $this->resultResponse("invalid", "", $errorMessage);
        }

        if ($po_details->last()->po_balance <= 0 || $po_details->last()->po_balance == null) {
            $errorMessage = GenericMethod::resultLaravelFormat("po_group.no", ["No available balance."]);
            return $this->resultResponse("invalid", "", $errorMessage);
        }
        $po_group = collect();
        $balance = $po_details->last()->po_balance;
        $po_details = POBatch::where("request_id", $po_details->last()->request_id)
            ->orderByDesc("id")
            ->whereNull('deleted_at')
            ->get(["request_id as batch", "po_no as no", "po_amount as amount", "rr_group as rr_no"]);

        $po_details->mapToGroups(function ($item, $v) use ($balance) {
            return [($item["balance"] = 0), ($item["rr_no"] = json_decode($item["rr_no"], true))];
        });

        $po_details = $po_details->reverse()->values();
        $po_details->first()->balance = $balance;
        $po_object = (object)["is_new_po" => false, "po_group" => $po_details];

        return $this->resultResponse("fetch", "PO number", $po_object);

        if ($po_details->isEmpty()) {
            return $this->getPODetailsv1($request);
        }

        return $this->resultResponse("success-no-content", "", []);
    }

    public function getPODetailsv1(PODetailsRequest $request)
    {
        $po_details = DB::connection('mysqlSecondConnection')->table('p_o_batches')
            ->rightJoin('transactions', 'p_o_batches.request_id', '=', 'transactions.request_id')
//            ->where('transactions.company_id', $request->company_id)
            ->where(function ($query) use ($request) {
                $query->where('transactions.company_id', $request->company_id)
                    ->orWhere('transactions.business_unit_id', $request->business_unit_id);
            })
            ->where('p_o_batches.po_no', $request->po_no)
            ->where('transactions.state', '!=', 'void')
            ->get();

//        $po_object = null;

        if (count($po_details) > 0) {
            if (strtoupper($request->payment_type) == 'FULL') {
                $errorMessage = GenericMethod::resultLaravelFormat('po_group.no', ['PO number already exist.']);
                return $this->resultResponse('invalid', '', $errorMessage);
            }

            if ($po_details->last()->balance_po_ref_amount <= 0 || $po_details->last()->balance_po_ref_amount == null) {
                $errorMessage = GenericMethod::resultLaravelFormat('po_group.no', ['No available balance.']);
                return $this->resultResponse('invalid', '', $errorMessage);
            }

            $po_group = collect();
            $balance = $po_details->last()->balance_po_ref_amount;
            $po_details = DB::connection('mysqlSecondConnection')->table('p_o_batches')
                ->where('request_id', $po_details->last()->request_id)
                ->orderByDesc('id')
                ->get(['request_id as batch', 'po_no as no', 'po_amount as amount', 'rr_group as rr_no']);

            $po_details->mapToGroups(function ($item, $v) use ($balance) {
                $item->balance = 0;
                $item->rr_no = json_decode($item->rr_no, true);
                return $item;
            });

            $po_details = $po_details->reverse()->values();
            $po_details->first()->balance = $balance;
            $po_object = (object)["is_new_po" => false, 'po_group' => $po_details];
            return $this->resultResponse('fetch', 'PO number', $po_object);
        }

//        if (!$po_object) {
//            $this->getPODetailsv1($request);
//        }

        return $this->resultResponse('success-no-content', '', []);

    }

    public function validateDocumentNo(Request $request)
    {
        $transaction_id = $request->transaction_id;

        if (
            Transaction::where("document_no", $request["document_no"])
                ->withTrashed()
                ->when(isset($transaction_id), function ($query) use ($transaction_id) {
                    $query->where("id", "<>", $transaction_id);
                })
                ->where("state", "!=", "void")
                ->first()
        ) {
            $errorMessage = GenericMethod::resultLaravelFormat("document.no", ["Document number already exist."]);
            return $this->resultResponse("invalid", "", $errorMessage);
        }
        return $this->resultResponse("success-no-content", "", []);
    }

    public function validateReferenceNo(Request $request)
    {
        $transaction_id = $request->transaction_id;

        if (
            Transaction::where(function ($query) use ($request) {
                $query->where("company_id", $request["company_id"])
                    ->orWhere("business_unit_id", $request["business_unit_id"]);
            })
                ->where("referrence_no", $request["reference_no"])
                ->where("supplier_id", $request["supplier_id"])
                ->where("state", "!=", "void")
                ->when(isset($transaction_id), function ($query) use ($transaction_id) {
                    $query->where("id", "<>", $transaction_id);
                })
                ->first()
        ) {
            $errorMessage = GenericMethod::resultLaravelFormat("document.reference.no", ["Reference number already exist."]);
            return $this->resultResponse("invalid", "", $errorMessage);
        }
        return $this->resultResponse("success-no-content", "", []);
    }

    public function validateSOANumber(Request $request)
    {
        $transaction_id = $request->transaction_id;

        $existSOA = Transaction::where(function ($query) use ($request) {
            $query
                ->where(function ($query) use ($request) {
                    $query
                        ->where(function ($query) use ($request) {
                            $query->where("utilities_from", "<", $request->from)->where("utilities_to", ">", $request->from);
                        })
                        ->orWhere(function ($query) use ($request) {
                            $query->where("utilities_from", "<", $request->to)->where("utilities_to", ">", $request->to);
                        });
                })
                ->orWhere(function ($query) use ($request) {
                    $query->where(function ($query) use ($request) {
                        $query->where("utilities_from", ">=", $request->from)->where("utilities_to", "<=", $request->to);
                    });
                });
        })
//            ->where("utilities_receipt_no", $request->utilities_receipt_no)
//            ->where("supplier_id", $request->supplier_id)
//            ->where("company_id", $request->company_id)
//            ->where("state", "!=", "void")
            ->where([
                "utilities_receipt_no" => $request->utilities_receipt_no,
                "supplier_id" => $request->supplier_id,
                "company_id" => $request->company_id,
                "state" => "!=", "void"
            ])
            ->when(isset($transaction_id), function ($query) use ($transaction_id) {
                $query->where("id", "<>", $transaction_id);
            })
            ->count();

        if ($existSOA > 0) {
            return $this->resultResponse(
                "invalid",
                "",
                GenericMethod::resultLaravelFormat("document.utility.receipt_no", ["SOA/Reference number already exist."])
            );
        }
    }

    public function validatePCFName(Request $request)
    {
        $transaction_id = $request->transaction_id;
        if (
            Transaction::where("pcf_name", $request["pcf_name"])
                ->where("state", "!=", "void")
                ->when(isset($transaction_id), function ($query) use ($transaction_id) {
                    $query->where("transactions.id", "<>", $transaction_id);
                })
                ->exists()
        ) {
            $errorMessage = GenericMethod::resultLaravelFormat("pcf_batch.name", ["PCF name already exist."]);
            return $this->resultResponse("invalid", "", $errorMessage);
        }
        return $this->resultResponse("success-no-content", "", []);
    }

    public function voidTransaction(Request $request, $id)
    {
        // $transaction = Transaction::with('po_details')->where("id", $id)
        //   ->where("state", "!=", "void")
        //   ->first();

        $transaction = Transaction::with("po_details")
            ->where("id", $id)
            ->where("state", "!=", "void")
            ->first();

        $date_requested = date("Y-m-d H:i:s");
        $status = "void";

        //Make not editable the previous transaction of latest transaction.
        if (!$transaction) {
            return $this->resultResponse("not-found", "Transaction", []);
        } else {

            $document_id = [1, 2, 4];
            if ((in_array($transaction->document_id, $document_id)) && $transaction->payment_type == "Partial") {
                if ($transaction) {
                    $poNos = $transaction->po_details->pluck("po_no");
                }

                $currentRequestIds = POBatch::whereIn("po_no", $poNos)
                    ->pluck("request_id")
                    ->toArray();

                Transaction::where("request_id", end($currentRequestIds) - 1)->update([
                    "is_not_editable" => false,
                ]);
            }

        }

        if (!isset($transaction)) {
            return $this->resultResponse("not-found", "Transaction", []);
        }

        $transaction->status = "requestor-void";
        $transaction->state = "void";
        $transaction->reason_id = $request->id;
        $transaction->reason = $request->description;
        $transaction->reason_remarks = $request->remarks;
        $transaction->save();

        GenericMethod::insertRequestorLogs(
            $id,
            $transaction->transaction_id,
            $date_requested,
            $transaction->remarks,
            $transaction->users_id,
            $status,
            $request->id,
            $request->description,
            $request->remarks
        );

        return $this->resultResponse("void", strtoupper($transaction->transaction_id), []);
    }

    public function viewRequestorLogs(Request $request)
    {
        $requestor_logs = GenericMethod::viewRequestLogs($request);
        if (count($requestor_logs) == true) {
            $requestor_logs = RequestLog::collection($requestor_logs);
            return $this->resultResponse("fetch", "Requestor Logs", $requestor_logs);
        }
        return $this->resultResponse("not-found", "Requestor Logs", []);
    }

    public function chequeIndex(Request $request)
    {
        $status = $request->input("state", "request");
        $rows = $request->input("rows", 10);
        $search = $request->input("search");
        $suppliers = json_decode($request->input("suppliers")) ?? [];
        $document_ids = $this->getRequestData($request, "document_ids");
        $companies = $this->getRequestData($request, "companies");
        $is_confidential = $request->input('is_confidential');
        $is_mc = $request->input('is_mc');
        $voucher_numbers = $this->getRequestData($request, 'voucher_numbers');

        $cheque_from = isset($request["cheque_from"])
            ? Carbon::createFromFormat("Y-m-d", $request->input("cheque_from"))->format("Y-m-d")
            : null;
        $cheque_to = isset($request["cheque_to"])
            ? Carbon::createFromFormat("Y-m-d", $request->input("cheque_to"))->format("Y-m-d")
            : null;

        $transactions = Transaction
            ::with([
                "users:id,first_name,middle_name,last_name,department,position",
                "supplier.supplier_type:id,type as name",
                "account_titles",
                "treasuryCheque",
                "treasuryAccountTitle",
                "cheques.assignedTreasury"
            ])
            ->when($status == 'cheque', function ($query) {
                return $query->whereHas('cheques', function ($query) {
                    $query->where('status', 'cheque-cheque')
                        ->latest('updated_at');
                });
            })
            ->when($status == 'return', function ($query) {
                return $query->whereHas('cheques', function ($query) {
                    $query->where('status', 'cheque-return')
                        ->latest('updated_at');
                });
            })

            // creation of cheque
            ->when($status == "pending-cheque", function ($query) use ($is_confidential) {
                return $query->where(function ($query) use ($is_confidential) {
                    $query->where('status', 'inspect-inspect')
                        ->orWhere(function ($query) {
                            $query->where('status', 'transmit-transmit')
                                ->where('document_id', '!=', 8);
                        });
//                        ->orWhere(function ($query) use ($is_confidential) {
//                            $query->when($is_confidential != 1, function ($query) {
//                                $query->where('status', 'approve-approve')
//                                    ->where('is_mc', 1);
//                            });
//                        });
                })->where(function ($query) {
                    $query->where('assigned_id', auth()->user()->id)
                        ->orWhere('assigned_id', null);
                });
            })
            ->when($status == "cheque-receive", function ($query) {
                return $query->whereIn("status", ["cheque-receive", "cheque-unhold", "cheque-unreturn"])
                    ->where(function ($query) {
                        $query->where('assigned_id', auth()->user()->id)
                            ->orWhere('assigned_id', null);
                    });
            })
            ->when($status == "cheque-cheque", function ($query) {
                return $query->whereIn("status", ["cheque-cheque", "cheque-unhold", "cheque-unreturn"])
                    ->where(function ($query) {
                        $query->where('assigned_id', auth()->user()->id)
                            ->orWhere('assigned_id', null);
                    });
            })
            ->when($status == "return-cheque", function ($query) {
                return $query->whereIn("status", ["audit-return", "release-return", "issue-return", "file-return"]);
            })
            ->when($status == "hold-cheque", function ($query) {
                return $query->where("status", "audit-hold");
            })

            // auditing of cheque
            ->when($status == "pending-audit", function ($query) {
                return $query->where("status", "cheque-cheque")->where("is_for_releasing", "!=", true);
            })
            ->when($status == "audit-receive", function ($query) {
                return $query->whereIn("status", ["audit-receive", "audit-unhold", "audit-unreturn"]);
            })

            // signing of cheque
            ->when($status == "pending-executive", function ($query) {
                //                return $query->where("status", "cheque-cheque");
                return $query->where("status", "audit-audit");
            })
            ->when($status == "executive-receive", function ($query) {
                return $query->whereIn("status", ["executive-receive", "executive-unhold", "executive-unreturn"]);
            })

            // releasing of cheque (internal)
            ->when($status == "pending-issue", function ($query) {
                return $query->where("status", "executive-executive");
            })
            ->when($status == "issue-receive", function ($query) {
                return $query->whereIn("status", ["issue-receive", "issue-unhold", "issue-unreturn"]);
            })
//            ->when($status == "return-issue", function ($query) {
//                return $query->where("status", "release-return");
//            })
            ->when($status == "hold-issue", function ($query) {
                return $query->where("status", "release-hold");
            })

            // releasing of cheque (external)
            ->when($status == "pending-release", function ($query) {
                return $query->where("status", "issue-issue")->where("is_for_releasing", true);
            })
            ->when($status == "release-receive", function ($query) {
                return $query->whereIn("status", ["release-receive", "release-unhold", "release-unreturn"]);
            })
            ->when(
                !in_array($status, [
                    "pending-cheque",
                    "cheque-receive",
                    "return-cheque",
                    "hold-cheque",
                    "pending-audit",
                    "audit-receive",
                    "pending-executive",
                    "executive-receive",
                    "pending-issue",
                    "issue-receive",
                    "return-issue",
                    "hold-issue",
                    "pending-release",
                    "release-receive",
                    "cheque",
                    "return"
                ]),
                function ($query) use ($status) {
                    return $query->where("status", preg_replace("/\s+/", "", $status));
                }
            )
            ->select([
                "id",
                "users_id",
                "supplier_id",
                "transaction_id",
                "category",

                "tag_no",
                "document_id",
                "document_type",
                "payment_type",
                "receipt_type",
                "voucher_no",
                "voucher_month",
                "remarks",

                "company_id",
                "company",
                "department_id",
                "department",
                "location_id",
                "location",

                "document_no",
                "document_amount",
                "principal",
                "interest",
                "gross_amount",
                "referrence_no",
                "referrence_amount",
                "input_tax",

                "date_requested",

                "status",
                "state",
                "is_confidential",
                "is_mc"
            ])

            //Confidential Filter
            ->when($is_confidential == null, function ($query) {
                $query->whereIn('is_confidential', [1, 0]);
            })
            ->when($is_confidential == 1, function ($query) {
                $query->where('is_confidential', 1);
            })

            //Managers Cheque Filter
            ->when($is_mc == null, function ($query) {
                $query->whereIn('is_mc', [1, 0]);
            })
            ->when($is_mc == 1, function ($query) {
                $query->whereIn('is_mc', [1]);
            })

            // Supplier Filter
            ->when(!empty($suppliers), function ($query) use ($suppliers) {
                return $query->whereIn("supplier_id", $suppliers);
            })

            //Company Filter
            ->when(!empty($companies), function ($query) use ($companies) {
                return $query->whereIn("company_id", $companies);
            })

            //Document Types Filter
            ->when(!empty($document_ids), function ($query) use ($document_ids) {
                $query->whereIn("document_id", $document_ids);
            })

            // Cheque Date Filter (Will deprecate)
            ->when($cheque_from && $cheque_to, function ($query) use ($cheque_from, $cheque_to) {
                return $query->whereHas("cheques.cheques", function ($query) use ($cheque_from, $cheque_to) {
                    return $query->whereDate("cheque_date", ">=", $cheque_from)->whereDate("cheque_date", "<=", $cheque_to);
                });
            })

            //Voucher Number
            ->when(!empty($voucher_numbers), function ($query) use ($voucher_numbers) {
                return $query->whereIn('id', $voucher_numbers);
            })
            ->whereLike([
                "remarks",
                "payment_type",
                "voucher_no",
                "tag_no",
                "company",
                "department",
                "location",
                "supplier",
                "document_no",
                "referrence_no"
            ], $search)
            ->latest("updated_at")
            ->paginate((int)$rows);

//        ChequeIndex::collection($transactions);
        $this->chequeIndexFormatter($transactions);

        if (count($transactions)) {
            return $this->resultResponse("fetch", "Transaction", $transactions);
        }

        return $this->resultResponse("not-found", "Transaction", []);
    }

    private function chequeIndexFormatter($transactions)
    {

        $transactions->getCollection()->transform(function ($transaction) {
            $resource = new TransactionResource1($transaction);
            $rental = $resource->getRental();

//            $cheques = $transaction->cheques->first()
//                ? $transaction->cheques->first()->chequeViaTransaction
//                    ? $transaction->cheques->first()->chequeViaTransaction
//                    : $transaction->cheques
//                : $transaction->cheques;
            $cheques = $transaction->treasuryCheque;

            $netAmount = $transaction->voucher->first()->account_title;
            $account_title = $transaction->treasuryAccountTitle->isEmpty()
                ? $transaction->account_titles
                : $transaction->treasuryAccountTitle->filter(function ($query) {
                    return $query->entry == "Credit" || (strpos($query->account_title_name, "Accounts Payable") !== false && $query->entry == "Debit");
                })->values();

            return [
                "id" => $transaction->id,
                "tag_no" => $transaction->tag_no,
                "transaction_no" => $transaction->transaction_id,
                "receipt_type" => $transaction->receipt_type,
                "payment_type" => $transaction->payment_type,
                "users" => $transaction->users,
                "document" => [
                    "id" => $transaction->document_id,
                    "name" => $transaction->document_type,
                ],
                "document_no" => $transaction->document_no,
                'document_amount' => ($transaction->document_id == 3)
                    ? ($transaction->category == in_array($transaction->category, $rental) ? $transaction->gross_amount : floatval((number_format(($transaction->principal + $transaction->interest), 2, '.', ''))))
                    : $transaction->document_amount ?? $transaction->referrence_amount,
                "reference_no" => $transaction->referrence_no,
                "input_tax" => $transaction->input_tax,
                "date_requested" => $transaction->date_requested,
                "company" => [
                    "id" => $transaction->company_id,
                    "name" => $transaction->company,
                ],
                "department" => [
                    "id" => $transaction->department_id,
                    "name" => $transaction->department,
                ],
                "location" => [
                    "id" => $transaction->location_id,
                    "name" => $transaction->location,
                ],
                "supplier" => [
                    "id" => $transaction->supplier->id,
                    "name" => $transaction->supplier->name,
                    "type" => $transaction->supplier->supplier_type->name,
                ],
                "voucher" => [
                    "no" => $transaction->voucher_no,
                    "month" => $transaction->voucher_month,
                ],
                "voucher_month" => $transaction->voucher_month,
                "cheques" => $cheques->load('bank')->map(function ($item) {
                    return [
                        "type" => $item->entry_type,
                        "no" => $item->cheque_no,
                        "bank" => [
                            "id" => $item->bank_id,
                            "name" => $item->bank_name,
                            'account_number' => $item->bank->account_no,
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
                "remarks" => $transaction->remarks,
                "status" => $transaction->state,
                "state" => $transaction->status,
                "is_confidential" => $transaction->is_confidential,
                "is_mc" => $transaction->is_mc,
                'dates' => [
                    'created' => $this->getDateEveryStatus($transaction->cheques, 'cheque-cheque'),
                ],
                "treasury" => $transaction->cheques->first() && $transaction->cheques->first()->user_id
                    ? $transaction->cheques->first()->assignedTreasury->first_name . ' ' . $transaction->cheques->first()->assignedTreasury->last_name
                    : null,
                "batch_no" => $transaction->cheques->first() && $transaction->cheques->first()->batch_no
                    ? $transaction->cheques->first()->batch_no
                    : null,
                "net_amount" => $netAmount->filter(function ($query) {
                    return strpos($query->account_title_name, "Accounts Payable") !== false && $query->entry == "Credit";
                })->sum('amount')
            ];
        });

        return $transactions;
    }

    public function clearChequeIndex(Request $request)
    {
        $status = $request->input("state", "request");
        $rows = $request->input("rows", 10);
        $search = $request->input("search");

        $cheques = Cheque::with("bank.AccountTitleTwo")
            ->where(function ($query) use ($search) {
                $query
                    ->where("cheque_no", "like", "%" . $search . "%")
                    ->orWhere("bank_name", "like", "%" . $search . "%")
                    ->orWhere("cheque_date", "like", "%" . $search . "%")
                    ->orWhere("cheque_amount", "like", "%" . $search . "%");
            })
            ->when($status == "pending-clear", function ($query) {
                $query
                    ->whereHas("transaction", function ($query) {
                        $query->whereIn("status", [
                            "release-release",
                            "file-receive",
                            "file-file",
                            "discharge-receive",
                            "discharge-discharge",
                            "pass-receive",
                            "pass-pass",
                        ]);
                    })
                    ->whereNull("is_cleared");
            })
            ->when($status == "clear-clear", function ($query) {
                $query->where("is_cleared", true);
            })
            ->latest("updated_at")
            ->paginate((int)$rows);
        ChequeClearIndex::collection($cheques);

        if (count($cheques)) {
            return $this->resultResponse("fetch", "Transaction", $cheques);
        }
        return $this->resultResponse("not-found", "Transaction", []);
    }

    public function chequeClear(Request $request, $id)
    {
        $accounts = $request->accounts;
        $cheque = Cheque::find($id);
        $clear_date = date("Y-m-d", strtotime($request->date));

        if ($cheque) {
            $sameCheques = Cheque::where("bank_id", $cheque->bank_id)
                ->where("cheque_no", $cheque->cheque_no)
                ->get()
                ->pluck("id");
            $sameTransactionIds = Cheque::where("bank_id", $cheque->bank_id)
                ->where("cheque_no", $cheque->cheque_no)
                ->get()
                ->pluck("transaction_id");
            Cheque::where("bank_id", $cheque->bank_id)
                ->where("cheque_no", $cheque->cheque_no)
                ->update([
                    "date_cleared" => $clear_date,
                    "is_cleared" => true,
                ]);

            foreach ($sameTransactionIds as $sameTransactionId) {
                Clear::create([
                    "transaction_id" => $sameTransactionId,
                    "tag_id" => Transaction::where("id", $sameTransactionId)->first()->tag_no,
                    "status" => "clear-clear",
                    "date_status" => date("Y-m-d"),
                    "date_cleared" => $clear_date,
                ]);
            }

            foreach ($accounts as $account) {
                foreach ($sameCheques as $sameCheque) {
                    ClearingAccountTitle::create([
                        "clear_id" => $sameCheque,
                        "entry" => $account["entry"],
                        "account_title_id" => $account["account_title"]["id"],
                        "account_title_name" => $account["account_title"]["name"],
                        "company_id" => data_get($account, "company.id"),
                        "company_code" => data_get($account, "company.code"),
                        "company_name" => data_get($account, "company.name"),
                        "department_id" => data_get($account, "department.id"),
                        "department_code" => data_get($account, "department.code"),
                        "department_name" => data_get($account, "department.name"),
                        "location_id" => data_get($account, "location.id"),
                        "location_code" => data_get($account, "location.code"),
                        "location_name" => data_get($account, "location.name"),
                        "business_unit_id" => data_get($account, "business_unit.id"),
                        "business_unit_code" => data_get($account, "business_unit.code"),
                        "business_unit_name" => data_get($account, "business_unit.name"),
                        "unit_id" => data_get($account, "unit.id"),
                        "unit_code" => data_get($account, "unit.code"),
                        "unit_name" => data_get($account, "unit.name"),
                        "sub_unit_id" => data_get($account, "sub_unit.id"),
                        "sub_unit_code" => data_get($account, "sub_unit.code"),
                        "sub_unit_name" => data_get($account, "sub_unit.name"),
                        "amount" => $account["amount"],
                        "remarks" => $account["remarks"],
                        "transaction_type" => "new",
                    ]);
                }
            }
            return $this->resultResponse("update", "Transaction", []);
        } else {
            return $this->resultResponse("not-found", "Transaction", []);
        }
    }

    public function generateBatchNo()
    {
        $maxBatchNo = Treasury::max('batch_no');
        return $maxBatchNo ? $maxBatchNo + 1 : 1;
    }
//    public function generateBatchNo()
//    {
//        $no = 1;
//        do {
//            $batch_no = $no;
//            $no++;
//        } while ($this->checkBatchNo($batch_no));
//
//        return $batch_no;
//    }
    function checkBatchNo($batch_no)
    {
        return Treasury::where("batch_no", $batch_no)->exists();
    }
//    public function getAvailableBankSeries($bank_id = null) {
//        $year = date("Y");
//
//        $bank_series = BankSeries::where('bank_id', $bank_id)
//            ->where('year', $year)
//            ->select(['from', 'to'])
//            ->first();
//
//        if (!$bank_series) {
//            return null; // or handle this case as you need
//        }
//
//        $start_bank_series = $bank_series->from;
//        $end_bank_series = $bank_series->to;
//
//        for ($i = $start_bank_series; $i <= $end_bank_series; $i++) {
//            if (!$this->checkBankSeries($bank_id, $i)) {
//                return $i;
//            }
//        }
//
//        return null; // or handle this case as you need, when all bank series numbers are used
//    }
//
//    function checkBankSeries($bank_id, $bank_series) {
//        return Cheque::where('bank_id', $bank_id)
//            ->where('cheque_no', $bank_series)
//            ->exists();
//    }
    public function chequeRevert($id, $request = null)
    {
        $batchNo = Treasury::where("transaction_id", $id)
            ->whereNotNull("batch_no")
            ->pluck("batch_no")
            ->first();

        if ($batchNo) {
            $treasuriesId = Treasury::where("batch_no", $batchNo)->pluck("id");

            $transactionIds = Treasury::whereIn("id", $treasuriesId)->pluck("transaction_id");

            $status = $request ? $request->process . "-return" : "cheque-receive";
            $state = $request ? "return" : "receive";

            Transaction::whereIn("id", $transactionIds)
                ->where("state", "!=", "void")
                ->update([
                    "status" => $status,
                    "state" => $state,
                ]);

            if ($request) {
                $cheques = Cheque::whereIn("transaction_id", $transactionIds)->get();

                $cheques->each(function ($cheque) use ($request) {
                    $cheque->update([
                        'reason_id' => data_get($request, 'reason.id'),
                        'reason' => data_get($request, 'reason.remarks'),
                    ]);
                    $cheque->delete();
                });
            } else {
                Cheque::whereIn("transaction_id", $transactionIds)->delete();
                $transactionIds->each(function ($transactionId) {

                    $transaction = Transaction::where("id", $transactionId)->first();

                    $transaction->cheques()->create([
                        'tag_id' => $transaction->tag_no,
                        'status' => 'cheque-receive',
                        'date_status' => date("Y-m-d"),
                    ]);
                });
            }

            VoucherAccountTitle::whereIn("treasury_id", $treasuriesId)->delete();
            Audit::whereIn("transaction_id", $transactionIds)->delete();
            Executive::whereIn("transaction_id", $transactionIds)->delete();
            Issue::whereIn("transaction_id", $transactionIds)->delete();
            Release::whereIn("transaction_id", $transactionIds)->delete();

            return $this->resultResponse("update", "Transaction", []);
        } else {
            return $this->resultResponse("not-found", "Transaction", []);
        }
    }

    public function chequeIndex1(Request $request)
    {
        $status = $request->input("state", "request");
        $rows = $request->input("rows", 10);
        $search = $request->input("search");
        $search = str_replace(',', '', $search);
        $tag_search = str_replace("tag#", "", $search);
        $suppliers = json_decode($request->input("suppliers")) ?? [];
        $companies = $this->getRequestData($request, "companies");
        $document_ids = $this->getRequestData($request, "document_ids");
        $cheque_from = isset($request["cheque_from"])
            ? Carbon::createFromFormat("Y-m-d", $request->input("cheque_from"))->format("Y-m-d")
            : null;
        $cheque_to = isset($request["cheque_to"])
            ? Carbon::createFromFormat("Y-m-d", $request->input("cheque_to"))->format("Y-m-d")
            : null;
        $is_confidential = $request->input("is_confidential", 0);

        $cheques = Cheque::query()->where(function ($query) use ($search) {
            $query->whereHas("transaction", function ($query) use ($search) {
                $query->whereLike([
                    "remarks",
                    "voucher_no",
                    "tag_no",
                ], $search);
            })
                ->orWhere(function ($query) use ($search) {
                    $query->whereLike([
                        "bank_name",
                        "cheque_no",
                        "cheque_amount",
                    ], $search);
                });
        });
        $cheques = $cheques->with([
            'bank' => function ($query) {
                $query->with([
                    'AccountTitleOne',
                    'AccountTitleTwo',
                    'CompanyOne',
                    'CompanyTwo',
                    'DepartmentOne',
                    'DepartmentTwo',
                    'LocationOne',
                    'LocationTwo',
                    'BusinessUnitOne',
                    'BusinessUnitTwo',
                    'SubUnitOne',
                    'SubUnitTwo'
                ]);
            }
        ])
            // auditing of cheque
            ->when($status == "pending-audit", function ($query) {
                $query
                    ->whereHas("transaction", function ($query) {
                        return $query->whereIn("status", ["cheque-cheque", "audit-receive"])->where("is_for_releasing", "!=", true);
                    })
                    ->whereNull("is_received")
                    ->whereNull("is_returned")
                    ->whereNull("is_audited");
            })
            ->when($status == "audit-receive", function ($query) {
                $query
                    ->whereHas("transaction", function ($query) {
                        return $query->whereIn("status", ["cheque-cheque", "audit-receive", "audit-unhold", "audit-unreturn"]);
                    })
                    ->where("is_received", true);
            })
            ->when($status == "audit-return", function ($query) {
                $query->whereHas("transaction", function ($query) {
                    return $query->where("status", "audit-return");
                });
            })
            ->when($status == 'return-audit', function ($query) {
                $query->whereHas("transaction", function ($query) {
                    return $query->where("status", "executive-return");
                });
            })

            //executive
            ->when($status == "pending-executive", function ($query) {
                $query
                    ->whereHas("transaction", function ($query) {
                        return $query->where("status", "audit-audit");
                    })
                    ->whereNull("is_received")
                    ->whereNull("is_returned")
                    ->whereNull("is_executived");
            })
            ->when($status == "executive-receive", function ($query) {
                $query
                    ->whereHas("transaction", function ($query) {
                        return $query->whereIn("status", [
                            "audit-audit",
                            "executive-receive",
                            "executive-unhold",
                            "executive-unreturn",
                        ]);
                    })
                    ->where("is_received", true);
            })

            // releasing of cheque (internal)
            ->when($status == "pending-issue", function ($query) {
                $query
                    ->whereHas("transaction", function ($query) {
                        return $query->where("status", "executive-executive")
                            ->where(function ($query) {
                                $query->where('assigned_id', auth()->user()->id)
                                    ->orWhere('assigned_id', null);
                            });
                    })
                    ->whereNull("is_received")
                    ->whereNull("is_returned")
                    ->whereNull("is_issued");
            })
            ->when($status == "issue-receive", function ($query) {
                $query
                    ->whereNull("issue_id")
                    ->whereNull('is_cancelled')
                    ->whereHas("transaction", function ($query) {
                        return $query->whereIn("status", [
                            "executive-executive",
                            "issue-receive",
                            "issue-unhold",
                            "issue-unreturn",
                        ])
                            ->where(function ($query) {
                                $query->where('assigned_id', auth()->user()->id)
                                    ->orWhere('assigned_id', null);
                            });
                    })
                    ->where("is_received", true);
            })
            ->when($status == "return-issue", function ($query) {
                $query->whereHas("transaction", function ($query) {
                    return $query->where("status", "release-return")
                        ->where(function ($query) {
                            $query->where('assigned_id', auth()->user()->id)
                                ->orWhere('assigned_id', null);
                        });
                });
            })
            ->when($status == "hold-issue", function ($query) {
                $query->whereHas("transaction", function ($query) {
                    return $query->where("status", "release-hold")
                        ->where(function ($query) {
                            $query->where('assigned_id', auth()->user()->id)
                                ->orWhere('assigned_id', null);
                        });
                });
            })

            // releasing of cheque (external)
            ->when($status == "pending-release", function ($query) {
                $query
                    ->whereHas("transaction", function ($query) {
                        return $query->whereIn("status", ["issue-issue", "release-receive"])
                            ->where("is_for_releasing", true);
//                            ->where('is_mc', 0);
                    })
                    ->whereNull("is_received");
            })
            ->when($status == "release-receive", function ($query) {
                //                $query->whereHas('transaction', function ($query) {
                //                    return $query->whereIn("status", ["release-receive", "release-unhold", "release-unreturn"]);
                //                });
                $query->whereNull("is_released")
                    ->whereHas("transaction", function ($query) {
                        return $query->whereIn("status", ["issue-issue", "release-receive", "release-unhold", "release-unreturn"]);
                    })
                    ->where("is_received", true)
                    ->where("is_uncollected", false);
            })
            ->when($status == "release-uncollected", function ($query) {
                $query->whereNull("is_released")
                    ->whereHas("transaction", function ($query) {
                        return $query->whereIn("status", ["issue-issue", "release-receive", "release-unhold", "release-unreturn"]);
                    })
                    ->where("is_received", true)
                    ->where("is_uncollected", true);
            })
            ->when($status == "return-release", function ($query) {
                $query
                    ->whereNull("issue_id")
                    ->whereHas("transaction", function ($query) {
                        return $query->where("status", "release-return");
                    });
            })

            //approval of cancelled cheque
            ->when($status == "pending-abort", function ($query) {
                $query->where([
                    'is_cancelled' => false
                ]);
            })
            ->when($status == 'abort-abort', function ($query) {
                $query->where([
                    'is_cancelled' => true
                ]);
            })

            //clearing of cheque
            ->when($status == "pending-clear", function ($query) {
                return $query
                    ->where(function ($query) {
                        $query->whereNull('is_cleared')
                            ->whereNotNull('issue_id')
                            ->whereHas('transaction', function ($query) {
                                return $query->whereIn("status", [
                                    "release-release",
                                    "file-receive",
                                    "file-file",
                                    "discharge-receive",
                                    "discharge-discharge",
                                    "pass-receive",
                                    "pass-pass"
                                ]);
                            });
                    });
//                    ->orWhere(function ($query) {
//                        $query->whereHas('transaction', function ($query) {
//                            return $query->where('is_mc', true)
//                                ->whereIn('status', ['issue-issue']);
//                        });
//                    });
            })
            ->when($status == "clear-receive", function ($query) {
                $query->whereHas("transaction", function ($query) {
                    return $query->whereIn("status", ["clear-receive", "clear-unhold", "clear-unreturn"]);
                });
                //                $query->whereNull('is_cleared')->whereNotNull('issue_id')->whereHas('transaction', function ($query) {
                //                    return $query->whereIn('status', [
                //                        "clear-receive",
                //                        "clear-unhold",
                //                        "clear-unreturn"
                //                    ]);
                //                });
            })
            ->when(
                !in_array($status, [
                    "pending-audit",
                    "audit-receive",
                    "pending-executive",
                    "executive-receive",
                    "issue-receive",
                    "pending-issue",
                    "return-issue",
                    "hold-issue",
                    "pending-release",
                    "release-receive",
                    "pending-clear",
                    "clear-receive",
                ]),
                function ($query) use ($status) {
                    $query
                        ->when($status == "issue-issue", function ($query) {
                            return $query
                                ->whereHas("transaction", function ($query) {
//                                    return $query->whereIn("status", ["issue-issue", "issue-receive", "executive-executive"]);
                                    return $query->where(function ($query) {
                                        $query->whereIn("status", ["issue-issue", "issue-receive", "executive-executive"]);
                                    });
//                                        ->orWhere(function ($query) {
//                                        $query->where("status", "release-release")->where("is_mc", 1);
//                                    });
                                })
                                ->where("is_issued", true)
                                ->whereNull("is_received");
                        })
                        ->when($status == "audit-audit", function ($query) {
                            return $query
                                ->whereHas("transaction", function ($query) {
                                    $query->whereIn("status", ["audit-audit", "cheque-cheque", "audit-receive"]);
                                })
                                ->where("is_audited", true)
                                ->whereNull("is_received");
                        })
                        ->when($status == "release-release", function ($query) {
                            $query
                                ->whereNotNull("is_released")
                                ->whereHas("transaction", function ($query) {
                                    return $query->whereIn("status", ["release-release", "release-receive"]);
//                                        ->where('is_mc', 0);
                                })
                                ->where("is_released", true);
                        })
                        ->when($status == "clear-clear", function ($query) {
                            return $query->whereNotNull("is_cleared");
                        })
                        ->when(
                            $status == "executive-executive",
                            function ($query) {
                                return $query
                                    ->whereHas("transaction", function ($query) {
                                        $query->whereIn("status", ["executive-executive", "executive-receive"]);
                                    })
                                    ->where("is_executived", true)
                                    ->whereNull("is_returned")
                                    ->whereNull("is_received");
                            }
                        )
                        ->when(in_array($status, ["audit-hold", "release-return", "hold-release"]), function ($query) use ($status) {
                            $query->whereHas("transaction", function ($query) use ($status) {
                                return $query->where("status", preg_replace("/\s+/", "", $status));
                            });
                        });
                }
            )
            ->select("bank_id", "bank_name", "cheque_no", DB::raw("MAX(updated_at) as latest_updated_at"))
            ->groupBy("bank_name", "cheque_no", "bank_id")

            // Search
//            ->where(function ($query) use ($search) {
//                $query->whereHas("transaction", function ($query) use ($search) {
//                    $query->whereLike([
//                        "remarks",
//                        "voucher_no",
//                        "tag_no",
//                    ], $search);
//                })
//                    ->orWhere(function ($query) use ($search){
//                        $query->whereLike([
//                            "bank_name",
//                            "cheque_no"
//                        ], $search);
//                    });
//            })
            ->when(count($suppliers), function ($query) use ($suppliers) {
                $query->whereHas("transaction", function ($query) use ($suppliers) {
                    return $query->whereIn("supplier_id", $suppliers);
                });
            })

            //Document Types Filter
            ->when(count($document_ids), function ($query) use ($document_ids) {
                $query->whereHas("transaction", function ($query) use ($document_ids) {
                    return $query->whereIn("document_id", $document_ids);
                });
            })

            //Organization Filter
            ->when(!empty($companies), function ($query) use ($companies) {
                return $query->whereHas("transaction", function ($query) use ($companies) {
                    return $query->whereIn("company_id", $companies);
                });
            })

            //Confidential
            ->when($status != in_array($status, [
                    'pending-audit',
                    'audit-receive',
                    'audit-audit',
                    'pending-executive',
                    'executive-receive',
                    'executive-executive',
                    'pending-issue',
                    'issue-receive',
                    'issue-issue',
                    'pending-clear'
                ]), function ($query) use ($is_confidential) {
                $query->when($is_confidential == 1, function ($query) {
                    $query->whereHas('transaction', function ($query) {
                        $query->where('is_confidential', 1);
                    });
                }, function ($query) {
                    $query->whereHas('transaction', function ($query) {
                        $query->where('is_confidential', 0);
                    });
                });
            })
            ->orderBy("latest_updated_at", "desc")
            ->paginate((int)$rows);

        $cheques->transform(function ($item) {
            $ids = Cheque::where("bank_id", $item->bank_id)
                ->where("cheque_no", $item->cheque_no)
                ->pluck("transaction_id")
                ->unique()
                ->toArray();

            $cheque_details = Cheque::where("bank_id", $item->bank_id)
                ->where("cheque_no", $item->cheque_no)
                ->first();

            $transaction = Transaction::whereIn("id", $ids)
                ->with('account_titles')
                ->get();
            $rental = [
                'stall a rental',
                'stall b rental',
                'stall c rental',
                'stall d rental',
                'cusa rental',
                'dorm rental',
                'additional rental',
                'lounge rental',
                'corporate special program - education',
                'official store rental',
                'unofficial store rental',
                'rental'
            ];

            $transaction = $transaction->map(function ($item) use ($ids, $rental) {
                return [
                    "id" => $item->id,
                    "tag_no" => $item->tag_no,
                    "transaction_no" => $item->transaction_id,
                    "input_tax" => $item->input_tax ?? 0,
                    "receipt_type" => $item->receipt_type ?? '---',
                    "payment_type" => $item->payment_type,
                    "document" => [
                        "id" => $item->document_id,
                        "name" => $item->document_type,
                    ],
                    "document_date" => $item->document_date ?? $item->date_requested,
                    "category" => $item->category ?? "---",
                    "document_no" => $item->document_no ?? '---',
                    "document_amount" =>
                        $item->document_id == 3
                            ? (in_array($item->category, $rental)
                            ? $item->gross_amount
                            : $item->principal + $item->interest)
                            : $item->document_amount,
                    "referrence_no" => $item->referrence_no,
                    "referrence_amount" => $item->referrence_amount,
                    "date_requested" => $item->date_requested,
                    "company" => [
                        "id" => $item->company_id,
                        "name" => $item->company,
                    ],
                    "business_unit" => [
                        "id" => $item->business_unit_id,
                        "name" => $item->business_unit,
                    ],
                    "department" => [
                        "id" => $item->department_id,
                        "name" => $item->department,
                    ],
                    "unit" => [
                        "id" => $item->unit_id,
                        "name" => $item->unit,
                    ],
                    "sub_unit" => [
                        "id" => $item->sub_unit_id,
                        "name" => $item->sub_unit,
                    ],
                    "location" => [
                        "id" => $item->location_id,
                        "name" => $item->location,
                    ],
                    "voucher" => [
                        "no" => $item->voucher_no,
                        "month" => $item->voucher_month,
//                        "account_titles" => $item->voucher->first()->account_title->map(function ($item) {
                        "account_titles" => $item->account_titles->map(function ($item) {
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
                                'business_unit' => [
                                    'id' => $item->business_unit_id,
                                    'code' => $item->business_unit_code,
                                    'name' => $item->business_unit_name,
                                ],
                                'department' => [
                                    'id' => $item->department_id,
                                    'code' => $item->department_code,
                                    'name' => $item->department_name,
                                ],
                                'unit' => [
                                    'id' => $item->unit_id,
                                    'code' => $item->unit_code,
                                    'name' => $item->unit_name,
                                ],
                                'sub_unit' => [
                                    'id' => $item->sub_unit_id,
                                    'code' => $item->sub_unit_code,
                                    'name' => $item->sub_unit_name,
                                ],
                                'location' => [
                                    'id' => $item->location_id,
                                    'code' => $item->location_code,
                                    'name' => $item->location_name,
                                ],
                                'is_default' => $item->is_default,
                            ];
                        }),
                    ],
                    "remarks" => $item->remarks,
                    "status" => $item->status,
                    "state" => $this->stateChange($item->state),
                ];
            });
            $supplier = Transaction::whereIn("id", $ids)
                ->with([
                    "supplier.supplier_type" => function ($query) {
                        return $query->select(["supplier_types.id", "supplier_types.type as name"]);
                    },
                ])
                ->get("supplier_id")
                ->pluck("supplier")
                ->flatten()
                ->unique("supplier_id")
                ->first();

            $distributed = Transaction::whereIn("id", $ids)
                ->select("distributed_id", "distributed_name")
                ->distinct("distributed_id")
                ->first();

            $supplier = $supplier
                ? [
                    "id" => $supplier["id"],
                    "name" => $supplier["name"],
                    "type" => $supplier["supplier_type"]["name"] ?? null,
                ]
                : null;

            $distributed = $distributed
                ? [
                    "id" => $distributed["distributed_id"],
                    "name" => $distributed["distributed_name"],
                ]
                : null;


            $treasury_account_titles = $this->getTreasuryAccountTitles($ids, $cheque_details);

            $account_titles = $treasury_account_titles->map(function ($item) {
                return [
                    "entry" => $item->entry,
                    "account_title" => [
                        "id" => $item->account_title_id,
                        "code" => $item->account_title_code,
                        "name" => $item->account_title_name,
                    ],
                    "amount" => $item->amount,
                    "remarks" => $item->remarks,
                    "company" => [
                        "id" => $item->company_id,
                        "code" => $item->company_code,
                        "name" => $item->company_name,
                    ],
                    "business_unit" => [
                        "id" => $item->business_unit_id,
                        "code" => $item->business_unit_code,
                        "name" => $item->business_unit_name,
                    ],
                    "department" => [
                        "id" => $item->department_id,
                        "code" => $item->department_code,
                        "name" => $item->department_name,
                    ],
                    "unit" => [
                        "id" => $item->unit_id,
                        "code" => $item->unit_code,
                        "name" => $item->unit_name,
                    ],
                    "sub_unit" => [
                        "id" => $item->sub_unit_id,
                        "code" => $item->sub_unit_code,
                        "name" => $item->sub_unit_name,
                    ],
                    "location" => [
                        "id" => $item->location_id,
                        "code" => $item->location_code,
                        "name" => $item->location_name,
                    ],
                    "is_default" => $item->is_default,
                ];
            });

//            $bank = $cheque_details->bank;
//            $bank_account_title_two = $bank->AccountTitleTwo;
//            $bank_company_one = $bank->CompanyOne;
//            $bank_company_two = $bank->CompanyTwo;
//            $bank_department_one = $bank->DepartmentOne;
//            $bank_department_two = $bank->DepartmentTwo;
//            $bank_location_one = $bank->LocationOne;
//            $bank_location_two = $bank->LocationTwo;
//            $bank_business_unit_one = $bank->BusinessUnitOne;
//            $bank_business_unit_two = $bank->BusinessUnitTwo;
//            $bank_sub_unit_one = $bank->SubUnitOne;
//            $bank_sub_unit_two = $bank->SubUnitTwo;

            $cheques = [
                "type" => $cheque_details->entry_type,
                "bank" => $item->bank,
                "no" => $cheque_details->cheque_no,
                "date" => $cheque_details->cheque_date,
                "amount" => $cheque_details->cheque_amount,
                "date_cleared" => $cheque_details->date_cleared,
                "date_issued" => $cheque_details->issue->created_at ?? null,
            ];

            return [
                "type" => $cheque_details->entry_type,
                "no" => $item->cheque_no,
                "bank" => [
                    "id" => $item->bank_id,
                    "name" => $item->bank_name,
                ],
                "amount" => $cheque_details->cheque_amount,
                "date" => $cheque_details->cheque_date,
                "supplier" => (object)$supplier,
                "accounts" => $account_titles,
                "transactions" => $transaction,
                "cheque" => $cheques,
                "distributed" => $distributed,
            ];
        });

        if (count($cheques)) {
            return $this->resultResponse("fetch", "Transaction", $cheques);
        }
        return $this->resultResponse("not-found", "Transaction", []);
    }

    function getTreasuryAccountTitles($ids, $cheque_details)
    {
        $query = Transaction::with('treasuryAccountTitle');

        if (count($ids) > 1) {
            $query->where('id', $ids[0]);
        } else {
            $query->whereIn('id', $ids);
        }

        $collection = $query->get()
            ->pluck("treasuryAccountTitle")
            ->flatten();

//        if (count($ids) <= 1) {
//            $collection = $collection->unique('account_title_id')->filter(function ($item, $index) use ($cheque_details) {
//                return $item->account_title_id == $cheque_details->bank->AccountTitleOne->id || $item->entry == 'Debit';
//            });
//
//            if (count($collection) <= 1) {
//                $collection = $collection->filter(function ($item, $index) use ($cheque_details) {
//                    return $item->amount == $cheque_details->cheque_amount || $item->entry == 'Debit';
//                });
//            }
//
//        }

        if (count($ids) <= 1) {
            $accountTitleOneId = optional(optional($cheque_details->bank)->AccountTitleOne)->id;

            $collection = $collection->unique('account_title_id')->filter(function ($item, $index) use ($accountTitleOneId) {
                return $item->account_title_id == $accountTitleOneId || $item->entry == 'Debit';
            });

            if (count($collection) <= 1) {
                $collection = $collection->filter(function ($item, $index) use ($cheque_details) {
                    return $item->amount == $cheque_details->cheque_amount || $item->entry == 'Debit';
                });
            }
        }

        return $collection->values();
    }

    public function chequeRevert1($bank_id, $cheque_no, $process, $request)
    {
        // $bank_id = $request->bank_id;
        // $cheque_no = $request->cheque_no;

        $cheque = Cheque::where("bank_id", $bank_id)
            ->where("cheque_no", $cheque_no)->get();

        if ($cheque) {
            $cheque->each(function ($item) use ($request) {
                $item->reason_id = data_get($request, "reason.id");
                $item->reason = data_get($request, "reason.remarks");
                $item->save();
            });
            $treasury_id = Cheque::withTrashed()->where("bank_id", $bank_id)
                ->where("cheque_no", $cheque_no)
                ->first()->treasury_id;

            $batch_no = Treasury::where("id", $treasury_id)->first()->batch_no;

            $treasuryIds = Treasury::where("batch_no", $batch_no)->pluck("id");
            $transactionIds = Treasury::whereIn("id", $treasuryIds)->pluck("transaction_id");

            Cheque::whereIn("treasury_id", $treasuryIds)->delete();
            VoucherAccountTitle::whereIn("treasury_id", $treasuryIds)->delete();
            Audit::whereIn("transaction_id", $transactionIds)->where('type', 'cheque')->delete();
//            Treasury::whereIn("id", $treasuryIds)->delete();

            $process == "cheque"
                ?
                ($status[] = [
                    "status" => "cheque-receive",
                    "state" => "receive",
                ])
                :
                ($status[] = [
                    "status" => "audit-return",
                    "state" => "return",
                ]);
            Transaction::whereIn("id", $transactionIds)
                ->where("state", "!=", "void")
                ->update([
                    "status" => $status[0]["status"],
                    "state" => $status[0]["state"],
                    "is_for_releasing" => false,
                ]);

            return $this->resultResponse("update", "Transaction", []);
        }
    }

    public function statusTransactionCounter(): JsonResponse
    {
        $permissions = auth()->user()->permissions;

        $statusMap = [
            1 => ["tag-return"], //Creation of Request
            2 => ["tag-return"], //Creation of Confidential Request
            3 => ["transmit-transmit"], //Auditing of Voucher
//            4 => [], //Received Receipt Report
//            5 => [], //Auditing of Cheque
//            6 => [], //External Releasing of Cheque
            7 => ["transmit-transmit", "audit-return", "inspect-inspect", "release-return", "approve-approve", "issue-return", "file-return"], //Creation of Cheque
//            8 => [], //Clearing of Cheque
//            9 => [], //Creation of Debit Memo
//            10 => [], //Reversal Request
//            11 => ["discharge-discharge", "release-release"], //Filing of Voucher
            11 => ["pass-pass", "discharge-discharge"], //Filing of Voucher
            12 => ["tag-tag", "extract-extract", "approve-return", "cheque-return", "inspect-return"], //Creation of Voucher
            13 => ["approve-approve"], //Transmittal of Confidential Document
            14 => ["release-release"], //Filing of Confidential Voucher
            15 => ['pending', 'voucher-return'], //Tagging of Confidential Document
//            16 => [], //Releasing of Confidential Cheque
            17 => ["voucher-voucher"], //Approval of Voucher
//            18 => [], //Approval of Confidential Voucher
            19 => ["approve-approve"], //Transmittal of Document
            20 => ["pending", "voucher-return", "gas-return"], //Tagging of Document
//            21 => [], //Creation of Counter Receipt
//            22 => [], //Monitoring of Counter Receipt
//            23 => [],  //Transmittal of Cheque
//            24 => [], //Internal Releasing of Cheque
            25 => ["tag-tag"], //Transmittal of Official Receipt
            26 => ["pass-pass"], //Filing of Official Receipt
            27 => ['gas-gas'], //Transmittal of GAS Voucher
            28 => ['tag-tag', 'approve-return'], //Vouchering of Confidential Document
            29 => ['tag-return'], //Creation of Managers Cheque
            30 => ['discharge-discharge', 'release-release'], //Filing of MC Voucher
            31 => ['approve-approve'], //Application of Loan
            33 => ["release-release"], //Transmittal for Filing of Voucher
        ];

        $response = [];

        foreach ($permissions as $permission) {
            if (isset($statusMap[$permission])) {
                $status = $statusMap[$permission];
                $permissionName = Permission::where('id', $permission)->first()->name;

                // Initialize all status counts to zero
//                $result = array_fill_keys($status, 0);
                $result = [
                    'pending' => 0,
                    'return' => 0,
                ];

                $user_id = null;
                $document_id = null;
                $receipt_type = null;
                $is_confidential = 0;
                $is_mc = 0;
                switch ($permissionName) {
                    case 'Transmittal of Official Receipt':
                    case 'Filing of Official Receipt':
                        $receipt_type = 'Official';
                        break;

                    case 'Creation of Request':
                    case 'Creation of Voucher':
                    case 'Transmittal of Document':
                    case 'Approval of Voucher':
                    case 'Filing of Voucher':
                    case 'Creation of Cheque':
                        $user_id = auth()->user()->id;
                        break;

                    case 'Auditing of Voucher':
                        $document_id = 8;
                        break;

                    case 'Creation of Confidential Request':
                    case 'Tagging of Confidential Document':
                    case 'Transmittal of Confidential Document':
                    case 'Application of Loan':
                        $is_confidential = 1;
                        break;

                    case 'Vouchering of Confidential Document':
                    case 'Filing of Confidential Voucher':
                        $user_id = auth()->user()->id;
                        $is_confidential = 1;
                        break;

                    case 'Filing of MC Voucher':
                        $is_mc = 1;
                        break;
                }

                $counts = Transaction::select('status', DB::raw('count(*) as count'))
//                    ->when($is_confidential == 1 , function ($query) use ($is_confidential) {
//                        $query->where('is_confidential', 1);
//                    }, function ($query) use ($is_confidential) {
//                        $query->where('is_confidential', 0);
//                    })
                    ->when($permissionName != in_array($permissionName, [
                            'Approval of Voucher',
                            'Creation of Cheque',
                            'Tagging of Document',
                            'Creation of Voucher',
                            'Transmittal of Official Receipt',
                            'Transmittal of GAS Voucher',
                            'Filing of Official Receipt',
                            'Application of Loan',
                            'Transmittal for Filing of Voucher'
                        ]), function ($query) use ($is_confidential, $is_mc) {
                        $query->when($is_confidential == 1, function ($query) use ($is_confidential, $is_mc) {
                            $query->where('is_confidential', 1);
                        }, function ($query) use ($is_confidential) {
                            $query->where('is_confidential', 0);
                        })
                            ->when($is_mc == 1, function ($query) {
                                $query->where('is_mc', 1);
                            }, function ($query) {
                                $query->where('is_mc', 0);
                            });

                    })
                    ->when($user_id, function ($query) use ($user_id, $permissionName) {
                        $query->where(function ($query) use ($user_id, $permissionName) {
                            $query->where('distributed_id', $user_id)
                                ->orWhere('approver_id', $user_id)
                                ->orWhere('users_id', $user_id)
                                ->orWhere(function ($query) use ($user_id, $permissionName) {
                                    $query->when($permissionName == 'Creation of Cheque', function ($query) use ($user_id) {
                                        $query->where('assigned_id', '=', $user_id)
                                            ->orWhereNull('assigned_id');
                                    });
                                });
                        });
                    })
                    ->when($receipt_type, function ($query) use ($receipt_type, $status) {
                        return $query->where('receipt_type', $receipt_type);
                    })
                    ->whereIn('status', $status)
                    ->where(function ($query) use ($permissionName) {
                        $query->where('status', '<>', 'approve-approve')
                            ->orWhere(function ($query) use ($permissionName) {
                                $query->where('status', '=', 'approve-approve')
                                    ->where('is_mc', $permissionName == 'Creation of Cheque' ? '=' : '<>', 1);
                            });
                    })
                    ->where(function ($query) use ($permissionName) {
                        $query->where('status', '<>', 'tag-tag')
                            ->orWhere(function ($query) use ($permissionName) {
                                $query->where('status', '=', 'tag-tag')
                                    ->where('receipt_type', $permissionName == 'Creation of Voucher' ? '=' : '<>', 'Unofficial')
                                    ->orWhere('receipt_type', null);
                            });
                    })
                    ->where(function ($query) use ($permissionName) {
                        $query->where('status', '<>', 'transmit-transmit')
                            ->orWhere(function ($query) use ($permissionName) {
                                $query->where('status', '=', 'transmit-transmit')
                                    ->where('document_id', $permissionName == 'Auditing of Voucher' ? '=' : '<>', 8);
                            });
                    })
                    ->where(function ($query) use ($permissionName) {
//                        $query->where('status', '<>', 'release-release')
                        $query->where('status', '<>', 'pass-pass')
                            ->orWhere(function ($query) use ($permissionName) {
//                                $query->where('status', '=', 'release-release')
                                $query->where('status', '=', 'pass-pass')
                                    ->where('receipt_type', $permissionName == 'Filing of Voucher' ? '=' : '<>', 'Unofficial')
                                    ->orWhere('receipt_type', null)
                                    ->orWhere('is_mc', 1);
                            });
                    })
                    ->groupBy('status')
                    ->get()
                    ->pluck('count', 'status')
                    ->toArray();

                foreach ($counts as $stat => $count) {
                    switch ($stat) {
                        case 'Pending':
                        case 'tag-tag':
                        case 'gas-gas':
                        case 'extract-extract':
                        case 'voucher-voucher':
                        case 'approve-approve':
                        case 'transmit-transmit':
                        case 'inspect-inspect':
                        case 'release-release':
                        case 'discharge-discharge':
                        case 'pass-pass':
                            $result['pending'] += $count;
                            break;

                        case 'tag-return':
                        case 'gas-return':
                        case 'voucher-return':
                        case 'cheque-return':
                        case 'approve-return':
                        case 'audit-return':
                        case 'release-return':
                        case 'inspect-return':
                        case 'issue-return':
                        case 'file-return':
                            $result['return'] += $count;
                            break;
                    }
                }

                $response[] = [
                    'permission' => $permissionName,
                    'result' => $result
                ];
            }
        }
        return response()->json($response);
    }

    public function statusChequeCounter()
    {
        $permissions = auth()->user()->permissions;

        $statusMap = [
            5 => ['cheque-cheque', 'executive-return'], //Auditing of Cheque
            6 => ['issue-issue'], // External Releasing of Cheque
            8 => ['release-release'], //Clearing of Cheque
            9 => [], //Creation of Debit Memo
            10 => [], //Reversal Request
            16 => ['issue-issue'], //Releasing of Confidential Cheque
            21 => [], //Creation of Counter Receipt
            22 => [], //Monitoring of Counter Receipt
            23 => ['audit-audit'], //Transmittal of Cheque
            24 => ['executive-executive'], //Internal Releasing of Cheque,
            39 => ['issue-receive'] //Approval of Cheque Cancellation
        ];

        $response = [];

        foreach ($permissions as $permission) {
            if (isset($statusMap[$permission])) {
                $status = $statusMap[$permission];
                $permissionName = Permission::where('id', $permission)->first()->name;
                $is_confidential = 0;
                $is_mc = 0;
                $user_id = null;

                // Initialize all status counts to zero
//                $result = array_fill_keys($status, 0);
                $result = [
                    'pending' => 0,
                    'return' => 0,
                ];

                switch ($permissionName) {
                    case 'Releasing of Confidential Cheque':
                        $is_confidential = 1;
                        break;

                    case 'Internal Releasing of Cheque':
                        $user_id = auth()->user()->id;
                        break;
                }

                // Count the total number of records for each status
                foreach ($status as $stat) {
                    $counts = Cheque::select('bank_id', 'cheque_no')
//                        ->when(isset($statusMap[$stat]), function ($query) use ($statusMap, $stat) {
//                            $query->whereHas('transaction', function ($query) use ($statusMap, $stat) {
//                                $query->whereIn('status', $statusMap[$stat]);
//                            });
//                        })

                        ->when($stat != in_array($stat, ['cheque-cheque', 'audit-audit', 'executive-executive', 'release-release']), function ($query) use ($is_confidential, $is_mc) {
                            $query->when($is_confidential == 1, function ($query) {
                                $query->whereHas('transaction', function ($query) {
                                    $query->where('is_confidential', 1);
                                });
                            }, function ($query) {
                                $query->whereHas('transaction', function ($query) {
                                    $query->where('is_confidential', 0);
                                });
                            });
                        })
                        ->when($stat == 'cheque-cheque', function ($query) {
                            $query->whereHas('transaction', function ($query) {
                                return $query->whereIn('status', ['cheque-cheque', 'audit-receive', 'audit-audit']);
                            })->whereNull('is_received')->whereNull('is_audited');
                        })
                        ->when($stat == 'audit-audit', function ($query) {
                            $query->whereHas('transaction', function ($query) {
                                $query->whereIn('status', ['cheque-cheque', 'audit-audit']);
                            })->where('is_audited', true)->whereNull('is_received');
                        })
                        ->when($stat == 'executive-executive', function ($query) {
                            $query->whereHas('transaction', function ($query) {
                                $query->whereIn('status', ['audit-audit', 'executive-executive']);
                            })->where('is_executived', true)->whereNull('is_received');
                        })
                        ->when($stat == 'issue-issue', function ($query) {
                            $query->whereHas('transaction', function ($query) {
                                $query->whereIn('status', ['executive-executive', 'issue-issue']);
                            })->where('is_issued', true)->whereNull('is_received');
                        })
                        ->when($stat == 'release-release', function ($query) {
                            $query->whereHas('transaction', function ($query) {
                                $query->whereIn('status', ['release-release', 'file-receive', 'file-file', 'discharge-receive', 'discharge-discharge']);
                            })->where('is_released', true)->whereNull('is_cleared');
                        })
                        ->when($stat == 'release-return', function ($query) {
                            $query->whereHas('transaction', function ($query) {
                                $query->whereIn('status', ['release-return']);
                            });
                        })
                        ->when($stat == 'executive-return', function ($query) {
                            $query->whereHas('transaction', function ($query) {
                                $query->whereIn('status', ['executive-return']);
                            });
                        })
                        ->when($stat == 'issue-receive', function ($query) {
                            $query->whereHas('transaction', function ($query) {
                                $query->whereIn('status', ['issue-receive']);
                            })
                                ->where('is_cancelled', false);
                        })
                        ->when($user_id, function ($query) use ($user_id) {
                            $query->whereHas('transaction', function ($query) use ($user_id) {
                                $query->where('assigned_id', $user_id)
                                    ->orWhereNull('assigned_id');
                            });
                        })
                        ->groupBy('bank_id', 'cheque_no')->get();

                    $counts = $counts->count();

                    // Only assign the count to the result if the status exists in the database
                    if ($counts > 0) {
//                        $result[$stat] = $counts;

                        switch ($stat) {
                            case 'audit-audit':
                            case 'cheque-cheque':
                            case 'issue-issue':
                            case 'release-release':
                            case 'executive-executive':
                            case 'issue-receive':
                                $result['pending'] = $counts;
                                break;

                            case 'voucher-return':
                            case 'approve-return':
                            case 'release-return':
                            case 'executive-return':
                                $result['return'] = $counts;
                                break;
                        }
                    }
                }

                $response[] = [
                    'permission' => $permissionName,
                    'result' => $result
                ];
            }
        }
        return response()->json($response);
    }

    public function statusJournalsCounter() {
        $permissions = auth()->user()->permissions;

        $statusMap = [
            41 => [], //General Journal - AP Approval *
            48, //General Journal - AR Approval *
            49, //General Journal - Treasury Approval - 12 *
            50, //General Journal - Cost & Budget 12 Approval *
            51, //General Journal - Fixed Asset 12 Approval *
            52, //General Journal - Confidential Approval - 12 *
            54, //General Journal - Sales Approval *
            55, //General Journal - FO Approval *
            56, //General Journal - Live Approval *
            61, //General Journal - Account Receivable Approval *
            68, //General Journal - AP Specialist Approval - 12 *
            69, //General Journal - PCF Approval *
            71, //General Journal - AP Specialist Approval - 22 *
            75, //General Journal - Fixed Asset Approval - 22 *
            76, //General Journal - Cost & Budget Approval - 22 *
            77, //General Journal - Cost & Budget Approval - 30 *
            79, //General Journal - Confidential Approval - 22 *
            85, //General Journal - Accruals & Reversals Approval *
            87, //General Journal - Treasury - Approval - 22 *
        ];

        $response = [];

        foreach ($permissions as $permission) {
            if (isset($statusMap[$permission])) {
                $permissionName = Permission::where('id', $permission)->first()->name;
                $approver_id = auth()->user()->id;
                $count = 0;

                switch($permissionName) {
                    case 'General Journal - AP Approval':
                        $db = 'general_journals';
                        $count = $this->journalCounter($db);
                        break;
                    case 'General Journal - Account Receivable Approval':
                        $db = 'account_receivable_journals';
                        $count = $this->journalCounter($db);
                        break;
                    case 'General Journal - Treasury Approval - 12':
                        $db = 'treasury_journals';
                        $count = $this->journalCounter($db);
                        break;
                    case 'Cost & Budget 12 Approval':
                        $db = 'cost_budget_journals';
                        $count = $this->journalCounter($db);
                        break;
                    case 'Fixed Asset 12 Approval':
                        $db = 'fixed_asset_journals';
                        $count = $this->journalCounter($db);
                        break;
                    case 'Confidential Approval - 12':
                        $db = 'confidential_journals';
                        $count = $this->journalCounter($db);
                        break;
                    case 'General Journal - Sales Approval':
                        $db = 'sales_journals';
                        $count = $this->journalCounter($db);
                        break;
                    case 'General Journal - FO Approval':
                        $db = 'fo_journals';
                        $count = $this->journalCounter($db);
                        break;
                    case 'General Journal - Live Approval':
                        $db = 'live_journals';
                        $count = $this->journalCounter($db);
                        break;
                    case 'General Journal - AP Specialist Approval - 12':
                        $db = 'ap_specialist_journals';
                        $count = $this->journalCounter($db);
                        break;
                    case 'General Journal - PCF Approval':
                        $db = 'pcf_journals';
                        $count = $this->journalCounter($db);
                        break;
                    case 'General Journal - AP Specialist Approval - 22':
                        $db = 'ap_specialist22_journals';
                        $count = $this->journalCounter($db);
                        break;
                    case 'General Journal - Fixed Asset Approval - 22':
                        $db = 'fixed_asset22_journals';
                        $count = $this->journalCounter($db);
                        break;
                    case 'General Journal - Cost & Budget Approval - 22':
                        $db = 'cost_and_budget22_journals';
                        $count = $this->journalCounter($db);
                        break;
                    case 'General Journal - Cost & Budget Approval - 30':
                        $db = 'cost_and_budget30_journals';
                        $count = $this->journalCounter($db);
                        break;
                    case 'General Journal - Confidential Approval - 22':
                        $db = 'confidential22_journals';
                        $count = $this->journalCounter($db);
                        break;
                    case 'General Journal - Accruals & Reversals Approval':
                        $db = 'accrual_reversal_journals';
                        $count = $this->journalCounter($db);
                        break;
                    case 'General Journal - Treasury - Approval - 22':
                        $db = 'treasury22_journals';
                        $count = $this->journalCounter($db);
                        break;
                }

                $response[] = [
                    'permission' => $permissionName,
                    'result' => [
                        'pending' => $count
                    ]
                ];
            }
        }

        return response()->json($response);
    }

    function journalCounter($table) {
        return DB::table($table)
            ->where([
                'deleted_at' => null,
                'approver_id' => auth()->user()->id,
                'is_approved' => null
            ])
            ->distinct('gj_number')
            ->count('gj_number');
    }

    public function chequeHistory($id)
    {
        $transactionIds = Treasury::where('transaction_id', $id)
            ->latest('updated_at')
            ->pluck('transaction_id');

        if ($transactionIds->isEmpty()) {
            return $this->resultResponse('not-found', 'Transaction', []);
        }

        $cheques = Cheque::onlyTrashed()
            ->whereIn('transaction_id', $transactionIds)
            ->select('bank_id', 'bank_name', 'cheque_no', 'cheque_amount', 'reason_id', 'reason')
            ->get();

        [$valid, $invalid] = $cheques->partition(function ($item) {
            return $item->reason_id == null;
        });

        return [
            'valid' => $valid->values(),
            'void' => $invalid->values(),
        ];
    }

    public function officialTransactions(Request $request): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
//        $transaction_from = date_format(date_create($request->input('transaction_from', Carbon::now()->format('Y-m-d'))), "Y-m-d");
//        $transaction_to = date_format(date_create($request->input('transaction_to', Carbon::now()->format('Y-m-d'))), "Y-m-d");

        $dateToday = Carbon::now()->timezone("Asia/Manila");
        $transaction_from = $this->getTransactionDate($request, "transaction_from", $dateToday->startOfMonth()->format("Y-m-d"));
        $transaction_to = $this->getTransactionDate($request, "transaction_to", $dateToday->endOfMonth()->format("Y-m-d"));

        $official = Transaction::where('receipt_type', 'Official')
            ->when($transaction_from, function ($query) use ($transaction_from) {
                return $query->whereDate('date_requested', '>=', $transaction_from);
            })
            ->when($transaction_to, function ($query) use ($transaction_to) {
                return $query->whereDate('date_requested', '<=', $transaction_to);
            })
            ->where("status", "gas-receive")
            ->latest("updated_at")
            ->get();

        return TransactionResource1::collection($official);
    }

    public function history(Request $request)
    {
        $dateToday = Carbon::now()->timezone("Asia/Manila");
        $status = $request->state;
        $rows = $request->rows;
        $search = $request->search;
        $tag_search = str_replace('tag#', '', $search);
        $suppliers = $this->getRequestData($request, "suppliers");
        $document_ids = $this->getRequestData($request, "document_ids");
        $companies = $this->getRequestData($request, "companies");
//        $transaction_from = Carbon::parse($this->getTransactionDate($request, "transaction_from", $dateToday->startOfMonth()->format("Y-m-d")))->startOfDay();
//        $transaction_to = Carbon::parse($this->getTransactionDate($request, "transaction_to", $dateToday->endOfMonth()->format("Y-m-d")))->endOfDay();
        $transaction_from = Carbon::parse($this->getTransactionDate($request, "transaction_from", $dateToday->format("Y-m-d")))->startOfDay();
        $transaction_to = Carbon::parse($this->getTransactionDate($request, "transaction_to", $dateToday->format("Y-m-d")))->endOfDay();
        $my_approve = $request->input('my_approve', 0);
        $is_confidential = $request->input('is_confidential', 0);
        $is_mc = $request->input('is_mc', 0);

        $statusMapping = [
            'tag' => ['relation' => 'tagHistory', 'table' => 'taggings', 'status' => 'tag-tag'],
            'inspect' => ['relation' => 'inspectHistory', 'table' => 'audits', 'status' => 'inspect-inspect'],
            'voucher' => ['relation' => 'voucherHistory', 'table' => 'associates', 'user' => 'distributed_id', 'status' => 'voucher-voucher'],
            'approve' => ['relation' => 'approveHistory', 'table' => 'approvers', 'user' => 'approver_id', 'role' => 'approver', 'status' => 'approve-approve'],
        ];

        $transactions = Transaction::
        leftJoin($statusMapping[$status]['table'], 'transactions.id', '=', $statusMapping[$status]['table'] . '.transaction_id')
            ->where($statusMapping[$status]['table'] . '.status', '=', $statusMapping[$status]['status'])
            ->orderBy($statusMapping[$status]['table'] . '.created_at', 'desc')
            ->with([
                $statusMapping[$status]['relation'],
                "users:id,first_name,middle_name,last_name,department,position",
                "supplier.supplier_type:id,type as name,transaction_days",
                "po_details:id,request_id,po_no,po_total_amount",
            ])
            ->when(isset($search), function ($query) use ($search) {
                $query->whereLike([
                    'tag_no',
                    'voucher_no',
                    'referrence_no',
                    'document_no'
                ], $search);
            }, function ($query) use ($statusMapping, $status, $transaction_from, $transaction_to) {
                $query->whereBetween($statusMapping[$status]['table'] . '.created_at', [$transaction_from, $transaction_to])
                    ->latest($statusMapping[$status]['table'] . '.created_at')
                    ->limit(1);
            })
            ->withTrashed(function ($query) {
                $query->where('deleted_at', '=', '2024-08-28 00:00:00');
            })
            ->select([
                "transactions.id",
                "transactions.users_id",
                "transactions.request_id",
                "transactions.supplier_id",
                "transactions.tag_no",
                "transactions.document_id",
                "transactions.transaction_id",
                "transactions.document_type",
                "transactions.payment_type",
                "transactions.remarks",
                "transactions.date_requested",
                "transactions.document_date",
                "transactions.company_id",
                "transactions.company",
                "transactions.department",
                "transactions.location",
                "transactions.document_no",
                "transactions.document_type",
                "transactions.document_amount",
                "transactions.referrence_no",
                "transactions.referrence_amount",
                "transactions.net_amount",
                "transactions.cheque_date",
                "transactions.receipt_type",
                "transactions.is_not_editable",
                "transactions.approver_id",
                "transactions.approver_name",
                "transactions.status",
                "transactions.state",
                "transactions.principal",
                "transactions.interest",
                "transactions.gross_amount",
                "transactions.category",
                "transactions.department_id",
                "transactions.location_id",
                "transactions.input_tax",
                "transactions.voucher_no",
                "transactions.voucher_month",
                "transactions.distributed_id",
                "transactions.distributed_name",
                'transactions.is_confidential',
                'transactions.is_mc',
                'transactions.is_new'
            ])
            ->when(isset($statusMapping[$status]['role']), function ($query) use ($statusMapping, $status, $my_approve) {
                $query->when($my_approve == 1, function ($query) {
                    $query->where('approver_id', auth()->user()->id);
                }, function ($query) use ($statusMapping, $status) {
                    $query->whereIn($statusMapping[$status]['user'], User::where('role', 'approver')->pluck('id'));
                });
            })
            ->when(isset($statusMapping[$status]['user']) && !isset($statusMapping[$status]['role']), function ($query) use ($statusMapping, $status) {
                $query->where($statusMapping[$status]['user'], auth()->user()->id);
            })
            ->when(!empty($document_ids), function ($query) use ($document_ids) {
                $query->whereIn("document_id", $document_ids);
            })
            ->when($status != 'approve', function ($query) use ($is_confidential) {
                $query->where('is_confidential', $is_confidential);
            })
//            ->when($is_mc == 1, function ($query) {
//                $query->where('is_mc', 1);
//            })
            ->when(!empty($suppliers), function ($query) use ($suppliers) {
                $query->whereIn("supplier_id", $suppliers);
            })
            ->when(!empty($companies), function ($query) use ($companies) {
                $query->whereIn("company_id", $companies);
            })
//            ->orderBy(DB::raw("(SELECT t.created_at FROM " . $statusMapping[$status]['table'] . " as t WHERE t.transaction_id = transactions.id ORDER BY t.created_at DESC LIMIT 1)"), 'desc')
            ->paginate($rows);

        $transactions = $this->historyIndexFormatter($transactions);

        if (count($transactions)) {
            return $this->resultResponse("fetch", "Transaction", $transactions);
        }
        return $this->resultResponse("not-found", "Transaction", []);
    }

    public function exportHistory(Request $request)
    {
        $dateToday = Carbon::now()->timezone("Asia/Manila");
        $transaction_from = Carbon::parse($this->getTransactionDate($request, "transaction_from", $dateToday->format("Y-m-d")))->startOfDay();
        $transaction_to = Carbon::parse($this->getTransactionDate($request, "transaction_to", $dateToday->format("Y-m-d")))->endOfDay();
        $status = $request->status;

        $transactions = Transaction::
        leftJoin('taggings', 'transactions.id', '=', 'taggings.transaction_id')
            ->where('taggings.status', '=', 'tag-tag')
//            ->whereBetween('taggings.created_at', [$transaction_from, $transaction_to])
                ->where(function ($query) use ($transaction_from, $transaction_to) {
                    $query->whereBetween('taggings.created_at', [$transaction_from, $transaction_to])
                        ->orWhereBetween('transactions.document_date', [$transaction_from, $transaction_to]);
            })
            ->with([
                'utilityLocation',
                'supplier',
                'supplier.supplier_type',
                'po_details',
                'receivedReceipts.purchaseOrders' => function ($query) {
                    $query->select('received_receipt_id', 'po_number');
                },
                'receivedReceipts.jobOrders' => function ($query) {
                    $query->select('received_receipt_id', 'jo_number');
                },
                $status
            ])
            ->select([
                'transactions.id',
                'transactions.company',
                'transactions.business_unit',
                'transactions.department',
                'transactions.unit',
                'transactions.sub_unit',
                'transactions.location',
                'transactions.document_id',
                'transactions.tag_no',
                'transactions.supplier_id',
                'transactions.date_requested',
                'transactions.category',
                'transactions.receipt_type',
                'transactions.state',
//                'transactions.status',
                'transactions.document_no',
                'transactions.referrence_no',
                'transactions.remarks',
                'transactions.document_amount',
                'transactions.document_date',
                'transactions.pcf_date',
                'transactions.gross_amount',
                'transactions.principal',
                'transactions.interest',
                'transactions.referrence_amount',
                'transactions.referrence_type',
                'taggings.status',
                'taggings.created_at',
                'transactions.utilities_from',
                'transactions.utilities_to',
                'transactions.utilities_location_id',
                'transactions.utilities_receipt_no'
            ])
            ->get();

        return $transactions->transform(function ($item) use ($status) {
            $stateChange = (new TransactionResource($item))->stateChange('tag');

            return [
                'fisto_tag_no' => $item->receipt_type == 'Unofficial' ? 'U-' . $item->tag_no : $item->tag_no,
                'company' => $item->company,
                'business_unit' => $item->business_unit,
                'department' => $item->department,
                'unit' => $item->unit,
                'sub_unit' => $item->sub_unit,
                'location' => $item->location,
                'color_of_receipt' => $item->referrence_type,
                'supplier' => $item->supplier,
                'date' => [
                    $stateChange => $this->getDateEveryStatus($item->$status, $item->status)
                ],
                'accnt_tag' => null,
                'transaction_date' => Carbon::parse($item->document_date)->format('Y-m-d'),
                'coverage_date' => $item->utilities_from ? Carbon::parse($item->utilities_from)->format('Y-m-d') . ' - ' . Carbon::parse($item->utilities_to)->format('Y-m-d') : null,
                'coverage_location' => isset($item->utilities_location_id)
                    ? $item->utilityLocation->location
                    : null,
                'SOA No.' => $item->utilities_receipt_no,
                'po' => $item->po_details->isNotEmpty()
                    ? $item->po_details->map(function ($po) {
                        return [
                            'po_no' => $po->po_no,
                        ];
                    })->pluck('po_no')->toArray()
                    : $item->receivedReceipts->map(function ($rr) {
                        return array_merge(
                            $rr->purchaseOrders->pluck('po_number')->toArray(),
                            $rr->jobOrders->pluck('jo_number')->toArray()
                        );
                    })->flatten()->unique()->values()->toArray(),
                'rr_number' => $item->po_details->isNotEmpty()
                    ? $item->po_details->first()->rr_group
                    : $item->receivedReceipts->map(function ($rr) {
                        return $rr->rr_number;
                    })->unique()->values(),
                'reference' => $item->document_no . ' ' . $item->referrence_no . ' ' . $item->remarks,
                'amount' => ($item->document_id == 3)
                    ? ($item->category == in_array($item->category, (new TransactionResource1($item))->getRental()) ? $item->gross_amount : (($item->principal + $item->interest)))
                    : $item->document_amount ?? $item->referrence_amount,
                'T' => null,
                'A' => null,
                'G' => null,
                'CV (13)' => null,
                'check_received_from_treasury' => null,
                'check_received_by_supplier/purchasing' => null,
                'remarks' => null,
                'date_returned_(PAD)_with_attachments_-_Maam_Vic' => null,
                'month' => null,
                'type1' => null,
                'PAD_REMARKS_(FULL_PAYMENT)' => null,
                'OLD_REMARKS' => null,
                'Status' => null,
                'month1' => null
            ];
        });
    }

    public function historyIndexFormatter($transactions)
    {
        $transactions->getCollection()->transform(function ($transaction) {
            $resource = new TransactionResource1($transaction);
            $rental = $resource->getRental();
            return [
                "id" => $transaction->id,
                "tag_no" => $transaction->tag_no,
                "users_id" => $transaction->users_id,
                "document_id" => $transaction->document_id,
                "transaction_id" => $transaction->transaction_id,
                "document_type" => $transaction->document_type,
                "payment_type" => $transaction->payment_type,
                "supplier" => $transaction->supplier,
                "remarks" => $transaction->remarks,
                "date_requested" => $transaction->date_requested,
                "company" => $transaction->company,
                "department" => $transaction->department,
                "location" => $transaction->location,
                "document_no" => $transaction->document_no,
                "document_amount" => ($transaction->document_id == 3)
                    ? ($transaction->category == in_array($transaction->category, $rental) ? $transaction->gross_amount : (($transaction->principal + $transaction->interest)))
                    : $transaction->document_amount,
                "cheque_date" => $transaction->document_id == 3 ? $transaction->cheque_date : null,
                "period_covered" => $transaction->document_id == 3 ? $transaction->period_covered : null,
                "referrence_no" => $transaction->referrence_no,
                "referrence_amount" => $transaction->referrence_amount,
                "users" => $transaction->users,
                "po_details" => in_array($transaction->document_id, [1, 2, 4, 5])
                    ? $transaction->po_details->map(function ($po) {
                        return [
                            "id" => $po->id,
                            "request_id" => $po->request_id,
                            "po_no" => $po->po_no,
                            "po_total_amount" => $po->po_total_amount
                        ];
                    })
                    : [],
                'receipt_type' => $transaction->receipt_type,
                'input_tax' => $transaction->input_tax,
                'voucher' => [
                    'no' => $transaction->voucher_no,
                ],
                'is_confidential' => $transaction->is_confidential,
                'is_mc' => $transaction->is_mc,
                "is_new" => $transaction->is_new ? 1 : 0,
                'distributed_name' => $transaction->distributed_name
            ];

        });

        return $transactions;
    }

    public function historyChequeIndex(Request $request)
    {
        $status = $request->input("state");
        $rows = $request->input("rows", 10);
        $search = $request->input("search");
        $suppliers = json_decode($request->input("suppliers")) ?? [];
        $companies = json_decode($request->input("companies")) ?? [];
        $document_ids = json_decode($request->input("document_ids")) ?? [];

        $cheque_from = isset($request["cheque_from"])
            ? Carbon::createFromFormat("Y-m-d", $request->input("cheque_from"))->format("Y-m-d")
            : null;
        $cheque_to = isset($request["cheque_to"])
            ? Carbon::createFromFormat("Y-m-d", $request->input("cheque_to"))->format("Y-m-d")
            : null;

        $statusMap = [
            'audit' => ['status' => 'is_audited'],
            'executive' => ['status' => 'is_executived'],
            'issue' => ['status' => 'is_issued'],
            'release' => ['status' => 'is_released'],
            'clear' => ['status' => 'is_cleared']
        ];

        $cheques = Cheque::withTrashed()
            //Supplier Filter
            ->when(isset($statusMap[$status]), function ($query) use ($statusMap, $status) {
                $query->where($statusMap[$status]['status'], true);
            })
            ->select("bank_id", "bank_name", "cheque_no", DB::raw("MAX(updated_at) as latest_updated_at"))
            ->when(count($suppliers), function ($query) use ($suppliers) {
                $query->whereHas("transaction", function ($query) use ($suppliers) {
                    return $query->whereIn("supplier_id", $suppliers)->withTrashed();
                });
            })
            ->when(count($companies), function ($query) use ($companies) {
                $query->whereHas("transaction", function ($query) use ($companies) {
                    return $query->whereIn("company_id", $companies)->withTrashed();
                });
            })
            ->when(count($document_ids), function ($query) use ($document_ids) {
                $query->whereHas("transaction", function ($query) use ($document_ids) {
                    return $query->whereIn("document_id", $document_ids)->withTrashed();
                });
            })

            // Search
            ->where(function ($query) use ($search) {
                $query->whereHas("transaction", function ($query) use ($search) {
                    $query
                        ->where("remarks", "like", "%" . $search . "%")
                        ->orWhere("payment_type", "like", "%" . $search . "%")
                        ->orWhere("tag_no", "like", "%" . $search . "%")
                        ->orWhere("company", "like", "%" . $search . "%")
                        ->orWhere("department", "like", "%" . $search . "%")
                        ->orWhere("location", "like", "%" . $search . "%")
                        ->orWhere("supplier", "like", "%" . $search . "%")
                        ->orWhere("document_no", "like", "%" . $search . "%")
                        ->orWhere("referrence_no", "like", "%" . $search . "%")
                        ->withTrashed();
                })
                    ->orWhere("cheque_no", "like", "%" . $search . "%");
            })
            ->groupBy("bank_name", "cheque_no", "bank_id")
            ->orderBy("latest_updated_at", "desc")
            ->paginate((int)$rows);

        $cheques->transform(function ($item) {
            $ids = Cheque::where("bank_id", $item->bank_id)
                ->where("cheque_no", $item->cheque_no)
                ->pluck("transaction_id")
                ->unique()
                ->toArray();

            $cheque_details = Cheque::where("bank_id", $item->bank_id)
                ->withTrashed()
                ->where("cheque_no", $item->cheque_no)
                ->first();

            $transaction = Transaction::withTrashed()->whereIn("id", $ids)->get();
            $rental = [
                'stall a rental',
                'stall b rental',
                'stall c rental',
                'stall d rental',
                'cusa rental',
                'dorm rental',
                'additional rental',
                'lounge rental',
                'corporate special program - education',
                'official store rental',
                'unofficial store rental',
                'rental'
            ];

            $transaction = $transaction->map(function ($item) use ($ids, $rental) {
                return [
                    "id" => $item->id,
                    "tag_no" => $item->tag_no,
                    "transaction_no" => $item->transaction_id,
                    "input_tax" => $item->input_tax,
                    "receipt_type" => $item->receipt_type,
                    "payment_type" => $item->payment_type,
                    "document" => [
                        "id" => $item->document_id,
                        "name" => $item->document_type,
                    ],
                    "document_date" => $item->document_date,
                    "category" => $item->category ?? "-",
                    "document_no" => $item->document_no,
                    "document_amount" =>
                        $item->document_id == 3
                            ? (in_array($item->category, $rental)
                            ? $item->gross_amount
                            : $item->principal + $item->interest)
                            : $item->document_amount,
                    "referrence_no" => $item->referrence_no,
                    "referrence_amount" => $item->referrence_amount,
                    "date_requested" => $item->date_requested,
                    "company" => [
                        "id" => $item->company_id,
                        "name" => $item->company,
                    ],
                    "department" => [
                        "id" => $item->department_id,
                        "name" => $item->department,
                    ],
                    "location" => [
                        "id" => $item->location_id,
                        "name" => $item->location,
                    ],
                    "voucher" => [
                        "no" => $item->voucher_no,
                        "month" => $item->voucher_month,
                    ],
                    "remarks" => $item->remarks,
                    "status" => $item->status,
                    "state" => $this->stateChange($item->state),
                ];
            });
            $supplier = Transaction::withTrashed()->whereIn("id", $ids)
                ->with([
                    "supplier.supplier_type" => function ($query) {
                        return $query->select(["supplier_types.id", "supplier_types.type as name"]);
                    },
                ])
                ->get("supplier_id")
                ->pluck("supplier")
                ->flatten()
                ->unique("supplier_id")
                ->first();

            $distributed = Transaction::withTrashed()->whereIn("id", $ids)
                ->select("distributed_id", "distributed_name")
                ->distinct("distributed_id")
                ->first();

            $supplier = $supplier
                ? [
                    "id" => $supplier["id"],
                    "name" => $supplier["name"],
                    "type" => $supplier["supplier_type"]["name"] ?? null,
                ]
                : null;

            $distributed = $distributed
                ? [
                    "id" => $distributed["distributed_id"],
                    "name" => $distributed["distributed_name"],
                ]
                : null;

            $treasury_account_titles = $this->getTreasuryAccountTitles($ids, $cheque_details);

            $account_titles = $treasury_account_titles->map(function ($item) {
                return [
                    "entry" => $item->entry,
                    "account_title" => [
                        "id" => $item->account_title_id,
                        "code" => $item->account_title_code,
                        "name" => $item->account_title_name,
                    ],
                    "amount" => $item->amount,
                    "remarks" => $item->remarks,
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
                    "is_default" => $item->is_default,
                ];
            });

            $bank = $cheque_details->bank;
            $bank_account_title_two = $bank->AccountTitleTwo;
            $bank_company_one = $bank->CompanyOne;
            $bank_company_two = $bank->CompanyTwo;
            $bank_department_one = $bank->DepartmentOne;
            $bank_department_two = $bank->DepartmentTwo;
            $bank_location_one = $bank->LocationOne;
            $bank_location_two = $bank->LocationTwo;
            $bank_business_unit_one = $bank->BusinessUnitOne;
            $bank_business_unit_two = $bank->BusinessUnitTwo;
            $bank_sub_unit_one = $bank->SubUnitOne;
            $bank_sub_unit_two = $bank->SubUnitTwo;

            $cheques = [
                "type" => $cheque_details->entry_type,
                "bank" => $bank,
                "no" => $cheque_details->cheque_no,
                "date" => $cheque_details->cheque_date,
                "amount" => $cheque_details->cheque_amount,
                "date_cleared" => $cheque_details->date_cleared,
            ];

            return [
                "type" => $cheque_details->entry_type,
                "no" => $item->cheque_no,
                "bank" => [
                    "id" => $item->bank_id,
                    "name" => $item->bank_name,
                ],
                "amount" => $cheque_details->cheque_amount,
                "date" => $cheque_details->cheque_date,
                "supplier" => (object)$supplier,
                "accounts" => $account_titles,
                "transactions" => $transaction,
                "cheque" => $cheques,
                "distributed" => $distributed,
            ];
        });

        if (count($cheques)) {
            return $this->resultResponse("fetch", "Transaction", $cheques);
        }
        return $this->resultResponse("not-found", "Transaction", []);
    }

    public function voucherTransaction($id)
    {
        $transaction = Transaction::where('id', $id)->withTrashed()->first();
        return new TransactionVoucherResource($transaction);
    }

    public function chequeTransaction($id)
    {
        $transaction = Transaction::where('id', $id)->withTrashed()->first();
        return new TransactionChequeResource($transaction);
    }

    public function voucherNumberDropdown(Request $request)
    {
//        return Transaction::vnumbers($request->input('status'))->get();

        return Transaction::when($request->input('status') == 'approve-approve', function ($query) {
            $query->whereIn('status', ['approve-approve'])
                ->where([
                    'is_mc' => 0,
                    'is_confidential' => 0
                ]);
        })
            ->when($request->input('status') == 'cheque-cheque', function ($query) {
                $query->whereIn('status', ['transmit-transmit', 'cheque-receive', 'inspect-inspect'])
                    ->orWhere(function ($query) {
                        $query->whereIn('status', ['approve-approve'])->where('is_mc', 1);
                    });
            })
            ->select([
                "id",
                "voucher_no"
            ])->get();
    }

    public function cashOutflowReport(Request $request) {
        $date = Carbon::parse($request->input('date'))->timezone("Asia/Manila")->day(15)->format('Y-m-d');
        $year = date('Y', strtotime($date));
        $month = date('m', strtotime($date));

        $transactions = Transaction::
        with([
            'company_info:id,code',
            'chequeHistory',
            'treasuryCheque',
            'company_info',
            'cheques'
        ])
            ->whereHas('chequeHistory', function ($query) use ($year, $month) {
                $query->whereMonth('created_at', $month)
                    ->whereYear('created_at', $year);
            })
            ->select(
                'id',
                'tag_no',
                'company_id',
                'company',
                'supplier',
                'remarks',
                'voucher_no',
                'document_date'
            )
            ->get();

        return $transactions->transform(function ($item) {
            $datetime = Carbon::parse($this->getDateEveryStatus($item->cheques, 'cheque-receive'))->setTimezone('Asia/Manila');
            return [
                'id' => $item->id,
                'dates' => [
                    'date_received' => $datetime->format('d/m/Y'),
                    'time_received' => $datetime->format('h:ia'),
                ],
                'tag_no' => $item->tag_no,
                'company' => [
                    'code' => $item->company_info->code,
                    'name' => $item->company,
                ],
                'type' => null,
                'payee' => $item->supplier,
                'cheque' => $item->treasuryCheque->map(function ($item) {
                    return [
                        'bank' => $item->bank_name,
                        'cheque_no' => $item->cheque_no,
                        'amount' => -abs($item->cheque_amount),
                        'cheque_date' => Carbon::parse($item->cheque_date)->format('m/d/Y'),
                    ];
                }),
                'transaction' => $item->remarks,
                'cv/ref_no' => $item->voucher_no,
                'invoice_date' => Carbon::parse($item->document_date)->format('d/m/Y'),
                'date_cleared/deposited' => null,
                'date_transmitted' => null,
                'transmitted_to' => null,
                'a-tag_no' => null,
                'month_cleared' => null,
                'remarks' => null,
                'status' => null,
                'db' => null,
                'rem' => null,
                'terms' => null,
                'for_budget' => null,
                'gas_remarks' => null,
            ];
        });
    }

    public function generalNumbersDropdown(Request $request)
    {
        $voucher_month = $request->voucher_month;

        $year = date('Y', strtotime($voucher_month));
        $month = date('m', strtotime($voucher_month));

        $gjNumbers = GeneralJournal::
        whereMonth('updated_at', $month)
            ->whereYear('updated_at', $year)
            ->where([
                'type' => 'Accruals',
                'user_id' => auth()->user()->id,
                'is_reversed' => true
            ])
            ->get();

        return $gjNumbers->pluck('gj_number')->unique()->values()
            ->map(function ($item) {

                $account_titles = GeneralJournal::where('gj_number', $item)
                    ->get();

                return [
                    'gj_number' => $item,
                    'account_titles' => $account_titles->filter(function ($item) {
                        return $item->entry == 'Credit';
                    })->transform(function ($item) {
                        return [
                            'entry' => 'debit',
                            'account_title_id' => $item->account_title_id,
                            'account_title_code' => $item->account_title_code,
                            'account_title' => $item->account_title_name,
                            'amount' => $item->amount,
                            'company_id' => $item->company_id,
                            'company_code' => $item->company_code,
                            'company' => $item->company_name,
                            'department_id' => $item->department_id,
                            'department_code' => $item->department_code,
                            'department' => $item->department_name,
                            'location_id' => $item->location_id,
                            'location_code' => $item->location_code,
                            'location' => $item->location_name,
                            'business_unit_id' => $item->business_unit_id,
                            'business_unit_code' => $item->business_unit_code,
                            'business_unit' => $item->business_unit_name,
                            'sub_unit_id' => $item->sub_unit_id,
                            'sub_unit_code' => $item->sub_unit_code,
                            'sub_unit' => $item->sub_unit_name,
                            'remarks' => $item->remarks,
                        ];
                    }),
                ];
            });

    }

    public function searchBankCheque(Request $request)
    {
        $bankID = $request->bank_id;
        $chequeNo = $request->cheque_no;

        $cheque = Cheque::where('bank_id', $bankID)
            ->where('cheque_no', $chequeNo)
            ->with([
                'bank' => function ($query) {
                    $query->with([
                        'AccountTitleOne',
                        'AccountTitleTwo',
                        'CompanyOne',
                        'CompanyTwo',
                        'DepartmentOne',
                        'DepartmentTwo',
                        'LocationOne',
                        'LocationTwo',
                        'BusinessUnitOne',
                        'BusinessUnitTwo',
                        'UnitOne',
                        'UnitTwo',
                        'SubUnitOne',
                        'SubUnitTwo'
                    ]);
                }
            ])
            ->select("bank_id", "bank_name", "cheque_no", DB::raw("MAX(updated_at) as latest_updated_at"))
            ->groupBy("bank_name", "cheque_no", "bank_id")
            ->orderBy("latest_updated_at", "desc")
            ->get();

        $cheque->transform(function ($item) {
            $ids = Cheque::where("bank_id", $item->bank_id)
                ->where("cheque_no", $item->cheque_no)
                ->pluck("transaction_id")
                ->unique()
                ->toArray();

            $cheque_details = Cheque::where("bank_id", $item->bank_id)
                ->where("cheque_no", $item->cheque_no)
                ->first();

            $transaction = Transaction::whereIn("id", $ids)
                ->with('account_titles')
                ->get();
            $rental = [
                'stall a rental',
                'stall b rental',
                'stall c rental',
                'stall d rental',
                'cusa rental',
                'dorm rental',
                'additional rental',
                'lounge rental',
                'corporate special program - education',
                'official store rental',
                'unofficial store rental',
                'rental'
            ];

            $transaction = $transaction->map(function ($item) use ($ids, $rental) {
                return [
                    "id" => $item->id,
                    "tag_no" => $item->tag_no,
                    "transaction_no" => $item->transaction_id,
                    "input_tax" => $item->input_tax ?? 0,
                    "receipt_type" => $item->receipt_type ?? '---',
                    "payment_type" => $item->payment_type,
                    "document" => [
                        "id" => $item->document_id,
                        "name" => $item->document_type,
                    ],
                    "document_date" => $item->document_date ?? $item->date_requested,
                    "category" => $item->category ?? "---",
                    "document_no" => $item->document_no ?? '---',
                    "document_amount" =>
                        $item->document_id == 3
                            ? (in_array($item->category, $rental)
                            ? $item->gross_amount
                            : $item->principal + $item->interest)
                            : $item->document_amount,
                    "referrence_no" => $item->referrence_no,
                    "referrence_amount" => $item->referrence_amount,
                    "date_requested" => $item->date_requested,
                    "company" => [
                        "id" => $item->company_id,
                        "name" => $item->company,
                    ],
                    "department" => [
                        "id" => $item->department_id,
                        "name" => $item->department,
                    ],
                    "location" => [
                        "id" => $item->location_id,
                        "name" => $item->location,
                    ],
                    "voucher" => [
                        "no" => $item->voucher_no,
                        "month" => $item->voucher_month,
//                        "account_titles" => $item->voucher->first()->account_title->map(function ($item) {
                        "account_titles" => $item->account_titles->map(function ($item) {
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
                        }),
                    ],
                    "remarks" => $item->remarks,
                    "status" => $item->status,
                    "state" => $this->stateChange($item->state),
                ];
            });
            $supplier = Transaction::whereIn("id", $ids)
                ->with([
                    "supplier.supplier_type" => function ($query) {
                        return $query->select(["supplier_types.id", "supplier_types.type as name"]);
                    },
                ])
                ->get("supplier_id")
                ->pluck("supplier")
                ->flatten()
                ->unique("supplier_id")
                ->first();

            $distributed = Transaction::whereIn("id", $ids)
                ->select("distributed_id", "distributed_name")
                ->distinct("distributed_id")
                ->first();

            $supplier = $supplier
                ? [
                    "id" => $supplier["id"],
                    "name" => $supplier["name"],
                    "type" => $supplier["supplier_type"]["name"] ?? null,
                ]
                : null;

            $distributed = $distributed
                ? [
                    "id" => $distributed["distributed_id"],
                    "name" => $distributed["distributed_name"],
                ]
                : null;


            $treasury_account_titles = $this->getTreasuryAccountTitles($ids, $cheque_details);

            $account_titles = $treasury_account_titles->map(function ($item) {
                return [
                    "entry" => $item->entry,
                    "account_title" => [
                        "id" => $item->account_title_id,
                        "code" => $item->account_title_code,
                        "name" => $item->account_title_name,
                    ],
                    "amount" => $item->amount,
                    "remarks" => $item->remarks,
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
                    "is_default" => $item->is_default,
                ];
            });

//            $bank = $cheque_details->bank;
//            $bank_account_title_two = $bank->AccountTitleTwo;
//            $bank_company_one = $bank->CompanyOne;
//            $bank_company_two = $bank->CompanyTwo;
//            $bank_department_one = $bank->DepartmentOne;
//            $bank_department_two = $bank->DepartmentTwo;
//            $bank_location_one = $bank->LocationOne;
//            $bank_location_two = $bank->LocationTwo;
//            $bank_business_unit_one = $bank->BusinessUnitOne;
//            $bank_business_unit_two = $bank->BusinessUnitTwo;
//            $bank_sub_unit_one = $bank->SubUnitOne;
//            $bank_sub_unit_two = $bank->SubUnitTwo;

            $cheques = [
                "type" => $cheque_details->entry_type,
                "bank" => $item->bank,
                "no" => $cheque_details->cheque_no,
                "date" => $cheque_details->cheque_date,
                "amount" => $cheque_details->cheque_amount,
                "date_cleared" => $cheque_details->date_cleared,
                "date_issued" => $cheque_details->issue->created_at ?? null,
            ];

            return [
                "type" => $cheque_details->entry_type,
                "no" => $item->cheque_no,
                "bank" => [
                    "id" => $item->bank_id,
                    "name" => $item->bank_name,
                ],
                "amount" => $cheque_details->cheque_amount,
                "date" => $cheque_details->cheque_date,
                "supplier" => (object)$supplier,
                "accounts" => $account_titles,
                "transactions" => $transaction,
                "cheque" => $cheques,
                "distributed" => $distributed,
            ];
        });

        if (count($cheque)) {
            return $this->resultResponse("fetch", "Transaction", $cheque);
        }
        return $this->resultResponse("not-found", "Transaction", []);
    }

    public function adjustDate(Request $request)
    {

        $bankID = $request->bank_id;
        $chequeNo = $request->cheque_no;
        $chequeDate = $request->cheque_date;

        $transactions = $request->transactions;
        for ($i = 0; $i < count($transactions); $i++) {
            $transaction = Transaction::find($transactions[$i]);
            $issue = $transaction->issue()->create([
                'status' => 'issue-issue'
            ]);

            for ($j = 0; $j < count($request->accounts); $j++) {
                $account = $request->accounts[$j];

                $issue->accountTitles()->create([
                    'entry' => data_get($account, 'entry'),
                    'account_title_id' => data_get($account, 'account_title.id'),
                    'account_title_code' => data_get($account, 'account_title.code'),
                    'account_title_name' => data_get($account, 'account_title.name'),
                    'amount' => data_get($account, 'amount'),
                    'remarks' => data_get($account, 'remarks'),
                    'transaction_type' => 'new',
                    'company_id' => data_get($account, 'company.id'),
                    'company_code' => data_get($account, 'company.code'),
                    'company_name' => data_get($account, 'company.name'),
                    'department_id' => data_get($account, 'department.id'),
                    'department_code' => data_get($account, 'department.code'),
                    'department_name' => data_get($account, 'department.name'),
                    'location_id' => data_get($account, 'location.id'),
                    'location_code' => data_get($account, 'location.code'),
                    'location_name' => data_get($account, 'location.name'),
                    'business_unit_id' => data_get($account, 'business_unit.id'),
                    'business_unit_code' => data_get($account, 'business_unit.code'),
                    'business_unit_name' => data_get($account, 'business_unit.name'),
                    'unit_id' => data_get($account, 'unit.id'),
                    'unit_code' => data_get($account, 'unit.code'),
                    'unit_name' => data_get($account, 'unit.name'),
                    'sub_unit_id' => data_get($account, 'sub_unit.id'),
                    'sub_unit_code' => data_get($account, 'sub_unit.code'),
                    'sub_unit_name' => data_get($account, 'sub_unit.name'),
                ]);
            }
        }

        Cheque::where('bank_id', $bankID)
            ->where('cheque_no', $chequeNo)
            ->update([
                'issue_id' => $issue->id,
                'cheque_date' => $chequeDate,
            ]);

        return response()->json([
            'message' => 'Cheque successfully adjusted'
        ], 200);
    }


    public function formatItem($item): array
    {
        $poNumber = collect($item->po_number)->unique();
        $joNumber = collect($item->jo_number)->unique();
        $combine = collect(array_merge($poNumber->toArray(), $joNumber->toArray()));

        $item = is_object($item) ? (array)$item : $item;

        return [
            "syncId" => $item['id'],
            "mark1" => "",
            "mark2" => "",
            "assetCIP" => "",
            "accountingTag" => $item['tag_no'],
            "transactionDate" => Carbon::parse($item['date_requested'])->format("Y-m-d"),
            "clientSupplier" => $item['supplier'],
            "accountTitleCode" => $item['account_title_code'] ?? null,
            "accountTitle" => $item['account_title_name'],
            "companyCode" => "0000",
            "company" => "RDFFLFI",
            "divisionCode" => $item['company_code'],
            "division" => $item['company_name'],
            "departmentCode" => $item['department_code'],
            "department" => $item['department_name'],
            "locationCode" => $item['location_code'],
            "location" => $item['location_name'],
            "unitCode" => $item['business_unit_code'],
            "unit" => $item['business_unit_name'],
            "subUnitCode" => $item['sub_unit_code'],
            "subUnit" => $item['sub_unit_name'],
            "poNumber" => $item['rr_number']
                ? $combine
                : explode(', ', $item['po_no']) ?? [],
            "rrNumber" => collect($item['rr_number'])->filter()->unique()->isNotEmpty()
                ? collect(array(collect($item['rr_number'])->unique()->implode(",")))
                : (json_decode($item['rr_group'], true)),
            "referenceNo" => $item['document_no'] ?? ($item['referrence_no'] ?? ($item['utilities_receipt_no'] ?? "x")),
            "itemCode" => "",
            "itemDescription" => "",
            "quantity" => "",
            "uom" => "",
            "unitPrice" => "",
            "lineAmount" => $item['entry'] == "Credit" ? -abs($item['amount']) : $item['amount'],
            "voucherJournal" => $item['voucher_no'],
            "accountType" => $item['account_type'] ?? "",
            "drcr" => $item['entry'],
            "assetCode" => "",
            "asset" => "",
            "serviceProviderCode" => ($item['service_provider_code']) ?? "",
            "serviceProvider" => ($item['service_provider']) ?? "",
            "boa" => $item['boa'],
            "allocation" => null,
            "accountGroup" => $item['account_group'] ?? "",
            "accountSubGroup" => $item['account_sub_group'] ?? "",
            "financialStatement" => $item['financial_statement'] ?? "",
            "unitResponsible" => $item['unit_responsible'] ?? "",
            "batch" => ($item['pcf_letter'] . " " . $item['pcf_date']) ?? "",
            "lineDescription" => $item['line_description'],
            "payrollPeriod" => "",
            "position" => "",
            "payrollType" => "",
            "payrollType2" => "",
            "depreciationDescription" => "",
            "remainingDepreciationValue" => "",
            "usefulLife" => "",
            "month" => $item['month'],
            "year" => $item['year'],
            "particulars" => "",
            "month2" => "",
            "farmType" => "",
            "jeanRemarks" => "",
            "from" => "",
            "changeTo" => "",
            "reason" => "",
            "checkingRemarks" => "",
            "bankName" => collect($item['bank_name'])->filter()->unique()->isNotEmpty()
                ? [collect($item['bank_name'])->unique()->implode(",")]
                : [],
            "chequeNumber" => collect($item['cheque_no'])->filter()->unique()->isNotEmpty()
                ? [collect($item['cheque_no'])->unique()->implode(",")]
                : [],
            "chequeDate" => $item['cheque_date'] ? Carbon::parse($item['cheque_date'])->format("Y-m-d") : null,
            "releasedDate" => $item['created_at'] ? Carbon::parse($item['created_at'])->format("Y-m-d") : null,
            "chequeVoucherNumber" => $item['voucher_no'],
            "boA2" => $item['boA2'],
            "system" => "FISTO",
            "books" => $item['books'],
        ];
    }

    public function chequeExport(Request $request) {
        $voucherMonth = $request->input('voucher_month');

        return Excel::download(new ChequeExport($voucherMonth), 'cheques.xlsx');
    }
}
