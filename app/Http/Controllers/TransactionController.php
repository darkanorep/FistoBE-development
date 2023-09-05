<?php

namespace App\Http\Controllers;

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

  public function index(Request $request)
  {
    $dateToday = Carbon::now()->timezone("Asia/Manila");

    $department = [];
    $users_id = Auth::user()->id;
    $role = Auth::user()->role;
    $status = isset($request["state"]) && $request["state"] ? $request["state"] : "request";
    $rows = isset($request["rows"]) && $request["rows"] ? (int) $request["rows"] : 10;
    $suppliers =
      isset($request["suppliers"]) && $request["suppliers"]
        ? array_map("intval", json_decode($request["suppliers"]))
        : [];
    $document_ids =
      isset($request["document_ids"]) && $request["document_ids"]
        ? array_map("intval", json_decode($request["document_ids"]))
        : [];
    $transaction_from =
      isset($request["transaction_from"]) && $request["transaction_from"]
        ? Carbon::createFromFormat("Y-m-d", $request["transaction_from"])
          ->startOfDay()
          ->format("Y-m-d H:i:s")
        : $dateToday->startOfDay()->format("Y-m-d H:i:s");
    $transaction_to =
      isset($request["transaction_to"]) && $request["transaction_to"]
        ? Carbon::createFromFormat("Y-m-d", $request["transaction_to"])
          ->endOfDay()
          ->format("Y-m-d H:i:s")
        : $dateToday->endOfDay()->format("Y-m-d H:i:s");
    $cheque_from =
      isset($request["cheque_from"]) && $request["cheque_from"]
        ? Carbon::createFromFormat("Y-m-d", $request["cheque_from"])
          ->startOfDay()
          ->format("Y-m-d H:i:s")
        : $dateToday->startOfDay()->format("Y-m-d H:i:s");
    $cheque_to =
      isset($request["cheque_to"]) && $request["cheque_to"]
        ? Carbon::createFromFormat("Y-m-d", $request["cheque_to"])
          ->endOfDay()
          ->format("Y-m-d H:i:s")
        : $dateToday->endOfDay()->format("Y-m-d H:i:s");

    $search = $request["search"];
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

    $is_voucher_transfered = $status == "voucher-transfer";
    $is_transmit_transfered = $status == "transmit-transfer";
    $is_file_transfered = $status == "file-transfer";

    $transactions = Transaction::select([
      "id",
      "company_id",
      // ,'tag_no'
    ])
      ->with("users", function ($query) {
        return $query->select(["id", "first_name", "middle_name", "last_name", "users.department", "position"]);
      })
      ->with("supplier.supplier_type", function ($query) {
        return $query->select(["id", "type as name", "transaction_days"]);
      })
      ->with("po_details", function ($query) {
        return $query->select(["id", "request_id", "po_no", "po_total_amount"]);
      })
      ->with("audit")
      ->with("executive")
      ->with("cheques.cheques")
      ->when(!empty($document_ids), function ($query) use ($document_ids) {
        $query->whereIn("document_id", $document_ids);
      })
      ->when(!empty($suppliers), function ($query) use ($suppliers) {
        $query->whereIn("supplier_id", $suppliers);
      })
      ->when(
        isset($request["cheque_from"]) || isset($request["cheque_to"]),
        function ($query) use ($cheque_from, $cheque_to) {
          $query->whereHas("cheques.cheques", function ($query) use ($cheque_from, $cheque_to) {
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
      ->where(function ($query) use ($search) {
        $query
          ->where("date_requested", "like", "%" . $search . "%")
          ->orWhere("remarks", "like", "%" . $search . "%")
          ->orWhere("tag_no", "like", "%" . $search . "%")
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
                strtolower($status) == "return-return",
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

            "status",
            "state",
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
                      // $query->whereIn("status", ["cheque-release"]);
                      $query->whereIn("status", ["issue-issue"])->where("is_for_releasing", "=", true);
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
                                strtolower($status) == "return-return",
                                function ($query) use ($status) {
                                  $query->whereIn("status", ["voucher-return"]);
                                },
                                function ($query) use ($status) {
                                  $query->when(
                                    strtolower($status) == "return-hold",
                                    function ($query) use ($status) {
                                      $query->whereIn("status", ["voucher-hold"]);
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

            "status",
            "state",
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
          ->when(
            strtolower($status) == "voucher-receive",
            function ($query) {
              $query->whereIn("status", ["voucher-receive", "voucher-unhold", "voucher-unreturn"]);
            },
            function ($query) use ($users_id, $status) {
              $query->when(
                strtolower($status) == "pending",
                function ($query) {
                  $query->whereIn("status", ["tag-tag", "voucher-transfer"]);
                },
                function ($query) use ($users_id, $status) {
                  $query->when(
                    strtolower($status) == "pending-transmit",
                    function ($query) {
                      $query->whereIn("status", ["approve-approve", "transmit-transfer", "inspect-inspect"]);
                    },
                    function ($query) use ($users_id, $status) {
                      $query->when(
                        strtolower($status) == "pending-file",
                        function ($query) {
                          $query->whereIn("status", ["release-release", "file-transfer"]);
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
                                    strtolower($status) == "return-return",
                                    function ($query) use ($status) {
                                      $query
                                        ->whereIn("status", [
                                          "cheque-return",
                                          "approve-return",
                                          "inspect-return",
                                          "issue-return",
                                          "audit-return",
                                        ])
                                        ->orWhere(function ($query) {
                                          $query->whereIn("status", ["audit-return"])->where("document_id", 9);
                                        });
                                    },
                                    function ($query) use ($status) {
                                      $query->when(
                                        strtolower($status) == "return-hold",
                                        function ($query) use ($status) {
                                          $query->whereIn("status", ["cheque-hold", "approve-hold"]);
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

            "status",
            "state",

            "distributed_id",
            "distributed_name",
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

            "approver_id",
            "approver_name",

            "status",
            "state",
          ])
          ->where("approver_id", $users_id);
      })
      ->when(in_array($role, $cheque_window), function ($query) use ($status, $is_auto_debit) {
        $query
          ->when(
            $is_auto_debit,
            function ($query) {
              $query->where("document_type", "Auto Debit");
            },
            function ($query) {
              $query->where("document_type", "<>", "Auto Debit");
            }
          )
          ->when(
            strtolower($status) == "cheque-receive",
            // function ($query) {
            //   $query
            //     ->whereIn("status", ["cheque-receive", "cheque-unhold", "cheque-unreturn"])
            //     ->whereNull("is_for_releasing");
            // },
            function ($query) use ($is_auto_debit) {
              $query
                ->whereIn("status", ["cheque-receive", "cheque-unhold", "cheque-unreturn"])
                ->whereNull("is_for_releasing")
                ->orWhere(function ($query) use ($is_auto_debit) {
                  $query->when($is_auto_debit, function ($query) {
                    $query->where("status", "cheque-receive")->where("is_for_releasing", true);
                  });
                });
            },
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
                      // $query->whereIn("status", ["transmit-transmit", "audit-return"])->where(function ($query) {
                      //   $query->whereNull("is_for_voucher_audit")->orWhere("is_for_releasing", true);
                      // });
                      $query->whereIn("status", ["transmit-transmit"])->where(function ($query) {
                        $query->whereNull("is_for_voucher_audit")->orWhere("is_for_releasing", true);
                      });
                    },
                    function ($query) use ($status) {
                      $query->when(
                        strtolower($status) == "pending-clear",
                        function ($query) {
                          $query->whereIn("status", ["file-file"]);
                        },
                        function ($query) use ($status) {
                          $query->when(
                            strtolower($status) == "return-return",
                            function ($query) use ($status) {
                              $query->whereIn("status", ["release-return", "reverse-return"]);
                            },
                            function ($query) use ($status) {
                              $query->when(
                                strtolower($status) == "return-hold",
                                function ($query) use ($status) {
                                  $query->whereIn("status", ["release-hold"]);
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

            "status",
            "state",
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
                      // $query->where("status", preg_replace("/\s+/", "", $status));
                      $query->when(
                        strtolower($status) == "audit-audit",
                        function ($query) {
                          $query->whereIn("status", ["audit-audit"])->orWhere(function ($query) {
                            $query->where("document_id", 9)->where("is_for_releasing", true);
                          });
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

            "approver_id",
            "approver_name",

            "status",
            "state",
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

            "approver_id",
            "approver_name",

            "status",
            "state",
          ]);
      })
      ->latest("updated_at")
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

    $singleTransaction = TransactionResource::collection($transaction);
    if (count($singleTransaction) != true) {
      throw new FistoException("No records found.", 404, null, []);
    }
    return $this->resultResponse("fetch", "Transaction details", $singleTransaction->first());
  }

  public function showCurrentPO($id)
  {
    $transaction = Transaction::where("id", $id)->get();
    $singleTransaction = TransactionResource::collection($transaction);
    if (count($singleTransaction) != true) {
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
        GenericMethod::validate_debit_amount(
          $fields["document"]["amount"],
          $fields["autoDebit_group"],
          "Document amount and net of cwt amount is not equal."
        );
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
        // switch ($request->input("document.payment_type")) {
        //   case "Partial":
        //     GenericMethod::documentNoValidationUpdate($request["document"]["no"], $id);

        //     if (empty($fields["po_group"])) {
        //       $errorMessage = GenericMethod::resultLaravelFormat("po_group", ["PO group required"]);
        //       return $this->resultResponse("invalid", "", $errorMessage);
        //     }

        //     $currentTransaction = Transaction::findOrFail($id);

        //     if ($currentTransaction->is_not_editable == 1) {
        //       $updateData = [
        //         "document_date" => data_get($fields, "document.date"),
        //         "remarks" => data_get($fields, "document.remarks"),
        //         "company" => data_get($fields, "document.company.name"),
        //         "department_id" => data_get($fields, "document.department.id"),
        //         "department" => data_get($fields, "document.department.name"),
        //         "location_id" => data_get($fields, "document.location.id"),
        //         "location" => data_get($fields, "document.location.name"),
        //         // "referrence_id" => data_get($fields, "document.reference.id"),
        //         // "referrence_type" => data_get($fields, "document.reference.type"),
        //         // "referrence_no" => data_get($fields, "document.reference.no"),
        //         "category_id" => data_get($fields, "document.category.id"),
        //         "category" => data_get($fields, "document.category.name"),
        //       ];

        //       if ($currentTransaction->status == "tag-return") {
        //         $updateData["status"] = "Pending";
        //         $updateData["state"] = "pending";
        //       }

        //       $currentTransaction->update($updateData, ["timestamps" => false]);

        //       return $this->resultResponse("update", "Transaction", []);
        //     }

        //     $fields["po_group"] = GenericMethod::ValidateIfPOExists(
        //       $fields["po_group"],
        //       $fields["document"]["company"]["id"],
        //       $id
        //     );

        //     $getAndValidatePOBalance = GenericMethod::getAndValidatePOBalance(
        //       $fields,
        //       $fields["document"]["company"]["id"],
        //       last($fields["po_group"])["no"],
        //       $fields["document"]["amount"],
        //       $fields["po_group"],
        //       $id
        //     );

        //     if (gettype($getAndValidatePOBalance) == "object") {
        //       return $this->resultResponse("invalid", "", $getAndValidatePOBalance);
        //     }

        //     if (gettype($getAndValidatePOBalance) == "array") {
        //       //Additional PO Validation
        //       $new_po = $getAndValidatePOBalance["new_po_group"];
        //       $po_total_amount = $getAndValidatePOBalance["po_total_amount"];
        //       $balance_with_additional_total_po_amount = $getAndValidatePOBalance["balance"];

        //       $changes = GenericMethod::getTransactionChanges($request_id, $request, $id);
        //       GenericMethod::updatePO(
        //         $request_id,
        //         $fields["po_group"],
        //         $po_total_amount,
        //         strtoupper($fields["document"]["payment_type"]),
        //         $id
        //       );

        //       $transaction = GenericMethod::updateTransaction(
        //         $id,
        //         $po_total_amount,
        //         $request_id,
        //         $date_requested,
        //         $request,
        //         $balance_with_additional_total_po_amount,
        //         $changes
        //       );
        //       if (isset($transaction->transaction_id)) {
        //         return $this->resultResponse("update", "Transaction", []);
        //       }
        //     }

        //     $po_total_amount = GenericMethod::getPOTotalAmount($request_id, $fields["po_group"]);
        //     $balance_po_ref_amount = $po_total_amount - $fields["document"]["amount"];

        //     if (isset($getAndValidatePOBalance)) {
        //       $balance_po_ref_amount = $getAndValidatePOBalance;
        //     }

        //     $changes = GenericMethod::getTransactionChanges($request_id, $request, $id);
        //     GenericMethod::updatePO(
        //       $request_id,
        //       $fields["po_group"],
        //       $po_total_amount,
        //       strtoupper($fields["document"]["payment_type"]),
        //       $id
        //     );

        //     $transaction = GenericMethod::updateTransaction(
        //       $id,
        //       $po_total_amount,
        //       $request_id,
        //       $date_requested,
        //       $request,
        //       $balance_po_ref_amount,
        //       $changes
        //     );
        //     if (isset($transaction->transaction_id)) {
        //       return $this->resultResponse("update", "Transaction", []);
        //     }
        //     break;

        //   default:
        //     GenericMethod::documentNoValidationUpdate($request["document"]["no"], $id);

        //     if (empty($fields["po_group"])) {
        //       $errorMessage = GenericMethod::resultLaravelFormat("po_group", ["PO group required"]);
        //       return $this->resultResponse("invalid", "", $errorMessage);
        //     }

        //     $duplicatePO = GenericMethod::validatePOFullUpdate(
        //       $fields["document"]["company"]["id"],
        //       $fields["po_group"],
        //       $id
        //     );
        //     if (isset($duplicatePO)) {
        //       return $this->resultResponse("invalid", "", $duplicatePO);
        //     }

        //     $po_total_amount = GenericMethod::getPOTotalAmount($request_id, $fields["po_group"]);

        //     $errorMessage = GenericMethod::validateWith1PesoDifference(
        //       "po_group.amount",
        //       "Document",
        //       $fields["document"]["amount"],
        //       $po_total_amount
        //     );
        //     if (!empty($errorMessage)) {
        //       return GenericMethod::resultResponse("invalid", "", $errorMessage);
        //     }

        //     $changes = GenericMethod::getTransactionChanges($request_id, $request, $id);
        //     GenericMethod::updatePO(
        //       $request_id,
        //       $fields["po_group"],
        //       $po_total_amount,
        //       strtoupper($fields["document"]["payment_type"]),
        //       $id
        //     );

        //     $transaction = GenericMethod::updateTransaction(
        //       $id,
        //       $po_total_amount,
        //       $request_id,
        //       $date_requested,
        //       $request,
        //       0,
        //       $changes
        //     );

        //     if ($transaction == "Nothing Has Changed") {
        //       return $this->resultResponse("nothing-has-changed", "Transaction", []);
        //     }
        //     if (isset($transaction->transaction_id)) {
        //       return $this->resultResponse("update", "Transaction", []);
        //     }
        //     break;
        // }
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

        // $transaction = Transaction::find($id);

        // $count = Transaction::where("transaction_id", $transaction->transaction_id)
        //   ->distinct("request_id")
        //   ->count("request_id");

        // if (
        //   Tagging::where("transaction_id", $transaction->transaction_id)
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

        GenericMethod::validate_debit_amount(
          $fields["document"]["amount"],
          $fields["autoDebit_group"],
          "Document amount and net of amount is not equal."
        );
        GenericMethod::update_debit_attachment($request_id, $fields["autoDebit_group"], $id);
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
      $po_object = (object) ["po_group" => $po_details];
      return $this->resultResponse("fetch", "PO number", $po_object);
    }
    return $this->resultResponse("success-no-content", "", []);
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
}
