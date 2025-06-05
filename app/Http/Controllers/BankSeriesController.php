<?php

namespace App\Http\Controllers;

use App\Http\Requests\BankSeriesRequest;
use App\Models\BankSeries;
use App\Models\Cheque;
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

    public function chequeNumberAvailable(Request $request)
    {
        $document_name = $request->input("document_name");

        $bankSeries = BankSeries::withTrashed()
            ->where('document_name', $document_name)
            ->select('bank_id', 'from', 'to', 'category', 'is_used')
            ->get();

        if ($bankSeries->isEmpty()) {
            return response()->json([]);
        }

        // Separate processing: blank vs prenumbered
        $rangesByBank = [];
        $minMaxByBank = [];

        foreach ($bankSeries as $series) {
            if ($series->category === 'prenumbered stock') {
                $rangesByBank[$series->bank_id][] = [$series->from, $series->to];
                if (!isset($minMaxByBank[$series->bank_id])) {
                    $minMaxByBank[$series->bank_id] = [$series->from, $series->to];
                } else {
                    $minMaxByBank[$series->bank_id][0] = min($minMaxByBank[$series->bank_id][0], $series->from);
                    $minMaxByBank[$series->bank_id][1] = max($minMaxByBank[$series->bank_id][1], $series->to);
                }
            }
        }

        // Get all used cheques for prenumbered banks
        $allUsedCheques = Cheque::where(function ($query) use ($minMaxByBank) {
            foreach ($minMaxByBank as $bank_id => [$min, $max]) {
                $query->orWhere(function ($q) use ($bank_id, $min, $max) {
                    $q->where('bank_id', $bank_id)
                        ->whereBetween('cheque_no', [$min, $max]);
                });
            }
        })->get(['cheque_no', 'bank_id']);

        $usedChequesByBank = $allUsedCheques->groupBy('bank_id')->map(function ($item) {
            return array_flip($item->pluck('cheque_no')->map(function ($n) {
                return (int)$n;
            })->toArray());
        });

        // Collect available cheques
        $availableCheques = [];

        // Handle prenumbered stock
        foreach ($rangesByBank as $bank_id => $ranges) {
            $used = $usedChequesByBank->get($bank_id, []);

            $found = null;
            foreach ($ranges as [$from, $to]) {
                for ($i = $from; $i <= $to; $i++) {
                    if (!isset($used[$i])) {
                        $found = $i;
                        break 2; // Exit both loops
                    }
                }
            }

            $availableCheques[] = [
                'bank_id' => $bank_id,
                'next_available_cheque' => $found,
            ];
        }

        // Handle blank stock: just return `from` if `is_used == false`
        $blankStocks = $bankSeries->where('category', 'blank stock')->where('is_used', false);
        foreach ($blankStocks as $blank) {
            $availableCheques[] = [
                'bank_id' => $blank->bank_id,
                'next_available_cheque' => $blank->from,
            ];
        }

        return response()->json($availableCheques);
    }
}
