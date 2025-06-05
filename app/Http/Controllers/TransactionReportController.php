<?php

namespace App\Http\Controllers;

use App\Http\Requests\TransactionReportRequest;
use App\Models\TransactionReport;
use Illuminate\Http\Request;

class TransactionReportController extends Controller
{
    public function index(Request $request) {
        $status = $request["status"];
        $rows = (int)$request->input("rows", 10);
        $search = $request["search"];
        $paginate = $request->input("paginate", 1);

        $transactionReports = TransactionReport::when(isset($status), function ($query) use ($status) {
            return $status ? $query->whereNull("deleted_at") : $query->whereNotNull("deleted_at");
        })
            ->where(function ($query) use ($search) {
                $query->where("name", "like", "%" . $search . "%");
            })
            ->latest("updated_at");

        if ($paginate == 1) {
            $transactionReports = $transactionReports->paginate($rows);
        } elseif ($paginate == 0) {
            $transactionReports = $transactionReports->get();
        }

        return $this->resultResponse("fetch", "Transaction Reports", $transactionReports);
    }

    public function store(TransactionReportRequest $request) {

        $name = $request->name;

        $transactionReport = TransactionReport::create([
            'name' => $name
        ]);

        return $this->resultResponse("save", "Transaction Report", $transactionReport);
    }

    public function show($id) {
        $transactionReport = TransactionReport::where("id", $id)->first();

        return $transactionReport
            ? $this->resultResponse("fetch", "Transaction Report", $transactionReport)
            : $this->resultResponse("not-found", "Transaction Report", []);
    }

    public function update(TransactionReportRequest $request, $id) {

        $transactionReport = TransactionReport::where("id", $id)->first();

        if ($transactionReport) {
            $transactionReport->update([
                "name" => $request->name,
            ]);

            return $this->resultResponse("update", "Transaction Report", $transactionReport);
        } else {
            return $this->resultResponse("not-found", "Transaction Report", []);
        }

    }

    public function change_status($id) {
        return $this->changeStatus($id, TransactionReport::class, "Transaction Report");
    }
}
