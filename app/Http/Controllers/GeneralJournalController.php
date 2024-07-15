<?php

namespace App\Http\Controllers;

use App\Http\Requests\GeneralJournalRequest;
use App\Models\Department;
use App\Models\GeneralJournal;
use App\Models\Transaction;
use Carbon\Carbon;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GeneralJournalController extends Controller
{

    public function index(Request $request)
    {
        $rows = $request->rows;
        $state = $request->state;
        $transactionFrom = $this->getTransactionDate($request, 'transaction_from', Carbon::now()->startOfMonth()->format('Y-m-d H:i:s'));
        $transactionTo = $this->getTransactionDate($request, 'transaction_to', Carbon::now()->endOfMonth()->format('Y-m-d H:i:s'));
        $search = $request->search;

        $generalJournals = GeneralJournal::select(['gj_number', 'voucher_no', 'transaction_id', DB::raw("MAX(updated_at) as latest_updated_at")])
            ->whereBetween('created_at', [$transactionFrom, $transactionTo])
            ->where('user_id', auth()->user()->id)
            ->groupBy('gj_number', 'voucher_no', 'transaction_id')
            ->when($state == 'adjust-entries', function ($query) {
                $query->where('type', 'Adjustment');
            }, function ($query) use ($state) {
                $query->when($state == 'accruals', function ($query) {
                    $query->where([
                        ['type', 'Accruals'],
                        ['is_reversed', false]
                    ]);
                }, function ($query) {
                    $query->where([
                        ['type', 'Accruals'],
                        ['is_reversed', true]
                    ]);
                });
            })
            ->whereLike(['gj_number', 'voucher_no'], $search)
            ->orderBy('latest_updated_at', 'desc')
            ->paginate($rows);

        $generalJournals->transform(function ($generalJournal) {
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

            $id = GeneralJournal::where('gj_number', $generalJournal->gj_number)->first()->id;

            $transaction = $generalJournal->transaction;
            $transaction = [
                'tag_no' => $transaction->tag_no ?? null,
                'reference_no' => $transaction->referrence_no ?? $transaction->utilities_receipt_no ?? null,
                'input_tax' => $transaction->input_tax ?? null,
                'receipt_type' => $transaction->receipt_type ?? null,
                'voucher_no' => $transaction->voucher_no ?? null,
                'supplier' => [
                    'id' => $transaction->supplier_id ?? null,
                    'name' => $transaction->supplier ?? null
                ],
//                'document_amount' => ($transaction->document_id == 3)
//                    ? ($transaction->category == in_array($transaction->category, $rental) ? $transaction->gross_amount : floatval((number_format(($transaction->principal + $transaction->interest), 2, '.', '')))) ?? $transaction->document_amount
//                    : $transaction->document_amount ?? $transaction->referrence_amount,
                'document_amount' => $transaction !== null
                    ? (
                    $transaction->document_id == 3
                        ? (
                    in_array($transaction->category, $rental)
                        ? $transaction->gross_amount
                        : floatval(number_format($transaction->principal + $transaction->interest, 2, '.', ''))
                    )
                        : $transaction->document_amount ?? $transaction->referrence_amount
                    )
                    : null,
                'document_type' => $transaction->document_type ?? null,
                'date' => $transaction->document_date ?? $transaction->date_requested ?? null
            ];

            $account_titles = GeneralJournal::where('gj_number', $generalJournal->gj_number)->get()->transform(function ($item) {
                return [
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
                    'remarks' => $item->remarks
                ];
            });

            return [
                'id' => $id,
                'gj_number' => $generalJournal->gj_number,
                'voucher_no' => $generalJournal->voucher_no,
                'created_at' => $generalJournal->latest_updated_at,
                'transaction' => $transaction,
                'account_titles' => $account_titles
            ];
        });

        if($generalJournals->isEmpty()) {
            return $this->resultResponse("not-found", "General Journals", []);
        } else {
            return $generalJournals;
        }
    }


    public function store(GeneralJournalRequest $request)
    {
        $transaction_id = $request->transaction_id;
        $voucher_no = $request->voucher_no;
        $voucher_month = $request->voucher_month;
        $account_titles = $request->account_titles;
        $type = $request->type;
        $department_id = null;

        foreach($account_titles as $account_title) {
            if($account_title['department']['id'] != null) {
                $department_id = $account_title['department']['id'];
                break;
            }
        }

        $gj_number = $this->generateGeneralNumber($department_id);

        foreach($account_titles as $account_title) {
            GeneralJournal::create([
                'transaction_id' => $transaction_id,
                'type' => $type,
                'voucher_no' => $voucher_no,
                'gj_number' => $gj_number,
                'entry' => $account_title['entry'],
                'amount' => $account_title['amount'],
                'account_title_id' => $account_title['account_title']['id'],
                'account_title_code' => $account_title['account_title']['code'],
                'account_title_name' => $account_title['account_title']['name'],
                'company_id' => $account_title['company']['id'],
                'company_code' => $account_title['company']['code'],
                'company_name' => $account_title['company']['name'],
                'department_id' => $account_title['department']['id'],
                'department_code' => $account_title['department']['code'],
                'department_name' => $account_title['department']['name'],
                'location_id' => $account_title['location']['id'],
                'location_code' => $account_title['location']['code'],
                'location_name' => $account_title['location']['name'],
                'business_unit_id' => $account_title['business_unit']['id'],
                'business_unit_code' => $account_title['business_unit']['code'],
                'business_unit_name' => $account_title['business_unit']['name'],
                'sub_unit_id' => $account_title['sub_unit']['id'],
                'sub_unit_code' => $account_title['sub_unit']['code'],
                'sub_unit_name' => $account_title['sub_unit']['name'],
                'user_id' => auth()->user()->id,
                'voucher_month' => $voucher_month,
                'is_reversed' => $type == 'Accruals' ? 0 : null,
                'remarks' => $account_title['remarks'],
            ]);
        }

        return response()->json(['message' => 'General Journal successfully created.'], 201);
    }

    public function show($id)
    {
        //
    }


    public function update($id)
    {
        $generalJournal = GeneralJournal::find($id);

        if (!$generalJournal) {
            return response()->json(['message' => 'General Journal not found.'], 404);
        }

        $ids = GeneralJournal::where('gj_number', $generalJournal->gj_number)->pluck('id')->toArray();

        foreach ($ids as $id) {
            $gj = GeneralJournal::find($id);

            if ($gj->entry == 'Credit') {
                $gj->update([
                    'entry' => 'Debit',
                    'is_reversed' => true
                ]);
            } else {
                $gj->update([
                    'entry' => 'Credit',
                    'is_reversed' => true
                ]);
            }
        }

        return response()->json(['message' => 'Entry updated successfully.']);
    }

    public function destroy($id)
    {
        GeneralJournal::where('gj_number', GeneralJournal::find($id)->gj_number)->delete();

        return response()->json(['message' => 'General Journal successfully deleted.'], 200);

    }


    function generateGeneralNumber($department_id) {
        $code = 'GJ';
        $voucher_code = Department::where('id', $department_id)->first()->voucherCode->code;
        $date = (new DateTime())->format('y-m');

        $series = 1;

        do {
            $formattedSeries = str_pad($series, 3, "0", STR_PAD_LEFT);
            $gj_number = $code . $voucher_code . $date . '-' . $formattedSeries;
            $series++;
        } while(GeneralJournal::where('gj_number', $gj_number)->exists());

        return $gj_number;
    }
}
