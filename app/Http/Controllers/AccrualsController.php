<?php

namespace App\Http\Controllers;

use App\Models\AccountTitle;
use App\Models\Accruals;
use App\Models\BusinessUnit;
use App\Models\Company;
use App\Models\Department;
use App\Models\Location;
use App\Models\SubUnit;
use App\Models\Supplier;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class AccrualsController extends Controller
{

    public function index(Request $request)
    {
        $rows = $request->input('rows', 10);
        $companies = $this->getRequestData($request, 'companies');
        $suppliers = $this->getRequestData($request, 'suppliers');
        $search = $request->search;
//        $adjustment_month = $request->input('adjustment_month', Carbon::now()->format('Y-m'));
//        $year = date('Y', strtotime($adjustment_month));
//        $month = date('m', strtotime($adjustment_month));
        $is_reversed = $request->input('is_reversed', 0);
        $year = $request->input('year');
        $month = $request->input('month');

        if ($year == 'all') {
            $accruals = Accruals::when($is_reversed, function ($query) {
                $query->where('is_reversed', 1);
            }, function ($query) {
                $query->whereIn('is_reversed', [0]);
            })
                ->when(!empty($suppliers), function ($query) use ($suppliers) {
                    $query->whereIn('supplier_id', $suppliers);
                })
                ->when(!empty($companies), function ($query) use ($companies) {
                    $query->whereIn('company_id', $companies);
                })
                ->select([
                'id',
                'adjustment_month',
                'supplier_id',
                'supplier_code',
                'supplier_name',
                'entry',
                'account_title_id',
                'account_title_code',
                'account_title_name',
                'company_id',
                'company_code',
                'company_name',
                'department_id',
                'department_code',
                'department_name',
                'location_id',
                'location_code',
                'location_name',
                'amount',
                'description',
                'po_no',
                'reference_no',
                'voucher_number',
                'is_reversed'
            ])->get();

            $accruals->transform(function ($item) {
                return (object) [
                    'id' => $item->id,
                    'adjustment_month' => $item->adjustment_month,
                    'supplier' => [
                        'id' => $item->supplier_id,
                        'code' => $item->supplier_code,
                        'name' => $item->supplier_name
                    ],
                    'entry' => $item->entry,
                    'account_title' => [
                        'id' => $item->account_title_id,
                        'code' => $item->account_title_code,
                        'name' => $item->account_title_name
                    ],
                    'company' => [
                        'id' => $item->company_id,
                        'code' => $item->company_code,
                        'name' => $item->company_name
                    ],
                    'department' => [
                        'id' => $item->department_id,
                        'code' => $item->department_code,
                        'name' => $item->department_name
                    ],
                    'location' => [
                        'id' => $item->location_id,
                        'code' => $item->location_code,
                        'name' => $item->location_name
                    ],
                    'amount' => $item->amount,
                    'description' => $item->description,
                    'po_no' => $item->po_no,
                    'reference_no' => $item->reference_no,
                    'voucher_number' => $item->voucher_number,
                    'is_reversed' => $item->is_reversed
                ];
            });
        } elseif(!empty($month) && !empty($year)) {
            $accruals = Accruals::select(
                'journal_name',
                'journal_description',
                'adjustment_month',
                'batch_no',
                'reversed_no',
                DB::raw("MAX(updated_at) as latest_updated_at"))
                ->when(!empty($companies), function ($query) use ($companies) {
                    $query->whereIn('division_id', $companies);
                })
//                ->where('user_id', auth()->user()->id)
                ->where(function ($query) {
                    $query->where('user_id', auth()->user()->id)
                        ->orWhere(function ($query) {
                            if (auth()->user()->position == 'SUPERVISOR' && auth()->user()->role == 'Approver') {
                                $query->where('user_id', '<>', auth()->user()->id);
                            }
                        });
                })
                ->whereLike(['journal_name', 'journal_description'], $search)
                ->groupBy(
                    'journal_name',
                    'journal_description',
                    'adjustment_month',
                    'batch_no',
                    'reversed_no'
                )
                ->orderBy('latest_updated_at', 'desc')
                ->whereLike(['journal_name', 'journal_description'], $search)
                ->get();

            $allAccountTitles = Accruals::
            when($is_reversed, function ($query) {
                $query->where('is_reversed', 1);
            }, function ($query) {
                $query->whereIn('is_reversed', [0]);
            })
                ->whereIn('batch_no', $accruals->pluck('batch_no'))
                ->get()
                ->groupBy(function ($query) use ($is_reversed) {
                    return $is_reversed ? $query->reversed_no : $query->batch_no;
                });

            $accruals->transform(function ($item) use ($allAccountTitles) {
//                $account_titles = $allAccountTitles->get($item->batch_no);
                $account_titles = $allAccountTitles->get($item->reversed_no ?? $item->batch_no);

                if ($account_titles === null) {
                    return null; // or handle the case where account_titles is null
                }

                return (object) [
                    'id' => $account_titles->first()->id,
                    'division' => [
                        'name' => $account_titles->first()->division_name
                    ],
                    'boa' => $account_titles->first()->boa,
                    'gj_number' => $item->gj_number,
                    'journal_name' => $item->journal_name,
                    'journal_description' => $item->journal_description,
                    'created_at' => $item->latest_updated_at,
                    'adjustment_month' => $account_titles->first()->adjustment_month,
                    'account_titles' => $account_titles->transform(function ($item) {
                        return (object) [
                            'id' => $item->id,
                            'po_no' => $item->po_no,
                            'reference_no' => $item->reference_no,
                            'voucher_number' => $item->voucher_number,
                            'supplier' => [
                                'name' => $item->supplier_name
                            ],
                            'entry' => $item->entry,
                            'amount' => $item->amount,
                            'account_title' => [
                                'id' => $item->account_title_id,
                                'code' => $item->account_title_code,
                                'name' => $item->account_title_name
                            ],
                            'company' => [
                                'id' => $item->company_id,
                                'code' => $item->company_code,
                                'name' => $item->company_name
                            ],
                            'department' => [
                                'id' => $item->department_id,
                                'code' => $item->department_code,
                                'name' => $item->department_name
                            ],
                            'location' => [
                                'id' => $item->location_id,
                                'code' => $item->location_code,
                                'name' => $item->location_name
                            ],
                            'business_unit' => [
                                'id' => $item->business_unit_id,
                                'code' => $item->business_unit_code,
                                'name' => $item->business_unit_name
                            ],
                            'sub_unit' => [
                                'id' => $item->sub_unit_id,
                                'code' => $item->sub_unit_code,
                                'name' => $item->sub_unit_name
                            ],
                            'remarks' => $item->description,
                            'is_reversed' => $item->is_reversed,
                            'reversed_at' => $item->reversed_at,
                        ];
                    }),
                    'reversed_no' => $item->reversed_no,
                    'reversed_at' => $account_titles->first()->reversed_at
                ];
            })->filter();
        } else {
            $accruals = Accruals::select(
                'journal_name', 'journal_description', 'adjustment_month', 'batch_no', DB::raw("MAX(updated_at) as latest_updated_at"))
                ->when($is_reversed, function ($query) {
                    $query->where('is_reversed', 1);
                }, function ($query) {
                    $query->whereIn('is_reversed', [0]);
                })
//                ->where('user_id', auth()->user()->id)
                ->where(function ($query) {
                    $query->where('user_id', auth()->user()->id)
                        ->orWhere(function ($query) {
                            if (auth()->user()->position == 'SUPERVISOR' && auth()->user()->role == 'Approver') {
                                $query->where('user_id', '<>', auth()->user()->id);
                            }
                        });
                })
                ->groupBy('journal_name', 'journal_description', 'adjustment_month', 'batch_no', 'is_reversed', 'reversed_at', 'reversed_no')
                ->orderBy('latest_updated_at', 'desc')
                ->get();
        }


        if ($year == 'all') {
//            $groupedAccruals = $accruals->groupBy(function ($item) {
//                return Carbon::parse($item->adjustment_month)->format('Y');
//            })->mapWithKeys(function ($yearGroup, $year) {
//                return [$year => $yearGroup->groupBy(function ($item) {
//                    return Carbon::parse($item->adjustment_month)->format('F');
//                })];
//            });

            $groupedAccruals = $accruals->groupBy(function ($item) {
                return Carbon::parse($item->adjustment_month)->format('Y');
            });

            return $groupedAccruals;

//            $currentPage = LengthAwarePaginator::resolveCurrentPage();
//            $currentItems = $groupedAccruals->slice(($currentPage - 1) * $rows, $rows)->values();
//            $totalItems = $groupedAccruals->count();
//
//            return new LengthAwarePaginator($currentItems, $totalItems, $rows);

        }
        if (empty($year) && empty($month)) {
            $groupedAccruals = $accruals->map(function ($item) use ($is_reversed) {
                return $is_reversed ? Carbon::parse($item->reversed_at)->format('Y')
                : Carbon::parse($item->adjustment_month)->format('Y');
            })->unique()->values();
        } elseif (!empty($year) && empty($month)) {
            $groupedAccruals = $accruals->filter(function ($item) use ($year, $is_reversed) {
                return $is_reversed ? Carbon::parse($item->reversed_at)->format('Y') == $year
                    : Carbon::parse($item->adjustment_month)->format('Y') == $year;
            })->groupBy(function ($item) use ($is_reversed) {
                return $is_reversed ? Carbon::parse($item->reversed_at)->format('F')
                : Carbon::parse($item->adjustment_month)->format('F');
            });
        } elseif (!empty($year) && !empty($month)) {
            $groupedAccruals = $accruals->filter(function ($item) use ($year, $month, $is_reversed) {
//                return $item !== null &&
//                    Carbon::parse($item->adjustment_month)->format('Y') == $year &&
//                    Carbon::parse($item->adjustment_month)->format('m') == str_pad($month, 2, '0', STR_PAD_LEFT);

                return $is_reversed ?
                    $item !== null &&
                    Carbon::parse($item->reversed_at)->format('Y') == $year &&
                    Carbon::parse($item->reversed_at)->format('m') == str_pad($month, 2, '0', STR_PAD_LEFT)
                    : $item !== null &&
                    Carbon::parse($item->adjustment_month)->format('Y') == $year &&
                    Carbon::parse($item->adjustment_month)->format('m') == str_pad($month, 2, '0', STR_PAD_LEFT);
            })->values();

            $currentPage = LengthAwarePaginator::resolveCurrentPage();
            $currentItems = $groupedAccruals->slice(($currentPage - 1) * $rows, $rows)->values();
            $totalItems = $groupedAccruals->count();

            return new LengthAwarePaginator($currentItems, $totalItems, $rows);
        } else {
            $groupedAccruals = $accruals->groupBy(function ($item) use ($is_reversed){
                return $is_reversed ? Carbon::parse($item->reversed_at)->format('Y')
                    : Carbon::parse($item->adjustment_month)->format('Y');
            })->map(function ($yearGroup) use ($is_reversed) {
                return $yearGroup->groupBy(function ($item) use ($is_reversed) {
                    return $is_reversed ? Carbon::parse($item->reversed_at)->format('F')
                        : Carbon::parse($item->adjustment_month)->format('F');
                });
            });
        }

        if ($accruals->isEmpty()) {
            return $this->resultResponse("not-found", "General Journals", []);
        }
        return $groupedAccruals;
    }


    public function store(Request $request)
    {
        $journal_name = data_get($request, "journal.name");
        $journal_description = data_get($request, "journal.description");
        $boa = $request->boa;
        $division_id = data_get($request, "division.id");
        $division_name = data_get($request, "division.name");
        $adjust_month = data_get($request, "adjustment_month");
        $account_titles = $request->account_titles;

        $batch_no = $this->generateGJBatchNo(Accruals::class);

        foreach ($account_titles as $account_title) {
            Accruals::create([
                'adjustment_month' => $adjust_month,
                'division_id' => $division_id,
                'division_name' => $division_name,
                'transaction_date' => Carbon::now(),
                'tag_no' => data_get($account_title, "tag_no"),
                'po_no' => data_get($account_title, "po_no"),
                'reference_no' => data_get($account_title, "reference_no"),
                'voucher_number' => data_get($account_title, "voucher_number"),
                'supplier_id' => data_get($account_title, "supplier.id"),
                'supplier_code' => data_get($account_title, "supplier.code"),
                'supplier_name' => data_get($account_title, "supplier.name"),
                'entry' => data_get($account_title, "entry"),
                'account_title_id' => data_get($account_title, "account_title.id"),
                'account_title_code' => data_get($account_title, "account_title.code"),
                'account_title_name' => data_get($account_title, "account_title.name"),
                'company_id' => data_get($account_title, "company.id"),
                'company_code' => data_get($account_title, "company.code"),
                'company_name' => data_get($account_title, "company.name"),
                'department_id' => data_get($account_title, "department.id"),
                'department_code' => data_get($account_title, "department.code"),
                'department_name' => data_get($account_title, "department.name"),
                'location_id' => data_get($account_title, "location.id"),
                'location_code' => data_get($account_title, "location.code"),
                'location_name' => data_get($account_title, "location.name"),
                'business_unit_id' => data_get($account_title, "business_unit.id"),
                'business_unit_code' => data_get($account_title, "business_unit.code"),
                'business_unit_name' => data_get($account_title, "business_unit.name"),
                'sub_unit_id' => data_get($account_title, "sub_unit.id"),
                'sub_unit_code' => data_get($account_title, "sub_unit.code"),
                'sub_unit_name' => data_get($account_title, "sub_unit.name"),
                'amount' => data_get($account_title, "amount"),
                'description' => data_get($account_title, "remarks"),

                'boa' => $boa,
                'user_id' => auth()->id(),
                'journal_name' => $journal_name,
                'journal_description' => $journal_description,
                'batch_no' => $batch_no
            ]);
        }

        return response()->json(['message' => 'Accrual Journal successfully created.'], 201);
    }



    public function update(Request $request, $id)
    {
        $accruals = Accruals::find($id);

        if ($accruals) {
            Accruals::where('batch_no', $accruals->batch_no)->forceDelete();
        }

        return $this->store($request);
    }

    public function destroy($id)
    {
        Accruals::where('batch_no', Accruals::find($id)->batch_no)->delete();

        return response()->json(['message' => 'General Journal successfully deleted.'], 200);
    }


    public function reverse(Request $request)
    {
        $request = $request->validate([
            'accruals' => 'required|array',
        ]);

        $reversed_no = Accruals::max('reversed_no') + 1;

        Accruals::whereIn('id', $request['accruals'])->update([
            'entry' => DB::raw("IF(entry = 'Credit', 'Debit', 'Credit')"),
            'is_reversed' => true,
            'reversed_at' => Carbon::now(),
            'reversed_no' => $reversed_no
        ]);

        return response()->json(['message' => 'Accrual successfully reversed.'], 200);
    }

    public function import(Request $request) {

        $journals = $request->all();
        $error = [];
        $test = [];
        $account_title_list = AccountTitle::withTrashed()->pluck('title')->toArray();
        $company_list = Company::withTrashed()->pluck('company')->toArray();
        $department_list = Department::withTrashed()->pluck('department')->toArray();
        $location_list = Location::withTrashed()->pluck('location')->toArray();
        $business_unit_list = BusinessUnit::withTrashed()->pluck('business_unit')->toArray();
        $sub_unit_list = SubUnit::withTrashed()->pluck('subunit')->toArray();

        $headers = "Account Tag, PO#, Reference No, Voucher Number, Supplier, DR/CR, Amount, Description, Account Title, Company, Department, Location, BOA";
        $template = ["tag_no", "po_no", "reference_no", "voucher_number", "supplier", "entry", "amount", "remarks", "account_title", "company", "department", "location", "boa"];
        $required = ["supplier", "entry", "amount", "account_title", "company", "department", "location"];
        $keys = array_keys(current($journals));
//        $this->validateHeader($template, $keys, $headers);
//
//        $index = 2;
//        foreach ($journals as $journal) {
//            $account_title = $journal['account_title'];
//            $company = $journal['company'];
//            $department = $journal['department'];
//            $location = $journal['location'];
////            $business_unit = $journal['business_unit'];
////            $sub_unit = $journal['sub_unit'];
//            $boa = $journal['boa'];
//
//            if (!in_array($account_title, $account_title_list) && !empty($account_title)) {
//                $error[] = (object)[
//                    "line" => $index,
//                    "description" => $account_title . " is not registered.",
//                ];
//            }
//
//            if (!in_array($department, $department_list) && !empty($department)) {
//                $error[] = (object)[
//                    "line" => $index,
//                    "description" => $department . " is not registered.",
//                ];
//            }
//
//            if (!in_array($location, $location_list) && !empty($location)) {
//                $error[] = (object)[
//                    "line" => $index,
//                    "description" => $location . " is not registered.",
//                ];
//            }
//
//            if (!in_array($company, $company_list) && !empty($company)) {
//                $error[] = (object)[
//                    "line" => $index,
//                    "description" => $company . " is not registered.",
//                ];
//            }
//
//            if ($boa != 'Accruals' || null) {
//                $error[] = (object)[
//                    "line" => $index,
//                    "description" => "BOA must be Accruals.",
//                ];
//            }
//
//            foreach ($journal as $key => $value) {
//                if (in_array($key, $required) && empty($value)) {
//                    $error[] = (object)[
//                        "error_type" => "empty",
//                        "line" => $index,
//                        "description" => $key . " is empty.",
//                    ];
//                }
//            }
//
//            $index++;
//        }

        if (isset($journals)) {
            foreach ($journals as $journal) {
                $supplier = Supplier::where('name', $journal['supplier'])->first();
                $account_title = AccountTitle::where('title', $journal['account_title'])->first();
                $company = Company::where('company', $journal['company'])->first();
                $department = Department::where('department', $journal['department'])->first();
                $location = Location::where('location', $journal['location'])->first();
                $business_unit = BusinessUnit::where('business_unit', $journal['business_unit'])->first() ?? null;
                $sub_unit = SubUnit::where('subunit', $journal['sub_unit'])->first() ?? null;

                $formattedJournal[] = [
                    'account_tag' => $journal['tag_no'],
                    'po_no' => $journal['po_no'],
                    'reference_no' => $journal['reference_no'],
                    'voucher_number' => $journal['voucher_number'],
                    'supplier' => [
                        'id' => $supplier->id,
                        'code' => $supplier->code,
                        'name' => $supplier->name
                    ],
                    'entry' => $journal['entry'],
                    'amount' => $journal['entry'] == 'Credit' ? abs($journal['amount']) : $journal['amount'],
                    'remarks' => $journal['remarks'],
                    'account_title' => [
                        'id' => $account_title->id,
                        'code' => $account_title->code,
                        'name' => $account_title->title
                    ],
                    'company' => [
                        'id' => $company->id,
                        'code' => $company->code,
                        'name' => $company->company
                    ],
                    'department' => [
                        'id' => $department->id,
                        'code' => $department->code,
                        'name' => $department->department
                    ],
                    'location' => [
                        'id' => $location->id,
                        'code' => $location->code,
                        'name' => $location->location
                    ],
                    'business_unit' => [
                        'id' => $business_unit->id ?? null,
                        'code' => $business_unit->code ?? null,
                        'name' => $business_unit->business_unit ?? null
                    ],
                    'sub_unit' => [
                        'id' => $sub_unit->id ?? null,
                        'code' => $sub_unit->code ?? null,
                        'name' => $sub_unit->subunit ?? null
                    ],
                    'boa' => $journal['boa']
                ];
            }

            return $formattedJournal;

        } else {
            return response()->json(['error' => $error], 400);
        }
    }

}
