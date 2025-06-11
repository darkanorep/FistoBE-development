<?php

namespace App\Http\Controllers;

use App\Http\Requests\PermissionRequest;
use App\Models\Permission;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    public function index(Request $request)
    {
        $rows = $request->input('rows', 10);
        $search = $request->input('search');
        $paginate = $request->input('paginate', true);

         $query = Permission::with('bookOfAccounts')->withTrashed()
            ->when($search, function ($query) use ($search) {
                $query->where('name', 'like', '%' . $search . '%');
            })
            ->latest('updated_at');

        $permissions = $paginate ? $query->paginate($rows) : $query->get();
        $permissions = ['permissions' => $permissions];

        if (count($permissions)) {
            return $this->resultResponse("fetch", "Permission", $permissions);
        } else {
            return $this->resultResponse("not-found", "Permission", []);
        }

    }

    public function store(PermissionRequest $request)
    {
        $name = $request->input('name');

        $permission = Permission::create(['name' => $name]);

        if ($permission) {
            return $this->resultResponse("create", "Permission", $permission);
        } else {
            return $this->resultResponse("error", "Permission", []);
        }
    }

    public function change_status($id) {
        return $this->changeStatus($id, Permission::class, 'Permission');
    }
}
