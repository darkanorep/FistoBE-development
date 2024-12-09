<?php

namespace App\Http\Controllers;

use App\Http\Requests\TreasuryChequeRequest;
use App\Models\TreasuryCheque;
use Illuminate\Http\Request;

class TreasuryChequeController extends Controller
{
    //

    public function index(Request $request)
    {
        $search = $request->input('search', '');
        $status = $request->input('status', 1);
        $row = $request->input('row', 10);
        $paginate = $request->input('paginate', 0);
        $treasuryCheques = TreasuryCheque::withTrashed()
            ->when($status, function ($query) {
                $query->whereNull('deleted_at');
            }, function ($query) {
                $query->whereNotNull('deleted_at');
            })
            ->with([
                'user',
            ])
            ->whereLike([
                'type',
                'companies',
            ], $search)
            ->latest('updated_at')
            ->paginate($row);


        if (count($treasuryCheques)) {
            return $this->resultResponse('fetch', 'Treasuries', $treasuryCheques);
        } else {
            return $this->resultResponse('not-found', 'Treasuries', []);
        }
    }

    public function store(TreasuryChequeRequest $request) {
        $type = $request->type;
        $user_id = $request->user_id;
        $companies = $request->companies;

        $treasuryCheque = TreasuryCheque::create([
            'type' => $type,
            'user_id' => $user_id,
            'companies' => $companies
        ]);

        return $this->resultResponse('save','Treasury', $treasuryCheque);
    }

    public function update(TreasuryChequeRequest $request, $id) {
        $type = $request->type;
        $user_id = $request->user_id;
        $companies = $request->companies;

        $treasuryCheque = TreasuryCheque::find($id);
        $treasuryCheque->type = $type;
        $treasuryCheque->user_id = $user_id;
        $treasuryCheque->companies = $companies;
        $treasuryCheque->save();

        return $this->resultResponse('update', 'Treasury', $treasuryCheque);
    }

    public function show($id) {
        $treasuryCheque = TreasuryCheque::withTrashed()
            ->with([
                'user',
            ])
            ->find($id);

        if ($treasuryCheque) {
            return $this->resultResponse('fetch', 'Treasury', $treasuryCheque);
        } else {
            return $this->resultResponse('not-found', 'Treasury', []);
        }
    }

    public function change_status($id)
    {
        return $this->changeStatus($id, TreasuryCheque::class, "Treasury");
    }
}
