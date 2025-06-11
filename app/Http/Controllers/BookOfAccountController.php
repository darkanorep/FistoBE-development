<?php

namespace App\Http\Controllers;

use App\Http\Requests\BookOfAccountRequest;
use App\Models\BookOfAccount;
use Illuminate\Http\Request;

class BookOfAccountController extends Controller
{
    public function index() {

        return BookOfAccount::with('permissions')
            ->when(request()->input('status'), function ($query) {
                return $query->whereNull('deleted_at');
            })
            ->when(request()->input('search'), function ($query) {
                return $query->where('name', 'like', '%' . request()->input('search') . '%');
            })
            ->latest()
            ->paginate(request()->input('rows', 10));
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
