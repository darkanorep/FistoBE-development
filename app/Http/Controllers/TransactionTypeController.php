<?php

namespace App\Http\Controllers;

use App\Http\Requests\DocumentCoaRequest;
use App\Http\Requests\TransactionTypeRequest;
use App\Models\TransactionType;
use Illuminate\Http\Request;

class TransactionTypeController extends Controller
{
    public function index(Request $request)
    {
        $status =  $request['status'];
        $rows =  (int) $request->input('rows', 10);
        $search =  $request['search'];
        $paginate = $request->input('paginate', 1);

      $transaction_types = TransactionType::withTrashed()
        ->with([
            "accounts" => function ($query) {
                $query->with([
                    "company:id,code,company as name",
                    "department:id,code,department as name",
                    "account_title:id,code,title as name",
                    "business_unit:id,code,business_unit as name",
                    "sub_unit:id,code,subunit as name",
                    "location:id,code,location as name",
                ]);
            }
            ])
            ->where(function ($query) use ($status){
                return ($status==true) ? $query->whereNull('deleted_at') : $query->whereNotNull('deleted_at');
            })
            ->where(function ($query) use ($search) {
                $query->where('transaction_type', 'like', '%' . $search . '%');
            })
            ->select(['id','transaction_type','transaction_types_coa_id','created_at','updated_at','deleted_at'])
            ->latest('updated_at');

        if ($paginate == 1) {
            $transaction_types = $transaction_types->paginate($rows);
        } else if ($paginate == 0) {
            $transaction_types = $transaction_types->get();
        }

        if(count($transaction_types)){
            return $this->resultResponse('fetch','Transaction Type', $transaction_types);
        } else {
            return $this->resultResponse('not-found','Transaction Type', []);
        }
    }

    public function store(TransactionTypeRequest $request, DocumentCoaRequest $documentCoaRequest) {

        $new_transaction_type = TransactionType::create([
            'transaction_type' => $request->transaction_type
        ]);

        $accounts = $documentCoaRequest['account'];
        if (isset($accounts)) {
            $this->tagCoa($new_transaction_type, $accounts);

            $new_transaction_type->update([
                'transaction_types_coa_id' => $new_transaction_type->accounts->pluck('id')
            ]);
        }

        return $this->resultResponse('save', 'Transaction Type', $new_transaction_type);
    }

    public function update(TransactionTypeRequest $request, DocumentCoaRequest $documentCoaRequest, $id) {
        $transaction_type = TransactionType::where('id', $id)->first();

        if ($transaction_type) {
            $transaction_type->update([
                'transaction_type' => $request->transaction_type
            ]);

            $accounts = $documentCoaRequest['account'];

            if (isset($accounts)) {
                $transaction_type->accounts()->delete();
                $this->tagCoa($transaction_type, $accounts);
                $transaction_type->update([
                    'transaction_types_coa_id' => $transaction_type->accounts->pluck('id')
                ]);
            }

            return $this->resultResponse('update', 'Transaction Type', $transaction_type);
        } else {
            return $this->resultResponse('not-found', 'Transaction Type', []);
        }
    }

    public function change_status($id) {
        $data = TransactionType::withTrashed()->find($id);

        if ($data) {
            if ($data->trashed()) {
                $data->restore();

                return $this->resultResponse("restore", 'Transaction Type', []);
            } else {
                $data->delete();

                return $this->resultResponse("archive", 'Transaction Type', []);
            }
        } else {

            return $this->resultResponse('not-found','Transaction Type', []);
        }
    }

    function tagCoa($model, $request){
        foreach ($request as $account) {
            $model->accounts()->create([
                'entry' => $account['entry'] ?? null,
                'company_id' => $account['company_id'] ?? null,
                'business_unit_id' => $account['business_unit_id'] ?? null,
                'department_id' => $account['department_id'],
                'sub_unit_id' => $account['sub_unit_id'] ?? null,
                'location_id' => $account['location_id'] ?? null,
                'account_title_id' => $account['account_title_id'] ?? null,
            ]);
        }
    }
}
