<?php

namespace App\Http\Controllers;

use App\Http\Requests\GeneralJournalRequest;
use App\Models\AccountTitle;
use App\Models\Accruals;
use App\Models\BusinessUnit;
use App\Models\Company;
use App\Models\Department;
use App\Models\FixedAssetJournal;
use App\Models\GeneralJournal;
use App\Models\Location;
use App\Models\SubUnit;
use App\Models\Supplier;
use App\Models\Transaction;
use App\Services\JournalServices;
use Carbon\Carbon;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class GeneralJournalController extends Controller
{

    private $journalService;

    public function __construct(GeneralJournal $generalJournal)
    {
        $this->journalService = (new JournalServices($generalJournal));
    }

    public function index(Request $request)
    {
        return $this->journalService->index($request);
    }

    public function indexForApproval(Request $request)
    {
        return $this->journalService->indexForApproval($request);
    }

    public function store(Request $request)
    {
        return $this->journalService->store($request->all());
    }

    public function updateGeneralJournal($id, Request $request)
    {
        return $this->journalService->updateGeneralJournal($id, $request->all());
    }

    public function destroy($id)
    {
        return $this->journalService->destroy($id);
    }

    public function action($id)
    {
        return $this->journalService->action($id);
    }

    public function import(Request $request)
    {
        return $this->journalService->import($request);
    }

    public function posted($id)
    {
        return $this->journalService->posted($id);
    }

//    private $supplier;
//    private $accountTitle;
//    private $company;
//    private $businessUnit;
//    private $subUnit;
//    private $department;
//    private $location;
//    public function __construct() {
//        $this->accountTitle = AccountTitle::select('id', 'code', 'title')->withTrashed()->get();
//        $this->supplier = Supplier::select('id', 'code', 'name')->withTrashed()->get();
//        $this->company = Company::select('id', 'code', 'company')->withTrashed()->get();
//        $this->businessUnit = BusinessUnit::select('id', 'code', 'business_unit')->withTrashed()->get();
//        $this->subUnit = SubUnit::select('id', 'code', 'subunit')->withTrashed()->get();
//        $this->department = Department::select('id', 'code', 'department')->withTrashed()->get();
//        $this->location = Location::select('id', 'code', 'location')->withTrashed()->get();
//    }
//
//    public function index(Request $request)
//{
//    $rows = $request->input('rows', 10);
//    $companies = $this->getRequestData($request, 'companies');
//    $is_posted = $request->input('is_posted', 0);
//    $is_year_end = $request->input('is_year_end', 0);
//    $search = $request->search;
//    $status = $request->input('status', 'pending');
////        $adjustment_month = $request->input('adjustment_month');
////        $year = date('Y', strtotime($adjustment_month));
////        $month = date('m', strtotime($adjustment_month));
//    $year = $request->input('year');
//    $month = $request->input('month');
//
//    $entryEnabled = DB::table('settings')->where('key', 'entry_enabled')->first();
//
//    if (!empty($month) && !empty($year)) {
//        $generalJournals = GeneralJournal::select([
//            'gj_number',
//            'journal_name',
//            'journal_description',
//            'is_posted',
//            'adjustment_month',
//            'is_year_end',
//            'is_approved',
//            'reason_id',
//            'reason',
//            DB::raw("MAX(updated_at) as latest_updated_at")
//        ])
////            ->whereBetween('created_at', [$transactionFrom, $transactionTo])
//            ->when($status == 'pending', function ($query) {
//                $query->whereNull('is_approved');
//            })
//            ->when($status == 'approved', function ($query) {
//                $query->where('is_approved', true);
//            })
//            ->when($status == 'rejected', function ($query) {
//                $query->where('is_approved', false);
//            })
//            ->when($is_posted == 1, function ($query) {
//                $query->where('is_posted', true);
//            }, function ($query) {
//                $query->where('is_posted', false);
//            })
////            ->when(!empty($companies), function ($query) use ($companies) {
////                $query->whereIn('division_id', $companies);
////            })
////            ->when(isset($adjustment_month), function ($query) use ($year, $month) {
////                $query->whereYear('adjustment_month', $year)
////                    ->whereMonth('adjustment_month', $month);
////            })
////                ->where('user_id', auth()->user()->id)
//            ->where(function ($query) {
//                $query->where('user_id', auth()->user()->id)
//                    ->orWhere(function ($query) {
//                        if (auth()->user()->position == 'SUPERVISOR' && auth()->user()->role == 'Approver') {
//                            $query->where('user_id', '<>', auth()->user()->id);
//                        }
//                    });
//            })
//            ->groupBy('gj_number', 'journal_name', 'journal_description', 'is_posted', 'adjustment_month', 'is_year_end', 'is_approved', 'reason_id', 'reason')
//            ->orderBy('latest_updated_at', 'desc')
//            ->whereLike(['gj_number', 'journal_name', 'journal_description'], $search)
//            ->get();
////                 ->paginate($rows);
//
//        $allAccountTitles = GeneralJournal::whereIn('gj_number', $generalJournals->pluck('gj_number')->toArray())
//            ->get()
//            ->groupBy('gj_number');
//
//
//        $generalJournals->transform(function ($item) use ($allAccountTitles, $generalJournals) {
////            $account_titles = $allAccountTitles[$item->gj_number];
//            $account_titles = $allAccountTitles->get($item->gj_number);
//
//            return (object) [
//                'id' => $account_titles->last()->id,
//                'division' => [
//                    'id' => $account_titles->first()->division_id,
//                    'name' => $account_titles->first()->division_name
//                ],
//                'boa' => $account_titles->first()->boa,
//                'gj_number' => $item->gj_number,
//                'journal_name' => $item->journal_name,
//                'journal_description' => $item->journal_description,
//                'created_at' => $item->latest_updated_at,
//                'adjustment_month' => $account_titles->first()->adjustment_month,
//                'account_titles' => $account_titles->transform(function ($item) {
//                    return (object) [
//                        'po_no' => $item->po_no ? array($item->po_no) : [],
//                        'rr_no' => $item->rr_no ? array($item->rr_no) : [],
//                        'tag_no' => $item->tag_no,
//                        'reference_no' => $item->reference_no,
//                        'voucher_number' => $item->voucher_number,
//                        'supplier' => [
//                            'name' => $item->supplier_name
//                        ],
//                        'entry' => $item->entry,
//                        'amount' => $item->amount,
//                        'account_title' => [
//                            'id' => $item->account_title_id,
//                            'code' => $item->account_title_code,
//                            'name' => $item->account_title_name
//                        ],
//                        'company' => [
//                            'id' => $item->company_id,
//                            'code' => $item->company_code,
//                            'name' => $item->company_name
//                        ],
//                        'department' => [
//                            'id' => $item->department_id,
//                            'code' => $item->department_code,
//                            'name' => $item->department_name
//                        ],
//                        'location' => [
//                            'id' => $item->location_id,
//                            'code' => $item->location_code,
//                            'name' => $item->location_name
//                        ],
//                        'business_unit' => [
//                            'id' => $item->business_unit_id,
//                            'code' => $item->business_unit_code,
//                            'name' => $item->business_unit_name
//                        ],
//                        'sub_unit' => [
//                            'id' => $item->sub_unit_id,
//                            'code' => $item->sub_unit_code,
//                            'name' => $item->sub_unit_name
//                        ],
//                        'remarks' => $item->description,
//                        'transaction_date' => $item->transaction_date,
//                        'attachments' => $item->media->map(function ($media) {
//                            return [
//                                'file_name' => $media->file_name,
//                                'base64' => 'data:' . $media->mime_type . ';base64,' . base64_encode(file_get_contents($media->getPath()))
////                                    'base64' => base64_encode(file_get_contents($media->getPath()))
//                            ];
//                        }),
//                    ];
//                }),
//                'is_posted' => $item->is_posted,
//                'posted_at' => $item->posted_at,
//                'is_year_end' => $item->is_year_end,
//                'attachments' => collect($account_titles)->last()->attachments,
//                'is_approved' => $item->is_approved,
//                'reason_id' => $item->reason_id,
//                'reason' => $item->reason,
//            ];
//        });
//    } else {
//        $generalJournals = GeneralJournal::select(['gj_number', 'journal_name', 'journal_description', 'is_posted', 'adjustment_month', 'is_year_end', 'is_approved', 'reason_id', 'reason', DB::raw("MAX(updated_at) as latest_updated_at")])
//            ->when($is_posted == 1, function ($query) {
//                $query->where('is_posted', true);
//            }, function ($query) {
//                $query->where('is_posted', false);
//            })
//            ->when($status == 'pending', function ($query) {
//                $query->whereNull('is_approved');
//            })
//            ->when($status == 'approved', function ($query) {
//                $query->where('is_approved', true);
//            })
//            ->when($status == 'rejected', function ($query) {
//                $query->where('is_approved', false);
//            })
////                ->where('user_id', auth()->user()->id)
//            ->where(function ($query) {
//                $query->where('user_id', auth()->user()->id)
//                    ->orWhere(function ($query) {
//                        if (auth()->user()->position == 'SUPERVISOR' && auth()->user()->role == 'Approver') {
//                            $query->where('user_id', '<>', auth()->user()->id);
//                        }
//                    });
//            })
//            ->groupBy('gj_number', 'journal_name', 'journal_description', 'is_posted', 'adjustment_month', 'is_year_end', 'is_approved', 'reason_id', 'reason')
//            ->orderBy('latest_updated_at', 'desc')
//            ->get();
//    }
//
//    if (empty($year) && empty($month)) {
//        $groupedGeneralJournals = $generalJournals->map(function ($item) use ($is_posted){
//            return $is_posted ? Carbon::parse($item->posted_at)->format('Y') : Carbon::parse($item->adjustment_month)->format('Y');
//        })->unique()->values();
//    } elseif (!empty($year) && empty($month)) {
//        $groupedGeneralJournals = $generalJournals->filter(function ($item) use ($year, $is_posted) {
//            return $is_posted
//                ? Carbon::parse($item->posted_at)->format('Y') == $year && $item->is_year_end == 0
//                : Carbon::parse($item->adjustment_month)->format('Y') == $year && $item->is_year_end == 0;
//        })->groupBy(function ($item) use ($is_posted) {
//            return $is_posted
//                ? Carbon::parse($item->posted_at)->format('F')
//                : Carbon::parse($item->adjustment_month)->format('F');
//        })->toArray();
//
//        $groupedGeneralJournalsYearEnd = $generalJournals->filter(function ($item) use ($year, $is_posted) {
//            return $is_posted
//                ? Carbon::parse($item->posted_at)->format('Y') == $year && $item->is_year_end == 1
//                : Carbon::parse($item->adjustment_month)->format('Y') == $year && $item->is_year_end == 1;
//        })->groupBy(function ($item) use ($is_posted) {
//            return 'Year End ' . ($is_posted
//                    ? Carbon::parse($item->posted_at)->format('F')
//                    : Carbon::parse($item->adjustment_month)->format('F'));
//        })->toArray();
//
//
//        $groupedGeneralJournals = array_merge($groupedGeneralJournals, $groupedGeneralJournalsYearEnd);
//
//
//    } elseif (!empty($year) && !empty($month)) {
////            $groupedGeneralJournals = $generalJournals->filter(function ($item) use ($year, $month, $is_posted, $is_year_end) {
//////                return Carbon::parse($item->posted_at)->format('Y') == $year &&
//////                    Carbon::parse($item->posted_at)->format('m') == str_pad($month, 2, '0', STR_PAD_LEFT);
////
////                return $is_posted ?
////                    Carbon::parse($item->posted_at)->format('Y') == $year &&
////                    Carbon::parse($item->posted_at)->format('m') == str_pad($month, 2, '0', STR_PAD_LEFT)
////                    :  Carbon::parse($item->adjustment_month)->format('Y') == $year &&
////                    Carbon::parse($item->adjustment_month)->format('m') == str_pad($month, 2, '0', STR_PAD_LEFT);
////            });
//
//        $groupedGeneralJournals = $generalJournals->filter(function ($item) use ($year, $month, $is_posted, $is_year_end) {
//            if ($is_year_end) {
//                return $is_posted
//                    ? Carbon::parse($item->posted_at)->format('Y') == $year &&
//                    Carbon::parse($item->posted_at)->format('m') == str_pad($month, 2, '0', STR_PAD_LEFT) &&
//                    $item->is_year_end == 1
//                    : Carbon::parse($item->adjustment_month)->format('Y') == $year &&
//                    Carbon::parse($item->adjustment_month)->format('m') == str_pad($month, 2, '0', STR_PAD_LEFT) &&
//                    $item->is_year_end == 1;
//            } else {
//                return $is_posted
//                    ? Carbon::parse($item->posted_at)->format('Y') == $year &&
//                    Carbon::parse($item->posted_at)->format('m') == str_pad($month, 2, '0', STR_PAD_LEFT) &&
//                    $item->is_year_end == 0
//                    : Carbon::parse($item->adjustment_month)->format('Y') == $year &&
//                    Carbon::parse($item->adjustment_month)->format('m') == str_pad($month, 2, '0', STR_PAD_LEFT) &&
//                    $item->is_year_end == 0;
//            }
//        });
//
//        $currentPage = LengthAwarePaginator::resolveCurrentPage();
//        $currentItems = $groupedGeneralJournals->slice(($currentPage - 1) * $rows, $rows)->values();
//        $totalItems = $groupedGeneralJournals->count();
//
//        return new LengthAwarePaginator($currentItems, $totalItems, $rows);
//
//    } else {
//        $groupedGeneralJournals = $generalJournals->groupBy(function ($item) use ($is_posted) {
//            return $is_posted
//                ? Carbon::parse($item->posted_at)->format('Y')
//                : Carbon::parse($item->adjustment_month)->format('Y');
//        })->map(function ($yearGroup) use ($is_posted){
//            return $yearGroup->groupBy(function ($item) use ($is_posted) {
//                return $is_posted
//                    ? Carbon::parse($item->posted_at)->format('F')
//                    : Carbon::parse($item->adjustment_month)->format('F');
//            });
//        });
//    }
//
//    return $groupedGeneralJournals;
//
//}
//
//    public function indexForApproval(Request $request)
//    {
//        $search = $request->search;
//        $status = $request->input('status', 'pending');
//        $rows = $request->input('rows', 10);
//        $generalJournals = GeneralJournal::where('approver_id', auth()->user()->id)
//            ->where('is_posted', false)
//            ->when($status == 'pending', function ($query) {
//                $query->whereNull('is_approved');
//            })
//            ->when($status == 'approved', function ($query) {
//                $query->where('is_approved', true);
//            })
//            ->when($status == 'rejected', function ($query) {
//                $query->where('is_approved', false);
//            })
//            ->select([
//                'gj_number',
//                'journal_name',
//                'journal_description',
//                'is_posted',
//                'adjustment_month',
//                'is_year_end',
//                'is_approved',
//                'reason_id',
//                'reason',
//                DB::raw("MAX(updated_at) as latest_updated_at")
//            ])->groupBy('gj_number', 'journal_name', 'journal_description', 'is_posted', 'adjustment_month', 'is_year_end', 'is_approved', 'reason_id', 'reason')
//            ->orderBy('latest_updated_at', 'desc')
//            ->whereLike(['gj_number', 'journal_name', 'journal_description'], $search)
//            ->get();
//
//        $allAccountTitles = GeneralJournal::whereIn('gj_number', $generalJournals->pluck('gj_number')->toArray())
//            ->get()
//            ->groupBy('gj_number');
//
//
//        $generalJournals->transform(function ($item) use ($allAccountTitles, $generalJournals) {
//            $account_titles = $allAccountTitles->get($item->gj_number);
//
//            return (object)[
//                'id' => $account_titles->last()->id,
//                'division' => [
//                    'id' => $account_titles->first()->division_id,
//                    'name' => $account_titles->first()->division_name
//                ],
//                'boa' => $account_titles->first()->boa,
//                'gj_number' => $item->gj_number,
//                'journal_name' => $item->journal_name,
//                'journal_description' => $item->journal_description,
//                'created_at' => $item->latest_updated_at,
//                'adjustment_month' => $account_titles->first()->adjustment_month,
//                'account_titles' => $account_titles->transform(function ($item) {
//                    return (object)[
//                        'po_no' => $item->po_no ? array($item->po_no) : [],
//                        'rr_no' => $item->rr_no ? array($item->rr_no) : [],
//                        'tag_no' => $item->tag_no,
//                        'reference_no' => $item->reference_no,
//                        'voucher_number' => $item->voucher_number,
//                        'supplier' => [
//                            'name' => $item->supplier_name
//                        ],
//                        'entry' => $item->entry,
//                        'amount' => $item->amount,
//                        'account_title' => [
//                            'id' => $item->account_title_id,
//                            'code' => $item->account_title_code,
//                            'name' => $item->account_title_name
//                        ],
//                        'company' => [
//                            'id' => $item->company_id,
//                            'code' => $item->company_code,
//                            'name' => $item->company_name
//                        ],
//                        'department' => [
//                            'id' => $item->department_id,
//                            'code' => $item->department_code,
//                            'name' => $item->department_name
//                        ],
//                        'location' => [
//                            'id' => $item->location_id,
//                            'code' => $item->location_code,
//                            'name' => $item->location_name
//                        ],
//                        'business_unit' => [
//                            'id' => $item->business_unit_id,
//                            'code' => $item->business_unit_code,
//                            'name' => $item->business_unit_name
//                        ],
//                        'sub_unit' => [
//                            'id' => $item->sub_unit_id,
//                            'code' => $item->sub_unit_code,
//                            'name' => $item->sub_unit_name
//                        ],
//                        'remarks' => $item->description,
//                        'transaction_date' => $item->transaction_date,
////                        'attachments' => $item->media->map(function ($media) {
////                            return [
////                                'file_name' => $media->file_name,
////                                'base64' => 'data:' . $media->mime_type . ';base64,' . base64_encode(file_get_contents($media->getPath()))
//////                                    'base64' => base64_encode(file_get_contents($media->getPath()))
////                            ];
////                        }),
//                    ];
//                }),
//                'is_posted' => $item->is_posted,
//                'posted_at' => $item->posted_at,
//                'is_year_end' => $item->is_year_end,
//                'is_approved' => $item->is_approved,
//                'reason_id' => $item->reason_id,
//                'reason' => $item->reason,
////                'attachments' => collect($account_titles)->last()->attachments
//            ];
//        });
//
//        $currentPage = LengthAwarePaginator::resolveCurrentPage();
//        $currentItems = $generalJournals->slice(($currentPage - 1) * $rows, $rows)->values();
//        $totalItems = $generalJournals->count();
//
//        return new LengthAwarePaginator($currentItems, $totalItems, $rows);
//    }
//
//    public function action($id) {
//
//        $process = request()->input('process');
//        $reason_id = request()->input('reason_id', null);
//        $reason = request()->input('reason', null);
//        $generalJournals = GeneralJournal::find($id);
//
//        if ($generalJournals) {
//            switch($process) {
//                case 'approve':
//                    GeneralJournal::where('gj_number', $generalJournals->gj_number)
//                        ->update([
//                            'is_approved' => true
//                        ]);
//                    break;
//                case 'reject':
//                    GeneralJournal::where('gj_number', $generalJournals->gj_number)
//                        ->update([
//                            'is_approved' => false,
//                            'reason_id' => $reason_id,
//                            'reason' => $reason
//                        ]);
//                    break;
//            }
//
//            $generalJournals->journals()->create([
//                'status' => $process,
//                'user_id' => auth()->user()->id,
//                'reason_id' => $reason_id,
//                'reason' => $reason
//            ]);
//
//            return response()->json(
//                ['message' => $generalJournals->gj_number . ' successfully ' . $process . 'd.']
//                , 200);
//        }
//    }
//
//    public function store(Request $request, $id = null)
//    {
//        $approver_id =  auth()->user()->journalUser()->first()->approver_id ?? null;
//        $journal_name = data_get($request, "journal.name");
//        $journal_description = data_get($request, "journal.description");
//        $boa = $request->boa;
//        $division_id = data_get($request, "division.id");
//        $division_name = data_get($request, "division.name");
//        $adjust_month = data_get($request, "adjustment_month");
//        $isYearEnd = data_get($request, "is_year_end", 0);
//        $account_titles = $request->account_titles;
//        $department_id = null;
//
//        foreach($account_titles as $account_title) {
//            if(data_get($account_title, "department.id")) {
//                $department_id = $account_title['department']['id'];
//                break;
//            }
//        }
//
//        $gj_number = $this->generateGeneralNumber($department_id, GeneralJournal::class);
//        $batch_no = $this->generateGJBatchNo(GeneralJournal::class);
//
//        foreach($account_titles as $account_title) {
//            $adjustments = GeneralJournal::create([
//                'adjustment_month' => $adjust_month,
//                'is_year_end' => $isYearEnd,
//                'division_id' => $division_id,
//                'division_name' => $division_name,
//                'approver_id' => $approver_id,
//                'tag_no' => data_get($account_title, "tag_no"),
//                'description' => data_get($account_title, "remarks"),
////                'po_no' => data_get($account_title, "po_no"),
////                'rr_no' => data_get($account_title, "rr_no"),
//                'po_no' => implode(', ', data_get($account_title, "po_no", [])),
//                'rr_no' => implode(', ', data_get($account_title, "rr_no", [])),
//                'reference_no' => data_get($account_title, "reference_no"),
//                'voucher_number' => data_get($account_title, "voucher_number"),
//                'transaction_date' => data_get($account_title, "transaction_date"),
//                'supplier_id' => data_get($account_title, "supplier.id"),
//                'supplier_code' => data_get($account_title, "supplier.code"),
//                'supplier_name' => data_get($account_title, "supplier.name"),
//                'entry' => data_get($account_title, "entry"),
//                'account_title_id' => data_get($account_title, "account_title.id"),
//                'account_title_code' => data_get($account_title, "account_title.code"),
//                'account_title_name' => data_get($account_title, "account_title.name"),
//                'company_id' => data_get($account_title, "company.id"),
//                'company_code' => data_get($account_title, "company.code"),
//                'company_name' => data_get($account_title, "company.name"),
//                'department_id' => data_get($account_title, "department.id"),
//                'department_code' => data_get($account_title, "department.code"),
//                'department_name' => data_get($account_title, "department.name"),
//                'location_id' => data_get($account_title, "location.id"),
//                'location_code' => data_get($account_title, "location.code"),
//                'location_name' => data_get($account_title, "location.name"),
//                'business_unit_id' => data_get($account_title, "business_unit.id"),
//                'business_unit_code' => data_get($account_title, "business_unit.code"),
//                'business_unit_name' => data_get($account_title, "business_unit.name"),
//                'sub_unit_id' => data_get($account_title, "sub_unit.id"),
//                'sub_unit_code' => data_get($account_title, "sub_unit.code"),
//                'sub_unit_name' => data_get($account_title, "sub_unit.name"),
//                'amount' => data_get($account_title, "amount"),
//
//                'boa' => $boa,
//                'user_id' => auth()->user()->id,
//                'journal_name' => $journal_name,
//                'journal_description' => $journal_description,
//                'gj_number' => $gj_number,
//                'batch_no' => $batch_no
//            ]);
//        }
//
////        if ($request->hasFile('attachments')) {
////            $adjustments->addMultipleMediaFromRequest(['attachments'])
////                ->each(function ($fileAdder) use ($gj_number) {
////                    $fileAdder->toMediaCollection('attachments')
////                        ->update(['gj_number' => $gj_number]);
////                });
////        }
//        if ($id) {
//
//            DB::table('media')->where('model_type', GeneralJournal::class)
//                ->where('model_id', $adjustments->id)
//                ->update(['model_id' => $id]);
//
//            if ($request->hasFile('attachments')) {
//                $adjustments->addMultipleMediaFromRequest(['attachments'])
//                    ->each(function ($fileAdder) use ($gj_number) {
//                        $fileAdder->toMediaCollection('attachments')
//                            ->update(['gj_number' => $gj_number]);
//                    });
//            }
//
//        } else {
//            if ($request->hasFile('attachments')) {
//                $adjustments->addMultipleMediaFromRequest(['attachments'])
//                    ->each(function ($fileAdder) use ($gj_number) {
//                        $fileAdder->toMediaCollection('attachments')
//                            ->update(['gj_number' => $gj_number]);
//                    });
//            }
//        }
//        return response()->json(['message' => 'General Journal successfully created.'], 201);
//    }
//
//    public function updateGeneralJournal($id, Request $request)
//    {
//        $generalJournal = GeneralJournal::find($id);
//
//        if ($generalJournal) {
//            GeneralJournal::where('batch_no', $generalJournal->batch_no)->forceDelete();
////            $generalJournal->media()->delete();
//        }
//
//        return $this->store($request, $id);
//    }
//
//    public function destroy($id)
//    {
//        GeneralJournal::where('batch_no', GeneralJournal::find($id)->batch_no)->delete();
//
//        return response()->json(['message' => 'General Journal successfully deleted.'], 200);
//
//    }
//
//    public function import(Request $request) {
//
//        $journals = $request->all();
//        $error = [];
//        $account_title_list = AccountTitle::withTrashed()->pluck('title')->toArray();
////        $account_title_list = $this->accountTitle->withTrashed()->pluck('title')->toArray();
//        $company_list = Company::withTrashed()->pluck('company')->toArray();
////        $company_list = $this->company->withTrashed()->pluck('company')->toArray();
//        $department_list = Department::withTrashed()->pluck('department')->toArray();
////        $department_list = $this->department->withTrashed()->pluck('department')->toArray();
//        $location_list = Location::withTrashed()->pluck('location')->toArray();
////        $location_list = $this->location->withTrashed()->pluck('location')->toArray();
//        $business_unit_list = BusinessUnit::withTrashed()->pluck('business_unit')->toArray();
////        $business_unit_list = $this->businessUnit->withTrashed()->pluck('business_unit')->toArray();
//        $sub_unit_list = SubUnit::withTrashed()->pluck('subunit')->toArray();
////        $sub_unit_list = $this->subUnit->withTrashed()->pluck('subunit')->toArray();
//
//        $headers = "Account Tag, PO#, RR#, Reference No, Voucher Number, Supplier, DR/CR, Amount, Description, Account Title, Company, Department, Location, BOA";
//        $template = ["tag_no", "po_no", "rr_no", "reference_no", "voucher_number", "supplier", "entry", "amount", "remarks", "account_title", "company", "department", "location", "boa"];
//        $required = ["supplier", "entry", "amount", "account_title", "company", "department", "location"];
//        $keys = array_keys(current($journals));
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
//
//        if (isset($journals)) {
//            foreach ($journals as $journal) {
//                $supplier = $this->supplier->where('name', $journal['supplier'])->first();
//                $accountTitle = $this->accountTitle->where('title', $journal['account_title'])->first();
//                $company = $this->company->where('company', $journal['company'])->first();
//                $department = $this->department->where('department', $journal['department'])->first();
//                $location = $this->location->where('location', $journal['location'])->first();
//                $business_unit = $this->businessUnit->where('business_unit', $journal['business_unit'])->first() ?? null;
//                $sub_unit = $this->subUnit->where('subunit', $journal['sub_unit'])->first() ?? null;
//
//                $formattedJournal[] = [
//                    'account_tag' => $journal['tag_no'],
//                    'po_no' => $journal['po_no'],
//                    'rr_no' => $journal['rr_no'],
//                    'reference_no' => $journal['reference_no'],
//                    'voucher_number' => $journal['voucher_number'],
//                    'supplier' => [
//                        'id' => $supplier->id,
//                        'code' => $supplier->code,
//                        'name' => $supplier->name
//                    ],
//                    'entry' => $journal['entry'],
//                    'amount' => $journal['entry'] == 'Credit' ? abs($journal['amount']) : $journal['amount'],
//                    'remarks' => $journal['remarks'],
//                    'account_title' => [
//                        'id' => $accountTitle->id,
//                        'code' => $accountTitle->code,
//                        'name' => $accountTitle->title
//                    ],
//                    'company' => [
//                        'id' => $company->id,
//                        'code' => $company->code,
//                        'name' => $company->company
//                    ],
//                    'department' => [
//                        'id' => $department->id,
//                        'code' => $department->code,
//                        'name' => $department->department
//                    ],
//                    'location' => [
//                        'id' => $location->id,
//                        'code' => $location->code,
//                        'name' => $location->location
//                    ],
//                    'business_unit' => [
//                        'id' => $business_unit->id ?? null,
//                        'code' => $business_unit->code ?? null,
//                        'name' => $business_unit->business_unit ?? null
//                    ],
//                    'sub_unit' => [
//                        'id' => $sub_unit->id ?? null,
//                        'code' => $sub_unit->code ?? null,
//                        'name' => $sub_unit->subunit ?? null
//                    ],
//                    'boa' => $journal['boa']
//                ];
//            }
//
//            return $formattedJournal;
//
//        } else {
//            return response()->json(['error' => $error], 400);
//        }
//    }
//
//    public function posted($id)
//    {
//        $generalJournal = GeneralJournal::find($id);
//
//        if ($generalJournal) {
//            GeneralJournal::where('batch_no', $generalJournal->batch_no)
//                ->update([
//                    'is_posted' => true,
//                    'posted_at' => Carbon::now()->format('Y-m-d')
//                ]);
//        }
//
//        $generalJournal->journals()->create([
//            'status' => 'posted',
//            'user_id' => auth()->user()->id
//        ]);
//
//        return response()->json(
//            ['message' => $generalJournal->gj_number . ' successfully posted.']
//            , 200);
//    }

}
