<?php

namespace App\Http\Controllers;

use App\Http\Requests\BookOfAccountRequest;
use App\Models\BookOfAccount;
use Illuminate\Http\Request;

class BookOfAccountController extends Controller
{
    public function index(Request $request) {

        $status = $request->input('status');
        $rows = (int)$request->input('rows', 10);
        $search = $request->input('search', '');
        $paginate = $request->input('paginate', true);

        $bookOfAccounts = BookOfAccount::with('permissions')
            ->when(isset($status), function ($query) use ($status) {
                return $status ? $query->whereNull('deleted_at') : $query->whereNotNull('deleted_at');
            })
            ->when($search, function ($query) use ($search) {
                return $query->where('name', 'like', '%' . $search . '%');
            })
            ->latest();

        if ($paginate) {
            $bookOfAccounts = $bookOfAccounts->paginate($rows);
        } else {
            $bookOfAccounts = $bookOfAccounts->get();
        }

        if (count($bookOfAccounts)) {
            return $this->resultResponse("fetch", "Book of Accounts", $bookOfAccounts);
        } else {
            return $this->resultResponse("not-found", "Book of Accounts", []);
        }
    }

    public function store(BookOfAccountRequest $request) {

        $name = $request->input('name');
        $permissions = $request->input('permissions');

        $bookOfAccount = BookOfAccount::create([
            'name' => $name
        ]);

        collect($permissions)->each(function ($permission) use ($bookOfAccount) {
            $bookOfAccount->permissions()->attach($permission);
        });

        return $this->resultResponse("save", "Book of Account", $bookOfAccount);

    }

    public function show($id)
    {
        $bookOfAccount = BookOfAccount::with('permissions')->where("id", $id)->first();

        return $bookOfAccount
            ? $this->resultResponse("fetch", "Book of Account", $bookOfAccount)
            : $this->resultResponse("not-found", "Book of Account", []);
    }

    public function update($id) {

        $name = request()->input('name');
        $permissions = request()->input('permissions');

        $bookOfAccount = BookOfAccount::where("id", $id)->first();

        if ($bookOfAccount) {
            $bookOfAccount->update([
                'name' => $name
            ]);

            $bookOfAccount->permissions()->detach();
            collect($permissions)->each(function ($permission) use ($bookOfAccount) {
                $bookOfAccount->permissions()->attach($permission);
            });

            return $this->resultResponse("update", "Book of Account", $bookOfAccount);
        } else {
            return $this->resultResponse("not-found", "Book of Account", []);
        }
    }

    public function change_status($id)
    {
        return $this->changeStatus($id, BookOfAccount::class, "Book of Account");
    }

}
