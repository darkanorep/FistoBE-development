<?php

namespace App\Http\Controllers;

use App\Http\Requests\GeneralJournalRequest;
use App\Models\AccountTitle;
use App\Models\BusinessUnit;
use App\Models\Company;
use App\Models\Department;
use App\Models\GeneralJournal;
use App\Models\Location;
use App\Models\SubUnit;
use App\Models\Supplier;
use App\Models\Transaction;
use Carbon\Carbon;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class GeneralJournalController extends Controller
{

    public function index(Request $request)
    {
        $rows = $request->input('rows', 10);
        $companies = $this->getRequestData($request, 'companies');
        $is_posted = $request->input('is_posted', 0);
        $search = $request->search;
//        $adjustment_month = $request->input('adjustment_month');
//        $year = date('Y', strtotime($adjustment_month));
//        $month = date('m', strtotime($adjustment_month));
        $year = $request->input('year');
        $month = $request->input('month');



        if (!empty($month) && !empty($year)) {
             $generalJournals = GeneralJournal::select([
                 'gj_number',
                 'journal_name',
                 'journal_description',
                 'is_posted',
                 'adjustment_month',
                 DB::raw("MAX(updated_at) as latest_updated_at")
             ])
//            ->whereBetween('created_at', [$transactionFrom, $transactionTo])
                ->when($is_posted == 1, function ($query) {
                    $query->where('is_posted', true);
                }, function ($query) {
                    $query->where('is_posted', false);
                })
//            ->when(!empty($companies), function ($query) use ($companies) {
//                $query->whereIn('division_id', $companies);
//            })
//            ->when(isset($adjustment_month), function ($query) use ($year, $month) {
//                $query->whereYear('adjustment_month', $year)
//                    ->whereMonth('adjustment_month', $month);
//            })
//                ->where('user_id', auth()->user()->id)
                     ->where(function ($query) {
                     $query->where('user_id', auth()->user()->id)
                         ->orWhere(function ($query) {
                             if (auth()->user()->position == 'SUPERVISOR' && auth()->user()->role == 'Approver') {
                                 $query->where('user_id', '<>', auth()->user()->id);
                             }
                         });
                 })
                ->groupBy('gj_number', 'journal_name', 'journal_description', 'is_posted', 'adjustment_month')
                ->orderBy('latest_updated_at', 'desc')
                ->whereLike(['gj_number', 'journal_name', 'journal_description'], $search)
                 ->get();
//                 ->paginate($rows);

            $allAccountTitles = GeneralJournal::whereIn('gj_number', $generalJournals->pluck('gj_number')->toArray())
                ->get()
                ->groupBy('gj_number');


            $generalJournals->transform(function ($item) use ($allAccountTitles) {
//            $account_titles = $allAccountTitles[$item->gj_number];
                $account_titles = $allAccountTitles->get($item->gj_number);

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
                            'po_no' => $item->po_no ? array($item->po_no) : [],
                            'rr_no' => $item->rr_no ? array($item->rr_no) : [],
                            'tag_no' => $item->tag_no,
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
                        ];
                    }),
                    'is_posted' => $item->is_posted,
                    'posted_at' => $item->posted_at
                ];
            });
        } else {
            $generalJournals = GeneralJournal::select(['gj_number', 'journal_name', 'journal_description', 'is_posted', 'adjustment_month', DB::raw("MAX(updated_at) as latest_updated_at")])
                ->when($is_posted == 1, function ($query) {
                    $query->where('is_posted', true);
                }, function ($query) {
                    $query->where('is_posted', false);
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
                ->groupBy('gj_number', 'journal_name', 'journal_description', 'is_posted', 'adjustment_month')
                ->orderBy('latest_updated_at', 'desc')
                ->get();
        }

        if (empty($year) && empty($month)) {
            $groupedGeneralJournals = $generalJournals->map(function ($item) use ($is_posted){
//                return Carbon::parse($item->adjustment_month)->format('Y');
                return $is_posted ? Carbon::parse($item->posted_at)->format('Y') : Carbon::parse($item->adjustment_month)->format('Y');
            })->unique()->values();
        } elseif (!empty($year) && empty($month)) {
            $groupedGeneralJournals = $generalJournals->filter(function ($item) use ($year, $is_posted) {
                return $is_posted ? Carbon::parse($item->posted_at)->format('Y') == $year : Carbon::parse($item->adjustment_month)->format('Y') == $year;
            })->groupBy(function ($item) use ($is_posted) {
                return $is_posted ? Carbon::parse($item->posted_at)->format('F') : Carbon::parse($item->adjustment_month)->format('F');
            });
        } elseif (!empty($year) && !empty($month)) {
            $groupedGeneralJournals = $generalJournals->filter(function ($item) use ($year, $month, $is_posted) {
//                return Carbon::parse($item->posted_at)->format('Y') == $year &&
//                    Carbon::parse($item->posted_at)->format('m') == str_pad($month, 2, '0', STR_PAD_LEFT);

                return $is_posted ?
                    Carbon::parse($item->posted_at)->format('Y') == $year &&
                    Carbon::parse($item->posted_at)->format('m') == str_pad($month, 2, '0', STR_PAD_LEFT)
                    :  Carbon::parse($item->adjustment_month)->format('Y') == $year &&
                    Carbon::parse($item->adjustment_month)->format('m') == str_pad($month, 2, '0', STR_PAD_LEFT);
            });

            $currentPage = LengthAwarePaginator::resolveCurrentPage();
            $currentItems = $groupedGeneralJournals->slice(($currentPage - 1) * $rows, $rows)->values();
            $totalItems = $groupedGeneralJournals->count();

            return new LengthAwarePaginator($currentItems, $totalItems, $rows);

        } else {
            $groupedGeneralJournals = $generalJournals->groupBy(function ($item) use ($is_posted) {
                return $is_posted ? Carbon::parse($item->posted_at)->format('Y') : Carbon::parse($item->adjustment_month)->format('Y');
            })->map(function ($yearGroup) use ($is_posted){
                return $yearGroup->groupBy(function ($item) use ($is_posted) {
                    return $is_posted ? Carbon::parse($item->posted_at)->format('F') : Carbon::parse($item->adjustment_month)->format('F');
                });
            });
        }

//        if ($generalJournals->isEmpty()) {
//            return $this->resultResponse("not-found", "General Journals", []);
//        }

        return $groupedGeneralJournals;

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
        $department_id = null;

        foreach($account_titles as $account_title) {
            if(data_get($account_title, "department.id")) {
                $department_id = $account_title['department']['id'];
                break;
            }
        }

        $gj_number = $this->generateGeneralNumber($department_id);
        $batch_no = $this->generateGJBatchNo(GeneralJournal::class);

        foreach($account_titles as $account_title) {
            GeneralJournal::create([
                'adjustment_month' => $adjust_month,
                'division_id' => $division_id,
                'division_name' => $division_name,
                'tag_no' => data_get($account_title, "tag_no"),
                'description' => data_get($account_title, "remarks"),
//                'po_no' => data_get($account_title, "po_no"),
//                'rr_no' => data_get($account_title, "rr_no"),
                'po_no' => implode(', ', data_get($account_title, "po_no", [])),
                'rr_no' => implode(', ', data_get($account_title, "rr_no", [])),
                'reference_no' => data_get($account_title, "reference_no"),
                'voucher_number' => data_get($account_title, "voucher_number"),
                'transaction_date' => Carbon::now(),
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

                'boa' => $boa,
                'user_id' => auth()->user()->id,
                'journal_name' => $journal_name,
                'journal_description' => $journal_description,
                'gj_number' => $gj_number,
                'batch_no' => $batch_no
            ]);
        }

        return response()->json(['message' => 'General Journal successfully created.'], 201);
    }

    public function show($id)
    {
        //
    }


    public function update($id, Request $request)
    {
        $generalJournal = GeneralJournal::find($id);

        if ($generalJournal) {
            GeneralJournal::where('batch_no', $generalJournal->batch_no)->forceDelete();
        }

        return $this->store($request);


//        $generalJournal = GeneralJournal::find($id);
//
//        if (!$generalJournal) {
//            return response()->json(['message' => 'General Journal not found.'], 404);
//        }
//
//        $ids = GeneralJournal::where('gj_number', $generalJournal->gj_number)->pluck('id')->toArray();
//
//        foreach ($ids as $id) {
//            $gj = GeneralJournal::find($id);
//
//            if ($gj->entry == 'Credit') {
//                $gj->update([
//                    'entry' => 'Debit',
//                    'is_reversed' => true,
//                    'reversed_at' => Carbon::now()->addMonth(1)
//                ]);
//            } else {
//                $gj->update([
//                    'entry' => 'Credit',
//                    'is_reversed' => true,
//                    'reversed_at' => Carbon::now()->addMonth(1)
//                ]);
//            }
//        }
//
//        return response()->json(['message' => 'Entry updated successfully.']);
    }

    public function destroy($id)
    {
        GeneralJournal::where('batch_no', GeneralJournal::find($id)->batch_no)->delete();

        return response()->json(['message' => 'General Journal successfully deleted.'], 200);

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
        $this->validateHeader($template, $keys, $headers);

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
//            if (!in_array($location, $location_list) && !empty($location)){
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
//            if ($boa != 'Adjustment') {
//                $error[] = (object)[
//                    "line" => $index,
//                    "description" => "BOA must be Adjustment.",
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
                    'rr_no' => $journal['rr_no'],
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


    public function posted($id)
    {
        $generalJournal = GeneralJournal::find($id);

        if ($generalJournal) {
            GeneralJournal::where('batch_no', $generalJournal->batch_no)
                ->update([
                    'is_posted' => true,
                    'posted_at' => Carbon::now()->format('Y-m-d')
                ]);
        }

        return response()->json(
            ['message' => $generalJournal->gj_number . ' successfully posted.']
            , 200);
    }

}
