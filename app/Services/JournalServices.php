<?php

namespace App\Services;

use App\Exceptions\FistoException;
use App\Exceptions\FistoLaravelException;
use App\Http\Controllers\Controller;
use App\Models\AccountTitle;
use App\Models\BusinessUnit;
use App\Models\Company;
use App\Models\Department;
use App\Models\Location;
use App\Models\SubUnit;
use App\Models\Supplier;
use App\Models\Unit;
use Carbon\Carbon;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class JournalServices
{
    private $model;
    private $accountTitle;
    private $supplier;
    private $company;
    private $businessUnit;
    private $unit;
    private $subUnit;
    private $department;
    private $location;
    private $controller;

    public function __construct($model)
    {
        $this->model = $model;
        $this->accountTitle = AccountTitle::select('id', 'code', 'title')->withTrashed()->get();
        $this->supplier = Supplier::select('id', 'code', 'name')->withTrashed()->get();
        $this->company = Company::select('id', 'code', 'company')->withTrashed()->get();
        $this->businessUnit = BusinessUnit::select('id', 'code', 'business_unit')->withTrashed()->get();
        $this->unit = Unit::select('id', 'code', 'name')->withTrashed()->get();
        $this->subUnit = SubUnit::select('id', 'code', 'name')->withTrashed()->get();
        $this->department = Department::select('id', 'code', 'department')->withTrashed()->get();
        $this->location = Location::select('id', 'code', 'location')->withTrashed()->get();
        $this->supplier = Supplier::select('id', 'code', 'name')->withTrashed()->get();
        $this->controller = new Controller();
    }

    public function index(Request $request)
    {
        $rows = $request->input('rows', 10);
//        $companies = $this->getRequestData($request, 'companies');
        $companies = $this->controller->getRequestData($request, 'companies');
        $is_posted = $request->input('is_posted', 0);
        $is_year_end = $request->input('is_year_end', 0);
        $search = $request->search;
        $status = $request->input('status', 'pending');
//        $adjustment_month = $request->input('adjustment_month');
//        $year = date('Y', strtotime($adjustment_month));
//        $month = date('m', strtotime($adjustment_month));
        $year = $request->input('year');
        $month = $request->input('month');

        $entryEnabled = DB::table('settings')->where('key', 'entry_enabled')->first();

        if (!empty($month) && !empty($year)) {
            $generalJournals = $this->model::select([
                'gj_number',
                'journal_name',
                'journal_description',
                'is_posted',
                'adjustment_month',
                'is_year_end',
                'is_approved',
                'reason_id',
                'reason',
                DB::raw("MAX(updated_at) as latest_updated_at")
            ])
//            ->whereBetween('created_at', [$transactionFrom, $transactionTo])
                ->when($status == 'pending', function ($query) {
                    $query->whereNull('is_approved');
                })
                ->when($status == 'approved', function ($query) {
                    $query->where('is_approved', true);
                })
                ->when($status == 'rejected', function ($query) {
                    $query->where('is_approved', false);
                })
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
                ->groupBy('gj_number', 'journal_name', 'journal_description', 'is_posted', 'adjustment_month', 'is_year_end', 'is_approved', 'reason_id', 'reason')
                ->orderBy('latest_updated_at', 'desc')
                ->whereLike(['gj_number', 'journal_name', 'journal_description'], $search)
                ->get();
//                 ->paginate($rows);

            $allAccountTitles = $this->model::whereIn('gj_number', $generalJournals->pluck('gj_number')->toArray())
                ->get()
                ->groupBy('gj_number');


            $generalJournals->transform(function ($item) use ($allAccountTitles, $generalJournals) {
//            $account_titles = $allAccountTitles[$item->gj_number];
                $account_titles = $allAccountTitles->get($item->gj_number);

                return (object) [
                    'id' => $account_titles->last()->id,
                    'division' => [
                        'id' => $account_titles->first()->division_id,
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
                            'unit' => [
                                'id' => $item->unit_id,
                                'code' => $item->unit_code,
                                'name' => $item->unit_name
                            ],
                            'sub_unit' => [
                                'id' => $item->sub_unit_id,
                                'code' => $item->sub_unit_code,
                                'name' => $item->sub_unit_name
                            ],
                            'remarks' => $item->remarks,
                            'description' => $item->description,
                            'transaction_date' => $item->transaction_date,
                            'attachments' => $item->media->map(function ($media) {
                                return [
                                    'file_name' => $media->file_name,
                                    'base64' => 'data:' . $media->mime_type . ';base64,' . base64_encode(file_get_contents($media->getPath()))
//                                    'base64' => base64_encode(file_get_contents($media->getPath()))
                                ];
                            }),
                        ];
                    }),
                    'is_posted' => $item->is_posted,
                    'posted_at' => $item->posted_at,
                    'is_year_end' => $item->is_year_end,
                    'attachments' => collect($account_titles)->last()->attachments,
                    'is_approved' => $item->is_approved,
                    'reason_id' => $item->reason_id,
                    'reason' => $item->reason,
                ];
            });
        } else {
            $generalJournals = $this->model::select(['gj_number', 'journal_name', 'journal_description', 'is_posted', 'adjustment_month', 'is_year_end', 'is_approved', 'reason_id', 'reason', DB::raw("MAX(updated_at) as latest_updated_at")])
                ->when($is_posted == 1, function ($query) {
                    $query->where('is_posted', true);
                }, function ($query) {
                    $query->where('is_posted', false);
                })
                ->when($status == 'pending', function ($query) {
                    $query->whereNull('is_approved');
                })
                ->when($status == 'approved', function ($query) {
                    $query->where('is_approved', true);
                })
                ->when($status == 'rejected', function ($query) {
                    $query->where('is_approved', false);
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
                ->groupBy('gj_number', 'journal_name', 'journal_description', 'is_posted', 'adjustment_month', 'is_year_end', 'is_approved', 'reason_id', 'reason')
                ->orderBy('latest_updated_at', 'desc')
                ->get();
        }

        if (empty($year) && empty($month)) {
            $groupedGeneralJournals = $generalJournals->map(function ($item) use ($is_posted){
                return $is_posted ? Carbon::parse($item->posted_at)->format('Y') : Carbon::parse($item->adjustment_month)->format('Y');
            })->unique()->values();
        } elseif (!empty($year) && empty($month)) {
            $groupedGeneralJournals = $generalJournals->filter(function ($item) use ($year, $is_posted) {
                return $is_posted
                    ? Carbon::parse($item->posted_at)->format('Y') == $year && $item->is_year_end == 0
                    : Carbon::parse($item->adjustment_month)->format('Y') == $year && $item->is_year_end == 0;
            })->groupBy(function ($item) use ($is_posted) {
                return $is_posted
                    ? Carbon::parse($item->posted_at)->format('F')
                    : Carbon::parse($item->adjustment_month)->format('F');
            })->toArray();

            $groupedGeneralJournalsYearEnd = $generalJournals->filter(function ($item) use ($year, $is_posted) {
                return $is_posted
                    ? Carbon::parse($item->posted_at)->format('Y') == $year && $item->is_year_end == 1
                    : Carbon::parse($item->adjustment_month)->format('Y') == $year && $item->is_year_end == 1;
            })->groupBy(function ($item) use ($is_posted) {
                return 'Year End ' . ($is_posted
                        ? Carbon::parse($item->posted_at)->format('F')
                        : Carbon::parse($item->adjustment_month)->format('F'));
            })->toArray();


            $groupedGeneralJournals = array_merge($groupedGeneralJournals, $groupedGeneralJournalsYearEnd);


        } elseif (!empty($year) && !empty($month)) {
//            $groupedGeneralJournals = $generalJournals->filter(function ($item) use ($year, $month, $is_posted, $is_year_end) {
////                return Carbon::parse($item->posted_at)->format('Y') == $year &&
////                    Carbon::parse($item->posted_at)->format('m') == str_pad($month, 2, '0', STR_PAD_LEFT);
//
//                return $is_posted ?
//                    Carbon::parse($item->posted_at)->format('Y') == $year &&
//                    Carbon::parse($item->posted_at)->format('m') == str_pad($month, 2, '0', STR_PAD_LEFT)
//                    :  Carbon::parse($item->adjustment_month)->format('Y') == $year &&
//                    Carbon::parse($item->adjustment_month)->format('m') == str_pad($month, 2, '0', STR_PAD_LEFT);
//            });

            $groupedGeneralJournals = $generalJournals->filter(function ($item) use ($year, $month, $is_posted, $is_year_end) {
                if ($is_year_end) {
                    return $is_posted
                        ? Carbon::parse($item->posted_at)->format('Y') == $year &&
                        Carbon::parse($item->posted_at)->format('m') == str_pad($month, 2, '0', STR_PAD_LEFT) &&
                        $item->is_year_end == 1
                        : Carbon::parse($item->adjustment_month)->format('Y') == $year &&
                        Carbon::parse($item->adjustment_month)->format('m') == str_pad($month, 2, '0', STR_PAD_LEFT) &&
                        $item->is_year_end == 1;
                } else {
                    return $is_posted
                        ? Carbon::parse($item->posted_at)->format('Y') == $year &&
                        Carbon::parse($item->posted_at)->format('m') == str_pad($month, 2, '0', STR_PAD_LEFT) &&
                        $item->is_year_end == 0
                        : Carbon::parse($item->adjustment_month)->format('Y') == $year &&
                        Carbon::parse($item->adjustment_month)->format('m') == str_pad($month, 2, '0', STR_PAD_LEFT) &&
                        $item->is_year_end == 0;
                }
            });

            $currentPage = LengthAwarePaginator::resolveCurrentPage();
            $currentItems = $groupedGeneralJournals->slice(($currentPage - 1) * $rows, $rows)->values();
            $totalItems = $groupedGeneralJournals->count();

            return new LengthAwarePaginator($currentItems, $totalItems, $rows);

        } else {
            $groupedGeneralJournals = $generalJournals->groupBy(function ($item) use ($is_posted) {
                return $is_posted
                    ? Carbon::parse($item->posted_at)->format('Y')
                    : Carbon::parse($item->adjustment_month)->format('Y');
            })->map(function ($yearGroup) use ($is_posted){
                return $yearGroup->groupBy(function ($item) use ($is_posted) {
                    return $is_posted
                        ? Carbon::parse($item->posted_at)->format('F')
                        : Carbon::parse($item->adjustment_month)->format('F');
                });
            });
        }

        return $groupedGeneralJournals;

    }

    public function indexForApproval(Request $request)
    {
        $search = $request->search;
        $status = $request->input('status', 'pending');
        $rows = $request->input('rows', 10);
        $generalJournals = $this->model::where('approver_id', auth()->user()->id)
            ->where('is_posted', false)
            ->when($status == 'pending', function ($query) {
                $query->whereNull('is_approved');
            })
            ->when($status == 'approved', function ($query) {
                $query->where('is_approved', true);
            })
            ->when($status == 'rejected', function ($query) {
                $query->where('is_approved', false);
            })
            ->select([
                'gj_number',
                'journal_name',
                'journal_description',
                'is_posted',
                'adjustment_month',
                'is_year_end',
                'is_approved',
                'reason_id',
                'reason',
                DB::raw("MAX(updated_at) as latest_updated_at")
            ])->groupBy('gj_number', 'journal_name', 'journal_description', 'is_posted', 'adjustment_month', 'is_year_end', 'is_approved', 'reason_id', 'reason')
            ->orderBy('latest_updated_at', 'desc')
            ->whereLike(['gj_number', 'journal_name', 'journal_description'], $search)
            ->get();

        $allAccountTitles = $this->model::whereIn('gj_number', $generalJournals->pluck('gj_number')->toArray())
            ->get()
            ->groupBy('gj_number');


        $generalJournals->transform(function ($item) use ($allAccountTitles, $generalJournals) {
            $account_titles = $allAccountTitles->get($item->gj_number);

            return (object)[
                'id' => $account_titles->last()->id,
                'division' => [
                    'id' => $account_titles->first()->division_id,
                    'name' => $account_titles->first()->division_name
                ],
                'boa' => $account_titles->first()->boa,
                'gj_number' => $item->gj_number,
                'journal_name' => $item->journal_name,
                'journal_description' => $item->journal_description,
                'created_at' => $item->latest_updated_at,
                'adjustment_month' => $account_titles->first()->adjustment_month,
                'account_titles' => $account_titles->transform(function ($item) {
                    return (object)[
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
                        'unit' => [
                            'id' => $item->unit_id,
                            'code' => $item->unit_code,
                            'name' => $item->unit_name
                        ],
                        'sub_unit' => [
                            'id' => $item->sub_unit_id,
                            'code' => $item->sub_unit_code,
                            'name' => $item->sub_unit_name
                        ],
                        'remarks' => $item->description,
                        'transaction_date' => $item->transaction_date,
//                        'attachments' => $item->media->map(function ($media) {
//                            return [
//                                'file_name' => $media->file_name,
//                                'base64' => 'data:' . $media->mime_type . ';base64,' . base64_encode(file_get_contents($media->getPath()))
////                                    'base64' => base64_encode(file_get_contents($media->getPath()))
//                            ];
//                        }),
                    ];
                }),
                'is_posted' => $item->is_posted,
                'posted_at' => $item->posted_at,
                'is_year_end' => $item->is_year_end,
                'is_approved' => $item->is_approved,
                'reason_id' => $item->reason_id,
                'reason' => $item->reason,
//                'attachments' => collect($account_titles)->last()->attachments
            ];
        });

        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $currentItems = $generalJournals->slice(($currentPage - 1) * $rows, $rows)->values();
        $totalItems = $generalJournals->count();

        return new LengthAwarePaginator($currentItems, $totalItems, $rows);
    }

    public function store(array $data, $id = null)
    {
        $approver_id =  auth()->user()->journalUser()->first()->approver_id ?? null;
        $journal_name = data_get($data, "journal.name");
        $journal_description = data_get($data, "journal.description");
        $boa = data_get($data, "boa");
        $division_id = data_get($data, "division.id");
        $division_name = data_get($data, "division.name");
        $adjust_month = data_get($data, "adjustment_month");
        $isYearEnd = data_get($data, "is_year_end", 0);
        $account_titles = data_get($data, "account_titles");
        $department_id = null;

        foreach($account_titles as $account_title) {
            if(data_get($account_title, "department.id")) {
                $department_id = $account_title['department']['id'];
                break;
            }
        }

        $gj_number = $this->controller->generateGeneralNumber($department_id, $this->model);
        $batch_no = $this->controller->generateGJBatchNo($this->model);

        $chunkSize = 500; // adjust this depending on memory and DB capacity
        $rows = [];

        foreach ($account_titles as $account_title) {
            $rows[] = [
                'adjustment_month' => $adjust_month,
                'is_year_end' => $isYearEnd,
                'division_id' => $division_id,
                'division_name' => $division_name,
                'approver_id' => $approver_id,
                'tag_no' => data_get($account_title, "tag_no"),
                'remarks' => data_get($account_title, "remarks"),
                'description' => data_get($account_title, "description"),
                'po_no' => is_array(data_get($account_title, "po_no"))
                    ? implode(', ', data_get($account_title, "po_no", []))
                    : data_get($account_title, "po_no"),
                'rr_no' => is_array(data_get($account_title, "rr_no"))
                    ? implode(', ', data_get($account_title, "rr_no", []))
                    : data_get($account_title, "rr_no"),
                'reference_no' => data_get($account_title, "reference_no"),
                'voucher_number' => data_get($account_title, "voucher_number"),
                'transaction_date' => data_get($account_title, "transaction_date"),
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
                'unit_id' => data_get($account_title, "unit.id"),
                'unit_code' => data_get($account_title, "unit.code"),
                'unit_name' => data_get($account_title, "unit.name"),
                'sub_unit_id' => data_get($account_title, "sub_unit.id"),
                'sub_unit_code' => data_get($account_title, "sub_unit.code"),
                'sub_unit_name' => data_get($account_title, "sub_unit.name"),
                'amount' => data_get($account_title, "amount"),

                'boa' => $boa,
                'user_id' => auth()->id(),
                'journal_name' => $journal_name,
                'journal_description' => $journal_description,
                'gj_number' => $gj_number,
                'batch_no' => $batch_no,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (count($rows) >= $chunkSize) {
                $this->model::insert($rows);
                $rows = []; // reset for next chunk
            }
        }

// insert any remaining rows
        if (!empty($rows)) {
            $this->model::insert($rows);
        }


//        if ($request->hasFile('attachments')) {
//            $adjustments->addMultipleMediaFromRequest(['attachments'])
//                ->each(function ($fileAdder) use ($gj_number) {
//                    $fileAdder->toMediaCollection('attachments')
//                        ->update(['gj_number' => $gj_number]);
//                });
//        }
        if ($id) {
            DB::table('media')->where('model_type', $this->model)
                ->where('model_id', $this->model->id)
                ->update(['model_id' => $id]);

            if (data_get($data, "attachments")) {
                $this->model->addMultipleMediaFromRequest(['attachments'])
                    ->each(function ($fileAdder) use ($gj_number) {
                        $fileAdder->toMediaCollection('attachments')
                            ->update(['gj_number' => $gj_number]);
                    });
            }

        } else {
            if (data_get($data, "attachments")) {
                $this->model->addMultipleMediaFromRequest(['attachments'])
                    ->each(function ($fileAdder) use ($gj_number) {
                        $fileAdder->toMediaCollection('attachments')
                            ->update(['gj_number' => $gj_number]);
                    });
            }
        }
        return response()->json(['message' => 'General Journal successfully created.'], 201);

    }

    public function updateGeneralJournal($id, $request)
    {
        $generalJournal = $this->model::find($id);

        if ($generalJournal) {
            $this->model::where('batch_no', $generalJournal->batch_no)->forceDelete();
//            $generalJournal->media()->delete();
        }

        return $this->store($request, $id);
    }

    public function action($id) {

        $process = request()->input('process');
        $reason_id = request()->input('reason_id', null);
        $reason = request()->input('reason', null);
        $generalJournals = $this->model::find($id);

        if ($generalJournals) {
            switch($process) {
                case 'approve':
                    $this->model::where('gj_number', $generalJournals->gj_number)
                        ->update([
                            'is_approved' => true
                        ]);
                    break;
                case 'reject':
                    $this->model::where('gj_number', $generalJournals->gj_number)
                        ->update([
                            'is_approved' => false,
                            'reason_id' => $reason_id,
                            'reason' => $reason
                        ]);
                    break;
            }

            $generalJournals->journals()->create([
                'status' => $process,
                'user_id' => auth()->user()->id,
                'reason_id' => $reason_id,
                'reason' => $reason
            ]);

            return response()->json(
                ['message' => $generalJournals->gj_number . ' successfully ' . $process . 'd.']
                , 200);
        }
    }

    public function destroy($id)
    {
        $this->model::where('batch_no', $this->model::find($id)->batch_no)->delete();

        return response()->json(['message' => 'General Journal successfully deleted.'], 200);

    }

    public function import(Request $request) {

        $journals = $request->all();
        $suppliers = $this->supplier->keyBy('name');
        $accountTitles = $this->accountTitle->keyBy('title');
        $companies = $this->company->keyBy('company');
        $departments = $this->department->keyBy('department');
        $locations = $this->location->keyBy('location');
        $businessUnits = $this->businessUnit->keyBy('business_unit');
        $units = $this->unit->keyBy('name'); // make sure it's correct key
        $subUnits = $this->subUnit->keyBy('name'); // make sure it's correct key

        $error = [];
        $account_title_list = $this->accountTitle->pluck('title')->toArray();
        $company_list = $this->company->pluck('company')->toArray();
        $department_list = $this->department->pluck('department')->toArray();
        $location_list = $this->location->pluck('location')->toArray();
        $business_unit_list = $this->businessUnit->pluck('business_unit')->toArray();
        $unit_list = $this->unit->pluck('name')->toArray();
        $sub_unit_list = $this->subUnit->pluck('name')->toArray();
        $bookOfAccounts = $this->bookOfAccounts();
        $supplier_list = $this->supplier->pluck('name')->toArray();

        $headers = "Account Tag, PO#, RR#, Reference No, Voucher Number, Supplier, DR/CR, Amount, Description, Account Title, Company, Department, Location, BOA";
        $template = ["tag_no", "po_no", "rr_no", "reference_no", "voucher_number", "supplier", "entry", "amount", "remarks", "account_title", "company", "department", "location", "boa"];
        $required = ["supplier", "entry", "amount", "account_title", "company", "department", "location", "boa"];
        $keys = array_keys(current($journals));
//        $this->validateHeader($template, $keys, $headers);
//        $this->controller->validateHeader($template, $keys, $headers);

        $index = 2;
        foreach ($journals as $journal) {
            $account_title = $journal['account_title'];
            $supplier = $journal['supplier'];
            $company = $journal['company'];
            $department = $journal['department'];
            $location = $journal['location'];
            $unit = $journal['unit'];
            $business_unit = $journal['business_unit'];
            $sub_unit = $journal['sub_unit'];
            $boa = $journal['boa'];

            if (!in_array($account_title, $account_title_list) && !empty($account_title)) {
                $error[] = (object)[
                    "line" => $index,
                    "description" => $account_title . " is not registered.",
                ];
            }

            if (!in_array($supplier, $supplier_list) && !empty($supplier)) {
                $error[] = (object)[
                    "line" => $index,
                    "description" => $supplier . " is not registered.",
                ];
            }

            if (!in_array($department, $department_list) && !empty($department)) {
                $error[] = (object)[
                    "line" => $index,
                    "description" => $department . " is not registered.",
                ];
            }

            if (!in_array($location, $location_list) && !empty($location)){
                $error[] = (object)[
                    "line" => $index,
                    "description" => $location . " is not registered.",
                ];
            }

            if (!in_array($company, $company_list) && !empty($company)) {
                $error[] = (object)[
                    "line" => $index,
                    "description" => $company . " is not registered.",
                ];
            }

//            if (!$boa || !in_array($boa, $bookOfAccounts)) {
//                $error[] = (object)[
//                    "line" => $index,
//                    "description" => $boa . " is invalid.",
//                ];
//            }

//            if ($boa != 'Adjustment') {
//                $error[] = (object)[
//                    "line" => $index,
//                    "description" => "BOA must be Adjustment.",
//                ];
//            }

            foreach ($journal as $key => $value) {
                if (in_array($key, $required) && empty($value)) {
                    $error[] = (object)[
                        "error_type" => "empty",
                        "line" => $index,
                        "description" => $key . " is empty.",
                    ];
                }
            }

            $index++;
        }

        if (!empty($error)) {
            return response()->json([
                'error' => $error,
            ], 400);
        }

        if (isset($journals)) {
            foreach ($journals as $journal) {
                $supplier = $suppliers[$journal['supplier']] ?? null;
                $account_title = $accountTitles[$journal['account_title']] ?? null;
                $company = $companies[$journal['company']] ?? null;
                $department = $departments[$journal['department']] ?? null;
                $location = $locations[$journal['location']] ?? null;
                $business_unit = $businessUnits[$journal['business_unit']] ?? null;
                $unit = $units[$journal['unit']] ?? null;
                $sub_unit = $subUnits[$journal['sub_unit']] ?? null;
                $formattedJournal[] = [
                    'account_tag' => $journal['tag_no'],
                    'po_no' => $journal['po_no'],
                    'rr_no' => $journal['rr_no'],
                    'reference_no' => $journal['reference_no'],
                    'voucher_number' => $journal['voucher_number'],
                    'supplier' => [
                        'id' => $supplier->id ?? null,
                        'code' => $supplier->code ?? null,
                        'name' => $supplier->name ?? null
                    ],
                    'entry' => $journal['entry'],
                    'amount' => $journal['entry'] == 'Credit' ? abs($journal['amount']) : $journal['amount'],
                    'remarks' => $journal['remarks'],
                    'description' => $journal['description'],
                    'account_title' => [
                        'id' => $account_title->id ?? null,
                        'code' => $account_title->code ?? null,
                        'name' => $account_title->title ?? null
                    ],
                    'company' => [
                        'id' => $company->id,
                        'code' => $company->code,
                        'name' => $company->company
                    ],
                    'business_unit' => [
                        'id' => $business_unit->id ?? null,
                        'code' => $business_unit->code ?? null,
                        'name' => $business_unit->business_unit ?? null
                    ],
                    'department' => [
                        'id' => $department->id,
                        'code' => $department->code,
                        'name' => $department->department
                    ],
                    'unit' => [
                        'id' => $unit->id ?? null,
                        'code' => $unit->code ?? null,
                        'name' => $unit->name ?? null
                    ],
                    'sub_unit' => [
                        'id' => $sub_unit->id ?? null,
                        'code' => $sub_unit->code ?? null,
                        'name' => $sub_unit->name ?? null
                    ],
                    'location' => [
                        'id' => $location->id,
                        'code' => $location->code,
                        'name' => $location->location
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
        $generalJournal = $this->model::find($id);

        if ($generalJournal) {
            $this->model::where('batch_no', $generalJournal->batch_no)
                ->update([
                    'is_posted' => true,
                    'posted_at' => Carbon::now()->format('Y-m-d')
                ]);
        }

        $generalJournal->journals()->create([
            'status' => 'posted',
            'user_id' => auth()->user()->id
        ]);

        return response()->json(
            ['message' => $generalJournal->gj_number . ' successfully posted.']
            , 200);
    }

    private function bookOfAccounts() {
        return collect([
            '13th month Accrual',
            'Accruals - E-Pig Farms',
            'Accruals - Food & Beverages',
            'Accruals - Fresh Options',
            'Accruals - Lodestar Feedmill & Veterinary Products',
            'Accruals - Meats Production',
            'Accruals - RDF Corporate Services',
            'Accruals - Red Dragon Farm',
            'AR Accruals - Fresh Options',
            'AR Reversal - Fresh Options',
            'Bank Transaction',
            'FA Depreciation',
            'Freon',
            'Fuel Register',
            'General Journal - E-Pig Farms',
            'General Journal - Food & Beverages',
            'General Journal - Fresh Options',
            'General Journal - Lodestar Feedmill & Veterinary Products',
            'General Journal - Meats Production',
            'General Journal - RDF Corporate Services',
            'General Journal - Red Dragon Farm',
            'MIR - Feedmill',
            'MIR - Meats Production',
            'MIR - RDF Corporate Services',
            'PAG-IBIG REGISTER',
            'Payroll Register',
            'PCF Depot - Meats Production',
            'PCF Finance - RDF Corporate Services',
            'PCF Payroll',
            'PCF Purchasing - RDF Corporate Services',
            'PHILHEALTH REGISTER',
            'Prepayments - Food & Beverages',
            'Prepayments - Fresh Options',
            'Reversal - E-Pig Farms',
            'Reversal - Food & Beverages',
            'Reversal - Fresh Options',
            'Reversal - Lodestar Feedmill & Veterinary Products',
            'Reversal - Meats Production',
            'Reversal - RDF Corporate Services',
            'Reversal - Red Dragon Farm',
            'Sales Journal - Agri-Aquatic',
            'Sales Journal - E-Pig Farms',
            'Sales Journal - Fresh Options',
            'Sales Journal - GC',
            'Sales Journal - Lodestar Feedmill & Veterinary Products',
            'Sales Journal - Meats Production',
            'Sales Journal - Pharmacy',
            'Sales Journal - RDF Corporate Services',
            'Sales Journal - Red Dragon Farm',
            'Sales Journal RSC - Fresh Options',
            'Specialist General Journal - Fresh Options',
            'Specialist General Journal - Lodestar Feedmill & Veterinary Products',
            'Specialist General Journal - Meats Production',
            'Specialist General Journal - RDF Corporate Services',
            'SSS Register',
            'Voucher Payable - E-Pig Farms',
            'Voucher Payable - Food & Beverages',
            'Voucher Payable - Fresh Options',
            'Voucher Payable - Lodestar Feedmill & Veterinary Products',
            'Voucher Payable - Meats Production',
            'Voucher Payable - RDF Corporate Services',
            'Voucher Payable - Red Dragon Farm',
            'VP Confi - RDF Corporate Services'
        ])->toArray();
    }

}
