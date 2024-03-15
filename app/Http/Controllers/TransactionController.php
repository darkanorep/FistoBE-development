<?php

namespace App\Http\Controllers;

use App\Http\Resources\ChequeClearIndex;
use App\Http\Resources\ChequeIndex;
use App\Http\Resources\TransactionResource1;
use App\Models\Audit;
use App\Models\BankSeries;
use App\Models\Cheque;
use App\Models\Clear;
use App\Models\ClearingAccountTitle;
use App\Models\Permission;
use App\Models\Supplier;
use App\Models\Treasury;
use App\Models\VoucherAccountTitle;
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
use Carbon\Carbon;

use App\Http\Requests\TransactionPostRequest;
use Illuminate\Validation\Rule;

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

    function getRequestData($request, $key, $default = []) {
        return isset($request[$key]) && $request[$key]
            ? array_map("intval", json_decode($request[$key]))
            : $default;
    }

    function getTransactionDate($request, $key, $default) {
        return isset($request[$key]) && $request[$key]
            ? Carbon::createFromFormat("Y-m-d", $request[$key])->format("Y-m-d H:i:s")
            : $default;
    }

    public function index(Request $request)
    {
        $dateToday = Carbon::now()->timezone("Asia/Manila");

        $department = [];
        $users_id = Auth::user()->id;
        $role = Auth::user()->role;
        $status = isset($request["state"]) && $request["state"] ? $request["state"] : "request";
        $rows = isset($request["rows"]) && $request["rows"] ? (int)$request["rows"] : 10;
//        $suppliers =
//            isset($request["suppliers"]) && $request["suppliers"]
//                ? array_map("intval", json_decode($request["suppliers"]))
//                : [];
//        $document_ids =
//            isset($request["document_ids"]) && $request["document_ids"]
//                ? array_map("intval", json_decode($request["document_ids"]))
//                : [];
//        $transaction_from =
//            isset($request["transaction_from"]) && $request["transaction_from"]
//                ? Carbon::createFromFormat("Y-m-d", $request["transaction_from"])
//                ->startOfDay()
//                ->format("Y-m-d H:i:s")
//                : $dateToday->startOfDay()->format("Y-m-d H:i:s");
//        $transaction_to =
//            isset($request["transaction_to"]) && $request["transaction_to"]
//                ? Carbon::createFromFormat("Y-m-d", $request["transaction_to"])
//                ->endOfDay()
//                ->format("Y-m-d H:i:s")
//                : $dateToday->endOfDay()->format("Y-m-d H:i:s");
//        $cheque_from =
//            isset($request["cheque_from"]) && $request["cheque_from"]
//                ? Carbon::createFromFormat("Y-m-d", $request["cheque_from"])
//                ->startOfDay()
//                ->format("Y-m-d H:i:s")
//                : $dateToday->startOfDay()->format("Y-m-d H:i:s");
//        $cheque_to =
//            isset($request["cheque_to"]) && $request["cheque_to"]
//                ? Carbon::createFromFormat("Y-m-d", $request["cheque_to"])
//                ->endOfDay()
//                ->format("Y-m-d H:i:s")
//                : $dateToday->endOfDay()->format("Y-m-d H:i:s");
        $suppliers = $this->getRequestData($request, "suppliers");
        $document_ids = $this->getRequestData($request, "document_ids");
        $transaction_from = $this->getTransactionDate($request, "transaction_from", $dateToday->startOfMonth()->format("Y-m-d H:i:s"));
        $transaction_to = $this->getTransactionDate($request, "transaction_to", $dateToday->endOfMonth()->format("Y-m-d H:i:s"));
        $cheque_from = $this->getTransactionDate($request, "cheque_from", $dateToday->startOfMonth()->format("Y-m-d H:i:s"));
        $cheque_to = $this->getTransactionDate($request, "cheque_to", $dateToday->endOfMonth()->format("Y-m-d H:i:s"));
        $search = $request["search"];
        $tag_search = str_replace('tag#', '', $search);
        $state = isset($request["state"]) ? $request["state"] : "request";
        !empty($request["department"])
            ? ($department = json_decode($request["department"]))
            : array_push($department, Auth::user()->department[0]["name"]);
        $is_auto_debit = isset($request["is_auto_debit"]) && $request["is_auto_debit"] ? 1 : 0;

        $request_window = ["Requestor"];
        $admin_window = ["Administrator"];
        $tag_window = ["AP Tagging"];
        $voucher_window = ["AP Associate", "AP Specialist"];
        $approve_window = ["Approver"];
        $cheque_window = ["Treasury Associate"];
        $audit_window = ["Audit Associate"];
        $executive_assistant = ["Executive Assistant"];
        $gas_window = ["GAS Associate"];

        $is_voucher_transfered = $status == "voucher-transfer";
        $is_transmit_transfered = $status == "transmit-transfer";
        $is_file_transfered = $status == "file-transfer";

//        $transactions = Transaction::where(function ($query) use ($suppliers, $document_ids) {
//                $query->whereIn('supplier_id', $suppliers)
//                    ->orWhereIn('document_id', $document_ids);
//            })
//            ->paginate($rows);
//
//        TransactionIndex::collection($transactions);
//
//        if (count($transactions)) {
//            return $this->resultResponse("fetch", "Transaction", $transactions);
//        }
//        return $this->resultResponse("not-found", "Transaction", []);

        $transactions = Transaction::select([
            "id",
            "company_id",
            // ,'tag_no'
        ])
            ->with([
                "users:id,first_name,middle_name,last_name,department,position",
                //          "supplier:id,name,supplier_type_id",
                "supplier.supplier_type:id,type as name,transaction_days",
                "po_details:id,request_id,po_no,po_total_amount",
//                "cheques.cheques",
            ])
            ->when(!empty($document_ids), function ($query) use ($document_ids) {
                $query->whereIn("document_id", $document_ids);
            })
            ->when(!empty($suppliers), function ($query) use ($suppliers) {
                $query->whereIn("supplier_id", $suppliers);
            })
            ->when(
                isset($request["cheque_from"]) || isset($request["cheque_to"]),
                function ($query) use ($cheque_from, $cheque_to) {
                    $query->whereHas("cheques.cheques",  function ($query) use ($cheque_from, $cheque_to) {
                        $query->where("cheque_date", ">=", $cheque_from)->where("cheque_date", "<=", $cheque_to);
                    });
                },
                function ($query) use ($document_ids, $suppliers, $transaction_from, $transaction_to) {
                    $query->when(!empty($document_ids) || !empty($suppliers), function ($query) use (
                        $transaction_from,
                        $transaction_to
                    ) {
                        $query->where("date_requested", ">=", $transaction_from)->where("date_requested", "<=", $transaction_to);
                    });
                }
            )
            ->where(function ($query) use ($search, $tag_search) {
                $query
                    ->where("date_requested", "like", "%" . $search . "%")
                    ->orWhere("remarks", "like", "%" . $search . "%")
//                    ->orWhere("tag_no", "like", "%" . $search . "%")
                    ->orWhere("tag_no", "=", $tag_search)
                    ->orWhere("transaction_id", "like", "%" . $search . "%")
                    ->orWhere("document_amount", "like", "%" . $search . "%")
                    ->orWhere("document_type", "like", "%" . $search . "%")
                    ->orWhere("payment_type", "like", "%" . $search . "%")
                    ->orWhere("company", "like", "%" . $search . "%")
                    ->orWhere("department", "like", "%" . $search . "%")
                    ->orWhere("location", "like", "%" . $search . "%")
                    ->orWhere("supplier", "like", "%" . $search . "%")
                    ->orWhere("document_no", "like", "%" . $search . "%")
                    ->orWhere("referrence_no", "like", "%" . $search . "%")
                    ->orWhere("po_total_amount", "like", "%" . $search . "%")
                    ->orWhere("referrence_total_amount", "like", "%" . $search . "%")
                    ->orWhereHas("po_details", function ($query) use ($search) {
                        $query->where("po_no", "like", "%" . $search . "%");
                    })
                    ->orWhereHas("users", function ($query) use ($search) {
                        $query->where(
                            DB::raw(
                                "REPLACE(
                        CONCAT(
                            COALESCE(first_name,''),' ',
                            COALESCE(last_name,''),
                            COALESCE(suffix,'')
                        ),
                    '  ',' ')"
                            ),
                            "like",
                            "%" . $search . "%"
                        );
                    });
            })
            ->when(in_array($role, $request_window), function ($query) use ($status, $department) {
                $query
                    ->when(
                        strtoupper($status) == "PENDING",
                        function ($query) {
                            $query->whereNotIn("status", ["requestor-void", "tag-return"]);
                        },
                        function ($query) use ($status) {
                            $query->when(
                                strtolower($status) == "return-request",
                                function ($query) use ($status) {
                                    $query->whereIn("status", ["tag-return"]);
                                },
                                function ($query) use ($status) {
                                    $query->when(
                                        strtolower($status) == "return-hold",
                                        function ($query) use ($status) {
                                            $query->whereIn("status", ["tag-hold"]);
                                        },
                                        function ($query) use ($status) {
                                            $query->when(
                                                strtolower($status) == "return-void",
                                                function ($query) use ($status) {
                                                    $query->whereIn("status", ["tag-void"]);
                                                },
                                                function ($query) use ($status) {
                                                    $query->where("status", preg_replace("/\s+/", "", $status));
                                                }
                                            );
                                        }
                                    );
                                }
                            );
                        }
                    )
                    ->whereIn("department_details", $department)
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

                        "company_id",
                        "company",
                        "department",
                        "location",

                        "document_no",
                        "document_amount",
                        "referrence_no",
                        "referrence_amount",
                        "net_amount",
                        "cheque_date",
                        "receipt_type",
                        "is_not_editable",

                        "status",
                        "state",
                        "principal",
                        "interest",
                        "gross_amount",
                        "category",
                        "department_id",
                        "location_id",
                        "input_tax"
                    ]);
            })
            ->when(in_array($role, $tag_window), function ($query) use ($status) {
                $query
                    ->when(
                        strtolower($status) == "tag-receive",
                        function ($query) {
                            $query->whereIn("status", ["tag-receive", "tag-unhold", "tag-unreturn"]);
                        },
                        function ($query) use ($status) {
                            $query->when(
                                strtolower($status) == "pending",
                                function ($query) {
                                    $query->whereIn("status", ["pending"]);
                                },
                                function ($query) use ($status) {
                                    $query->when(
                                        strtolower($status) == "pending-release", //remove this
                                        function ($query) use ($status) {
                                            $query->whereIn("status", ["issue-issue"])->where("is_for_releasing", "=", true);
                                            //                        $query->where(function ($query) {
                                            //                            $query->whereIn('status', ["issue-issue"])->where('receipt_type', 'unofficial')->where("is_for_releasing", "=", true);
                                            //                        })->orWhere(function ($query) {
                                            //                            $query->where('status', 'discharge-discharge');
                                            //                        });
                                        },
                                        function ($query) use ($status) {
                                            $query->when(
                                                strtolower($status) == "pending-file",
                                                function ($query) {
                                                    $query->whereIn("status", ["file-file"]);
                                                },
                                                function ($query) use ($status) {
                                                    $query->when(
                                                        strtolower($status) == "reverse-request",
                                                        function ($query) use ($status) {
                                                            $query->whereIn("status", ["reverse-request"]);
                                                        },
                                                        function ($query) use ($status) {
                                                            $query->when(
                                                                strtolower($status) == "return-tag",
                                                                function ($query) use ($status) {
                                                                    $query->whereIn("status", ["voucher-return", "gas-return"]);
                                                                },
                                                                function ($query) use ($status) {
                                                                    $query->when(
                                                                        strtolower($status) == "hold-tag",
                                                                        function ($query) use ($status) {
                                                                            $query->whereIn("status", ["voucher-hold", "gas-hold"]);
                                                                        },
                                                                        function ($query) use ($status) {
                                                                            $query->when(
                                                                                strtolower($status) == "return-void",
                                                                                function ($query) use ($status) {
                                                                                    $query->whereIn("status", ["voucher-void"]);
                                                                                },
                                                                                function ($query) use ($status) {
                                                                                    $query->where("status", preg_replace("/\s+/", "", $status));
                                                                                }
                                                                            );
                                                                        }
                                                                    );
                                                                }
                                                            );
                                                        }
                                                    );
                                                }
                                            );
                                        }
                                    );
                                }
                            );
                        }
                    )
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

                        "company_id",
                        "company",
                        "department",
                        "location",

                        "document_no",
                        "document_amount",
                        "referrence_no",
                        "referrence_amount",
                        "net_amount",
                        "cheque_date",
                        "receipt_type",
                        "is_not_editable",

                        "status",
                        "state",
                        "principal",
                        "interest",
                        "gross_amount",
                        "category",
                        "department_id",
                        "location_id",
                        "input_tax"
                    ]);
            })
            ->when(in_array($role, $voucher_window), function ($query) use (
                $users_id,
                $status,
                $is_voucher_transfered,
                $is_transmit_transfered,
                $is_file_transfered
            ) {
                $query
                    ->where("distributed_id", $users_id)
                    ->when(
                        strtolower($status) == "voucher-receive",
                        function ($query) {
                            $query->whereIn("status", ["voucher-receive", "voucher-unhold", "voucher-unreturn"]);
                        },
                        function ($query) use ($users_id, $status) {
                            $query->when(
                                strtolower($status) == "pending",
                                function ($query) {
                                    //                  $query->whereIn("status", ["tag-tag", "voucher-transfer"])->where('receipt_type', 'unofficial');
                                    $query
                                        ->where(function ($query) {
                                            $query->whereIn("status", ["tag-tag", "voucher-transfer"])->where("receipt_type", "unofficial");
                                        })
                                        ->orWhere(function ($query) {
                                            $query->where("status", "gas-gas")->where("receipt_type", "official");
                                        });
                                },
                                function ($query) use ($users_id, $status) {
                                    $query->when(
                                        strtolower($status) == "pending-transmit",
                                        function ($query) {
                                            $query
                                                ->whereIn("status", ["approve-approve", "transmit-transfer"])
                                                ->whereNull("is_for_releasing");
                                        },
                                        function ($query) use ($users_id, $status) {
                                            $query->when(
                                                strtolower($status) == "pending-file",
                                                function ($query) {
                                                    //                          $query->whereIn("status", ["release-release", "file-transfer"]);
                                                    $query
                                                        ->where(function ($query) {
                                                            $query->whereIn("status", ["release-release"])->where("receipt_type", "unofficial");
                                                        })
                                                        ->orWhere(function ($query) {
                                                            $query->where("status", "discharge-discharge");
                                                        });
                                                },
                                                function ($query) use ($users_id, $status) {
                                                    $query->when(
                                                        strtolower($status) == "pending-request",
                                                        function ($query) use ($users_id) {
                                                            $query->whereIn("status", ["reverse-request"]);
                                                        },
                                                        function ($query) use ($users_id, $status) {
                                                            $query->when(
                                                                strtolower($status) == "reverse-receive-approver",
                                                                function ($query) {
                                                                    $query->whereIn("status", ["reverse-receive-approver"]);
                                                                },
                                                                function ($query) use ($status) {
                                                                    $query->when(
                                                                        strtolower($status) == "return-voucher",
                                                                        function ($query) use ($status) {
                                                                            $query->whereIn("status", [
                                                                                "cheque-return",
                                                                                "approve-return",
                                                                                "inspect-return",
                                                                                "issue-return",
                                                                                "debit-return",
                                                                            ]);
                                                                        },
                                                                        function ($query) use ($status) {
                                                                            $query->when(
                                                                                strtolower($status) == "hold-voucher",
                                                                                function ($query) use ($status) {
                                                                                    $query->whereIn("status", [
                                                                                        "cheque-hold",
                                                                                        "approve-hold",
                                                                                        "inspect-hold",
                                                                                        "issue-hold",
                                                                                        "debit-hold",
                                                                                    ]);
                                                                                },
                                                                                function ($query) use ($status) {
                                                                                    $query->when(
                                                                                        strtolower($status) == "return-void",
                                                                                        function ($query) use ($status) {
                                                                                            $query->whereIn("status", ["cheque-void", "approve-void"]);
                                                                                        },
                                                                                        function ($query) use ($status) {
                                                                                            $query->where("status", preg_replace("/\s+/", "", $status));
                                                                                        }
                                                                                    );
                                                                                }
                                                                            );
                                                                        }
                                                                    );
                                                                }
                                                            );
                                                        }
                                                    );
                                                }
                                            );
                                        }
                                    );
                                }
                            );
                        }
                    )
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

                        "company_id",
                        "company",
                        "department",
                        "location",

                        "document_no",
                        "document_amount",
                        "referrence_no",
                        "referrence_amount",
                        "net_amount",
                        "cheque_date",
                        "receipt_type",
                        "is_not_editable",

                        "status",
                        "state",

                        "distributed_id",
                        "distributed_name",
                        //              "is_cleared",
                        "principal",
                        "interest",
                        "gross_amount",
                        "category",
                        "department_id",
                        "location_id",
                        "input_tax"
                    ])
                    ->when(
                        in_array(strtolower($status), ["pending-request", "reverse-receive-approver", "reverse-approve"]),
                        function ($query) use ($users_id) {
                            $query->where("reverse_distributed_id", $users_id);
                        },
                        function ($query) use (
                            $status,
                            $users_id,
                            $is_voucher_transfered,
                            $is_transmit_transfered,
                            $is_file_transfered
                        ) {
                            $query->when(
                                $is_voucher_transfered,
                                function ($query) use ($users_id) {
                                    $query->whereHas("transfer_voucher", function ($query) use ($users_id) {
                                        $query->where("from_distributed_id", $users_id);
                                    });
                                },
                                function ($query) use ($status, $users_id, $is_transmit_transfered, $is_file_transfered) {
                                    $query->when(
                                        $is_transmit_transfered,
                                        function ($query) use ($users_id) {
                                            $query->whereHas("transfer_transmit", function ($query) use ($users_id) {
                                                $query->where("from_distributed_id", $users_id);
                                            });
                                        },
                                        function ($query) use ($status, $users_id, $is_file_transfered) {
                                            $query->when(
                                                $is_file_transfered,
                                                function ($query) use ($users_id) {
                                                    $query->whereHas("transfer_file", function ($query) use ($users_id) {
                                                        $query->where("from_distributed_id", $users_id);
                                                    });
                                                },
                                                function ($query) use ($users_id) {
                                                    $query->where("distributed_id", $users_id);
                                                }
                                            );
                                        }
                                    );
                                }
                            );
                        }
                    );
            })
            ->when(in_array($role, $approve_window), function ($query) use ($users_id, $status) {
                $query
                    ->when(
                        strtolower($status) == "approve-receive",
                        function ($query) {
                            $query->whereIn("status", ["approve-receive", "approve-unhold", "approve-unreturn"]);
                        },
                        function ($query) use ($status) {
                            $query->when(
                                strtolower($status) == "pending",
                                function ($query) {
                                    $query->whereIn("status", ["voucher-voucher"]);
                                },
                                function ($query) use ($status) {
                                    $query->where("status", preg_replace("/\s+/", "", $status));
                                }
                            );
                        }
                    )
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

                        "company_id",
                        "company",
                        "department",
                        "location",

                        "document_no",
                        "document_amount",
                        "referrence_no",
                        "referrence_amount",
                        "net_amount",
                        "cheque_date",
                        "receipt_type",
                        "is_not_editable",

                        "approver_id",
                        "approver_name",

                        "status",
                        "state",
                        "principal",
                        "interest",
                        "gross_amount",
                        "category",
                        "department_id",
                        "location_id",
                        "input_tax"
                    ])
                    ->where("approver_id", $users_id);
            })
            ->when(in_array($role, $cheque_window), function ($query) use ($status, $is_auto_debit, $search) {
                $query
                    // ->when(
                    //   $is_auto_debit,
                    //   function ($query) {
                    //     $query->where("document_type", "Auto Debit");
                    //   },
                    //   function ($query) {
                    //     $query->where("document_type", "<>", "Auto Debit");
                    //   }
                    // )
                    ->when(
                        strtolower($status) == "cheque-receive",
                        function ($query) {
                            $query
                                ->whereIn("status", ["cheque-receive", "cheque-unhold", "cheque-unreturn"])
                                ->whereNull("is_for_releasing");
                        },
                        // function ($query) use ($is_auto_debit) {
                        //   $query
                        //     ->whereIn("status", ["cheque-receive", "cheque-unhold", "cheque-unreturn"])
                        //     ->whereNull("is_for_releasing")
                        //     ->orWhere(function ($query) use ($is_auto_debit) {
                        //       $query->when($is_auto_debit, function ($query) {
                        //         $query->where("status", "cheque-receive")->where("is_for_releasing", true);
                        //       });
                        //     });
                        // },
                        function ($query) use ($status) {
                            $query->when(
                                strtolower($status) == "cheque-cheque",
                                function ($query) {
                                    $query->whereIn("status", ["cheque-cheque", "cheque-reverse"])->where("is_for_releasing", false);
                                },
                                function ($query) use ($status) {
                                    $query->when(
                                        strtolower($status) == "pending",
                                        function ($query) {
                                            $query
                                                ->whereIn("status", ["transmit-transmit"])
                                                ->where(function ($query) {
                                                    $query->whereNull("is_for_voucher_audit")->orWhere("is_for_releasing", true);
                                                })
                                                ->orWhere(function ($query) {
                                                    $query->where("status", "inspect-inspect")->where("document_id", 8); //PCF
                                                });
                                        },
                                        function ($query) use ($status) {
                                            $query->when(
                                                strtolower($status) == "pending-clear",
                                                function ($query) {
                                                    $query
                                                        ->whereIn("status", [
                                                            "release-release",
                                                            "file-receive",
                                                            "file-file",
                                                            "discharge-receive",
                                                            "discharge-discharge",
                                                        ])
                                                        //                              ->whereNull('is_cleared');
                                                        ->whereHas("cheques.cheques", function ($query) {
                                                            $query->whereNull("is_cleared");
                                                        });
                                                },
                                                function ($query) use ($status) {
                                                    $query->when(
                                                        strtolower($status) == "return-return",
                                                        function ($query) use ($status) {
                                                            $query->whereIn("status", ["reverse-return"]);
                                                        },
                                                        function ($query) use ($status) {
                                                            $query->when(
                                                            // strtolower($status) == "return-hold",
                                                            // function ($query) use ($status) {
                                                            //   $query->whereIn("status", ["release-hold"]);
                                                            // }
                                                                strtolower($status) == "hold-cheque",
                                                                function ($query) use ($status) {
                                                                    $query->whereIn("status", ["audit-hold"]);
                                                                },
                                                                function ($query) use ($status) {
                                                                    $query->when(
                                                                        strtolower($status) == "return-void",
                                                                        function ($query) use ($status) {
                                                                            $query->whereIn("status", ["release-void"]);
                                                                        },
                                                                        function ($query) use ($status) {
                                                                            $query->when(
                                                                                strtolower($status) == "pending-issue",
                                                                                function ($query) {
                                                                                    $query
                                                                                        ->where("status", "executive-executive")
                                                                                        ->where("is_for_releasing", true);
                                                                                },
                                                                                function ($query) use ($status) {
                                                                                    $query->when(
                                                                                        strtolower($status) == "issue-receive",
                                                                                        function ($query) {
                                                                                            $query->where("status", "issue-receive")->where("is_for_releasing", true);
                                                                                        },
                                                                                        function ($query) use ($status) {
                                                                                            $query->when(
                                                                                                strtolower($status) == "issue-issue",
                                                                                                function ($query) {
                                                                                                    $query
                                                                                                        ->where("status", "issue-issue")
                                                                                                        ->where("is_for_releasing", true);
                                                                                                },
                                                                                                function ($query) use ($status) {
                                                                                                    $query->when(
                                                                                                        strtolower($status) == "pending-debit",
                                                                                                        function ($query) {
                                                                                                            $query
                                                                                                                ->where("document_id", 9)
                                                                                                                ->where("status", "inspect-inspect");
                                                                                                        },
                                                                                                        function ($query) use ($status) {
                                                                                                            $query->when(
                                                                                                                strtolower($status) == "return-cheque",
                                                                                                                function ($query) {
                                                                                                                    $query->whereIn("status", ["audit-return"]);
                                                                                                                },
                                                                                                                function ($query) use ($status) {
                                                                                                                    $query->when(
                                                                                                                        strtolower($status) == "return-release",
                                                                                                                        function ($query) {
                                                                                                                            $query->whereIn("status", ["release-return"]);
                                                                                                                        },
                                                                                                                        function ($query) use ($status) {
                                                                                                                            $query->when(
                                                                                                                                strtolower($status) == "clear-clear",
                                                                                                                                function ($query) {
                                                                                                                                    //                                                                      $query->where('is_cleared', true);
                                                                                                                                    $query->whereHas("cheques.cheques", function (
                                                                                                                                        $query
                                                                                                                                    ) {
                                                                                                                                        $query->where("is_cleared", true);
                                                                                                                                    });
                                                                                                                                },
                                                                                                                                function ($query) use ($status) {
                                                                                                                                    $query->where(
                                                                                                                                        "status",
                                                                                                                                        preg_replace("/\s+/", "", $status)
                                                                                                                                    );
                                                                                                                                }
                                                                                                                            );
                                                                                                                        }
                                                                                                                    );
                                                                                                                }
                                                                                                            );
                                                                                                        }
                                                                                                    );
                                                                                                }
                                                                                            );
                                                                                        }
                                                                                    );
                                                                                }
                                                                            );
                                                                        }
                                                                    );
                                                                }
                                                            );
                                                        }
                                                    );
                                                }
                                            );
                                        }
                                    );
                                }
                            );
                        }
                    )
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

                        "company_id",
                        "company",
                        "department_id",
                        "department",
                        "location_id",
                        "location",

                        "document_no",
                        "document_amount",
                        "referrence_no",
                        "referrence_amount",
                        "net_amount",
                        "cheque_date",
                        "receipt_type",
                        "is_not_editable",

                        "status",
                        "state",
                        "voucher_no",
                        "voucher_month",
                        //              "is_cleared",
                        "principal",
                        "interest",
                        "gross_amount",
                        "category",
                        "department_id",
                        "location_id",
                        "input_tax"
                    ]);
            })
            ->when(in_array($role, $audit_window), function ($query) use ($status) {
                $query
                    ->when(
                        strtolower($status) == "audit-receive",
                        function ($query) {
                            $query->whereIn("status", ["audit-receive", "audit-unhold", "audit-unreturn"]);
                        },
                        function ($query) use ($status) {
                            $query->when(
                                strtolower($status) == "pending",
                                function ($query) {
                                    $query
                                        ->whereIn("status", ["cheque-cheque", "transmit-transmit"])
                                        // ->whereNull("is_for_releasing")
                                        // ->where("is_for_voucher_audit", false);
                                        ->where(function ($query) {
                                            // $query->where("is_for_cheque_audit", true);
                                            $query->where("is_for_releasing", "!=", true);
                                            // $query->where("is_for_releasing", "!=", false);
                                        });
                                    // ->where(function ($query) {
                                    //   $query->whereNull("is_for_releasing")->where("is_for_voucher_audit", false);
                                    // });
                                    // ->where(function ($query) {
                                    //   $query->where("is_for_voucher_audit", false)->orWhereNull("is_for_voucher_audit");
                                    // });
                                },
                                function ($query) use ($status) {
                                    $query->when(
                                        strtolower($status) == "pending-inspect",
                                        function ($query) {
                                            $query->whereIn("status", ["transmit-transmit"])->where("is_for_voucher_audit", true);
                                        },
                                        function ($query) use ($status) {
                                            $query->when(
                                                strtolower($status) == "inspect-inspect",
                                                function ($query) {
                                                    // $query->whereIn("status", ["audit-audit"])->orWhere(function ($query) {
                                                    //   $query->where("document_id", 9)->where("is_for_releasing", true);
                                                    // });
                                                    $query->whereIn("status", ["inspect-inspect"]);
                                                },
                                                function ($query) use ($status) {
                                                    $query->where("status", preg_replace("/\s+/", "", $status));
                                                }
                                            );
                                        }
                                    );
                                }
                            );
                        }
                    )
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

                        "company_id",
                        "company",
                        "department",
                        "location",

                        "document_no",
                        "document_amount",
                        "referrence_no",
                        "referrence_amount",
                        "net_amount",
                        "cheque_date",
                        "receipt_type",
                        "is_not_editable",

                        "approver_id",
                        "approver_name",

                        "status",
                        "state",
                        "principal",
                        "interest",
                        "gross_amount",
                        "category",
                        "department_id",
                        "location_id",
                        "input_tax"
                    ]);
            })
            ->when(in_array($role, $executive_assistant), function ($query) use ($status) {
                $query
                    ->when(
                        strtolower($status) == "executive-receive",
                        function ($query) {
                            $query->whereIn("status", ["executive-receive", "executive-unhold", "executive-unreturn"]);
                        },
                        function ($query) use ($status) {
                            $query->when(
                                strtolower($status) == "pending",
                                function ($query) {
                                    $query->whereIn("status", ["audit-audit"]);
                                },
                                function ($query) use ($status) {
                                    $query->where("status", preg_replace("/\s+/", "", $status));
                                }
                            );
                        }
                    )
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

                        "company_id",
                        "company",
                        "department",
                        "location",

                        "document_no",
                        "document_amount",
                        "referrence_no",
                        "referrence_amount",
                        "net_amount",
                        "cheque_date",
                        "receipt_type",
                        "is_not_editable",

                        "approver_id",
                        "approver_name",

                        "status",
                        "state",
                        "principal",
                        "interest",
                        "gross_amount",
                        "category",
                        "department_id",
                        "location_id",
                        "input_tax"
                    ]);
            })
            ->when(in_array($role, $gas_window), function ($query) use ($status) {
                $query
                    ->when(
                        strtolower($status) == "gas-receive",
                        function ($query) {
                            $query->whereIn("status", ["gas-receive", "gas-unhold", "gas-unreturn"]);
                        },
                        function ($query) use ($status) {
                            $query->when(
                                strtolower($status) == "pending",
                                function ($query) {
                                    $query->whereIn("status", ["tag-tag"])->where("receipt_type", "official");
                                },
                                function ($query) use ($status) {
                                    $query->when(
                                        strtolower($status) == "pending-discharge",
                                        function ($query) {
                                            $query->whereIn("status", ["release-release"])->where("receipt_type", "official");
                                        },
                                        function ($query) use ($status) {
                                            $query->where("status", preg_replace("/\s+/", "", $status));
                                        }
                                    );
                                }
                            );
                        }
                    )
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

                        "company_id",
                        "company",
                        "department",
                        "location",

                        "document_no",
                        "document_amount",
                        "referrence_no",
                        "referrence_amount",
                        "net_amount",
                        "cheque_date",
                        "receipt_type",
                        "is_not_editable",

                        "approver_id",
                        "approver_name",

                        "status",
                        "state",
                        "principal",
                        "interest",
                        "gross_amount",
                        "category",
                        "department_id",
                        "location_id",
                        "input_tax"
                    ]);
            })
            ->latest('updated_at')
            ->paginate($rows);

        TransactionIndex::collection($transactions);

        if (count($transactions)) {
            return $this->resultResponse("fetch", "Transaction", $transactions);
        }
        return $this->resultResponse("not-found", "Transaction", []);
    }

    public function showTransaction($id)
    {
        $counter_receipt_status = null;
        $counter_receipt_no = null;
        // $transaction = DB::table('transactions')->where('id',$id)->first();
        $transaction = Transaction::where("id", $id)->get();
        if ($transaction->isEmpty()) {
            throw new FistoException("No records found.", 404, null, []);
        }

        $counter_receipt_details = CounterReceiptMethod::get_counter_receipt_id(
            $transaction->first()->referrence_no,
            $transaction->first()->supplier_id,
            $transaction->first()->department_id
        );
        if ($counter_receipt_details) {
            $counter_receipt_status = $counter_receipt_details->counter_receipt_status;
            $counter_receipt_no = $counter_receipt_details->counter_receipt_no;
        }

        $transaction->map(function ($value) use ($counter_receipt_status, $counter_receipt_no) {
            $value["counter_receipt_status"] = $counter_receipt_status;
            $value["counter_receipt_no"] = $counter_receipt_no;
        });

//                $singleTransaction = TransactionResource::collection($transaction);
        $singleTransaction = TransactionResource1::collection($transaction);
        if (count($singleTransaction) != true) {
            throw new FistoException("No records found.", 404, null, []);
        }
        return $this->resultResponse("fetch", "Transaction details", $singleTransaction->first());
    }

    public function showCurrentPO($id)
    {
        $transaction = Transaction::where("id", $id)->get();
        $singleTransaction = TransactionResource::collection($transaction);
        if (!count($singleTransaction)) {
            throw new FistoException("No records found.", 404, null, []);
        }
        return $singleTransaction->first();
    }

    public function store(TransactionPostRequest $request)
    {
        $fields = $request->validated();
        $date_requested = date("Y-m-d H:i:s");
        $transaction_id = GenericMethod::getTransactionID(Auth::user()->department[0]["name"]);
        $request_id = GenericMethod::getRequestID();

        switch ($fields["document"]["id"]) {
            case 1: //PAD
                if (empty($fields["po_group"])) {
                    $errorMessage = GenericMethod::resultLaravelFormat("po_group", ["PO group required"]);

                    return $this->resultResponse("invalid", "", $errorMessage);
                }

                switch ($request->input("document.payment_type")) {
                    case "Partial":
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
                            // ->where('company_id', $fields["document"]["company"]["id"])
                            // ->where('supplier_id', $fields["document"]["supplier"]["id"])
                            ->exists();

                        if ($existTransaction) {
                            $currentRequestids = POBatch::where("po_no", last($fields["po_group"])["no"])->pluck("request_id");

                            $ids = [];

                            for ($i = 0; $i < count($currentRequestids); $i++) {
                                $ids[] = $currentRequestids[$i];
                            }

                            // Transaction::where('request_id', '=', end($ids))
                            // ->update([
                            //   'is_not_editable' => true
                            // ]);

                            //enable new transaction
                            Transaction::where("request_id", "=", end($ids))->update([
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
                                $balance_with_additional_total_po_amount
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
                            $balance_po_ref_amount
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

                        //---------------------------------------------------------------------------------------------------
                        break;

                    default:
                        GenericMethod::documentNoValidation($request["document"]["no"]);

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

                        $transaction = GenericMethod::insertTransaction(
                            $transaction_id,
                            $po_total_amount,
                            $request_id,
                            $date_requested,
                            $fields
                        );

                        $request_id = $transaction->id;

                        GenericMethod::insertPO(
                            $request_id,
                            $fields["po_group"],
                            $po_total_amount,
                            strtoupper($fields["document"]["payment_type"])
                        );

                        if (isset($transaction->transaction_id)) {
                            return $this->resultResponse("save", "Transaction", []);
                        }
                        break;
                }

                break;

            case 5: //Contractor's Billing
                if (empty($fields["po_group"])) {
                    $errorMessage = GenericMethod::resultLaravelFormat("po_group", ["PO group required"]);

                    return $this->resultResponse("invalid", "", $errorMessage);
                }

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

                $errorMessage = GenericMethod::validateWith1PesoDifference(
                    "po_group.amount",
                    "Document",
                    $fields["document"]["amount"],
                    $po_total_amount
                );

                if (!empty($errorMessage)) {
                    return GenericMethod::resultResponse("invalid", "", $errorMessage);
                }

                $transaction = GenericMethod::insertTransaction(
                    $transaction_id,
                    $po_total_amount,
                    $request_id,
                    $date_requested,
                    $fields
                );

                $request_id = $transaction->id;

                GenericMethod::insertPO(
                    $request_id,
                    $fields["po_group"],
                    $po_total_amount,
                    strtoupper($fields["document"]["payment_type"])
                );

                if (isset($transaction->transaction_id)) {
                    return $this->resultResponse("save", "Transaction", []);
                }

                break;

            case 2: //PRM Common
                GenericMethod::documentNoValidation($request["document"]["no"]);
                $transaction = GenericMethod::insertTransaction($transaction_id, null, $request_id, $date_requested, $fields);
                if (isset($transaction->transaction_id)) {
                    return $this->resultResponse("save", "Transaction", []);
                }
                break;

            case 3: // PRM Multiple
                GenericMethod::documentNoValidation($request["document"]["no"]);
                $transaction = GenericMethod::insertTransaction($transaction_id, null, $request_id, $date_requested, $fields);

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

                $transaction = GenericMethod::insertTransaction($transaction_id, null, $request_id, $date_requested, $fields);
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

                $transaction = GenericMethod::insertTransaction($transaction_id, null, $request_id, $date_requested, $fields);
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

                $transaction = GenericMethod::insertTransaction($transaction_id, null, $request_id, $date_requested, $fields);

                $request_id = $transaction->id;

                GenericMethod::insertClient($request_id, $fields["document"]["payroll"]["clients"]);

                if (isset($transaction->transaction_id)) {
                    return $this->resultResponse("save", "Transaction", []);
                }

                break;

            case 4: //Receipt
                $isFull = strtoupper($fields["document"]["payment_type"]) === "FULL";
                $isQty = $fields["document"]["reference"]["type"] === "DR Qty";

                if (empty($fields["po_group"])) {
                    $errorMessage = GenericMethod::resultLaravelFormat("po_group", ["PO group required"]);
                    return $this->resultResponse("invalid", "", $errorMessage);
                }

                if (!$isQty && $isFull) {
                    //Full
                    $duplicateRef = GenericMethod::validateReferenceNo($fields);

                    if (isset($duplicateRef)) {
                        return $this->resultResponse("invalid", "", $duplicateRef);
                    }

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

                    $transaction = GenericMethod::insertTransaction(
                        $transaction_id,
                        $po_total_amount,
                        $request_id,
                        $date_requested,
                        $fields
                    );

                    $request_id = $transaction->id;

                    GenericMethod::insertPO(
                        $request_id,
                        $fields["po_group"],
                        $po_total_amount,
                        strtoupper($fields["document"]["payment_type"])
                    );

                    if (isset($transaction->transaction_id)) {
                        return $this->resultResponse("save", "Transaction", []);
                    }
                }

                if (!$isQty && !$isFull) {
                    //Partial

                    $duplicateRef = GenericMethod::validateReferenceNo($fields);

                    if (isset($duplicateRef)) {
                        return $this->resultResponse("invalid", "", $duplicateRef);
                    }

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
                    $existTransaction = Transaction::where("company_id", $fields["document"]["company"]["id"])
                        // ->where('company_id', $fields["document"]["company"]["id"])
                        // ->where('supplier_id', $fields["document"]["supplier"]["id"])
                        ->exists();

                    if ($existTransaction) {
                        $currentRequestids = POBatch::where("po_no", last($fields["po_group"])["no"])->pluck("request_id");

                        $ids = [];

                        for ($i = 0; $i < count($currentRequestids); $i++) {
                            $ids[] = $currentRequestids[$i];
                        }

                        // Transaction::where('request_id', '=', end($ids))
                        // ->update([
                        //   'is_not_editable' => true
                        // ]);

                        //enable new transaction
                        Transaction::where("request_id", "=", end($ids))->update([
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
                            $balance_with_additional_total_po_amount
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
                        $balance_po_ref_amount
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

                isset($fields["autoDebit_group"])
                    ? GenericMethod::validate_debit_amount(
                    $fields["document"]["amount"],
                    $fields["autoDebit_group"],
                    "Document amount and net of cwt amount is not equal."
                )
                    : null;

                $transaction = GenericMethod::insertTransaction($transaction_id, null, $request_id, $date_requested, $fields);
                if (isset($transaction->transaction_id)) {
                    return $this->resultResponse("save", "Transaction", []);
                }
                break;
        }

        return $this->resultResponse("not-exist", "Document number", []);
    }

    public function update(TransactionPostRequest $request, $id)
    {
        $fields = $request->validated();
        $date_requested = date("Y-m-d H:i:s");
        $request_id = $request["transaction"]["request_id"];

        switch ($fields["document"]["id"]) {
            case 1: //PAD
                switch ($request->input("document.payment_type")) {
                    case "Partial":
                        GenericMethod::documentNoValidationUpdate($request["document"]["no"], $id);

                        if (empty($fields["po_group"])) {
                            $errorMessage = GenericMethod::resultLaravelFormat("po_group", ["PO group required"]);
                            return $this->resultResponse("invalid", "", $errorMessage);
                        }

                        $currentTransaction = Transaction::findOrFail($id);

                        if ($currentTransaction->is_not_editable == 1) {
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
                        break;

                    default:
                        GenericMethod::documentNoValidationUpdate($request["document"]["no"], $id);

                        if (empty($fields["po_group"])) {
                            $errorMessage = GenericMethod::resultLaravelFormat("po_group", ["PO group required"]);
                            return $this->resultResponse("invalid", "", $errorMessage);
                        }

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
                            0,
                            $changes
                        );

                        if ($transaction == "Nothing Has Changed") {
                            return $this->resultResponse("nothing-has-changed", "Transaction", []);
                        }
                        if (isset($transaction->transaction_id)) {
                            return $this->resultResponse("update", "Transaction", []);
                        }
                        break;
                }
                // GenericMethod::documentNoValidationUpdate($request["document"]["no"], $id);

                // if (empty($fields["po_group"])) {
                //   $errorMessage = GenericMethod::resultLaravelFormat("po_group", ["PO group required"]);
                //   return $this->resultResponse("invalid", "", $errorMessage);
                // }

                // $duplicatePO = GenericMethod::validatePOFullUpdate(
                //   $fields["document"]["company"]["id"],
                //   $fields["po_group"],
                //   $id
                // );
                // if (isset($duplicatePO)) {
                //   return $this->resultResponse("invalid", "", $duplicatePO);
                // }

                // $po_total_amount = GenericMethod::getPOTotalAmount($request_id, $fields["po_group"]);

                // $errorMessage = GenericMethod::validateWith1PesoDifference(
                //   "po_group.amount",
                //   "Document",
                //   $fields["document"]["amount"],
                //   $po_total_amount
                // );
                // if (!empty($errorMessage)) {
                //   return GenericMethod::resultResponse("invalid", "", $errorMessage);
                // }

                // $changes = GenericMethod::getTransactionChanges($request_id, $request, $id);
                // GenericMethod::updatePO(
                //   $request_id,
                //   $fields["po_group"],
                //   $po_total_amount,
                //   strtoupper($fields["document"]["payment_type"]),
                //   $id
                // );

                // $transaction = GenericMethod::updateTransaction(
                //   $id,
                //   $po_total_amount,
                //   $request_id,
                //   $date_requested,
                //   $request,
                //   0,
                //   $changes
                // );

                // if ($transaction == "Nothing Has Changed") {
                //   return $this->resultResponse("nothing-has-changed", "Transaction", []);
                // }
                // if (isset($transaction->transaction_id)) {
                //   return $this->resultResponse("update", "Transaction", []);
                // }
                break;

            case 5: //Contractor's Billing
                if (empty($fields["po_group"])) {
                    $errorMessage = GenericMethod::resultLaravelFormat("po_group", ["PO group required"]);
                    return $this->resultResponse("invalid", "", $errorMessage);
                }

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
                    0,
                    $changes
                );

                if ($transaction == "Nothing Has Changed") {
                    return $this->resultResponse("nothing-has-changed", "Transaction", []);
                }
                if (isset($transaction->transaction_id)) {
                    return $this->resultResponse("update", "Transaction", []);
                }
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
                if (isset($transaction->transaction_id)) {
                    return $this->resultResponse("update", "Transaction", []);
                }
                break;

            case 3: //PRM Multiple
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

                // return $transaction;

                if ($transaction == "Nothing Has Changed") {
                    return $this->resultResponse("nothing-has-changed", "Transaction", []);
                } elseif ($transaction == "On Going Transaction") {
                    return GenericMethod::resultResponse("ongoing", "", []);
                }

                if (isset($transaction->transaction_id)) {
                    return $this->resultResponse("update", "Transaction", []);
                }

                #----------------------------------------------------------------------#

                // $transaction = Transaction::find($id);

                // $count = Transaction::where("transaction_id", $transaction->transaction_id)
                //   ->distinct("request_id")
                //   ->count("request_id");

                // if (
                //   Transaction::where("transaction_id", $transaction->transaction_id)
                //     ->whereNotIn("status", ["tag-return", "tag-void"])
                //     ->exists()
                // ) {
                //   return GenericMethod::resultResponse("ongoing", "", []);
                // }

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
                if (isset($transaction->transaction_id)) {
                    return $this->resultResponse("update", "Transaction", []);
                }
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
                if (isset($transaction->transaction_id)) {
                    return $this->resultResponse("update", "Transaction", []);
                }
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
                if (isset($transaction->transaction_id)) {
                    return $this->resultResponse("update", "Transaction", []);
                }
                break;

            case 4: //Receipt
                // $row = Transaction::find($id);
                // $row->receipt()->create($row->toArray());

                // $row->receipt->update([
                //   'transactions_id' => $id,
                //   'remarks' => $fields['document']['remarks'],
                // ]);

                // Transaction::where('id', $id)->update($row->receipt->toArray());
                // return;

                //-------------------------------------------------------------------

                $isFull = strtoupper($fields["document"]["payment_type"]) === "FULL";

                if (empty($fields["po_group"])) {
                    $errorMessage = GenericMethod::resultLaravelFormat("po_group", ["PO group required"]);
                    return $this->resultResponse("invalid", "", $errorMessage);
                }

                $duplicateRef = GenericMethod::validateReferenceNo($fields, $id);
                if (isset($duplicateRef)) {
                    return $this->resultResponse("invalid", "", $duplicateRef);
                }

                if ($isFull) {
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
                        0,
                        $changes
                    );
                    // if($transaction == "Nothing Has Changed"){
                    //     return $this->resultResponse('nothing-has-changed',"Transaction",[]);
                    // }
                    if (isset($transaction->transaction_id)) {
                        return $this->resultResponse("update", "Transaction", []);
                    }
                }

                $currentTransaction = Transaction::findOrFail($id);

                // $result = POBatch::with('request')->where('request_id', $currentTransaction->request_id)->get();
                // $isNotEditable = $result[0]['request']['is_not_editable'];

                if ($currentTransaction->is_not_editable == 1) {
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

                // GenericMethod::validate_debit_amount(
                //   $fields["document"]["amount"],
                //   $fields["autoDebit_group"],
                //   "Document amount and net of amount is not equal."
                // );
                // GenericMethod::update_debit_attachment($request_id, $fields["autoDebit_group"], $id);

                if ($fields["autoDebit_group"]) {
                    GenericMethod::validate_debit_amount(
                        $fields["document"]["amount"],
                        $fields["autoDebit_group"],
                        "Document amount and net of amount is not equal."
                    );
                    GenericMethod::update_debit_attachment($request_id, $fields["autoDebit_group"], $id);
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
                if (isset($transaction->transaction_id)) {
                    return $this->resultResponse("update", "Transaction", []);
                }
                break;
        }
        // return $this->resultResponse("not-exist", "Document number", []);
    }

    public function getPODetails(PODetailsRequest $request)
    {
        $transaction_id = $request->transaction_id;
        $fields = $request->validated();
        $po_details = DB::table("transactions")
            ->leftJoin("p_o_batches", "transactions.request_id", "=", "p_o_batches.request_id")
            ->where("transactions.company_id", $fields["company_id"])
            ->where("p_o_batches.po_no", $fields["po_no"])
            ->where("transactions.state", "!=", "void")
            ->when(isset($transaction_id), function ($query) use ($transaction_id) {
                $query->where("transactions.id", "<>", $transaction_id);
            })
            ->get(["balance_po_ref_amount as po_balance", "transactions.request_id"]);

        if (count($po_details) > 0) {
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
                ->get(["request_id as batch", "po_no as no", "po_amount as amount", "rr_group as rr_no"]);

            $po_details->mapToGroups(function ($item, $v) use ($balance) {
                return [($item["balance"] = 0), ($item["rr_no"] = json_decode($item["rr_no"], true))];
            });

            $po_details = $po_details->reverse()->values();
            $po_details->first()->balance = $balance;
            $po_object = (object)["po_group" => $po_details];
            return $this->resultResponse("fetch", "PO number", $po_object);
        }

        if ($po_details->isEmpty()) {
            return $this->getPODetailsv1($request);
        }

        return $this->resultResponse("success-no-content", "", []);
    }

    public function getPODetailsv1(PODetailsRequest $request) {
        $po_details = DB::connection('mysqlSecondConnection')->table('p_o_batches')
            ->rightJoin('transactions', 'p_o_batches.request_id', '=', 'transactions.request_id')
            ->where('transactions.company_id', $request->company_id)
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
            $po_object = (object)['po_group' => $po_details];
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
            Transaction::where("company_id", $request["company_id"])
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
            ->where("utilities_receipt_no", $request->utilities_receipt_no)
            ->where("supplier_id", $request->supplier_id)
            ->where("company_id", $request->company_id)
            ->where("state", "!=", "void")
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
            if (
                ($transaction->document_id == 4 || $transaction->document_id == 1) &&
                $transaction->payment_type == "Partial"
            ) {
                if ($transaction) {
                    $poNos = $transaction->po_details->pluck("po_no");
                }

                $currentRequestIds = POBatch::whereIn("po_no", $poNos)
                    ->pluck("request_id")
                    ->toArray();

//                $test = Transaction::whereIn("request_id", $currentRequestIds)
//                    ->where("state", "!=", "void")
//                    ->pluck("is_not_editable")
//                    ->toArray();
//
//                if (count($test) == 2) {
//                    $test = collect($test);
//
//                    $is_editable = $test
//                        ->filter(function ($q) {
//                            return $q == 1;
//                        })
//                        ->count();
//
//                    $is_not_editable = $test
//                        ->filter(function ($q) {
//                            return $q == 0;
//                        })
//                        ->count();
//
//                    if ($is_editable == $is_not_editable) {
//                        Transaction::whereIn("request_id", $currentRequestIds)
//                            ->where("state", "!=", "void")
//                            ->update([
//                                "is_not_editable" => false,
//                            ]);
//                    }
//                }

                Transaction::where("request_id", end($currentRequestIds) - 1)->update([
                    "is_not_editable" => false,
                ]);

                // POBatch::where('request_id', end($currentRequestIds)-1)->update([
                //   'is_modifiable' => true
                // ]);
            }

            // elseif ($transaction->document_id == 3) {
            //   $transaction->update([
            //     "state" => $status,
            //   ]);

            //   switch ($transaction->category) {
            //     case "additional rental":
            //     case "lounge rental":
            //     case "stall a rental":
            //     case "stall b rental":
            //     case "stall c rental":
            //     case "stall d rental":
            //     case "cusa rental":
            //     case "dorm rental":
            //     case "rental":
            //       $gross_amount = Transaction::where("transaction_id", $transaction->transaction_id)
            //         ->where("state", "!=", "void")
            //         ->sum(DB::raw("gross_amount"));

            //       Transaction::where("transaction_id", $transaction->transaction_id)
            //         ->where("state", "!=", "void")
            //         ->update([
            //           "total_gross" => $gross_amount,
            //           "document_amount" => $gross_amount,
            //         ]);
            //       break;

            //     case "official store leasing":
            //     case "unofficial store leasing":
            //     case "leasing":
            //       $transactionData = Transaction::where("transaction_id", $transaction->transaction_id)
            //         ->where("state", "!=", "void")
            //         ->selectRaw(
            //           "SUM(principal) as principal_amount, SUM(interest) as interest_amount, SUM(cwt) as cwt_amount"
            //         )
            //         ->first();

            //       $document_amount =
            //         $transactionData->principal_amount + $transactionData->interest_amount - $transactionData->cwt_amount;

            //       Transaction::where("transaction_id", $transaction->transaction_id)
            //         ->where("state", "!=", "void")
            //         ->update([
            //           "document_amount" => $document_amount,
            //         ]);

            //       break;

            //     case "loans":
            //       $transactionData = Transaction::where("transaction_id", $transaction->transaction_id)
            //         ->where("state", "!=", "void")
            //         ->selectRaw(
            //           "SUM(principal) as principal_amount, SUM(interest) as interest_amount, SUM(cwt) as cwt_amount"
            //         )
            //         ->first();

            //       $document_amount =
            //         $transactionData->principal_amount + $transactionData->interest_amount - $transactionData->cwt_amount;

            //       Transaction::where("transaction_id", $transaction->transaction_id)
            //         ->where("state", "!=", "void")
            //         ->update([
            //           "document_amount" => $document_amount,
            //         ]);

            //       break;
            //   }
            // }
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
        $state = $request->input("state", "request");

        $rows = $request->input("rows", 10);

        $search = $request->input("search");

        $suppliers = json_decode($request->input("suppliers")) ?? [];

        $cheque_from = isset($request["cheque_from"])
            ? Carbon::createFromFormat("Y-m-d", $request->input("cheque_from"))->format("Y-m-d")
            : null;
        $cheque_to = isset($request["cheque_to"])
            ? Carbon::createFromFormat("Y-m-d", $request->input("cheque_to"))->format("Y-m-d")
            : null;

        $transactions = Transaction::with([
            "users" => function ($query) {
                return $query->select([
                    "users.id",
                    "users.first_name",
                    "users.middle_name",
                    "users.last_name",
                    "users.department",
                    "users.position",
                ]);
            },
            "supplier.supplier_type" => function ($query) {
                return $query->select(["supplier_types.id", "supplier_types.type as name"]);
            },
            //            "cheques.cheques"
        ])

            // Supplier Filter
            ->when(count($suppliers), function ($query) use ($suppliers) {
                return $query->whereIn("supplier_id", $suppliers);
            })

            // Cheque Date Filter (Will deprecate)
            ->when($cheque_from && $cheque_to, function ($query) use ($cheque_from, $cheque_to) {
                return $query->whereHas("cheques.cheques", function ($query) use ($cheque_from, $cheque_to) {
                    return $query->whereDate("cheque_date", ">=", $cheque_from)->whereDate("cheque_date", "<=", $cheque_to);
                });
            })

            // Search
            ->where(function ($query) use ($search) {
                $query
                    ->where("remarks", "like", "%" . $search . "%")
                    ->orWhere("payment_type", "like", "%" . $search . "%")
                    ->orWhere("tag_no", "like", "%" . $search . "%")
                    ->orWhere("company", "like", "%" . $search . "%")
                    ->orWhere("department", "like", "%" . $search . "%")
                    ->orWhere("location", "like", "%" . $search . "%")
                    ->orWhere("supplier", "like", "%" . $search . "%")
                    ->orWhere("document_no", "like", "%" . $search . "%")
                    ->orWhere("referrence_no", "like", "%" . $search . "%");
            })

            // creation of cheque
            ->when($status == "pending-cheque", function ($query) {
//                return $query->whereIn("status", ["transmit-transmit", "inspect-inspect"]);
                return $query->where(function ($query) {
                    $query->where("status", "transmit-transmit")
                        ->where("document_id", '!=', 8);
                })->orWhere("status", "inspect-inspect");
            })
            ->when($status == "cheque-receive", function ($query) {
                return $query->whereIn("status", ["cheque-receive", "cheque-unhold", "cheque-unreturn"]);
            })
            ->when($status == "return-cheque", function ($query) {
                return $query->where("status", "audit-return");
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
            ->when($status == "return-issue", function ($query) {
                return $query->where("status", "release-return");
            })
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
                "state"
            ])
            ->latest("updated_at")
            ->paginate((int)$rows);

        ChequeIndex::collection($transactions);

        if (count($transactions)) {
            return $this->resultResponse("fetch", "Transaction", $transactions);
        }

        return $this->resultResponse("not-found", "Transaction", []);
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

    //    public function chequeClear1(Request $request) {
    //        $accounts = $request->accounts;
    //        $cheque = Cheque::where('bank_id', $request->bank_id)
    //            ->where('cheque_no', $request->cheque_no)
    //            ->first();
    //        $clear_date = date('Y-m-d', strtotime($request->date));
    //
    //        if ($cheque) {
    //            $sameCheques = Cheque::where('bank_id', $cheque->bank_id)->where('cheque_no', $cheque->cheque_no)->get()->pluck('id');
    //            $sameTransactionIds = Cheque::where('bank_id', $cheque->bank_id)->where('cheque_no', $cheque->cheque_no)->get()->pluck('transaction_id');
    //            Cheque::where('bank_id', $cheque->bank_id)
    //                ->where('cheque_no', $cheque->cheque_no)
    //                ->update([
    //                    'date_cleared' => $clear_date,
    //                    'is_cleared' => true
    //                ]);
    //
    //            foreach ($sameTransactionIds as $sameTransactionId) {
    //                Clear::create([
    //                    'transaction_id' => $sameTransactionId,
    //                    'tag_id' => Transaction::where('id', $sameTransactionId)->first()->tag_no,
    //                    'status' => 'clear-clear',
    //                    'date_status' => date('Y-m-d'),
    //                    'date_cleared' => $clear_date,
    //                ]);
    //            }
    //
    //            foreach ($accounts as $account) {
    //                foreach ($sameCheques as $sameCheque) {
    //                    ClearingAccountTitle::create([
    //                        'clear_id' => $sameCheque,
    //                        'entry' => $account['entry'],
    //                        'account_title_id' => $account['account_title']['id'],
    //                        'account_title_name' => $account['account_title']['name'],
    //                        'company_id' => data_get($account, 'company.id'),
    //                        'company_code' => data_get($account, 'company.code'),
    //                        'company_name' => data_get($account, 'company.name'),
    //                        'department_id' => data_get($account, 'department.id'),
    //                        'department_code' => data_get($account, 'department.code'),
    //                        'department_name' => data_get($account, 'department.name'),
    //                        'location_id' => data_get($account, 'location.id'),
    //                        'location_code' => data_get($account, 'location.code'),
    //                        'location_name' => data_get($account, 'location.name'),
    //                        'business_unit_id' => data_get($account, 'business_unit.id'),
    //                        'business_unit_code' => data_get($account, 'business_unit.code'),
    //                        'business_unit_name' => data_get($account, 'business_unit.name'),
    //                        'sub_unit_id' => data_get($account, 'sub_unit.id'),
    //                        'sub_unit_code' => data_get($account, 'sub_unit.code'),
    //                        'sub_unit_name' => data_get($account, 'sub_unit.name'),
    //                        'amount' => $account['amount'],
    //                        'remarks' => $account['remarks'],
    //                        'transaction_type' => 'new'
    //                    ]);
    //                }
    //            }
    //            return $this->resultResponse("update", "Transaction", []);
    //        } else {
    //            return $this->resultResponse("not-found", "Transaction", []);
    //        }
    //    }
    public function generateBatchNo()
    {
        $no = 1;
        do {
            $batch_no = $no;
            $no++;
        } while ($this->checkBatchNo($batch_no));

        return $batch_no;
    }

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

    public function chequeRevert($id)
    {
        $batchNo = Treasury::where("transaction_id", $id)
            ->whereNotNull("batch_no")
            ->pluck("batch_no")
            ->first();

        if ($batchNo) {
            $treasuriesId = Treasury::where("batch_no", $batchNo)->pluck("id");

            $transactionIds = Treasury::whereIn("id", $treasuriesId)->pluck("transaction_id");

            Transaction::whereIn("id", $transactionIds)
                ->where("state", "!=", "void")
                ->update([
                    "status" => "cheque-receive",
                    "state" => "receive",
                ]);

            Cheque::whereIn("transaction_id", $transactionIds)->forceDelete();
            VoucherAccountTitle::whereIn("treasury_id", $treasuriesId)->forceDelete();
            Treasury::whereIn("transaction_id", $transactionIds)->delete();

            $transactionIds->each(function ($transactionId) {
                Treasury::create([
                    "tag_id" => Transaction::where("id", $transactionId)->first()->tag_no,
                    "status" => "cheque-receive",
                    "date_status" => date("Y-m-d"),
                    "transaction_id" => $transactionId,
                ]);
            });

            return $this->resultResponse("update", "Transaction", []);
        } else {
            return $this->resultResponse("not-found", "Transaction", []);
        }
    }

    //===Testing Module===//
    public function chequeIndex1(Request $request)
    {
        $status = $request->input("state", "request");
        $state = $request->input("state", "request");

        $rows = $request->input("rows", 10);

        $search = $request->input("search");

        $suppliers = json_decode($request->input("suppliers")) ?? [];

        $cheque_from = isset($request["cheque_from"])
            ? Carbon::createFromFormat("Y-m-d", $request->input("cheque_from"))->format("Y-m-d")
            : null;
        $cheque_to = isset($request["cheque_to"])
            ? Carbon::createFromFormat("Y-m-d", $request->input("cheque_to"))->format("Y-m-d")
            : null;

        $cheques = Cheque
            //Supplier Filter
            ::when(count($suppliers), function ($query) use ($suppliers) {
                $query->whereHas("transaction", function ($query) use ($suppliers) {
                    return $query->whereIn("supplier_id", $suppliers);
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
                        ->orWhere("referrence_no", "like", "%" . $search . "%");
                });
            })

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
                        return $query->where("status", "executive-executive");
                    })
                    ->whereNull("is_received")
                    ->whereNull("is_returned")
                    ->whereNull("is_issued");
            })
            ->when($status == "issue-receive", function ($query) {
                $query
                    ->whereNull("issue_id")
                    ->whereHas("transaction", function ($query) {
                        return $query->whereIn("status", [
                            "executive-executive",
                            "issue-receive",
                            "issue-unhold",
                            "issue-unreturn",
                        ]);
                    })
                    ->where("is_received", true);
            })
            ->when($status == "return-issue", function ($query) {
                $query->whereHas("transaction", function ($query) {
                    return $query->where("status", "release-return");
                });
            })
            ->when($status == "hold-issue", function ($query) {
                $query->whereHas("transaction", function ($query) {
                    return $query->where("status", "release-hold");
                });
            })

            // releasing of cheque (external)
            ->when($status == "pending-release", function ($query) {
                $query
                    ->whereHas("transaction", function ($query) {
                        return $query->whereIn("status", ["issue-issue", "release-receive"])->where("is_for_releasing", true);
                    })
                    ->whereNull("is_received");
            })
            ->when($status == "release-receive", function ($query) {
                //                $query->whereHas('transaction', function ($query) {
                //                    return $query->whereIn("status", ["release-receive", "release-unhold", "release-unreturn"]);
                //                });
                $query
                    ->whereNull("is_released")
                    ->whereHas("transaction", function ($query) {
                        return $query->whereIn("status", ["issue-issue", "release-receive", "release-unhold", "release-unreturn"]);
                    })
                    ->where("is_received", true);
            })
            ->when($status == "return-release", function ($query) {
                $query
                    ->whereNull("issue_id")
                    ->whereHas("transaction", function ($query) {
                    return $query->where("status", "release-return");
                });
            })

            //clearing of cheque
            ->when($status == "pending-clear", function ($query) {
                //                $query->whereHas('transaction', function ($query) {
                //                    return $query->whereIn('status', [
                //                        "release-release",
                //                        "file-receive",
                //                        "file-file",
                //                        "discharge-receive",
                //                        "discharge-discharge"
                //                    ]);
                //                });
                return $query
                    ->whereNull("is_cleared")
                    ->whereNotNull("issue_id")
                    ->whereHas("transaction", function ($query) {
                        return $query->whereIn("status", [
                            "release-release",
                            "file-receive",
                            "file-file",
                            "discharge-receive",
                            "discharge-discharge",
                        ]);
                    });
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
                    //                $query->whereHas('transaction', function ($query) use ($status) {
                    //                    return $query->where("status", preg_replace("/\s+/", "", $status));
                    //                });
                    $query
                        ->when($status == "issue-issue", function ($query) {
                            //                    $query->whereNotNull('issue_id')->whereHas('transaction', function ($query) {
                            //                        return $query->whereIn("status", ["issue-issue", "issue-receive"]);
                            //                    })
                            //                        ->orWhere(function ($query) {
                            //                            $query->whereNotNull('issue_id');
                            //                        });
                            return $query
                                ->whereHas("transaction", function ($query) {
                                    return $query->whereIn("status", ["issue-issue", "issue-receive", "executive-executive"]);
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
                                })
                                ->where("is_released", true);
//                                ->whereNull("is_received");
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
                        //                        , function ($query) use ($status) {
                        //                            $query->whereHas('transaction', function ($query) use ($status) {
                        //                                return $query->where("status", preg_replace("/\s+/", "", $status));
                        //                            });
                        //                        }
                        )
                        ->when(in_array($status, ["audit-hold", "release-return", "hold-release"]), function ($query) use ($status) {
                            $query->whereHas("transaction", function ($query) use ($status) {
                                return $query->where("status", preg_replace("/\s+/", "", $status));
                            });
                        });
                }
            )

            //            ->when($status == $status, function ($query) use ($status) {
            //            $query->whereHas('transaction', function ($query) use ($status) {
            //                return $query->where('status', $status);
            //            });
            //        })
            ->select("bank_id", "bank_name", "cheque_no", DB::raw("MAX(updated_at) as latest_updated_at"))
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
                ->where("cheque_no", $item->cheque_no)
                ->first();

            $transaction = Transaction::whereIn("id", $ids)->get();
            $transaction = $transaction->map(function ($item) use ($ids) {
                return [
                    "id" => $item->id,
                    "tag_no" => $item->tag_no,
                    "transaction_no" => $item->transaction_id,
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
                            ? ($item->category == "rental"
                            ? $item->gross_amount
                            : $item->principal + $item->interest)
                            : $item->document_amount,
                    "reference_no" => $item->referrence_no,
                    "reference_amount" => $item->referrence_amount,
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

            //            $voucher_account_titles = Transaction::with('account_titles')
            //                ->whereIn('id', $ids)
            //                ->get()
            //                ->pluck('account_titles')
            //                ->flatten()
            //                ->unique('account_title_id')
            //                ->values();

//      $treasury_account_titles = Transaction::with("treasuryAccountTitle")
//        ->whereIn("id", $ids)
//        ->get()
//        ->pluck("treasuryAccountTitle")
//        ->flatten()
//        ->unique("account_title_id")
//        ->filter(function ($item, $index) use ($cheque_details) {
//          return $item->account_title_id == $cheque_details->bank->AccountTitleOne->id || $item->entry == "Debit";
//        })
//        ->values();
//
//      if (count($ids) > 1) {
//        $treasury_account_titles = Transaction::with("treasuryAccountTitle")
//          ->where("id", $ids[0])
//          ->get()
//          ->pluck("treasuryAccountTitle")
//          ->flatten()
//          ->values();
//      }

            $treasury_account_titles = $this->getTreasuryAccountTitles($ids, $cheque_details);

            //            $totalDebit = Transaction::with('treasuryAccountTitle')
            //                ->whereIn('id', $ids)
            //                ->get()
            //                ->pluck('treasuryAccountTitle')
            //                ->flatten()
            //                ->unique('treasury_id')
            //                ->filter(function ($item, $index) use ($cheque_details){
            //                    return $item->account_title_id == $cheque_details->bank->AccountTitleOne->id || $item->entry == 'Debit';
            //                })
            //                ->sum('amount');

            //            $clear_account_titles = Transaction::with('accountTitleClear')
            //                ->whereIn('id', $ids)
            //                ->get()
            //                ->pluck('accountTitleClear')
            //                ->flatten()
            //                ->unique('account_title_id')
            //                ->values();

            //            $account_titles_treasury = $treasury_account_titles->isEmpty() ? $voucher_account_titles : $treasury_account_titles;
            //            $account_titles = $clear_account_titles->isEmpty() ? $account_titles_treasury : $clear_account_titles;

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
            //            $voucher_account_titles = Transaction::with('account_titles')->whereIn('id', $ids)->get()->pluck('account_titles')->flatten()->unique('account_title_id');

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

        if (count($ids) <= 1) {
            $collection = $collection->unique('account_title_id')->filter(function ($item, $index) use ($cheque_details) {
                return $item->account_title_id == $cheque_details->bank->AccountTitleOne->id || $item->entry == 'Debit';
            });
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
            VoucherAccountTitle::whereIn("treasury_id", $treasuryIds)->forceDelete();
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
                ]);

            return $this->resultResponse("update", "Transaction", []);
        }
    }

    public function statusTransactionCounter()
    {
        $permissions = auth()->user()->permissions;
//        $user_id = $request->input('user_id', null);

        $statusMap = [
            1 => ["tag-return", "pending"], //Creation of Request
            2 => [], //Creation of Confidential Request
            3 => ["transmit-transmit"], //Auditing of Voucher
            4 => [], //Received Receipt Report
//            5 => [], //Auditing of Cheque
//            6 => [], //External Releasing of Cheque
            7 => ["transmit-transmit", "audit-return", "inspect-inspect"], //Creation of Cheque
//            8 => [], //Clearing of Cheque
            9 => [], //Creation of Debit Memo
            10 => [], //Reversal Request
            11 => ["discharge-discharge", "release-release"], //Filing of Voucher
            12 => ["gas-gas", "tag-tag", "approve-return", "cheque-return"], //Creation of Voucher
            13 => [], //Transmittal of Confidential Document
            14 => [], //Filing of Confidential Voucher
            15 => [], //Tagging and Vouchering
            16 => [], //Releasing of Confidential Cheque
            17 => ["voucher-voucher"], //Approval of Voucher
            18 => [], //Approval of Confidential Voucher
            19 => ["approve-approve"], //Transmittal of Document
            20 => ["pending", "voucher-return"], //Tagging of Document
            21 => [], //Creation of Counter Receipt
            22 => [], //Monitoring of Counter Receipt
//            23 => [],  //Transmittal of Cheque
//            24 => [], //Internal Releasing of Cheque
            25 => ["tag-tag"], //Transmittal of Official Receipt
            26 => ["release-release"], //Filing of Official Receipt
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
                switch ($permissionName) {
                    case 'Transmittal of Official Receipt':
                    case 'Filing of Official Receipt':
                        $receipt_type = 'Official';
                        break;

                    case 'Creation of Voucher':
                    case 'Transmittal of Document':
                    case 'Approval of Voucher':
                    case 'Filing of Voucher':
                        $user_id = auth()->user()->id;
                        break;

                    case 'Auditing of Voucher':
                        $document_id = 8;
                        break;
                }

                $counts = Transaction::select('status', DB::raw('count(*) as count'))
//                    ->when($document_id, function ($query) use ($document_id, $status) {
//                        if ($status == 'transmit-transmit' && $document_id != 8) {
//                            $query->where('status', $status);
//                        }
//                        return $query->where('document_id', $document_id);
//                    })
                    ->when($user_id, function ($query) use ($user_id) {
                        $query->where(function ($query) use ($user_id) {
                            $query->where('distributed_id', $user_id)
                                ->orWhere('approver_id', $user_id);
                        });
                    })
                    ->when($receipt_type, function ($query) use ($receipt_type, $status) {
                        return $query->where('receipt_type', $receipt_type);
                    })
                    ->when($permissionName == 'Creation of Voucher', function ($query) use ($status) {
                        $query->when($status == 'tag-tag', function ($query) {
                            $query->where(function ($query) {
                                $query->where('status', 'tag-tag')
                                    ->where('receipt_type', '!=', 'Official');
                            })->orWhere(function ($query) {
                                $query->where('status', 'gas-gas');
                            });
                        })
                            ->when($status == 'approve-return' || $status == 'cheque-return', function ($query) {
                                $query->whereIn('status', ['approve-return', 'cheque-return']);
                            });
                    })
                    ->when($permissionName == 'Filing of Voucher', function ($query) {
                        $query->where(function ($query) {
                            $query->where('status', 'release-release')
                                ->where('receipt_type', '!=', 'Official');
                        })->orWhere(function ($query) {
                            $query->where('status', 'discharge-discharge');
                        });
                    })
                    ->when($permissionName == 'Creation of Cheque', function ($query) use ($status) {
                        $query->when($status == 'transmit-transmit', function ($query) {
                            $query->where(function ($query) {
                                $query->where('status', 'transmit-transmit')
                                    ->where('document_id', '!=', 8);
                            })->orWhere(function ($query) {
                                $query->where('status', 'inspect-inspect');
                            });
                        })
                            ->when($status == 'audit-return', function ($query) {
                                $query->whereIn('status', ['audit-return']);
                            });

                    })
                    ->whereIn('status', $status)
                    ->groupBy('status')
                    ->get()
                    ->pluck('count', 'status')
                    ->toArray();


//                $counts = Transaction::select('status', DB::raw('count(*) as count'))
//                    ->whereIn('status', $status)
//                    ->when($user_id, function ($query) use ($user_id){
//                        $query->where('distributed_id', $user_id)
//                            ->orWhere('approver_id', $user_id);
//                    })
//                    ->when($receipt_type, function ($query) use ($receipt_type){
//                        $query->where('receipt_type', $receipt_type);
//                    })
//                    ->groupBy('status')
//                    ->get()
//                    ->pluck('count', 'status')
//                    ->toArray();

                // Update the counts
//                foreach ($counts as $stat => $count) {
//                    $result[strtolower($stat)] = $count;
//                }

                foreach ($counts as $stat => $count) {
                    switch ($stat) {
                        case 'Pending':
                        case 'tag-tag':
                        case 'gas-gas':
                        case 'voucher-voucher':
                        case 'approve-approve':
                        case 'transmit-transmit':
                        case 'inspect-inspect':
                        case 'release-release':
                        case 'discharge-discharge':
                            $result['pending'] = $count += $result['pending'];
                            break;

                        case 'tag-return':
                        case 'voucher-return':
                        case 'cheque-return':
                        case 'approve-return':
                        case 'audit-return':
                            $result['return'] = $count;
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

//    public function statusChequeCounter() {
//        $permissions = auth()->user()->permissions;
//
//        $statusMap = [
//            1 => [], //Creation of Request
//            2 => [], //Creation of Confidential Request
////            3 => [], //Auditing of Voucher
//            4 => [], //Received Receipt Report
//            5 => ["cheque-cheque", "audit-receive", "audit-audit"], //Auditing of Cheque
//            6 => ["issue-issue", "release-receive", "release-release"], //External Releasing of Cheque
////            7 => ["transmit-transmit", "cheque-receive", "cheque-cheque"], //Creation of Cheque
//            8 => ["clear-pending", "clear-clear"], //Clearing of Cheque
//            9 => [], //Creation of Debit Memo
//            10 => [], //Reversal Request
//            11 => [], //Filing of Voucher
////            12 => ["tag-tag", "gas-gas", "voucher-receive", "voucher-voucher", "approve-return", "cheque-return"], //Creation of Voucher
//            13 => [], //Transmittal of Confidential Document
//            14 => [], //Filing of Confidential Voucher
//            15 => [], //Tagging and Vouchering
//            16 => [], //Releasing of Confidential Cheque
////            17 => ["voucher-voucher", "approve-receive", "approve-approve"], //Approval of Voucher
//            18 => [], //Approval of Confidential Voucher
////            19 => ["approve-approve", "transmit-receive", "transmit-transmit"], //Transmittal of Document
////            20 => ["pending", "voucher-return"], //Tagging of Document
//            21 => [], //Creation of Counter Receipt
//            22 => [], //Monitoring of Counter Receipt
//            23 => ["audit-audit", "executive-receive", "executive-executive"],  //Transmittal of Cheque
//            24 => ["executive-executive", "issue-receive", "issue-issue"], //Internal Releasing of Cheque
////            25 => ["tag-tag", "gas-gas"], //Transmittal of Official Receipt
////            26 => [], //Filing of Official Receipt
//        ];
//
//        $response = [];
//
//        foreach ($permissions as $permission) {
//            if (isset($statusMap[$permission])) {
//                $status = $statusMap[$permission];
//                $permissionName = Permission::where('id', $permission)->first()->name;
//
//                // Initialize all status counts to zero
//                $result = array_fill_keys($status, 0);
//
//                // Count the total number of records for each status
//                foreach ($status as $stat) {
//
//
//                    $counts = Cheque::select('bank_id', 'cheque_no')
//                        ->when($stat == 'cheque-cheque', function ($query) {
//                            $query->whereHas('transaction', function ($query) {
//                                $query->whereIn('status', ['cheque-cheque', 'audit-receive', 'audit-audit']);
//                            })->whereNull('is_received')->whereNull('is_audited');
//                        })
//                        ->when($stat == 'audit-receive', function ($query) {
//                            $query->whereHas('transaction', function ($query) {
//                                $query->whereIn('status', ['cheque-cheque', 'audit-receive', 'audit-audit']);
//                            })->where('is_received', true)->whereNull('is_audited');
//                        })
//                        ->when($stat == 'audit-audit', function ($query) {
//                            $query->whereHas('transaction', function ($query) {
//                                $query->whereIn('status', ['cheque-cheque', 'audit-receive', 'audit-audit']);
//                            })->where('is_audited', true)->whereNull('is_received');
//                        })
//                        ->when($stat == 'executive-receive', function ($query) {
//                            $query->whereHas('transaction', function ($query) {
//                                $query->whereIn('status', ['audit-audit', 'executive-receive', 'executive-executive']);
//                            })->where('is_audited', true)->whereNull('is_executived')->where('is_received', true);
//                        })
//                        ->when($stat == 'executive-executive', function ($query) {
//                            $query->whereHas('transaction', function ($query) {
//                                $query->whereIn('status', ['audit-audit', 'executive-receive', 'executive-executive']);
//                            })->where('is_executived', true)->whereNull('is_received');
//                        })
//                        ->when($stat == 'issue-receive', function ($query) {
//                            $query->whereHas('transaction', function ($query) {
//                                $query->whereIn('status', ['executive-executive', 'issue-receive', 'issue-issue']);
//                            })->where('is_audited', true)->where('is_executived', true)->where('is_received', true);
//                        })
//                        ->when($stat == 'issue-issue', function ($query) {
//                            $query->whereHas('transaction', function ($query) {
//                                $query->whereIn('status', ['executive-executive', 'issue-receive', 'issue-issue']);
//                            })->where('is_issued', true)->whereNull('is_received');
//                        })
//                        ->when($stat == 'release-receive', function ($query) {
//                            $query->whereHas('transaction', function ($query) {
//                                $query->whereIn('status', ['issue-issue', 'release-receive', 'release-release']);
//                            })->where('is_issued', true)->where('is_received', true)->whereNull('is_released');
//                        })
//                        ->when($stat == 'release-release', function ($query) {
//                            $query->whereHas('transaction', function ($query) {
//                                $query->whereIn('status', ['release-release', 'discharge-discharge']);
//                            })->where('is_released', true);
//                        })
//                        ->when($stat == 'clear-pending', function ($query) {
//                            $query->whereHas('transaction', function ($query) {
//                                $query->whereIn('status', ['release-release', 'file-receive', 'file-file', 'discharge-receive', 'discharge-discharge']);
//                            })->where('is_released', true)->whereNull('is_cleared');
//                        })
//                        ->when($stat == 'clear-clear', function ($query) {
//                            $query->whereHas('transaction', function ($query) {
//                                $query->whereIn('status', ['release-release', 'file-receive', 'file-file', 'discharge-receive', 'discharge-discharge']);
//                            })->where('is_cleared', true);
//                        })
//                        ->groupBy('bank_id', 'cheque_no')->get();
//
//                    $counts = $counts->count();
//
//                    // Only assign the count to the result if the status exists in the database
//                    if ($counts > 0) {
//                        $result[$stat] = $counts;
//                    }
//                }
//
//                $response[] = [
//                    'permission' => $permissionName,
//                    'result' => $result
//                ];
//            }
//        }
//        return response()->json($response);
//
//    }

    public function statusChequeCounter()
    {
        $permissions = auth()->user()->permissions;

        $statusMap = [
            5 => ['cheque-cheque'], //Auditing of Cheque
            6 => ['issue-issue'], // External Releasing of Cheque
            8 => ['release-release'], //Clearing of Cheque
            9 => [], //Creation of Debit Memo
            10 => [], //Reversal Request
            16 => [], //Releasing of Confidential Cheque
            21 => [], //Creation of Counter Receipt
            22 => [], //Monitoring of Counter Receipt
            23 => ['audit-audit'], //Transmittal of Cheque
            24 => ['executive-executive', 'release-return'], //Internal Releasing of Cheque
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

                // Count the total number of records for each status
                foreach ($status as $stat) {
                    $counts = Cheque::select('bank_id', 'cheque_no')
//                        ->when(isset($statusMap[$stat]), function ($query) use ($statusMap, $stat) {
//                            $query->whereHas('transaction', function ($query) use ($statusMap, $stat) {
//                                $query->whereIn('status', $statusMap[$stat]);
//                            });
//                        })
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
//                        ->when($stat == 'clear-pending', function ($query) {
//                            $query->whereHas('transaction', function ($query) {
//                                $query->whereIn('status', ['release-release', 'file-receive', 'file-file', 'discharge-receive', 'discharge-discharge']);
//                            })->where('is_released', true)->whereNull('is_cleared');
//                        })


//                        ->when($stat == 'cheque-cheque', function ($query) {
//                            $query->whereNull('is_received')->whereNull('is_audited');
//                        })
//                        ->when($stat == 'audit-receive', function ($query) {
//                            $query->where('is_received', true)->whereNull('is_audited');
//                        })
//                        ->when($stat == 'audit-audit', function ($query) {
//                            $query->where('is_audited', true)->whereNull('is_received');
//                        })
//                        ->when($stat == 'executive-receive', function ($query) {
//                            $query->where('is_audited', true)->whereNull('is_executived')->where('is_received', true);
//                        })
//                        ->when($stat == 'executive-executive', function ($query) {
//                            $query->where('is_executived', true)->whereNull('is_received');
//                        })
//                        ->when($stat == 'issue-receive', function ($query) {
//                            $query->where('is_audited', true)->where('is_executived', true)->where('is_received', true);
//                        })
//                        ->when($stat == 'issue-issue', function ($query) {
//                            $query->where('is_issued', true)->whereNull('is_received');
//                        })
//                        ->when($stat == 'release-receive', function ($query) {
//                            $query->where('is_issued', true)->where('is_received', true)->whereNull('is_released');
//                        })
//                        ->when($stat == 'release-release', function ($query) {
//                            $query->where('is_released', true)->whereNull('is_cleared');
//                        })
//                        ->when($stat == 'clear-pending', function ($query) {
//                            $query->where('is_released', true)->whereNull('is_cleared');
//                        })
//                        ->when($stat == 'clear-clear', function ($query) {
//                            $query->where('is_cleared', true);
//                        })
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
                                $result['pending'] = $counts;
                                break;

                            case 'voucher-return':
                            case 'approve-return':
                            case 'release-return':
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

    public function chequeHistory($id) {
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
        $transaction_from = date_format(date_create($request->input('transaction_from', Carbon::now()->format('Y-m-d'))), "Y-m-d");
        $transaction_to = date_format(date_create($request->input('transaction_to', Carbon::now()->format('Y-m-d'))), "Y-m-d");

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

}
