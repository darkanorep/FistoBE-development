<?php

namespace App\Http\Controllers;

use App\Http\Requests\BankSeriesRequest;
use App\Models\BankSeries;
use App\Services\GenericServices;
use Illuminate\Http\Request;

class BankSeriesController extends Controller
{

    public function __construct(GenericServices $genericServices)
    {
        $this->genericServices = $genericServices;
    }

    public function index(Request $request) {

        $status = $request["status"];
        $rows = (int)$request->input("rows", 10);
        $search = $request["search"];
        $paginate = $request->input("paginate", 1);

        $bank_series = BankSeries::withTrashed()
            ->with([
                'bank:id,name'
            ])
            ->when(isset($status), function ($query) use ($status) {
                return $status ? $query->whereNull("deleted_at") : $query->whereNotNull("deleted_at");
            })
            ->where(function ($query) use ($search) {
                $query->where("from", "like", "%" . $search . "%")
                    ->orWhere("to", "like", "%" . $search . "%")
                    ->orWhereHas('bank', function ($query) use ($search) {
                        $query->where("name", "like", "%" . $search . "%");
                    });
            })
            ->latest("updated_at");

        if ($paginate == 1) {
            $bank_series = $bank_series->paginate($rows);

        } elseif ($paginate == 0) {
            $bank_series = $bank_series->get();
        }

        if (count($bank_series)) {
            return $this->resultResponse("fetch", "Bank Series", $bank_series);
        } else {
            return $this->resultResponse("not-found", "Bank Series", []);
        }

    }

    public function store(BankSeriesRequest $request) {

        $bank_series = $this->genericServices->store(BankSeries::class, $request->validated());

        return $this->resultResponse('save', 'Bank Series', $bank_series);

    }

    public function update(BankSeries $bank_series, BankSeriesRequest $request) {

        if ($bank_series) {
            $bank_series = $this->genericServices->update($bank_series, $request->validated());

            return $this->resultResponse('update', 'Bank Series', $bank_series);
        } else {
            return $this->resultResponse('not-found', 'Bank Series');
        }
    }

    public function change_status($id) {
        return $this->changeStatus($id, BankSeries::class, 'Bank Series');
    }

    public function chequeNumber(Request $request) {
        $bank_id = $request->bank_id;

        return BankSeries::where('bank_id', $bank_id)->whereNull('deleted_at')->get();
    }
}
