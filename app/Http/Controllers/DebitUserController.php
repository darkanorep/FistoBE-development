<?php

namespace App\Http\Controllers;

use App\Http\Requests\DebitUserRequest;
use App\Models\DebitUser;
use Illuminate\Http\Request;

class DebitUserController extends Controller
{
    public function index(Request $request) {
        $search = $request->input('search', '');
        $status = $request->input('status', 1);
        $row = $request->input('row', 10);
        $paginate = $request->input('paginate', 0);

        $debitUser = DebitUser::withTrashed()
            ->when($status, function ($query) {
                $query->whereNull('deleted_at');
            }, function ($query) {
                $query->whereNotNull('deleted_at');
            })
            ->with([
                'user' => function ($query) {
                    $query->select('id', 'first_name', 'last_name', 'middle_name', 'suffix', 'position', 'department', 'id_prefix', 'id_no');
                }
            ])
            ->whereLike([
                'user.first_name',
                'user.last_name',
                'user.middle_name',
                'user.suffix',
                'user.position',
                'user.department',
                'user.id_prefix',
                'user.id_no',
            ], $search)
            ->latest('updated_at')
            ->when($paginate, function ($query) use ($row) {
                return $query->paginate($row);
            }, function ($query) {
                return $query->get();
            });


        if (count($debitUser)) {
            return $this->resultResponse('fetch', 'Users', $debitUser);
        } else {
            return $this->resultResponse('not-found', 'Users', []);
        }
    }

    public function store(DebitUserRequest $request) {

        $debitUser = DebitUser::create([
            'user_id' => $request->user_id
        ]);

        return $this->resultResponse('save','User', $debitUser);
    }

    public function show($id) {

        $debitUser = DebitUser::where('id', $id)
            ->with([
                'user' => function ($query) {
                    $query->select('id', 'first_name', 'last_name', 'middle_name', 'suffix', 'position', 'department', 'id_prefix', 'id_no');
                }
            ])
            ->first();

        if ($debitUser) {
            return $this->resultResponse('fetch', 'User', $debitUser);
        } else {
            return $this->resultResponse('not-found', 'User', []);
        }
    }

    public function update(DebitUserRequest $request, $id) {

        $debitUser = DebitUser::find($id);

        if ($debitUser) {
            $debitUser->update([
                'user_id' => $request->user_id
            ]);

            return $this->resultResponse('update', 'User', $debitUser);
        } else {
            return $this->resultResponse('not-found', 'User', []);
        }
    }

    public function change_status($id)
    {
        return $this->changeStatus($id, DebitUser::class, "User");
    }

}
